# 26 — Known Limitations

Consolidated register of every non-trivial limitation, gap, and technical debt item. Each carries a stable ID (`G-N`) so PRs and roadmap items can reference them.

Severity:
- **HIGH** — Security, correctness, or production readiness risk. Fix before or shortly after go-live.
- **MEDIUM** — Business or UX gap. Fix soon.
- **LOW** — Nice-to-have or aesthetic; safe to defer.

---

## HIGH

### G-1 — No automated test suite
- **What:** `tests/Feature` and `tests/Unit` are empty. No CI. No coverage.
- **Impact:** Every change is high-regression risk.
- **Fix:** Follow [20 Testing Strategy](20-testing-strategy.md) — start with the top-10 feature tests around payments and orders.
- **Owner:** Engineering.

### G-2 — Stripe & PayPal are stubs
- **What:** `app/Services/Payment/StripeGateway.php` and `PayPalGateway.php` exist as scaffolds; no live logic.
- **Impact:** International customers cannot pay with cards from most non-Indian issuers.
- **Fix:** Implement per [10 Payment Architecture § 5 & 6](10-payment-architecture.md).

### G-7 — No 2FA for admin login
- **What:** Admins log in with email + password only.
- **Impact:** Compromise of admin credentials = full store takeover.
- **Fix:** Enable Laravel Fortify's `two-factor-authentication` feature, or integrate an OTP-over-email/SMS step.

### G-10 — OTP stored plaintext in `password_resets.otp`
- **What:** OTP written verbatim to DB. `hash_equals` compare is applied, but at-rest storage is cleartext.
- **Impact:** DB dump gives an attacker current/recent OTPs.
- **Fix:** Migration to add `otp_hash`; write hash on issue; compare with `hash_equals(hash('sha256', $submitted), $row->otp_hash)`. Drop `otp` column after backfill.

### G-11 — COD stock decrement enables inventory drain
- **What:** COD orders decrement stock at placement, not at delivery. Bad actors can flood COD orders that they never accept, tying up inventory.
- **Impact:** Real customers see out-of-stock while inventory is committed to fake COD orders.
- **Fix:** Adopt a **reserve** semantic:
  - Add `orders.status='reserved'`.
  - Reserved stock counted separately (`orders_reserved_qty` computed).
  - Auto-cancel reserved orders after N hours without dispatch.

### G-12 — Google OAuth email-match takeover
- **What:** `SocialAuthController::callback` matches by `google_id` OR `email`. If an attacker owns a Google account with the same email as an existing password-only account, they can link + gain access.
- **Impact:** Account takeover.
- **Fix:** If a customer exists by email but has no `google_id`, do NOT log them in via Google. Instead, force a re-verify: send an email link to the primary address confirming the linkage before setting `google_id`.

### G-14 — Open admin registration
- **What:** `POST /admin/register` (rate-limited only) accepts anyone and creates an admin.
- **Impact:** Publicly-facing registration for admin panel.
- **Fix:** Either remove the route in production, gate behind an invite token, or require `is_active=false` and admin approval before login is unlocked.

### G-15 — `TrustProxies::$proxies = '*'`
- **What:** Trusts all proxy headers.
- **Impact:** If deployed without a reverse proxy, an attacker can spoof `X-Forwarded-For` / `X-Forwarded-Proto`.
- **Fix:** Set `$proxies` to the explicit IP range of your load balancer / CDN.

---

## MEDIUM

### G-3 — Delhivery + 4 other courier adapters are stubs
- **What:** Only `ShiprocketAdapter` is fully wired. `Delhivery` is partially usable; `BlueDart`, `DTDC`, `Xpressbees`, `Shadowfax` are scaffolds.
- **Impact:** Cannot dispatch through these providers without implementation work.
- **Fix:** Implement per [11 Courier Architecture § 7](11-courier-architecture.md).

### G-4 — No admin UI for review moderation
- **What:** New reviews land as `is_approved=false` but there is no admin CRUD page.
- **Impact:** Reviews cannot be approved without DB access.
- **Fix:** Add `resources/js/Pages/Admin/Review/Index.vue` + `Admin\ReviewController` with approve/spam/delete actions.

### G-5 — No brand entity
- **What:** Products have a free-text `brand` string; no dedicated table.
- **Impact:** Cannot filter shop by brand; no brand landing pages.
- **Fix:** Add `brands` table + FK on `products.brand_id`. Admin CRUD. Migration script from free-text to FK.

