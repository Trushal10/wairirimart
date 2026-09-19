# 14 — Authentication & Authorization (RBAC)

## 1. Two independent guards

Vue-Ecommerce uses **two separate Laravel auth guards** with **two separate models and tables**:

| Guard      | Model                | Table       | Provider name | Used for                   |
| ---------- | -------------------- | ----------- | ------------- | -------------------------- |
| `web`      | `App\Models\User`    | `users`     | `users`       | Admin panel (`/admin/*`)   |
| `customer` | `App\Models\Customer`| `customers` | `customers`   | Storefront                 |

Config: `config/auth.php`.

Because they are separate guards on separate tables, a customer cannot log in as an admin (and vice-versa). Sessions can coexist — an admin can be signed in as a customer at the same time in the same browser (separate cookies).

## 2. Admin authentication

- **Login:** `/admin/login` — email + password.
- **Handler:** `Auth\AuthenticatedSessionController@store`.
- **Rate limit:** 5 per minute per IP.
- **Post-login:** redirects to `AppServiceProvider::HOME` (typically `/admin/dashboard`).
- **AdminOnly middleware** (`app/Http/Middleware/AdminOnly.php`) — after `auth`, asserts `Auth::user()->role === 'admin'`; otherwise 403.
- **Registration:** `/admin/register` — open registration (rate-limited); creates `users.role = 'admin'` by default (⚠️ see [26 Known Limitations](26-known-limitations.md) — this should be locked down or gated by invite).

## 3. Customer authentication

- **Login:** `/login` — identifier (email or phone) + password.
- **Handler:** `Client\LoginUserController@loginUser`.
- **Rate limit:** 5 per minute per IP.
- **Behaviour:**
  1. Password verified **first** (blocks OTP-bomb).
  2. If password wrong → generic "Invalid credentials." (blocks enumeration).
  3. If `email_verified_at` is NULL, issue OTP → redirect to `/verification`.
  4. Otherwise, `Auth::guard('customer')->login($customer)` + redirect to intended URL.
- **ClientAuthMiddleware** (`app/Http/Middleware/ClientAuthMiddleware.php`) — guards `/profile`, `/order`, `/returns/*`, `/wishlist`, `/razorpay/callback`, address routes. Redirects to `/login` if unauthenticated.

## 4. Registration

