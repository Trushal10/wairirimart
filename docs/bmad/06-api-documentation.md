# 06 — API Documentation

**Convention**: The application uses server-rendered HTML (Blade) for the storefront and Inertia (JSON payload → Vue) for the admin panel. There is **no dedicated JSON API layer** and no `routes/api.php` is enabled (`bootstrap/app.php:14` — `api:` is commented out). All endpoints below are `POST/PUT/DELETE` form submissions or `GET` HTML pages unless explicitly noted.

- **CSRF token** is required on every non-GET request (Blade forms embed `@csrf`; Inertia auto-injects the header).
- **Session cookie** is used for authentication (both admin `web` and customer `customer` guard).
- **Response types**: HTML (Blade), Inertia JSON (admin), JSON (specific AJAX endpoints like `/wishlist/toggle`, `/apply-coupon`).

For a machine-readable route dump: `php artisan route:list`.

---

## 1. Endpoint index (140+ routes)

Grouped by domain. All URIs are absolute paths relative to `APP_URL`.

### 1.1 Storefront (public)

| Method | URI                                    | Handler                                            | Middleware      | Notes                          |
| ------ | -------------------------------------- | -------------------------------------------------- | --------------- | ------------------------------ |
| GET    | `/`                                    | `Client\HomeController@index`                      | —               | Homepage                       |
| GET    | `/shop`                                | `Client\ShopController@index`                      | —               | `?q=&category=&sort=`          |
| GET    | `/categories`                          | `Client\CategoryController@index`                  | —               |                                |
| GET    | `/product-detail/{slug}`               | `Client\ProductController@index`                   | —               |                                |
| GET    | `/contact`                             | `Client\HomeController@contact`                    | —               |                                |
| POST   | `/contact`                             | `Client\HomeController@saveContact`                | throttle:5,1    | Contact form submit            |
| GET    | `/about`                               | `AboutController@index`                            | —               |                                |
| GET    | `/sitemap.xml`                         | `SitemapController@index`                          | —               | XML sitemap                    |
| GET    | `/404`                                 | (fallback via Laravel)                             | —               |                                |

### 1.2 Cart (public, session-based)

| Method | URI                          | Handler                                    | Middleware       | Notes                                       |
| ------ | ---------------------------- | ------------------------------------------ | ---------------- | ------------------------------------------- |
| GET    | `/shopping-cart`             | `Client\ShoppingCartController@index`      | —                | Cart page                                   |
| POST   | `/update-cart-item`          | `Client\ShoppingCartController@updateCheckOutItem` | —        | Update quantity                             |
| POST   | `/remove-checkout-item`      | `Client\ShoppingCartController@removeCheckOutItem` | —        | Remove from cart                            |
| GET    | `/cart`                      | `Client\CartController@cartList`           | —                | JSON — cart contents for mini-cart          |
| POST   | `/add-to-cart`               | `Client\CartController@addToCart`          | throttle:60,1    | Body: `productId, variantId?, quantity`     |
| POST   | `/quick-add`                 | `Client\CartController@quickAdd`           | throttle:60,1    |                                             |
| POST   | `/remove-cart-item`          | `Client\CartController@removeCartItem`     | throttle:60,1    |                                             |

### 1.3 Checkout & orders (customer auth)

| Method | URI                                   | Handler                                    | Middleware                                   | Notes                                                                |
| ------ | ------------------------------------- | ------------------------------------------ | -------------------------------------------- | -------------------------------------------------------------------- |
| GET    | `/checkout`                           | `Client\CheckoutController@index`          | —                                            | Redirects to `/login` if not authed                                  |
| POST   | `/order`                              | `Client\OrderController@save`              | `client_auth`, throttle:10,1                 | Places an order. Body: `address_id, payment_method, coupon_code?, agree_tos` |
| GET    | `/order/confirmation/{orderNo}`       | `Client\OrderController@confirmation`      | `client_auth`, `whereAlphaNumeric`           | Ownership-guarded                                                    |
| GET    | `/invoice/{orderNo}`                  | `InvoiceController@customerInvoice`        | `client_auth`, `whereAlphaNumeric`           | HTML invoice; browser can save as PDF                                |
| POST   | `/razorpay/callback`                  | `Client\PaymentController@razorpayCallback`| `client_auth`, throttle:20,1                 | Razorpay success handler                                              |

