# 21 — QA Report

Living log of QA findings. Split into (a) fixes **already applied**, (b) known **remaining issues**, and (c) manual smoke checklist.

Last full audit: **2026-07-16** (see `memory/project_frontend_audit.md`).

---

## 1. Fixes applied (recent audits)

### 1.1 CRITICAL — auth / payment / data integrity

| # | Finding                                                                                                   | Fix                                                                                             | File(s)                                                                                     |
| - | --------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| 1 | `email_verified_at`, `phone_verified_at` mass-assignable → attacker could pre-verify via `update($request->all())` | Removed from `$fillable`                                                                     | `app/Models/Customer.php`                                                                   |
| 2 | Login issued OTP for unverified account before password was verified → OTP-bomb of known emails            | Password verified first; generic `Invalid credentials.` for wrong user AND wrong password         | `app/Http/Controllers/Client/LoginUserController.php`                                       |
| 3 | Forgot-password revealed "Account not found" → enumeration                                                 | Generic response always; fixed crash when token missing; switched OTP compare to `hash_equals`   | `app/Http/Controllers/Client/ForgotPasswordController.php`                                  |
| 4 | Razorpay callback trusted client-posted amount → free-goods exploit                                       | Fetch payment from Razorpay API server-side; assert amount/currency/order_id                     | `app/Http/Controllers/Client/PaymentController.php`                                         |
| 5 | Razorpay callback ↔ webhook race → double stock decrement / double notification                            | `lockForUpdate()` on payments row; atomic `order_placed_notified_at` UPDATE                       | `app/Http/Controllers/Client/PaymentController.php`                                         |
| 6 | Variant stock not decremented on Razorpay path → oversold variants                                        | Added variant stock decrement                                                                     | `app/Http/Controllers/Client/PaymentController.php`                                         |
| 7 | Refund events double-processed                                                                            | Dedup by `refund_id` in `payment.meta.processed_refund_ids`; dedup webhook by event id            | `app/Http/Controllers/Client/PaymentController.php`                                         |
| 8 | Over-refund silently absorbed                                                                             | Cap at `payment.amount - refunded_amount`; log the truncation                                     | `app/Http/Controllers/Client/PaymentController.php`                                         |
| 9 | `payment.failed` webhook could regress a `paid` payment                                                    | Guard: never regress `paid`                                                                       | `app/Http/Controllers/Client/PaymentController.php`                                         |
| 10 | Shiprocket webhook accepted query-string tokens (leak to logs)                                             | HMAC-SHA256 `X-Shiprocket-Signature`; header-only shared-token fallback; query-string rejected     | `app/Http/Controllers/Client/PaymentController.php`                                         |
| 11 | Malformed webhook JSON returned 200                                                                       | Now 400                                                                                          | `app/Http/Controllers/Client/PaymentController.php`                                         |
| 12 | Return `markRefunded` allowed from `approved` (contradicted assertion)                                    | Enforce `received → refunded` only                                                                | `app/Http/Controllers/Admin/ReturnController.php`                                           |
| 13 | Return `markReceived` only restocked product, not variant                                                 | Increment ProductVariant stock too                                                                | `app/Http/Controllers/Admin/ReturnController.php`                                           |

### 1.2 HIGH — reliability & security hardening

| # | Fix                                                                                                                              | File(s)                                                                                              |
| - | -------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| 14 | All 4 notifications now `implements ShouldQueue` (SMTP no longer blocks order commit / contact form)                              | `app/Notifications/{OrderPlaced,OrderShipped,OrderStatusChanged,ContactNotification}.php`             |
| 15 | Rate limits added: contact 5/min, admin auth 5/min, webhooks 120/min                                                              | `routes/web.php`, `routes/auth.php`                                                                  |
| 16 | `SecurityHeaders` middleware — HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy                  | `app/Http/Middleware/SecurityHeaders.php`, `bootstrap/app.php`                                       |
| 17 | `.env.example` production-safe defaults (APP_ENV=production, APP_DEBUG=false, SESSION_ENCRYPT=true, SESSION_SECURE_COOKIE=true)    | `.env.example`                                                                                       |
| 18 | `Contact` model — replaced `$guarded=[]` with explicit `$fillable`                                                                 | `app/Models/Contact.php`                                                                             |

### 1.3 UI / accessibility (prior audit)

- `index.blade.php` star-rating loop rendered inverted count → fixed.
- `components/footer.blade.php` broken links + missing alt text + external link security (`rel="noopener"`) → fixed.
- `components/header.blade.php` account button + mobile search action + cart `aria-label` → fixed.
- Checkout `agree_tos` server + client validation → added.
- Shop search `q` param + empty state + accessible pagination → added.
- Wishlist skips soft-deleted products → fixed.
- `about.blade.php` / `404.blade.php` — `env()` → `config()` (allows config caching).

---

## 2. Known remaining issues (NOT fixed — future work)

### 2.1 HIGH

- **G-12** — **Google OAuth email-match takeover** (`Client\SocialAuthController::callback`): matching by google_id OR email risks binding an attacker's Google account to an existing password account. **Fix:** enforce explicit merge with re-verify.
- **G-10** — **OTP stored plaintext** (`password_resets.otp`). **Fix:** hash + `hash_equals` compare + migration.
- **OTP brute-force per token** — only per-IP throttle. **Fix:** attempt counter on `password_resets` row; lock after N tries.
- **Order-number race** (`Client\OrderController::save`) — `latest('id')->first() + 1` is racy. **Fix:** retry loop on unique-index violation OR use a DB sequence.
- **G-11** — **COD stock reservation** — stock is decremented at placement; abusers can drain inventory via never-picked-up COD orders. **Fix:** reserve-not-decrement (`orders.status='reserved'` with TTL cleanup).

