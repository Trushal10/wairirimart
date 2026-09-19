# 15 — Security Architecture

## 1. Threat model (top risks)

| Risk                                       | Mitigation                                                      | Status  |
| ------------------------------------------ | --------------------------------------------------------------- | ------- |
| Payment amount tampering                   | Server-side verification against Razorpay API                   | Live    |
| Callback / webhook race                    | `lockForUpdate()` on payment row + dedup on webhook event ID    | Live    |
| Signature spoofing on webhooks             | HMAC-SHA256 with per-provider secret                            | Live    |
| CSRF on state-changing requests            | Laravel VerifyCsrfToken; webhooks whitelisted (signature verified) | Live |
| SQL injection                              | Eloquent + query builder parameter binding throughout           | Live    |
| XSS in Blade / Vue                         | `{{ }}` escaping by default; only `{!! !!}` where trusted       | Live    |
| Session hijacking                          | HttpOnly + Secure + SameSite=lax cookies; SESSION_ENCRYPT       | Live    |
| Password brute force                       | 5/min throttle on all auth endpoints                            | Live    |
| OTP-bomb (attacker triggers OTP mail flood) | Password verified before OTP re-issue; send-otp throttle 3/min | Live    |
| User enumeration on login/forgot           | Generic responses (`Invalid credentials.`)                      | Live    |
| Timing-based OTP guess                     | `hash_equals` constant-time compare                             | Live    |
| Content sniffing / clickjacking            | X-Content-Type-Options + X-Frame-Options                        | Live    |
| Cross-domain data leak                     | Referrer-Policy: strict-origin-when-cross-origin                | Live    |
| Malicious file upload                      | Type + size validation via FormRequest / CommonHelper           | Live    |
| Encrypted-at-rest credentials              | Laravel Encrypter on `payment_gateways.credentials` and `delivery_partners.credentials` | Live |
| Public admin registration                  | Rate-limited but not gated — anyone can create an admin         | ⚠️ Open |
| Google OAuth email takeover                | (Currently `first()` by email) — no forced link + verify         | ⚠️ Open |
| Plaintext OTP in DB                        | Recommended: hash + `hash_equals`                                | ⚠️ Open |
| COD inventory drain                        | Reserve-not-decrement pattern recommended                        | ⚠️ Open |

See [21 QA Report](21-qa-report.md) for the full living list of security findings and their remediation status.

---

## 2. SecurityHeaders middleware

Registered in `bootstrap/app.php:24-25` on both `web` and `api` groups.

File: `app/Http/Middleware/SecurityHeaders.php`.