### 1.4 Webhooks (public, CSRF-exempt)

| Method | URI                       | Handler                                        | Middleware       | Notes                                                                 |
| ------ | ------------------------- | ---------------------------------------------- | ---------------- | --------------------------------------------------------------------- |
| POST   | `/webhooks/razorpay`      | `Client\PaymentController@razorpayWebhook`     | throttle:120,1   | HMAC-SHA256 signature required (`X-Razorpay-Signature`)               |
| POST   | `/webhooks/shiprocket`    | `Client\PaymentController@shiprocketWebhook`   | throttle:120,1   | HMAC-SHA256 signature required (`X-Shiprocket-Signature`) or token    |

### 1.5 Tracking (public, session-verified)

| Method | URI                             | Handler                             | Middleware                                   | Notes                                              |
| ------ | ------------------------------- | ----------------------------------- | -------------------------------------------- | -------------------------------------------------- |
| GET    | `/track`                        | `Client\TrackingController@form`    | —                                            | Search form                                        |
| POST   | `/track`                        | `Client\TrackingController@verify`  | throttle:10,1                                | Verify order# + email                              |
| GET    | `/track/{orderNo}`              | `Client\TrackingController@show`    | `whereAlphaNumeric`                          | Requires session grant                             |
| GET    | `/track/{orderNo}/live`         | `Client\TrackingController@live`    | throttle:120,1                               | JSON — for polling                                 |
| POST   | `/track/{orderNo}/logout`       | `Client\TrackingController@logout`  | `whereAlphaNumeric`                          | Ends tracking session                              |

### 1.6 Customer auth

| Method | URI                       | Handler                                            | Middleware       | Notes                                                              |
| ------ | ------------------------- | -------------------------------------------------- | ---------------- | ------------------------------------------------------------------ |
| GET    | `/register`               | `Client\RegisterUserController@index`              | —                |                                                                    |
| POST   | `/register`               | `Client\RegisterUserController@save`               | throttle:5,1     | Body: `name, email, phone, password, password_confirmation, agree_tos`  |
| GET    | `/verification`           | `Client\RegisterUserController@verify`             | —                | OTP entry page                                                     |
| POST   | `/verify-otp`             | `Client\RegisterUserController@verifyOtp`          | throttle:5,1     | Body: `token, otp`                                                 |
| GET    | `/login`                  | `Client\LoginUserController@index`                 | —                |                                                                    |
| POST   | `/login`                  | `Client\LoginUserController@loginUser`             | throttle:5,1     | Body: `email_or_phone, password`                                   |
| DELETE | `/logout`                 | `Client\LoginUserController@logout`                | `client_auth`    |                                                                    |
| GET    | `/forgot-password`        | `Client\ForgotPasswordController@index`            | —                |                                                                    |
| POST   | `/forgot-password`        | `Client\ForgotPasswordController@forgotPassword`   | throttle:5,1     | Body: `email`                                                      |
| GET    | `/reset-password`         | `Client\ForgotPasswordController@resetPasswordForm`| —                |                                                                    |
| PUT    | `/reset-password`         | `Client\ForgotPasswordController@resetPassword`    | throttle:5,1     | Body: `token, otp, password, password_confirmation`                |
| POST   | `/send-otp`               | `Client\OtpController@index`                       | throttle:3,1     | Re-issue OTP                                                       |
| GET    | `/auth/{provider}/redirect` | `Client\SocialAuthController@redirect`           | provider=google  |                                                                    |
| GET    | `/auth/{provider}/callback` | `Client\SocialAuthController@callback`           | provider=google  | ⚠️ Incomplete; see [15 Security](15-security-architecture.md)      |

### 1.7 Customer profile (customer auth)

All under `client_auth` middleware.

| Method | URI                              | Handler                                             | Notes                                    |
| ------ | -------------------------------- | --------------------------------------------------- | ---------------------------------------- |
| GET    | `/profile`                       | `Client\ProfileController@index`                    |                                          |
| PUT    | `/profile`                       | `Client\ProfileController@updateProfile`            | Body: `name, email, phone`               |
| PUT    | `/profile-image`                 | `Client\ProfileController@updateProfileImage`       | Multipart form                           |
| PUT    | `/update-password`               | `Client\ProfileController@updatePassword`           | Body: `current_password, password, password_confirmation` |
| POST   | `/user-address-save`             | `Client\CustomerAddressController@save`             |                                          |
| GET    | `/{id}/user-address-edit`        | `Client\CustomerAddressController@edit`             | `whereNumber('id')`                      |
| PUT    | `/{id}/user-address-update`      | `Client\CustomerAddressController@update`           |                                          |
| DELETE | `/{id}/user-address-delete`      | `Client\CustomerAddressController@delete`           |                                          |