### 2.2 MEDIUM

- **Idempotency-Key on `/order`** — session-based 5 s window. **Fix:** DB unique on `(customer_id, idempotency_key)`.
- **Google-OAuth silent remember-me** — `Auth::login($customer, true)` forces persistent cookie. **Fix:** default `remember=false`; opt-in checkbox.
- **`ProfileController::updatePassword`** — verify current-password check is enforced (flagged by prior audit, not re-verified).
- **G-13** — Return `markRefunded` does NOT trigger Razorpay refund; admin must hit `Admin\OrderController::refund` separately. **Fix:** auto-invoke refund from `markRefunded` using `return.refund_amount`.
- **`SocialAuthController::callback`** — Google flow is incomplete per Phase 3.
- **`TrackingController`** — session grant is order_no-scoped; doesn't re-verify email; risk of session-share leakage.
- **Admin new-order notification** — no email/SMS to ops when an order lands.
- **Open admin registration** — `/admin/register` allows anyone to create an admin (rate-limited only). **Fix:** invite-only or seed-only.
- **`TrustProxies::$proxies = '*'`** — spoof-friendly if deployed without a reverse proxy. **Fix:** explicit proxy IP list.

### 2.3 LOW / notes

- Route name typo `client.reset-pasword` — kept for BC (consistent between route + view).
- Legacy typo `orders.coupan_code` — kept for BC.
- Login/register buttons `type="button"` — JS-only submit path.
- Cart mini-template placeholder strings — fragile but functional.
- Sticky ATC bar `d-none` — dead code, safe to delete.
- `config/cors.php` not published — Laravel defaults apply (permissive for same-origin).
- No CSP header — recommended for defence-in-depth.

---

## 3. Manual smoke checklist (release gate)

Run before every production deploy. Screenshot everything.

### 3.1 Auth
- [ ] Register a customer → OTP arrives (log or SMTP).
- [ ] Verify OTP → redirected to /profile.
- [ ] Wrong-password login → generic error.
- [ ] Wrong-email login → generic error (SAME message).
- [ ] Forgot password with unknown email → generic success (no enumeration).
- [ ] Reset with wrong OTP → generic error, no crash.
- [ ] Google OAuth login → creates account or logs in (if configured).
- [ ] Admin `/admin/register` → creates admin (test staging only!).
- [ ] Admin `/admin/login` → dashboard.

### 3.2 Catalog
- [ ] Homepage renders slider + featured categories + featured products.
- [ ] `/shop` — search preserves `?q=` in URL; empty state visible when no results.
- [ ] Product detail — variant picker updates price + stock.
- [ ] Add to cart works with variants + without.
- [ ] Wishlist heart toggles + persists.

### 3.3 Checkout (Razorpay)
- [ ] Cart → checkout — bounces to /login when logged out.
- [ ] Choose address → coupon → agree TOS → place order.
- [ ] Razorpay modal opens; complete test payment.
- [ ] Callback flips payment to `paid`.
- [ ] Order confirmation page renders with correct total.
- [ ] Invoice link works.
- [ ] OrderPlaced email in logs.
- [ ] Stock decremented on both product and variant.

### 3.4 Checkout (COD)
- [ ] Same as above but COD.
- [ ] Order confirmation directly.

### 3.5 Amount tampering (staging)
- [ ] Intercept POST /razorpay/callback and change amount → 422; payment stays pending.

### 3.6 Webhook idempotency (staging)
- [ ] Trigger Razorpay `payment.captured` webhook twice with same event id → payment stays `paid`, no duplicate notification.
- [ ] Trigger `refund.processed` twice with same refund_id → `refunded_amount` incremented once.

### 3.7 Shipment
- [ ] Assign AWB from admin → shipment rows update; OrderShipped email fires.
- [ ] Duplicate assign → notification does NOT fire twice.
- [ ] Simulate Shiprocket webhook with bad signature → 400.

### 3.8 Returns
- [ ] Customer requests return within window → success.
- [ ] Outside window → rejected.
- [ ] Admin approve → status=approved.
- [ ] Admin mark received → stock restored on product + variant.
- [ ] Admin mark refunded from `received` → OK.
- [ ] Admin mark refunded from `approved` → throws (should fail).

### 3.9 Admin
- [ ] Non-admin user cannot reach `/admin/dashboard` → 403.
- [ ] Command palette (⌘K) opens.
- [ ] Payment gateway edit + toggle logs to `admin_activity_logs`.
- [ ] Reports export streams a CSV.

### 3.10 Security headers
- [ ] `curl -I https://yourbrand.com/` returns HSTS, X-Content-Type-Options, X-Frame-Options.

### 3.11 Rate limiting
- [ ] 6 rapid `POST /login` from one IP → last returns 429.
- [ ] 6 rapid `POST /contact` from one IP → last returns 429.

### 3.12 Queue + scheduler
- [ ] `php artisan queue:work --once` picks up one job.
- [ ] `SyncActiveShipmentsJob` runs when scheduled (check `laravel.log`).

---

## 4. Verification of prior audit

- `php -l` clean on every modified PHP file at audit time.
- `php artisan route:list` shows webhook routes still bound correctly.
- No breaking schema changes — all fixes are code-level.

---

## 5. Where to record new findings

- **Small**: append to this file under § 2 in the appropriate severity bucket.
- **Big**: file a task in the project tracker + summarise here.
- **Immediate fix**: PR + note under § 1 in the format `# | Finding | Fix | File(s)`.
