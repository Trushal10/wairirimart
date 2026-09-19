# 20 — Testing Strategy

## 1. Current state

- **PHPUnit 11.5** is a dev dependency.
- `tests/` folder scaffolding is present (`tests/Feature/`, `tests/Unit/`, `tests/TestCase.php`).
- **No tests are written.** `tests/Feature` and `tests/Unit` contain only Laravel's default sample class (or are effectively empty).
- No CI pipeline; tests are not part of the deploy gate.

**Implication:** every code change is high-risk. Manual QA on every PR is currently the only line of defence.

This document describes the **recommended** test strategy the team should adopt.

---

## 2. Test pyramid target

```
              ┌───────────────────┐
              │  E2E — Playwright  │  ~10 tests   (smoke)
              ├───────────────────┤
              │ Feature (HTTP)     │  ~60 tests   (Laravel PHPUnit)
              ├───────────────────┤
              │      Unit          │  ~150 tests  (Laravel PHPUnit)
              └───────────────────┘
```

Rules of thumb:
- **Unit** — pure functions and value calculations (CouponService, CartService totals, OtpService, format helpers). Fast, no DB.
- **Feature** — HTTP-level assertions with a real DB (SQLite in memory works for most cases). Covers routes, middleware, controllers, form requests, notifications.
- **E2E** — Only for the most business-critical journeys (register → buy → track → return). Runs against a staging DB with test payment credentials.

---

## 3. Framework setup

`phpunit.xml` is committed and configured for the Laravel `Tests\TestCase` base. To run:

```bash
php artisan test
# or
vendor/bin/phpunit
# with coverage
php artisan test --coverage
```

Recommended `.env.testing`:
```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
QUEUE_CONNECTION=sync
MAIL_MAILER=array
CACHE_STORE=array
SESSION_DRIVER=array
BCRYPT_ROUNDS=4
```

Use `RefreshDatabase` trait on tests that touch the DB.

---

## 4. Unit test priorities

| Class                                 | Tests to write                                                                              |
| ------------------------------------- | ------------------------------------------------------------------------------------------- |
| `App\Services\CartService`            | add, update, remove, count, totals with mixed variants, coupon application                    |
| `App\Services\CouponService`          | fixed vs percent, min_order, max_discount, expiry, per-customer, usage_limit atomic          |
| `App\Services\OtpService`             | issue, verify (correct, expired, wrong, timing), TTL enforcement                              |
| `App\Services\SmsService`             | log driver noop, msg91 body composition, twilio param mapping, failure swallowing            |
| `App\Services\ShipmentService`        | assignAwb success, `notifyCustomerShipped` guarded                                            |
| `App\Services\Payment\RazorpayGateway`| signature verify (good/bad), fetchPayment stub, refund cap                                    |
| `App\Helper\CommonHelper`             | makeSlug edge cases, uploadFile with fake disk, validation rule composition                   |
| `App\Models\Order`                    | scopes, relationship loads, snapshot mutators if any                                          |
| `App\Models\Coupon`                   | isValid()-style helpers if added                                                              |

---

## 5. Feature test priorities

Order roughly by revenue impact:

### 5.1 Payment / order (critical)
- `POST /order` — happy path (COD): creates order, decrements stock, sends notification, redirects to confirmation.
- `POST /order` — Razorpay path: creates order, creates Razorpay order, returns JSON, does NOT send notification yet.
- `POST /order` — out-of-stock: rejects with 422; no order created; no stock change.
- `POST /order` — invalid coupon: rejects; original totals stand.
- `POST /order` — tampered totals (client posts a lower total): server ignores client total; recomputes.
- `POST /razorpay/callback` — good signature + matching amount: flips payment paid, sends notification once.
- `POST /razorpay/callback` — signature mismatch: 400; no state change.
- `POST /razorpay/callback` — amount mismatch: 422; no state change.
- `POST /webhooks/razorpay` — captured event: idempotent (duplicate returns 200 + no re-notify).
- `POST /webhooks/razorpay` — bad signature: 400.
- `POST /webhooks/razorpay` — payment.failed after paid: no regression.
- `POST /webhooks/razorpay` — refund.processed: increments refunded_amount, dedups by refund_id.

### 5.2 Auth
- Customer register issues OTP; login blocked until verified.
- Customer login with wrong password returns generic 401.
- Customer login of unverified account re-issues OTP only after password verify.
- Forgot-password with unknown email returns generic success.
- Reset-password uses `hash_equals` to compare OTP.
- Admin login requires `role='admin'`.
- CSRF token missing → 419 on POST.

### 5.3 Cart
- Add-to-cart persists in session; variant-aware.
- Update quantity below 1 removes.
- Remove-cart-item removes.
- Cart page recomputes totals from live DB prices.

### 5.4 Coupons
- Apply valid coupon → discount reflected.
- Apply expired coupon → error.
- Apply coupon over usage_limit → error.
- Apply coupon scoped to another customer → error.
- Order commit consumes the coupon atomically.

### 5.5 Wishlist / reviews
- Toggle wishlist adds/removes.
- Wishlist requires auth.
- Review submit requires auth + product exists; defaults `is_approved=false`.