### 1.8 Returns (customer auth)

| Method | URI                                        | Handler                                     | Notes                                    |
| ------ | ------------------------------------------ | ------------------------------------------- | ---------------------------------------- |
| GET    | `/returns/{orderNo}/new`                   | `Client\ReturnController@create`            |                                          |
| POST   | `/returns/{orderNo}`                       | `Client\ReturnController@store`             | throttle:10,1                            |
| GET    | `/returns/view/{returnNo}`                 | `Client\ReturnController@show`              |                                          |
| POST   | `/returns/view/{returnNo}/cancel`          | `Client\ReturnController@cancel`            | Only from `requested` state              |

### 1.9 Wishlist

| Method | URI                     | Handler                              | Middleware                        | Notes                                            |
| ------ | ----------------------- | ------------------------------------ | --------------------------------- | ------------------------------------------------ |
| GET    | `/wishlist`             | `Client\WishlistController@index`    | `client_auth`                     | Wishlist page                                    |
| POST   | `/wishlist/toggle`      | `Client\WishlistController@toggle`   | `client_auth`, throttle:60,1      | JSON — Body: `product_id, variant_id?`. Response: `{added: bool}` |
| POST   | `/wishlist/remove`      | `Client\WishlistController@remove`   | `client_auth`                     |                                                  |
| GET    | `/wishlist/ids`         | `Client\WishlistController@ids`      | —                                 | JSON — returns wishlist IDs for the current session (empty if not authed) |

### 1.10 Coupons & reviews

| Method | URI                | Handler                                | Middleware                      | Notes                                        |
| ------ | ------------------ | -------------------------------------- | ------------------------------- | -------------------------------------------- |
| POST   | `/apply-coupon`    | `Client\CouponController@apply`        | throttle:20,1                   | JSON — Body: `code, subtotal`. Response: `{discount, total}` |
| DELETE | `/remove-coupon`   | `Client\CouponController@remove`       | —                               | Clears session coupon                        |
| GET    | `/reviews`         | `Client\ReviewController@getReviewsByProduct` | —                        | Query: `product_id`. JSON.                   |
| POST   | `/reviews`         | `Client\ReviewController@store`        | `client_auth`, throttle:10,60   | Body: `product_id, rate, title, review, image?` |

---

## 2. Admin API (all under `auth` + `admin` middleware, prefix `/admin`)

Admin endpoints return Inertia responses (JSON on XHR, HTML on first paint). Standard Inertia headers apply (`X-Inertia`, `X-Inertia-Version`).

### 2.1 Dashboard & profile

| Method | URI                | Handler                             | Route name                     |
| ------ | ------------------ | ----------------------------------- | ------------------------------ |
| GET    | `/admin/dashboard` | `HomeController@index`              | `admin.dashboard`              |
| GET    | `/admin/profile`   | `Admin\ProfileController@index`     | `admin.profile`                |
| PATCH  | `/admin/profile`   | `Admin\ProfileController@update`    | `admin.profile.update`         |
| GET    | `/admin/search`    | `Admin\SearchController@__invoke`   | `admin.search` (throttle:60,1) |

### 2.2 Products

| Method | URI                                        | Handler                                | Route name              |
| ------ | ------------------------------------------ | -------------------------------------- | ----------------------- |
| GET    | `/admin/a_products`                        | `Admin\ProductController@index`        | `admin.products`        |
| GET    | `/admin/a_products/create`                 | `Admin\ProductController@create`       | `admin.products.create` |
| POST   | `/admin/a_products/create`                 | `Admin\ProductController@store`        | `admin.product.store`   |
| GET    | `/admin/a_products/{product}/edit`         | `Admin\ProductController@edit`         | `admin.product.edit`    |
| PUT    | `/admin/a_products/{product}/update`       | `Admin\ProductController@update`       | `admin.product.update`  |
| DELETE | `/admin/a_products/{product}/delete`       | `Admin\ProductController@delete`       | `admin.product.delete`  |