### Admin
- `/admin/register` — Fresh Laravel scaffold, creates a `users` row with `role='admin'`.
- **Rate limit:** 5/min per IP.
- No email verification is enforced (Laravel's `VerifyEmail` machinery is present but not required by any middleware).

### Customer
- `/register` — Requires terms acceptance (`agree_tos` server-enforced).
- OTP is issued at registration; email must be verified before login is unlocked.

## 5. Password reset

### Admin
- Uses Laravel's default password broker (`password.email`, `password.reset` routes).
- Emailed link with signed token.
- Token TTL: 60 minutes (Laravel default).

### Customer
- Email + OTP flow via `Client\ForgotPasswordController`.
- OTP compared with `hash_equals` (constant-time).
- No enumeration — response is always generic ("If an account exists, you'll receive an email").
- ⚠️ **Known limitation:** OTP is stored plaintext in `password_resets.otp` — see [26 Known Limitations G-10](26-known-limitations.md).

## 6. Google OAuth (customer only)

- Route: `/auth/google/redirect` → `/auth/google/callback`.
- Handler: `Client\SocialAuthController` (uses Laravel Socialite).
- Config: `services.google.client_id`, `client_secret`, `redirect`.
- Behaviour: find customer by `google_id` OR `email`; if match, log in; if not, create.
- ⚠️ **Known limitation:** matching by `email` risks account takeover (attacker with same email may bind their Google) — see [26 Known Limitations G-12](26-known-limitations.md).

## 7. Session configuration

Config: `config/session.php` (values overridden by `.env`).

| Key                      | Value (prod)         |
| ------------------------ | -------------------- |
| driver                   | `database`           |
| lifetime                 | 120 minutes          |
| encrypt                  | `true` (via `SESSION_ENCRYPT=true`) |
| secure_cookie            | `true` over HTTPS    |
| same_site                | `lax`                |
| http_only                | `true`               |

Sessions are regenerated on login/logout; expired sessions are pruned by Laravel's `session:gc` (auto-triggered on request based on lottery odds).

## 8. Authorization model — RBAC

**Role model** is minimal:
- `users.role` is an enum (`admin` | `user`).
- Only `admin` role is used in practice (the "user" value exists but is not admitted anywhere in the app).
- **No sub-roles** (e.g. `manager`, `ops`, `support`).
- **No per-resource permissions** (all admins can do anything an admin can do).
- **No feature flags per role.**

If your business needs role granularity (e.g. an "ops-only" user who can assign AWBs but not delete products), you must add either:
- A permission column / table (Spatie/laravel-permission is the go-to).
- Custom Laravel policies (`AuthServiceProvider::registerPolicies`).

## 9. Route-level authorization matrix

| Route family              | Guard needed  | Extra middleware                     | Notes                                             |
| ------------------------- | ------------- | ------------------------------------ | ------------------------------------------------- |
| `/admin/login`, `/admin/register`, `/admin/forgot-password`, `/admin/reset-password/*` | none | `guest`, `throttle:5,1` | Standard Fortify-style pages |
| `/admin/*` (everything else) | `web`       | `auth`, `admin` (AdminOnly)         | 403 if not admin                                  |
| `/webhooks/*`             | none          | `throttle:120,1`                     | Signature-verified inside controller              |
| `/register`, `/login`, `/forgot-password`, `/reset-password`, OTP routes | none | `throttle:5,1` (or 3,1 for send-otp) | Public                                           |
| `/checkout`               | none          | none                                 | Redirects to `/login` if not authed               |
| `/order`, `/razorpay/callback`, `/wishlist/*`, `/profile*`, `/user-address-*`, `/returns/{orderNo}*`, `/invoice/*` | `customer` | `client_auth`, throttling as per endpoint | Ownership checks inside controller |
| `/track/{orderNo}*`       | none          | Session grant required for `/show`   | Grant issued by `/track` verify step              |
| Public storefront (`/`, `/shop`, `/product-detail/*`, `/contact`, `/categories`, `/about`) | none | none | HTML, no auth |

## 10. CSRF

- All non-GET routes require a CSRF token.
- Blade forms include `@csrf`.
- Inertia auto-injects `X-XSRF-TOKEN` header.
- **Whitelist** (in `bootstrap/app.php`):
  - `webhooks/razorpay`
  - `webhooks/shiprocket`
- **Never** add more paths to the CSRF whitelist without HMAC verification.

## 11. Password hashing

- Algorithm: `bcrypt` with `BCRYPT_ROUNDS=12` (`.env`).
- Passwords are hashed at write time by Laravel's `Hash::make` inside `Customer::save` / `User::save` (via `Hashable` trait or explicit call in controllers).

## 12. Session hijacking mitigation

- `HttpOnly` cookie (JS cannot read).
- `Secure` cookie in production (HTTPS-only).
- `SameSite=lax` (no cross-site cookie in state-changing requests).
- Session ID regenerated on login/logout.
- Session encryption enabled (`SESSION_ENCRYPT=true`).

## 13. Account lockout

Not implemented. Rate limiting (5/min per IP) is the only brute-force defence. If needed, add a lockout after N failed attempts — see [27 Roadmap](27-roadmap.md).

## 14. Multi-factor authentication (2FA)

**Not implemented** for admin or customer. Considered a Roadmap item.

## 15. Session storage upgrade path

If moving from `database` to `redis` session driver:
1. Set `SESSION_DRIVER=redis` and configure Redis env.
2. Deploy — all users sign out (sessions in DB are ignored).
3. No code change.

## 16. Impersonation

Not implemented. Support staff cannot impersonate a customer for troubleshooting.

## 17. Audit trail

Admin actions are logged to `admin_activity_logs` via `AuditLogger::log(action, subject, changes)`. Customer authentication events (login / logout / failed login) are **not** currently persisted — only present in `laravel.log`.