### 5.6 Returns
- Create return within window → 201; state=requested.
- Create return outside window → 422.
- Approve → state transitions.
- Mark received → increments stock (product + variant).
- Mark refunded from `received` → OK; from `approved` → throws.

### 5.7 Admin
- Non-admin cannot access `/admin/*` (403).
- Admin can create/edit/delete products.
- Admin can toggle payment gateway active.
- Admin update order status writes `order_status_histories` and dispatches notification.
- Audit log written on gateway update.

---

## 6. Notification tests

Use `Notification::fake()`:

```php
Notification::fake();
$this->post('/order', $payload);
Notification::assertSentTo($customer, OrderPlaced::class);
Notification::assertSentTimes(OrderPlaced::class, 1);
```

For the exactly-once guards:

```php
// Trigger both callback and webhook for the same payment
$this->post('/razorpay/callback', $callbackPayload);
$this->postJson('/webhooks/razorpay', $webhookPayload, $webhookHeaders);
Notification::assertSentTimes(OrderPlaced::class, 1);   // exactly one, not two
```

---

## 7. Queue tests

```php
Queue::fake();
$this->post('/order', $payload);
Queue::assertPushedOn('default', SendOrderPlacedJob::class);
```

For jobs that use `withoutOverlapping`, test the lock behaviour with `Cache::fake()`.

---

## 8. HTTP mocks for gateways

Wrap all outbound HTTP through `Http::` (Laravel HTTP client). Then in tests:

```php
Http::fake([
    'api.razorpay.com/*' => Http::response(['id' => 'pay_TEST', 'amount' => 149900, 'currency' => 'INR', 'order_id' => 'order_TEST', 'status' => 'captured']),
    'apiv2.shiprocket.in/*' => Http::response(['awb_code' => 'AWB123', ...]),
]);
```

If gateway classes use SDKs directly (Razorpay PHP SDK), inject a mock via dependency container.

---

## 9. E2E strategy

Use **Playwright** (recommended) or Cypress.

```bash
npm i -D @playwright/test
npx playwright install
```

Suggested smoke suite:
1. Anonymous browse → add to cart → login → checkout → COD → confirmation.
2. Registered login → checkout → Razorpay (test key) → callback stub → confirmation.
3. Customer creates return → admin approves → admin marks received → stock restored.
4. Admin login → assign AWB (mocked Shiprocket) → shipment status updates.
5. Admin creates product with variants → visible on shop page.

Run E2E against a staging environment with test payment / courier credentials.

---

## 10. Test data

- **Factories:** `database/factories/UserFactory`, `ProductFactory`, `ProductCategoryFactory` are present. Add more:
  - `CustomerFactory`
  - `OrderFactory` (with `hasItems()` state)
  - `CouponFactory` (with `active()`, `expired()`, `fixed()`, `percent()` states)
  - `ShipmentFactory`
  - `PaymentFactory`
- **Seeders:** only `DatabaseSeeder` for local dev; do NOT run in tests (use factories).

---

## 11. Coverage targets (recommended)

| Layer     | Target                     |
| --------- | -------------------------- |
| Unit      | 80% line coverage          |
| Feature   | Every non-trivial route    |
| E2E       | 5 happy paths + 3 sad paths|

Not a hard rule — coverage is a proxy, not a goal. Prefer tests that describe **behaviour** and would fail on real regressions over tests that hit uncovered lines mechanically.

---

## 12. CI recommendation

Add `.github/workflows/ci.yml`:

```yaml
name: CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env: { MYSQL_ALLOW_EMPTY_PASSWORD: 1, MYSQL_DATABASE: testing }
        ports: ['3306:3306']
        options: --health-cmd "mysqladmin ping" --health-interval 10s
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2', extensions: pdo_mysql, mbstring, xml, curl, zip, bcmath, intl, gd }
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: composer install --no-interaction --prefer-dist
      - run: npm ci && npm run build
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan migrate --force
      - run: php artisan test --parallel
      - run: vendor/bin/pint --test
```

---

## 13. Code quality gates

- `vendor/bin/pint` — Code style (already a dev dep).
- `composer audit` — Dependency CVEs.
- `npm audit --production` — JS CVEs.
- (Optional) PHPStan / Psalm for static analysis.
- (Optional) Rector for automated refactoring.

Wire these into pre-push git hooks and CI.

---

## 14. Manual QA scripts (until tests are written)

See [21 QA Report](21-qa-report.md) for the current living checklist.

Broadly:
1. Every PR that touches payments — replay a Razorpay callback and webhook manually.
2. Every PR that touches shipments — assign an AWB in staging (sandbox account).
3. Every PR that touches auth — verify login + register + forgot flows.
4. Every PR that touches orders — place a full COD + full Razorpay order in staging.
5. Every PR that touches admin — confirm audit log entries.

---

## 15. Test data cleanup

- SQLite `:memory:` DB is auto-discarded per test class run — no cleanup needed.
- File uploads in tests must use `Storage::fake()` to avoid polluting the real disk.
- Notifications use `Notification::fake()`.
- Queue uses `Queue::fake()`.