### 2.3 Categories

| Method | URI                                        | Route name                  |
| ------ | ------------------------------------------ | --------------------------- |
| GET    | `/admin/a_category`                        | `admin.category`            |
| GET    | `/admin/a_category/create`                 | `admin.category.create`     |
| POST   | `/admin/a_category/create`                 | `admin.category.store`      |
| GET    | `/admin/a_category/{category}/edit`        | `admin.category.edit`       |
| PUT    | `/admin/a_category/{category}/update`      | `admin.category.update`     |
| DELETE | `/admin/a_category/{category}/delete`      | `admin.category.delete`     |

### 2.4 Orders & shipments

| Method | URI                                                       | Route name                        |
| ------ | --------------------------------------------------------- | --------------------------------- |
| GET    | `/admin/a_orders`                                         | `admin.orders`                    |
| GET    | `/admin/a_orders/{order}/detail`                          | `admin.order.detail`              |
| GET    | `/admin/a_orders/{order}/invoice`                         | `admin.order.invoice`             |
| PUT    | `/admin/a_orders/{id}/update`                             | `admin.order.update`              |
| PUT    | `/admin/a_orders/{order}/delivery`                        | `admin.orders.delivery.update`    |
| POST   | `/admin/a_orders/{order}/refund`                          | `admin.order.refund`              |
| GET    | `/admin/a_orders/{order}/serviceability`                  | `admin.order.serviceability`      |
| GET    | `/admin/a_orders/{order}/label`                           | `admin.order.label`               |
| POST   | `/admin/a_orders/shipment/{shipment}/assign-awb`          | `admin.shipment.assign_awb`       |
| POST   | `/admin/a_orders/shipment/{shipment}/pickup`              | `admin.shipment.pickup`           |
| POST   | `/admin/a_orders/shipment/{shipment}/label`               | `admin.shipment.label`            |
| POST   | `/admin/a_orders/shipment/{shipment}/sync`                | `admin.shipment.sync`             |
| POST   | `/admin/a_orders/shipment/{shipment}/cancel`              | `admin.shipment.cancel`           |

### 2.5 Returns

| Method | URI                                        | Route name                |
| ------ | ------------------------------------------ | ------------------------- |
| GET    | `/admin/returns`                           | `admin.returns.index`     |
| GET    | `/admin/returns/{return}`                  | `admin.returns.show`      |
| POST   | `/admin/returns/{return}/approve`          | `admin.returns.approve`   |
| POST   | `/admin/returns/{return}/reject`           | `admin.returns.reject`    |
| POST   | `/admin/returns/{return}/received`         | `admin.returns.received`  |
| POST   | `/admin/returns/{return}/refunded`         | `admin.returns.refunded`  |

### 2.6 Payments

| Method | URI                                        | Route name                    |
| ------ | ------------------------------------------ | ----------------------------- |
| GET    | `/admin/a_payment`                         | `admin.payments`              |
| DELETE | `/admin/a_payment/{payment}/delete`        | `admin.payment.delete`        |

### 2.7 Coupons

| Method | URI                                        | Route name                    |
| ------ | ------------------------------------------ | ----------------------------- |
| GET    | `/admin/a_coupons`                         | `admin.coupons.index`         |
| GET    | `/admin/a_coupons/create`                  | `admin.coupons.create`        |
| POST   | `/admin/a_coupons/create`                  | `admin.coupons.store`         |
| GET    | `/admin/a_coupons/{coupon}/edit`           | `admin.coupons.edit`          |
| PUT    | `/admin/a_coupons/{coupon}/update`         | `admin.coupons.update`        |
| DELETE | `/admin/a_coupons/{coupon}/delete`         | `admin.coupons.delete`        |
| POST   | `/admin/a_coupons/{coupon}/toggle`         | `admin.coupons.toggle`        |

### 2.8 Payment gateways & courier partners