### G-6 — No admin user management UI
- **What:** No invite / suspend / role assignment for admins.
- **Impact:** Adding/removing admins requires DB access.
- **Fix:** Add `Admin\UserController` with list, invite (email token), toggle `is_active`, assign role (once sub-roles exist).

### G-8 — No tax engine
- **What:** No GST calculation. Prices are treated as tax-inclusive by convention.
- **Impact:** Not B2B-invoice compliant; incorrect for jurisdictions requiring itemised tax.
- **Fix:** Add `tax_rates` table, HSN → rate mapping on products, per-item + per-order tax computed at checkout, displayed on invoice.

### G-9 — No CI/CD pipeline
- **What:** No `.github/workflows/*.yml` / `.gitlab-ci.yml` / equivalent.
- **Impact:** Manual deploy every time; no automated linting or test gating (once tests exist).
- **Fix:** Adopt the template in [20 Testing Strategy § 12](20-testing-strategy.md).

### G-13 — Return-refund does not auto-trigger Razorpay refund
- **What:** `Admin\ReturnController::markRefunded` records `refunded_at` but does NOT call `RazorpayGateway::refund()`.
- **Impact:** Admin must open the order and hit the separate refund button to actually move money.
- **Fix:** Inside `markRefunded`, if payment.type === 'razorpay', call `PaymentGatewayManager::driver('razorpay')->refund($payment, $return->refund_amount)`.

### G-16 — Idempotency-Key on order placement
- **What:** Duplicate submit is guarded by a 5-second session lock. Not robust across tabs / retries.
- **Impact:** In rare races, two orders can be created for the same intent.
- **Fix:** Add `orders (customer_id, idempotency_key)` DB unique index; require client to send an `Idempotency-Key` header on `POST /order`.

### G-17 — Google OAuth silent remember-me
- **What:** `Auth::login($customer, true)` in the Google callback forces a persistent cookie.
- **Impact:** Users don't consent to "remember me" — session lifetime > their expectation.
- **Fix:** Default to `remember=false`; add an opt-in checkbox on the login page (respected on both password and OAuth paths).

### G-18 — `ProfileController::updatePassword` — current-password check
- **What:** Prior audit flagged that current-password verification may be missing; not re-verified this session.
- **Impact:** Auth: any active session can change the password without proving they know the current one.
- **Fix:** Add `current_password` field + `current_password` validation rule.

### G-19 — Public tracking session leakage
- **What:** `TrackingController` grants a session token scoped to a single `order_no` but does not re-verify email on subsequent requests.
- **Impact:** If session is shared / stolen, an attacker can view that one order's tracking.
- **Fix:** Add short TTL to the grant; require re-verify after 15 minutes; log tracking access.

### G-20 — No admin new-order notification
- **What:** No email/SMS to ops when an order lands.
- **Impact:** Ops discover new orders only by refreshing the dashboard.
- **Fix:** Add `OrderPlacedAdmin` notification bound to `settings.email`, dispatched from the same guard point as OrderPlaced.

### G-21 — Order-number generation race
- **What:** `latest('id')->first() + 1` inside `Client\OrderController::save`.
- **Impact:** Two simultaneous placements can produce the same next order_no; DB unique index catches it but the user sees a 500.
- **Fix:** Wrap in a retry loop, or use a DB sequence, or move to a UUID-derived order_no.

### G-22 — No CSP header
- **What:** No `Content-Security-Policy` set.
- **Impact:** XSS defence-in-depth weakened.
- **Fix:** Start with `Content-Security-Policy-Report-Only` in the SecurityHeaders middleware; iterate; enforce.

---

### G-34 — Reports COGS is zero for products without cost_price
- **What:** COGS calculation coalesces to 0 when neither the variant nor the parent product has `cost_price` set. Newly-added columns default to NULL on existing rows.
- **Impact:** P&L Gross Profit == Revenue − Discounts for un-priced products, over-stating profit.
- **Fix:** Backfill `products.cost_price` / `product_variants.cost_price` via the product editor or a CSV import script.

### G-35 — Payment gateway fees are estimated when actual fee = 0
- **What:** P&L falls back to `services.reports.default_gateway_fee_rate` (default 2%) when `payments.gateway_fee` is 0. The Razorpay webhook payload includes `fee` in paisa but we don't currently persist it.
- **Impact:** Fees on the P&L are an estimate, not actual money moved.
- **Fix:** Extract `payload.payment.entity.fee` in the Razorpay webhook handler and write to `payments.gateway_fee`.

