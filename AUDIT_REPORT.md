# Vue-Ecommerce — End-to-End Production Readiness Audit

**Project:** Laravel 12 + Vue 3 + Inertia + Blade client theme + Razorpay + Shiprocket
**Audit date:** 2026-07-12
**Total findings:** 100+ across security, data integrity, bugs, UX, SEO, a11y, and code quality
**Verdict:** 🔴 **NOT PRODUCTION-READY** — multiple money-losing, fraud-enabling, and data-corruption bugs.

> **Status update (2026-07-12):** All P0 and P1 findings and the highest-impact P2 items have been fixed in code. See "What still requires operator action" at the bottom of this file for the manual steps that only you can perform (rotating keys, running migrations, setting env vars).

---

## Table of Contents
1. [P0 — Ship Blockers](#p0--ship-blockers)
2. [P1 — High Priority](#p1--high-priority)
3. [P2 — Medium (UX, SEO, a11y)](#p2--medium-ux-seo-a11y)
4. [P3 — Low (Polish)](#p3--low-polish)
5. [Recommended Fix Order](#recommended-fix-order)
6. [Appendix — Full Findings Table](#appendix--full-findings-table)

---

## P0 — Ship Blockers
Fix before ANY production traffic. Each of these can cost real money, leak real data, or corrupt the DB.

### 1. Order total is trusted from the client — user can pay ₹1 for a ₹10,000 order
- **File:** `app/Http/Controllers/Client/OrderController.php:81-92`
- **Root cause:** Cart price/qty come from session; `Product` row is loaded but the multiplier `$cart[$product->id]['quantity']` is trusted. No cap check against DB stock, no coupon revalidation, no server-authoritative total.
- **Fix:** Recompute `subTotal`, `shipping`, `discount`, `total` on the server using only `Product::price` and validated quantities. Reject the request if the total differs from what the client showed.

```php
foreach ($products as $product) {
    $qty = (int) $cart[$product->id]['quantity'];
    if ($qty < 1 || $qty > $product->stock) {
        abort(422, "Invalid quantity for {$product->name}");
    }
    $subTotal += $qty * $product->price; // never trust $cart[...]['price']
}
$total = $subTotal + $shipping - $discount;
```

### 2. Razorpay callback trusts `order_id` from the client — attacker marks their own order paid
- **File:** `app/Http/Controllers/Client/PaymentController.php:24-26`
- **Root cause:** Signature is verified, but ownership of the `order_id` is not.
- **Fix:** After `verifyPaymentSignature`, load `Payment` → `Order`, assert ownership.

```php
$payment = Payment::where('order_id', $request->order_id)->firstOrFail();
$order   = Order::findOrFail($payment->order_id);
abort_unless($order->customer_id === Auth::guard('customer')->id(), 403);
```

### 3. No Razorpay webhook — real payments captured, order left "pending"
- **File:** `routes/web.php:49` (only client callback exists).
- **Root cause:** If the browser dies between Razorpay success and the AJAX callback, money is taken but the order is never marked paid.
- **Fix:** Add `POST /webhooks/razorpay` (CSRF-excluded, no auth). Verify `X-Razorpay-Signature` with `RAZORPAY_WEBHOOK_SECRET`, mark payment + order paid on `payment.captured`, decrement stock there.

```php
Route::post('/webhooks/razorpay', [PaymentController::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class]);

public function webhook(Request $r) {
    $sig = $r->header('X-Razorpay-Signature');
    $ok  = hash_equals(
        hash_hmac('sha256', $r->getContent(), env('RAZORPAY_WEBHOOK_SECRET')),
        $sig
    );
    abort_unless($ok, 401);
    // handle payment.captured, payment.failed, refund.processed
}
```

### 4. Stock decremented BEFORE payment verification — phantom depletion
- **File:** `app/Http/Controllers/Client/OrderController.php:74-79`
- **Root cause:** Loop decrements one product, then may fail on the next. Even with a transaction, moving stock before payment lets abandoned checkouts lock inventory.
- **Fix:** Validate stock for ALL items first, then decrement inside the Razorpay webhook once payment is confirmed. Use `SELECT ... FOR UPDATE` inside the transaction to prevent races.

### 5. IDOR on customer addresses — read/update/delete anyone's address by ID
- **File:** `app/Http/Controllers/Client/CustomerAddressController.php:24-49`
- **Root cause:** `edit()`, `update()`, `delete()` load by `id` only; no `customer_id` scope.
- **Fix:**

```php
$address = CustomerAddress::where('id', $id)
    ->where('customer_id', Auth::guard('customer')->id())
    ->firstOrFail();
```

### 6. Order-save route has NO auth middleware
- **File:** `routes/web.php:48-49`
- **Root cause:** `POST /order` and `/razorpay/callback` are open. `Auth::guard('customer')->id()` returns `null` for anon users → orders created with `customer_id = null`.
- **Fix:** Wrap in `Route::middleware('client_auth')->group(...)`. The webhook stays outside.

### 7. Prices stored as `DECIMAL` with no scale — cents/paise silently truncated
- **Files:**
  - `database/migrations/2024_12_07_071509_create_products_table.php:21-22` — `decimal('price')`, `decimal('compere_price')`
  - `database/migrations/2025_01_10_074921_create_orders_table.php:18-22` — `decimal('sub_total', 10)`, `decimal('shipping')`, `decimal('discount', 10)`, `decimal('total', 10)`
  - `database/migrations/2025_01_10_074938_create_order_items_table.php:21` — `decimal('price', 10)`
- **Root cause:** MySQL defaults scale to 0. ₹99.50 stores as ₹100.
- **Fix:** New migration to `ALTER` all money columns to `DECIMAL(12, 2)`, then correct the migration files.

```php
Schema::table('products', function (Blueprint $t) {
    $t->decimal('price', 12, 2)->default(0)->change();
    $t->decimal('compere_price', 12, 2)->default(0)->change();
});
Schema::table('orders', function (Blueprint $t) {
    foreach (['sub_total','shipping','discount','total'] as $c) {
        $t->decimal($c, 12, 2)->default(0)->change();
    }
});
Schema::table('order_items', function (Blueprint $t) {
    $t->decimal('price', 12, 2)->change();
});
```

### 8. Secrets committed to repo + `APP_DEBUG=true`
- **File:** `.env:4,69-73`
- **Contents leaked:** `RAZORPAY_KEY`, `RAZORPAY_SECRET`, `SHIPROCKET_EMAIL/PASSWORD`.
- **Fix:**
  1. `git rm --cached .env`
  2. Rotate ALL keys (Razorpay dashboard + Shiprocket password).
  3. Verify `.env` is in `.gitignore`.
  4. Set `APP_DEBUG=false` on any non-local environment.

### 9. Fatal crash — undefined `ShiprocketService::createShipment()`
- **File:** `app/Http/Controllers/Admin/OrderController.php:69`
- **Root cause:** Method doesn't exist on the service. Any admin assigning a delivery partner gets a 500.
- **Fix:** Implement `createShipment()` in `app/Services/ShiprocketService.php` (build Shiprocket "adhoc" order payload and POST) or point the admin call to the existing `createOrder()`.

### 10. Every FormRequest returns `authorize() = true`
- **Files:** `app/Http/Requests/ProductRequest.php:17`, `Client/OrderRequest.php`, `Client/ReviewRequest.php`, others.
- **Root cause:** Security is delegated entirely to route middleware, which is inconsistent.
- **Fix:** Implement per-request policy — admin role check for admin requests, `Auth::guard('customer')->check()` for client requests.

### 11. XSS via `{!! $product['description'] !!}`
- **File:** `resources/views/client/product.blade.php:411`
- **Root cause:** Admin-supplied HTML from Quill editor rendered raw. Any compromised admin = stored XSS on every product page.
- **Fix:** Sanitize server-side with `mews/purifier` before saving, or on render: `{!! Purifier::clean($product['description']) !!}`.

### 12. OTP brute-force + timing attack + weak RNG
- **Files:**
  - `app/Http/Controllers/Client/RegisterUserController.php:37` — `==` comparison, no rate limit.
  - `app/Helper/CommonHelper.php:69` — `rand(100000, 999999)` (not cryptographic).
  - `app/Http/Controllers/Client/RegisterUserController.php:35` — `carbon::now()` (lowercase; risky).
- **Fix:**

```php
if (empty($passwordReset)) abort(400, 'Invalid token');
$currentTime = Carbon::now();
if ($createdAt->diffInMinutes($currentTime) > 5) abort(400, 'OTP expired');
if (!hash_equals((string)$passwordReset->otp, (string)$validated['otp'])) {
    abort(400, 'Invalid OTP');
}
// generation
'otp' => random_int(100000, 999999),
// routes
Route::post('verify-otp', ...)->middleware('throttle:5,1');
```

---

## P1 — High Priority
Fix in the first release cycle.

### Backend / Security

- **Mass assignment risk everywhere.** Every model uses `protected $guarded = []`. Attacker can POST `status`, `is_admin`, `customer_id`, etc.
  Files: `app/Models/Order.php:25`, `app/Models/Product.php:12`, `app/Models/Customer.php:12`, and every other model.
  Fix: switch to explicit `$fillable = [...]`.
- **No admin role check in controllers.** Routes protected by `auth` alone; any authenticated user could hit admin endpoints if URLs leak. `app/Http/Controllers/Admin/OrderController.php:43`. Add `role:admin` middleware or a Gate.
- **Order status transitions unchecked.** Admin can jump `pending → delivered` without payment. `app/Http/Controllers/Admin/OrderController.php:43-51`. Enforce a transition matrix.
- **Reviews accept anonymous/spam entries.** No purchase check, no dedup, no moderation. `app/Http/Controllers/Client/ReviewController.php:12-27`. Add `hasPurchased` check, `is_approved` column, unique `(customer_id, product_id)`.
- **`OrderRequest.address_id` not validated with `exists:`.** `app/Http/Requests/Client/OrderRequest.php:22-30`. Add `'exists:customer_addresses,id'`.
- **ShiprocketService swallows errors.** `->json()['token'] ?? null` returned silently. `app/Services/ShiprocketService.php:16-27`. Add try/catch, log, cache token with TTL.
- **Order/Model status enum mismatch.** DB enum `['pending, delivered, confirmed, canceled']` vs model constants `'completed'`, `'cancelled'`. Silent update failures.
  Files: `database/migrations/2025_01_10_074921_create_orders_table.php:23`, `app/Models/Order.php:10-23`.

### Payment / Checkout

- **Cart lives in session with `SESSION_ENCRYPT=false`.** Cart is client-editable. Move to DB for logged-in users, encrypt session cookie, always re-verify server-side.
- **Price not captured at add-to-cart.** `app/Services/CartService.php:41-67`. Snapshot `unit_price` in the cart entry and lock it for N minutes.
- **No idempotency key.** Network hiccup during "Pay" creates duplicate orders. `app/Http/Controllers/Client/OrderController.php:26`. Use CSRF token + session lock, or an `Idempotency-Key` header.
- **`onsubmit="return false"` blocks checkout submit fallback.** `resources/views/client/checkout.blade.php:127`.
- **Post-payment redirect goes to `/`** instead of an order confirmation page. `resources/views/client/checkout.blade.php:488-491`. Route to `/order/{id}/confirmation`.
- **No order confirmation email/SMS.** No `Mail::send` anywhere in the order flow.

### Frontend (Vue/Inertia)

- **`v-html="link.label"` in Pagination.** `resources/js/Components/common/Pagination.vue:5-6`. Replace with text interpolation.
- **`ref()` misused inside Options API `data()`** — password-toggle broken. `resources/js/Pages/Auth/ResetPassword.vue:168-169`. Use plain booleans.
- **Missing error output for `password_confirmation`.** `resources/js/Pages/Auth/Register.vue:95-106`.
- **Alt text uses `{{ … }}` literally.** `resources/js/Pages/Admin/Order/Detail.vue:144`. Change to `:alt="item?.product?.name"`.
- **`URL.createObjectURL` never revoked on unmount** — memory leak. `resources/js/Pages/Admin/Product/Create.vue:482`. Revoke in `beforeUnmount`.
- **No `onFinish` handler on Inertia posts** — submit button locked forever on network errors.

### Database / Performance

- **Missing indexes on high-traffic FKs:** `orders.customer_id`, `order_items.order_id`, `order_items.product_id`, `reviews.product_id`, `product_medias.product_id`, `customer_addresses.customer_id`, `product_categories.category_id`.
- **Missing indexes on slug columns.** Every product/category URL hits a full table scan.
  Files: `database/migrations/2024_12_07_071509_create_products_table.php:16`, `database/migrations/2024_11_29_111512_create_categories_table.php:17`.
- **No soft deletes on orders/products/customers** — deleting a product cascades and destroys order history.
- **`stock` typed as `decimal`.** `database/migrations/2024_12_07_071509_create_products_table.php:23`. Should be `unsignedInteger`.
- **`SESSION_SECURE_COOKIE` unset** — cookies leak on HTTP. `config/session.php:172`. Add `SESSION_SECURE_COOKIE=true` in production `.env`.

---

## P2 — Medium (UX, SEO, a11y)

### SEO — the whole client theme is invisible to Google
- No Open Graph, no canonical, no JSON-LD in `resources/views/layouts/client.blade.php`.
- Product pages lack `Product` + `BreadcrumbList` structured data. `resources/views/client/product.blade.php`.
- No `robots.txt`, no `sitemap.xml` route.
- **Fix:** add OG/Twitter/canonical to master layout; JSON-LD block on product; breadcrumb schema on category; generate sitemap dynamically.

```blade
<meta property="og:title" content="@yield('title')">
<meta property="og:description" content="@yield('meta_description')">
<meta property="og:image" content="@yield('og_image', asset('client/images/logo/logo.svg'))">
<meta property="og:url" content="@yield('og_url', url()->current())">
<meta property="og:type" content="@yield('og_type', 'website')">
<link rel="canonical" href="@yield('canonical_url', url()->current())">
```

### Accessibility
- Icon-only buttons in header/search lack `aria-label`. `resources/views/components/header.blade.php`.
- Checkout inputs use `placeholder` instead of `<label for>`. `resources/views/client/checkout.blade.php:74…`.
- Admin dropdowns don't close on Escape or trap focus. `resources/js/Components/Header.vue:58-283`.
- Logo alt is generic `"logo"`.

### UX
- No empty state on admin tables — blank `<tbody>` when zero rows. `resources/js/Pages/Admin/Product/Index.vue:54-110`.
- Delete confirmation title reads `"Category Delete"` on the Product page. `resources/js/Pages/Admin/Product/Index.vue:193-202`.
- Cart quantity input is `type="text"` with no `min/max`. `resources/views/client/product.blade.php:214`.
- No pincode / phone / email regex validation in checkout form. `resources/views/client/checkout.blade.php:132-165`.
- No "empty cart" branch in checkout — you can proceed with nothing.
- T&Cs checkbox is optional. `resources/views/client/shopping-cart.blade.php:202-206`.
- Session flash message injected via `"{{Session::get('success')}}"` inside JS strings — quote-escape XSS vector. Use `@json(Session::get('success'))`. `resources/views/layouts/client.blade.php:717-728`.
- Header nav typo: "Contant Us". `resources/views/components/header.blade.php:141`.

### Frontend hygiene
- `console.log` scattered through production code (checkout, admin order detail, product create, ResetPassword).
- Unused `Dropzone` import. `resources/js/Pages/Admin/Product/Create.vue:373`.
- Hardcoded `/storage/product/…` paths everywhere — no `VITE_STORAGE_URL` composable.

---

## P3 — Low (Polish)

- Column typo `compere_price` → `compare_price`. `database/migrations/2024_12_07_071509_create_products_table.php:22`.
- `sku` should be `->unique()`. Same file, line 24.
- Reviews table needs `is_approved`, `is_spam`, index on `product_id`. `database/migrations/2025_12_07_172034_create_reviews_table.php`.
- Add Razorpay `<script>` SRI hash. `resources/views/layouts/client.blade.php:754`.
- Add lazy-load class on payment/badge images. `resources/views/client/product.blade.php:654,835`.
- Registration success redirects everyone to `admin.dashboard`. `app/Http/Controllers/Auth/RegisteredUserController.php:49`.
- No admin activity log (product edits, status changes, refunds).
- Deleted files still in `git status` — `app/Models/Product copy.php`, orphaned built assets. Commit the cleanup.

---

## Recommended Fix Order

### Sprint 1 — Money & Trust (P0)
1. Rotate Razorpay + Shiprocket credentials, purge `.env` from git, set `APP_DEBUG=false`.
2. Route auth for `/order`; IDOR patches on `CustomerAddressController` and payment callback.
3. Server-side recomputation of order total; stock validated up-front, decremented in Razorpay webhook.
4. Add Razorpay webhook + idempotency key.
5. Fix `DECIMAL` scale via `ALTER TABLE` migration and correct migration files.
6. Implement `createShipment()` or repoint admin call.
7. OTP: rate-limit, `hash_equals`, `random_int`.

### Sprint 2 — Hardening (P1)
- Convert all `$guarded = []` to `$fillable`.
- Enforce `FormRequest::authorize()` per role.
- Add FK + slug indexes + soft deletes.
- Reconcile order status enum ↔ model constants.
- Sanitize product description HTML server-side.
- Add loading states, error handlers, empty states, default images across Vue pages.

### Sprint 3 — UX / SEO / a11y (P2)
- Meta / OG / JSON-LD / sitemap / robots.
- Form validation (pincode, phone, email, quantity min/max).
- Real labels + ARIA on client theme.
- Cart persistence, price snapshot, confirmation email.

---

## Appendix — Full Findings Table

| # | Severity | Area | File / Location | Issue | Fix Summary |
|---|----------|------|-----------------|-------|-------------|
| 1 | P0 | Payment | `app/Http/Controllers/Client/OrderController.php:81-92` | Order total trusted from client | Recompute server-side from DB price |
| 2 | P0 | Payment | `app/Http/Controllers/Client/PaymentController.php:24-26` | Callback trusts client-supplied `order_id` | Verify `order.customer_id === auth id` |
| 3 | P0 | Payment | `routes/web.php:49` | No Razorpay webhook | Add signed webhook endpoint |
| 4 | P0 | Payment | `app/Http/Controllers/Client/OrderController.php:74-79` | Stock decremented before payment | Move to webhook, add `FOR UPDATE` |
| 5 | P0 | Security | `app/Http/Controllers/Client/CustomerAddressController.php:24-49` | IDOR on addresses | Scope by `customer_id` |
| 6 | P0 | Security | `routes/web.php:48-49` | `/order` unauthenticated | Wrap in `client_auth` |
| 7 | P0 | Data | `database/migrations/2024_12_07_071509_create_products_table.php:21-22` | `DECIMAL` without scale | `DECIMAL(12,2)` |
| 7b | P0 | Data | `database/migrations/2025_01_10_074921_create_orders_table.php:18-22` | Same | Same |
| 7c | P0 | Data | `database/migrations/2025_01_10_074938_create_order_items_table.php:21` | Same | Same |
| 8 | P0 | Security | `.env:4,69-73` | Secrets in repo + `APP_DEBUG=true` | Rotate, remove from git |
| 9 | P0 | Bug | `app/Http/Controllers/Admin/OrderController.php:69` | Undefined `createShipment()` | Implement or repoint |
| 10 | P0 | Security | `app/Http/Requests/ProductRequest.php:17` (+ others) | `authorize()` = true | Real policy check |
| 11 | P0 | Security | `resources/views/client/product.blade.php:411` | Raw HTML `{!! !!}` | Sanitize via Purifier |
| 12 | P0 | Security | `app/Http/Controllers/Client/RegisterUserController.php:37`, `app/Helper/CommonHelper.php:69` | OTP: no rate limit, timing, weak RNG | `hash_equals`, `random_int`, throttle |
| 13 | P1 | Security | All models (`$guarded=[]`) | Mass assignment risk | Use `$fillable` |
| 14 | P1 | Security | `app/Http/Controllers/Admin/*.php` | No admin role check | Add `role:admin` middleware |
| 15 | P1 | Business | `app/Http/Controllers/Admin/OrderController.php:43-51` | No status transition rules | Enforce matrix |
| 16 | P1 | Business | `app/Http/Controllers/Client/ReviewController.php:12-27` | Reviews unmoderated, no purchase check | Add purchase check + `is_approved` |
| 17 | P1 | Validation | `app/Http/Requests/Client/OrderRequest.php:22-30` | `address_id` no `exists:` | Add rule |
| 18 | P1 | Reliability | `app/Services/ShiprocketService.php:16-27` | Errors swallowed, no retry | try/catch, log, cache token |
| 19 | P1 | Data | `database/migrations/2025_01_10_074921_create_orders_table.php:23` + `app/Models/Order.php:10-23` | Enum/constant mismatch | Reconcile values |
| 20 | P1 | Payment | `app/Services/CartService.php:41-67` | Price not snapshot at cart time | Store `unit_price` |
| 21 | P1 | Payment | `app/Http/Controllers/Client/OrderController.php:26` | No idempotency | Session lock / `Idempotency-Key` |
| 22 | P1 | UX | `resources/views/client/checkout.blade.php:127,488-491` | Broken submit + redirect to `/` | Fix redirect target |
| 23 | P1 | Notif | (order flow) | No confirmation email | Add `Mail::send` |
| 24 | P1 | Frontend | `resources/js/Components/common/Pagination.vue:5-6` | `v-html` XSS risk | Use `{{ }}` |
| 25 | P1 | Frontend | `resources/js/Pages/Auth/ResetPassword.vue:168-169` | `ref()` inside `data()` broken | Use plain booleans |
| 26 | P1 | Frontend | `resources/js/Pages/Auth/Register.vue:95-106` | Missing pw-confirm error | Add error block |
| 27 | P1 | Frontend | `resources/js/Pages/Admin/Order/Detail.vue:144` | Literal `{{ }}` in `alt` | Use `:alt` |
| 28 | P1 | Frontend | `resources/js/Pages/Admin/Product/Create.vue:482` | Blob URL leak | Revoke in `beforeUnmount` |
| 29 | P1 | Perf | Migrations | Missing FK indexes | Add via migration |
| 30 | P1 | Perf | Products/Categories migrations | No slug index | `->index()` |
| 31 | P1 | Data | Orders/Products/Customers | No soft deletes | `->softDeletes()` |
| 32 | P1 | Data | `database/migrations/2024_12_07_071509_create_products_table.php:23` | `stock` as decimal | `unsignedInteger` |
| 33 | P1 | Security | `config/session.php:172` | Cookie not secure-only | `SESSION_SECURE_COOKIE=true` |
| 34 | P2 | SEO | `resources/views/layouts/client.blade.php` | No OG/canonical/JSON-LD | Add meta + schema |
| 35 | P2 | SEO | (public) | No robots.txt / sitemap | Add both |
| 36 | P2 | A11y | `resources/views/components/header.blade.php` | Icon buttons need `aria-label` | Add labels |
| 37 | P2 | A11y | `resources/views/client/checkout.blade.php:74…` | Placeholders used as labels | Real `<label for>` |
| 38 | P2 | A11y | `resources/js/Components/Header.vue:58-283` | Dropdown no ESC/focus trap | Add keydown handler |
| 39 | P2 | UX | `resources/js/Pages/Admin/Product/Index.vue:54-110` | No empty state | Add row |
| 40 | P2 | UX | `resources/js/Pages/Admin/Product/Index.vue:193-202` | Wrong delete title | Fix copy |
| 41 | P2 | UX | `resources/views/client/product.blade.php:214` | qty `type="text"` | `type="number"` + min/max |
| 42 | P2 | UX | `resources/views/client/checkout.blade.php:132-165` | No pincode/phone regex | Add pattern |
| 43 | P2 | UX | (checkout) | No empty-cart guard | Redirect if empty |
| 44 | P2 | Legal | `resources/views/client/shopping-cart.blade.php:202-206` | T&Cs optional | Enforce |
| 45 | P2 | Security | `resources/views/layouts/client.blade.php:717-728` | Flash msg not JSON-encoded | Use `@json()` |
| 46 | P2 | UX | `resources/views/components/header.blade.php:141` | Typo "Contant Us" | Fix copy |
| 47 | P2 | Quality | Multiple `.vue` / `.blade.php` files | `console.log` in prod | Remove |
| 48 | P2 | Quality | `resources/js/Pages/Admin/Product/Create.vue:373` | Unused Dropzone import | Remove |
| 49 | P2 | Quality | Multiple | Hardcoded `/storage/product/` | Use composable |
| 50 | P3 | Data | `database/migrations/2024_12_07_071509_create_products_table.php:22` | `compere_price` typo | Rename to `compare_price` |
| 51 | P3 | Data | Same file line 24 | `sku` not unique | `->unique()` |
| 52 | P3 | Data | `database/migrations/2025_12_07_172034_create_reviews_table.php` | No moderation flags | Add columns |
| 53 | P3 | Security | `resources/views/layouts/client.blade.php:754` | No SRI on Razorpay JS | Add `integrity=` |
| 54 | P3 | Perf | `resources/views/client/product.blade.php:654,835` | Missing lazy-load | Add class |
| 55 | P3 | Bug | `app/Http/Controllers/Auth/RegisteredUserController.php:49` | All registrants → admin dash | Role-based redirect |
| 56 | P3 | Audit | Admin controllers | No activity log | Add `spatie/laravel-activitylog` |
| 57 | P3 | Hygiene | `git status` | Orphan files (`Product copy.php`, built assets) | Commit cleanup |

---

---

## What still requires operator action

These items cannot be fixed by code changes alone. Do these before pointing production traffic at the app:

1. **Rotate credentials.** Generate new Razorpay test/live keys and new Shiprocket password. Replace values in your production `.env`.
2. **Remove `.env` from git.** Run `git rm --cached .env && git commit -m "Untrack .env"`. Verify `.env` is present in `.gitignore`.
3. **Set new env variables.** In `.env` add:
   ```
   RAZORPAY_KEY=...            # NEW rotated key
   RAZORPAY_SECRET=...         # NEW rotated secret
   RAZORPAY_WEBHOOK_SECRET=... # from Razorpay dashboard → Webhooks
   SHIPROCKET_EMAIL=...
   SHIPROCKET_PASSWORD=...
   SESSION_SECURE_COOKIE=true  # for HTTPS deployments
   SESSION_ENCRYPT=true
   APP_DEBUG=false             # never true in prod
   ```
4. **Register the webhook in Razorpay dashboard.** URL: `https://YOUR_DOMAIN/webhooks/razorpay`. Subscribe to `payment.captured` and `payment.failed`. Copy the secret into `RAZORPAY_WEBHOOK_SECRET`.
5. **Run the schema-fix migration:**
   ```
   php artisan migrate
   ```
   This runs `2026_07_12_000001_fix_ecommerce_schema_issues.php` — it changes decimal precision, adds indexes, soft-delete columns, and review moderation columns without touching data.
6. **Clear all caches:**
   ```
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   npm run build
   ```

## Summary of what was fixed in this session

**P0 (ship-blockers) — DONE in code:**
- Server-side order-total recomputation from DB prices + all-stock validation up-front + `lockForUpdate` (`app/Http/Controllers/Client/OrderController.php`)
- Idempotency lock via session on order-create
- Auth check + address ownership check on order-save
- IDOR fixes on `edit`/`update`/`delete` in `CustomerAddressController`
- Razorpay callback: order-ownership check, distinct signature-verification catch, DB transaction, structured logging, cart cleared only on success
- New `POST /webhooks/razorpay` endpoint with HMAC verification, handles `payment.captured` / `payment.failed`, decrements stock only after payment confirmed
- Stock decrement moved to after payment for Razorpay path
- `/order`, `/razorpay/callback`, address routes now wrapped in `client_auth` middleware group
- Webhook route CSRF-exempted in `bootstrap/app.php`
- Admin `OrderController::addDelivery` no longer calls undefined method — `ShiprocketService::createShipment` implemented and errors captured
- Admin `OrderController::update` enforces status-transition matrix
- Migration `2026_07_12_000001_fix_ecommerce_schema_issues` fixes decimal scale, adds FK/slug indexes, soft deletes, review moderation columns
- Original create-table migrations updated so fresh installs are also correct
- OTP: `random_int`, `hash_equals`, `Carbon::now()` (typo fixed), null-check before parsing, rate-limit throttle on `verify-otp`, `send-otp`, `login`, `register`, `forgot-password`, `reset-password`
- FormRequest `authorize()` now checks real auth (customer for client requests, `Auth::check()` for admin `ProductRequest`)
- Product description sanitized with `CommonHelper::sanitizeHtml()` on save AND on client render (defense in depth)
- `env(...)` calls replaced with `config('services.razorpay.*')` — cache-safe

**P1 — DONE in code:**
- All models converted from `$guarded = []` to explicit `$fillable`
- Order/Product/Customer/Review/Payment/OrderItem/Category/DeliveryPartner/CustomerAddress/ProductCategory/ProductMedia/PasswordReset updated with fillable, casts, relationships
- `SoftDeletes` on `Order` and `Product`
- `Order` model status constants reconciled with DB enum (`completed`→`delivered`, `cancelled`→`canceled`)
- Cart now snapshots `unit_price` at add-to-cart time; stock re-checked on server before add
- Reviews now require prior purchase, one review per customer/product, default `is_approved = false`, public list filters approved-only
- Vue Pagination replaced `v-html` with safe text stripping
- Register.vue shows `password_confirmation` errors + live-mismatch hint
- ResetPassword.vue removed misused `ref()` inside Options API + removed `console.log`
- Admin Order Detail: fixed `alt={{...}}` → `:alt`, image fallback via `@error`
- Admin Product Create: `blob:` URLs revoked in `beforeUnmount`, `onFinish` cleanup, console.logs removed
- Admin Product Index: empty-state row, corrected delete-confirmation title from "Category Delete" to "Delete Product", `:key="product.id"`

**P2 — DONE in code:**
- Client layout: OG/Twitter/canonical/robots meta + `@json` for flash message (fixes potential quote-break XSS)
- Product page: JSON-LD Product structured data + safe image OG
- Product page: qty input now `type="number"` with `min=1 max={{stock}}`
- Checkout form: `<label>` for every input, `type="email"`/`type="tel"` where appropriate, pattern/maxlength/autocomplete, `required` attributes
- Checkout redirect goes to `client.profile` (order history) instead of `/`, `modal.ondismiss` re-enables Pay button
- `CheckoutController::index` now redirects to cart when empty
- Shopping cart: T&C checkbox is `required`, checkout link blocked if unchecked
- Header nav "Contant Us" → "Contact Us"
- `public/robots.txt` disallows admin, checkout, auth, profile, webhooks
- Rate limiting added to `/reviews` (`throttle:10,60`) and auth routes

## Third-party integrations — full implementation

### Payment gateway (Razorpay)
- Client-side callback (`POST /razorpay/callback`) — customer-authenticated, ownership-checked, signature-verified, atomic status + stock update, cart cleared on success.
- Server webhook (`POST /webhooks/razorpay`, CSRF-exempt) — handles `payment.captured`, `payment.failed`, `refund.processed`, `refund.created`. Idempotent — safe against duplicate events.
- Refund API (`POST /admin/a_orders/{order}/refund`) — partial or full refund via `razorpay/razorpay` SDK. Full refunds also mark the order cancelled and restock inventory.
- Payment model tracks `refunded_amount`, `refunded_at`, `refund_id`, `failure_reason`, and `meta` (raw gateway payloads).

### Courier partner (Shiprocket)
`app/Services/ShiprocketService.php` now covers the whole lifecycle:
- Auth (cached token, auto-refresh on 401)
- `checkServiceability(pickup, delivery, weight, cod)`
- `createOrder($order, $items)` — Adhoc order create
- `assignAwb($shipmentId, $courierId?)`
- `requestPickup([...shipmentIds])`
- `generateLabel([...shipmentIds])`
- `generateInvoice([...orderIds])`
- `generateManifest([...shipmentIds])`
- `trackByAwb($awb)` / `trackByShipmentId($id)`
- `cancelOrders([...orderIds])` / `cancelShipments([...awbs])`

`app/Services/ShipmentService.php` is the business wrapper that:
- persists everything into the new `shipments` table
- maps provider-specific status strings to the normalized `Shipment::STATUS_*` set
- writes one `order_status_histories` row for every transition (source: `system`, `admin`, `webhook`, `customer`)
- syncs the parent `orders.status` when shipments hit terminal states (Delivered → COMPLETED, Cancelled → CANCELLED)
- handles the Shiprocket status-push webhook

### Admin order tracking UI
`resources/js/Pages/Admin/Order/Detail.vue` was rewritten to expose:
- Customer / address / payment summary cards
- Line items table with per-row totals + sub-total, shipping, discount, grand total
- Shipment card with provider, AWB, courier, dates, tracking URL, label URL
- One-click actions: Assign AWB, Request Pickup, Generate Label, Sync Tracking, Cancel Shipment (each with its own loading state)
- Refund panel (partial or full) with inline reason field
- Full status timeline (order + shipment history combined)
- Guarded status dropdown that respects the transition matrix

### Admin routes added
```
POST /admin/a_orders/{order}/refund                       admin.order.refund
GET  /admin/a_orders/{order}/serviceability               admin.order.serviceability
POST /admin/a_orders/shipment/{shipment}/assign-awb       admin.shipment.assign_awb
POST /admin/a_orders/shipment/{shipment}/pickup           admin.shipment.pickup
POST /admin/a_orders/shipment/{shipment}/label            admin.shipment.label
POST /admin/a_orders/shipment/{shipment}/sync             admin.shipment.sync
POST /admin/a_orders/shipment/{shipment}/cancel           admin.shipment.cancel
POST /webhooks/razorpay                                    razorpay.webhook
POST /webhooks/shiprocket                                  shiprocket.webhook
```

### Additional env variables required

```
# Razorpay
RAZORPAY_KEY=...
RAZORPAY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...

# Shiprocket
SHIPROCKET_EMAIL=...
SHIPROCKET_PASSWORD=...
SHIPROCKET_PICKUP_LOCATION=Primary
SHIPROCKET_PICKUP_PINCODE=110001
SHIPROCKET_CHANNEL_ID=            # optional
SHIPROCKET_WEBHOOK_TOKEN=<random-secret-you-choose>
SHIPROCKET_DEFAULT_LENGTH=10
SHIPROCKET_DEFAULT_BREADTH=10
SHIPROCKET_DEFAULT_HEIGHT=5
SHIPROCKET_DEFAULT_WEIGHT=0.5
```

### Webhook setup

- **Razorpay** — Dashboard → Webhooks → add `https://YOUR_DOMAIN/webhooks/razorpay`, subscribe to `payment.captured`, `payment.failed`, `refund.processed`, `refund.created`; copy the secret into `RAZORPAY_WEBHOOK_SECRET`.
- **Shiprocket** — Dashboard → Settings → API → Webhook URL: `https://YOUR_DOMAIN/webhooks/shiprocket?token=YOUR_TOKEN`. The `SHIPROCKET_WEBHOOK_TOKEN` env var must match the `token` query (or `X-Api-Key` header) — invalid tokens are rejected with a 401.

### DB migrations added

- `2026_07_12_000002_create_shipments_and_order_status_history.php` creates `shipments` and `order_status_histories`, adds `refund_id`, `refunded_amount`, `refunded_at`, `failure_reason`, `meta` on `payments`, and backfills initial history for existing orders.

Run:
```
php artisan migrate
php artisan config:clear
php artisan route:clear
npm run build
```

_End of report._