| Method | URI                                                                    | Route name                             |
| ------ | ---------------------------------------------------------------------- | -------------------------------------- |
| GET    | `/admin/settings/payment-gateways`                                     | `admin.payment_gateways.index`         |
| GET    | `/admin/settings/payment-gateways/{paymentGateway}/edit`               | `admin.payment_gateways.edit`          |
| PUT    | `/admin/settings/payment-gateways/{paymentGateway}`                    | `admin.payment_gateways.update`        |
| POST   | `/admin/settings/payment-gateways/{paymentGateway}/toggle`             | `admin.payment_gateways.toggle`        |
| POST   | `/admin/settings/payment-gateways/{paymentGateway}/default`            | `admin.payment_gateways.default`       |
| POST   | `/admin/settings/payment-gateways/{paymentGateway}/test`               | `admin.payment_gateways.test`          |
| GET    | `/admin/settings/couriers`                                             | `admin.couriers.index`                 |
| GET    | `/admin/settings/couriers/{courier}/edit`                              | `admin.couriers.edit`                  |
| PUT    | `/admin/settings/couriers/{courier}`                                   | `admin.couriers.update`                |
| POST   | `/admin/settings/couriers/{courier}/toggle`                            | `admin.couriers.toggle`                |
| POST   | `/admin/settings/couriers/{courier}/default`                           | `admin.couriers.default`               |
| POST   | `/admin/settings/couriers/{courier}/test`                              | `admin.couriers.test`                  |

### 2.9a Reports & operating expenses (new — see [29 Reports Module](29-reports-module.md))

| Method | URI                                                | Route name                          |
| ------ | -------------------------------------------------- | ----------------------------------- |
| GET    | `/admin/reports`                                   | `admin.reports.index` (→ sales)     |
| GET    | `/admin/reports/sales`                             | `admin.reports.sales`               |
| GET    | `/admin/reports/orders`                            | `admin.reports.orders`              |
| GET    | `/admin/reports/payments`                          | `admin.reports.payments`            |
| GET    | `/admin/reports/shipping`                          | `admin.reports.shipping`            |
| GET    | `/admin/reports/pnl`                               | `admin.reports.pnl`                 |
| GET    | `/admin/reports/orders/export?format=csv\|xlsx\|pdf` | `admin.reports.orders.export`       |
| GET    | `/admin/reports/payments/export?format=…`          | `admin.reports.payments.export`     |
| GET    | `/admin/reports/shipping/export?format=…`          | `admin.reports.shipping.export`     |
| GET    | `/admin/reports/pnl/export?format=…`               | `admin.reports.pnl.export`          |
| GET    | `/admin/reports/orders.csv`                        | `admin.reports.orders.csv` (BC)     |
| GET    | `/admin/reports/payments.csv`                      | `admin.reports.payments.csv` (BC)   |
| GET    | `/admin/expenses`                                  | `admin.expenses.index`              |
| GET    | `/admin/expenses/create`                           | `admin.expenses.create`             |
| POST   | `/admin/expenses`                                  | `admin.expenses.store`              |
| GET    | `/admin/expenses/{expense}/edit`                   | `admin.expenses.edit`               |
| PUT    | `/admin/expenses/{expense}`                        | `admin.expenses.update`             |
| DELETE | `/admin/expenses/{expense}`                        | `admin.expenses.destroy`            |

Filters accepted by any report page: `preset`, `from`, `to`, `group`, `status`, `payment_status`, `payment_type`, `courier_id`, `category_id`, `brand`, `product_id`, `customer_id`, `search`, `sort`, `dir`, `per_page`. Every parameter is optional and validated server-side by `ReportFilters::fromRequest()`.

### 2.9 Sliders, contacts, settings, reports (legacy)

| Method | URI                                                          | Route name                       |
| ------ | ------------------------------------------------------------ | -------------------------------- |
| GET    | `/admin/slider`                                              | `admin.sliders`                  |
| GET    | `/admin/slider/create`                                       | `admin.slider.create`            |
| POST   | `/admin/slider/save`                                         | `admin.slider.save`              |
| GET    | `/admin/slider/{slider}/edit`                                | `admin.slider.edit`              |
| PUT    | `/admin/slider/{slider}/update`                              | `admin.slider.update`            |
| DELETE | `/admin/slider/delete/{slider}`                              | `admin.slider.delete`            |
| POST   | `/admin/slider/{slider}/media/save`                          | `admin.slider.media.save`        |
| PUT    | `/admin/slider/{slider}/media/{media}/update`                | `admin.slider.media.update`      |
| DELETE | `/admin/slider/{slider}/media/{media}/delete`                | `admin.slider.media.delete`      |
| GET    | `/admin/a_contacts`                                          | `admin.contacts`                 |
| DELETE | `/admin/a_contacts/{contact}/delete`                         | `admin.contact.delete`           |
| GET    | `/admin/setting`                                             | `admin.setting`                  |
| PUT    | `/admin/{setting}/update`                                    | `admin.setting.update`           |
| GET    | `/admin/reports/orders.csv`                                  | `admin.reports.orders`           |
| GET    | `/admin/reports/payments.csv`                                | `admin.reports.payments`         |