### G-36 — Excel export uses HTML masquerade
- **What:** `ReportExporter::streamXlsx()` outputs Excel-openable HTML with `application/vnd.ms-excel` MIME. Excel opens it fine but it's not a native `.xlsx`.
- **Impact:** No formulas, styles, or macros in exported files. Some strict spreadsheet consumers may reject it.
- **Fix:** Add `phpoffice/phpspreadsheet` and route XLSX exports through it if native `.xlsx` is required.

### G-37 — PDF export uses browser print, not server-side rendering
- **What:** `/admin/reports/{report}/export?format=pdf` renders `admin.reports.print` Blade with auto-print JS. User's browser handles Save-as-PDF.
- **Impact:** Cannot attach PDFs to scheduled emails or generate them headlessly.
- **Fix:** Add `barryvdh/laravel-dompdf` or `spatie/browsershot` and swap `renderPrintable()` for real PDF generation.

### G-38 — Report aggregates run per-request (no caching)
- **What:** Every hit on `/admin/reports/*` re-runs the SUM/COUNT queries. Fine for < 10k orders/month.
- **Impact:** For larger stores, page loads can slow down under repeated concurrent hits.
- **Fix:** Add a nightly `Schedule::call()` that pre-aggregates daily/weekly/monthly snapshots into a `report_daily_aggregates` table, then have `ReportService` read from that for date ranges > 7 days.

## LOW / notes

### G-23 — Route name typo `client.reset-pasword`
- **What:** Route named `reset-pasword` (missing "s"). Kept for BC.
- **Fix:** Rename with an alias for old name.

### G-24 — DB column typo `orders.coupan_code`
- **What:** Should be `coupon_code`. Kept for BC.
- **Fix:** Migration to rename with backfill.

### G-25 — Login/register buttons `type="button"`
- **What:** Native form submit not used; JS-only submit path.
- **Impact:** Non-JS clients cannot submit; disable-until-JS UX.
- **Fix:** Switch to `type="submit"` and let progressive enhancement work.

### G-26 — Cart mini-template uses placeholder strings
- **What:** Fragile string templating in `main.js`.
- **Fix:** Move mini-cart to a small Vue island.

### G-27 — Sticky ATC bar `d-none` — dead code
- **What:** Hidden component leftover from an experiment.
- **Fix:** Delete.

### G-28 — `config/cors.php` not published
- **What:** Uses Laravel defaults (permissive same-origin).
- **Impact:** N/A for storefront + admin on the same domain. Change if a JSON API is exposed for a mobile app.

### G-29 — No `SECURITY.md`
- **What:** No documented security disclosure policy.
- **Fix:** Add `SECURITY.md` with contact + SLA + scope.

### G-30 — No `LICENSE` file
- **What:** Not clear under what license the project is released.
- **Fix:** Add `LICENSE` (recommend MIT for internal projects, choose per business).

### G-31 — Legacy attribute tables coexist with new option_types JSON
- **What:** `attributes / attribute_values / product_variant_values` and `products.option_types + product_variants.options` both exist.
- **Impact:** Two sources of truth for variant metadata. New writes use JSON; queries mixing both can diverge.
- **Fix:** Migrate all reads to JSON; drop the normalised tables once no code references them.

### G-32 — No log rotation configured
- **What:** `storage/logs/laravel.log` grows unbounded.
- **Fix:** Add `/etc/logrotate.d/laravel` or use `LOG_STACK=daily`.

### G-33 — Image uploads stored under `public/uploads`
- **What:** Physical uploads sit inside the web root.
- **Impact:** If the web server accidentally serves `.php` from `/uploads`, an attacker could execute uploaded files.
- **Fix:** Nginx rule already blocks `.php` under `/uploads/` (see [17 Deployment](17-deployment-guide.md)). Consider moving to `storage/app/public` + `storage:link` for cleaner boundaries.

---

## Cross-cut recommendations

- Adopt **Laravel Fortify** to replace the hand-rolled auth flows (2FA, session management, password confirmation come for free).
- Adopt **Laravel Sanctum** if you introduce a mobile client — cookie session on web, tokens on mobile.
- Adopt **Spatie/laravel-permission** if role granularity is needed.
- Adopt **Spatie/laravel-backup** for automated backups.
- Adopt **Sentry** for error tracking.
- Adopt **Laravel Horizon** if / when moving to Redis queue.

See [27 Roadmap](27-roadmap.md) for prioritisation.