Sets:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` (HTTPS only)

Not set (opt-in as needed):
- `Content-Security-Policy` — Not configured. Recommend enabling in `report-only` first, then enforce.

---

## 3. Rate limiting

Throttles are applied at the route level. See [06 API Documentation § 5](06-api-documentation.md) for the full table.

Key rules:

| Endpoint                                        | Limit           |
| ----------------------------------------------- | --------------- |
| Admin auth (login, register, forgot, reset)     | 5 / min         |
| Customer auth                                   | 5 / min         |
| Customer send-otp                               | 3 / min         |
| Contact form                                    | 5 / min         |
| Order placement                                 | 10 / min        |
| Razorpay callback                               | 20 / min        |
| Cart / wishlist mutations                       | 60 / min        |
| Webhooks                                        | 120 / min       |
| Review submission                               | 10 / 60 sec     |

`AppServiceProvider` also defines a default API rate limiter (60/min per user or IP).

---

## 4. CSRF protection

- Enabled by default on `web` middleware group.
- Exempt paths (`bootstrap/app.php:36-39`):
  - `webhooks/razorpay`
  - `webhooks/shiprocket`
- All Blade forms use `@csrf`.
- All Inertia requests carry `X-XSRF-TOKEN` automatically.
- **Rule:** any new webhook route must both (a) be added to the CSRF whitelist and (b) verify HMAC signature inside the controller. Never accept unauthenticated webhooks.

---

## 5. Authentication defences

- Passwords hashed with bcrypt (rounds=12).
- Session regenerated on login/logout.
- Session ID rotated after login (Laravel default).
- Auth cookies: `HttpOnly`, `Secure` (prod), `SameSite=lax`.
- Failed login response is generic (`Invalid credentials.`) — no timing side-channel yet (add constant-time password compare if desired; bcrypt already dominates timing).

See [14 Auth & RBAC](14-authentication-rbac.md).

---

## 6. Payment security

Detailed in [10 Payment Architecture](10-payment-architecture.md). Highlights:

- Razorpay callback verifies signature + fetches payment server-side to assert amount/currency/order_id.
- Payment row `lockForUpdate()` before status flip → serialises callback vs webhook.
- Refunds: cap at `payment.amount`, dedup by refund_id.
- `payment.failed` webhook cannot regress an already-`paid` payment.
- Notification is exactly-once via atomic `payment.meta.order_placed_notified_at`.

---

## 7. Webhook security

### 7.1 Razorpay
- Header: `X-Razorpay-Signature`.
- Algorithm: HMAC-SHA256 over raw body with `webhook_secret`.
- Malformed JSON → 400 (previously 200 — fixed in hardening).
- Duplicate event IDs deduped via `payment.meta.webhook_events[]`.
- Duplicate refund IDs deduped via `payment.meta.processed_refund_ids[]`.

### 7.2 Shiprocket
- Header: `X-Shiprocket-Signature` (HMAC-SHA256 preferred).
- Fallback: shared-token header `X-Auth-Token` matched against `services.shiprocket.webhook_secret`.
- **Query-string tokens are rejected** — they leak to access logs.

---

## 8. Storage & secrets

- **`APP_KEY`** is the master encryption key for:
  - Session encryption (if enabled).
  - Encrypted fields (`payment_gateways.credentials`, `delivery_partners.credentials`).
  - Password reset tokens (Laravel default broker signing).
- If `APP_KEY` is rotated without re-encrypting fields, encrypted data is unreadable.
- Recommendation: back up `.env` alongside DB dumps; never commit `APP_KEY`.
- Cast `credentials` columns as encrypted arrays in the model (`protected $casts = ['credentials' => 'encrypted:array']`).

---

## 9. Input validation

- All controller entry points go through a FormRequest (`app/Http/Requests/*`).
- File uploads validated via `CommonHelper::getImageValidationRule` — mime types (JPEG, PNG, WebP) and max size (5 MB).
- Search / filter query strings — validated where used; sanitised via Eloquent bindings.
- Free-text (product description, review body, comment) is stored as-is; rendered escaped in Blade and Vue (`{{ }}` / `v-text`).

---

## 10. Output escaping

- **Blade:** all `{{ $var }}` output is HTML-escaped by default. Unescaped `{!! $var !!}` is used only for admin-controlled content (settings pages, structured data JSON-LD).
- **Vue:** `{{ }}` in templates is escaped; `v-html` is used sparingly and only on trusted content.
- **JSON:** all JSON responses use `json_encode` with `JSON_UNESCAPED_UNICODE` — no HTML string concatenation.

---

## 11. File upload security

- Uploads stored in `public/uploads/` (subdirs per resource).
- `CommonHelper::uploadFile` sanitises filenames and enforces the validation rule you pass in.
- Uploaded files are not executed by the web server — Nginx / Apache should have no PHP handler for `public/uploads/`.
- Recommendation: run a mime sniffer on uploads (`finfo`) in addition to trusting the client-declared extension.

---

## 12. TLS / HTTPS

- App is designed to be deployed behind HTTPS (LetsEncrypt or Cloudflare).
- HSTS enforced by SecurityHeaders middleware in production.
- `SESSION_SECURE_COOKIE=true` in production.
- TrustProxies middleware (`app/Http/Middleware/TrustProxies.php`) — currently `protected $proxies = '*'`. ⚠️ This is spoof-friendly if the app is deployed **without** a fronting proxy. If deploying direct-to-PHP-FPM (no reverse proxy), set to explicit IP ranges.

---

## 13. Logging & monitoring

- Log driver: `stack` (`.env` default) — single file at `storage/logs/laravel.log`.
- `LOG_LEVEL=warning` in production defaults; errors and above.
- Personal data policy: PII must **not** be included in log messages. If logging a request payload, redact `password`, `otp`, `credentials`.
- Recommendation: send `stack` to include a Slack channel for `error` level.

---

## 14. Data protection

- Passwords never leave the DB in cleartext.
- OTPs are transient (60-min TTL in `password_resets`) — but currently plaintext (⚠️ G-10).
- Payment/courier credentials encrypted at rest with `APP_KEY`.
- Customer PII (email, phone, address) is stored plaintext — needed for order fulfilment.
- Access to `customers` table should be restricted to the app user; DB `SELECT` privileges from BI tools should be logged.

---

## 15. Backup & recovery security

- Back up MySQL nightly.
- Back up `.env` (contains `APP_KEY`) **separately** and to an encrypted vault.
- Rotate DB credentials on personnel changes.
- Test restore quarterly.

See [28 Maintenance Guide](28-maintenance-guide.md).

---

## 16. Known open issues (see also [21 QA Report](21-qa-report.md))

- **G-7** 2FA missing for admins.
- **G-10** OTP plaintext.
- **G-11** COD inventory drain (reserve-not-decrement).
- **G-12** Google OAuth email match risks takeover.
- **Open admin registration** — anyone can register at `/admin/register`. Recommend either removing the route in production, gating by invite token, or hard-coding `is_active=0` on new admins pending approval.
- **`TrustProxies` = `'*'`** — dangerous if not behind a reverse proxy.
- **CSP** — not set; add `Content-Security-Policy` for defence-in-depth against XSS.

---

## 17. Security disclosure

There is **no `SECURITY.md`** file at the repo root and no security-report inbox is configured. Recommendation: add a `SECURITY.md` with:

- Preferred contact (email or `security.txt`).
- Response SLA.
- Coordinated disclosure policy.
- Scope (what's in / out).

---

## 18. Security testing

- **Automated:** none (see [20 Testing Strategy](20-testing-strategy.md)).
- **Manual:** every controller review; every gateway configuration change; every webhook payload.
- **External:** none commissioned; a pentest before public launch is strongly recommended.

Suggested first-pass automated tools:
- `composer audit` — dependency CVEs.
- `npm audit --production` — JS deps.
- `security-checker` (Symfony) — deprecated but still useful.
- `retire.js` on the built assets.