### 2.10 Admin auth (routes/auth.php)

| Method | URI                                       | Handler                                                    | Route name                       |
| ------ | ----------------------------------------- | ---------------------------------------------------------- | -------------------------------- |
| GET    | `/admin/register`                         | `Auth\RegisteredUserController@create`                     | `admin.register`                 |
| POST   | `/admin/register`                         | `Auth\RegisteredUserController@store`                      | (throttle:5,1)                   |
| GET    | `/admin/login`                            | `Auth\AuthenticatedSessionController@create`               | `admin.login`                    |
| POST   | `/admin/login`                            | `Auth\AuthenticatedSessionController@store`                | (throttle:5,1)                   |
| GET    | `/admin/forgot-password`                  | `Auth\PasswordResetLinkController@create`                  | `admin.password.request`         |
| POST   | `/admin/forgot-password`                  | `Auth\PasswordResetLinkController@store`                   | `admin.password.email` (throttle:5,1) |
| GET    | `/admin/reset-password/{token}`           | `Auth\NewPasswordController@create`                        | `admin.password.reset`           |
| POST   | `/admin/reset-password`                   | `Auth\NewPasswordController@store`                         | `admin.password.store` (throttle:5,1) |
| GET    | `/admin/verify-email`                     | `Auth\EmailVerificationPromptController`                   | `admin.verification.notice`      |
| GET    | `/admin/verify-email/{id}/{hash}`         | `Auth\VerifyEmailController`                               | `admin.verification.verify` (signed, throttle:6,1) |
| POST   | `/admin/email/verification-notification`  | `Auth\EmailVerificationNotificationController@store`       | `admin.verification.send` (throttle:6,1) |
| GET    | `/admin/confirm-password`                 | `Auth\ConfirmablePasswordController@show`                  | `admin.password.confirm`         |
| POST   | `/admin/confirm-password`                 | `Auth\ConfirmablePasswordController@store`                 |                                  |
| PUT    | `/admin/password`                         | `Auth\PasswordController@update`                           | `admin.password.update`          |
| POST   | `/admin/logout`                           | `Auth\AuthenticatedSessionController@destroy`              | `admin.logout`                   |

---

## 3. Request / response contracts

### 3.1 `POST /add-to-cart`
```json
Request  { "productId": 12, "variantId": 44, "quantity": 1, "size": "M", "color": "Red" }
Response { "status": "success", "count": 3, "message": "Added to cart" }
Errors   422 { "errors": { "productId": ["Product not found"] } }
```

### 3.2 `POST /order` (form data or JSON)
```json
Request  { "address_id": 5, "payment_method": "razorpay|cod", "coupon_code": "SAVE10", "agree_tos": 1 }
Response 302 → /order/confirmation/{orderNo} (COD) OR JSON with Razorpay `order_id`
Errors   422 (validation), 403 (unauthenticated), 409 (out of stock), 429 (rate limit)
```

### 3.3 `POST /razorpay/callback`
```json
Request { "razorpay_payment_id": "pay_...", "razorpay_order_id": "order_...", "razorpay_signature": "hex..." }
Response 302 → /order/confirmation/{orderNo}
Errors   400 (signature mismatch), 422 (amount mismatch), 409 (already paid)
```

### 3.4 `POST /webhooks/razorpay`
```
Headers  X-Razorpay-Signature: <hex>
Body     JSON payload from Razorpay
Response 200 (accepted / duplicate / no-op) | 400 (bad signature / bad JSON)
```

### 3.5 `POST /webhooks/shiprocket`
```
Headers  X-Shiprocket-Signature: <hex>    (preferred)
         or                              X-Auth-Token: <shared-secret>
Body     Shiprocket tracking JSON
Response 200 | 400 (bad signature / bad JSON)
```

### 3.6 `POST /apply-coupon`
```json
Request  { "code": "SAVE10", "subtotal": 1499.00 }
Response { "success": true, "discount": 149.90, "total": 1349.10, "code": "SAVE10" }
Errors   { "success": false, "message": "Coupon expired" }
```

### 3.7 `POST /wishlist/toggle`
```json
Request  { "product_id": 12, "variant_id": 44 }
Response { "success": true, "added": true, "count": 5 }
```

### 3.8 `POST /reviews`
```
Multipart form: product_id, rate (1..5), title, review, image? (max 2 MB)
Response 302 → back with flash
```

### 3.9 `GET /track/{orderNo}/live`
```json
Response { "status": "in_transit", "updates": [ {timestamp, status, remark}, ... ], "eta": "..." }
```

---

## 4. Authentication & authorization

- **Admin auth** — `web` guard, session cookie. Login at `/admin/login`. Every `/admin/*` route requires both `auth` and `admin` middleware (`AdminOnly`) which asserts `Auth::user()->role === 'admin'`.
- **Customer auth** — `customer` guard, session cookie. Login at `/login`. `ClientAuthMiddleware` guards `/profile`, `/order`, `/returns/*`, `/wishlist/*`, `/razorpay/callback`, address routes.
- **Webhook auth** — HMAC signature. No cookie.
- **CSRF** — Required on all state-changing HTTP methods except the two webhook URIs (`bootstrap/app.php:36-39`).

See [14 Auth & RBAC](14-authentication-rbac.md).

---

## 5. Rate limits

| Route family                                | Limit           | Justification                                    |
| ------------------------------------------- | --------------- | ------------------------------------------------ |
| `POST /contact`                             | 5 / min per IP  | Anti-spam                                        |
| `POST /login`, `/register`, `/forgot-password`, `PUT /reset-password`, `POST /verify-otp` | 5 / min per IP | Brute-force protection                       |
| `POST /send-otp`                            | 3 / min per IP  | OTP-bomb protection                              |
| `POST /add-to-cart`, `/quick-add`, `/remove-cart-item`, `/wishlist/toggle` | 60 / min per IP | Normal-user friendly cap        |
| `POST /apply-coupon`                        | 20 / min per IP | Coupon brute-force protection                    |
| `POST /order`                               | 10 / min per IP | Order-flood protection                           |
| `POST /razorpay/callback`                   | 20 / min per IP | Retry-friendly                                   |
| `POST /reviews`                             | 10 / 60 sec     | Anti-review-spam                                 |
| `POST /webhooks/*`                          | 120 / min per IP| Absorb provider retry storms                     |
| `POST /track` etc.                          | 10 / min        | Cheap-enumeration cap                            |
| `POST /admin/*` auth                        | 5 / min         | Same as customer auth                            |

---

## 6. Error responses

- **HTML routes** — Redirect back with `withErrors()` or flash messages; forms display them via Blade/Vue error partials.
- **JSON routes** (`/apply-coupon`, `/wishlist/toggle`, `/wishlist/ids`, `/track/{orderNo}/live`, `/cart`, `/reviews` GET) — Return `{ success: false, message: "..." }` on failure with HTTP 200/400/422 as appropriate.
- **Webhooks** — 200 on accepted/duplicate; 400 on signature or JSON error.
- **Inertia** — 302 with Inertia headers, or a Vue error page component (`Pages/Auth/*` or a generic error component).

---

## 7. Idempotency notes

- **Order placement** — Currently guarded by a 5-second session lock (mitigation, not a bullet). ⚠️ Recommendation: use a DB unique index on `(customer_id, idempotency_key)`. See [26 Known Limitations](26-known-limitations.md).
- **Payment webhook / callback race** — Deduped by `payment.meta.webhook_events` (top-level webhook ID) and `payment.meta.processed_refund_ids` (refund ID). Payment row is locked with `lockForUpdate()` before flipping status.
- **Order-placed notification** — Sent exactly once via atomic update on `payment.meta.order_placed_notified_at`.
- **Shipment notification** — Sent exactly once via `shipment.meta.ship_notified_at`.

---

## 8. Pagination

Admin list endpoints paginate server-side and return via Inertia props (typical page size 15, configurable per controller). Storefront `/shop` paginates via Laravel paginator (default 12) and passes to Blade.

---

## 9. Versioning

No versioning: all routes are v0 by convention. Breaking changes should introduce parallel routes rather than modify existing paths, since Blade templates and Vue admin pages call routes by name (Ziggy) and would need coordinated updates.
