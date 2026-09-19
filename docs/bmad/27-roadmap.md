# 27 — Roadmap

## Recently shipped
- **2026-07-17** — [Reports Module](29-reports-module.md) — Sales, Orders, Payments, Shipping, and P&L reports with KPIs, charts, filters, sortable/paginated tables, and CSV/Excel/PDF/Print exports. Includes an Operating Expenses CRUD and a custom `SelectDropdown` replacing native `<select>` throughout the module.
- **2026-07-17** — Added cost-price columns on products + variants, gateway_fee on payments, tax_amount + other_expense on orders, and the `expenses` table.



Prioritised, phase-based roadmap. Priorities are ordered by (a) production risk, (b) business value, (c) effort. Timeframes are rough — treat as sequencing, not deadlines.

Referenced gap IDs (`G-N`) resolve to entries in [26 Known Limitations](26-known-limitations.md).

---

## Phase 4 — Production hardening (next 4 weeks)

Blockers to running the app confidently at scale.

| # | Item                                                                            | Effort | Ref     |
| - | ------------------------------------------------------------------------------- | ------ | ------- |
| 1 | Adopt Sentry (or Bugsnag / Rollbar) for error tracking                          | S      | —       |
| 2 | Automate DB + uploads backups (Spatie/laravel-backup or custom cron)            | S      | —       |
| 3 | Configure log rotation (`LOG_STACK=daily` or logrotate)                          | XS     | G-32    |
| 4 | Add `.github/workflows/ci.yml` (Pint + PHPUnit + `composer audit`)              | S      | G-9     |
| 5 | Restrict `/admin/register` (invite-only or seed-only)                            | S      | G-14    |
| 6 | Tighten `TrustProxies::$proxies` to explicit IPs                                | XS     | G-15    |
| 7 | Add `Content-Security-Policy-Report-Only` header                                | M      | G-22    |
| 8 | Hash OTPs at rest (`password_resets.otp_hash`)                                   | M      | G-10    |
| 9 | Verify + fix `ProfileController::updatePassword` current-password check         | S      | G-18    |
| 10 | Add `OrderPlacedAdmin` notification to `settings.email`                        | S      | G-20    |
| 11 | Add feature tests around payment callback/webhook + order commit               | L      | G-1     |
| 12 | Add SECURITY.md + LICENSE                                                       | XS     | G-29 / G-30 |

**Exit criteria:** production goes live with monitoring, backups, CI, and no HIGH-severity known limitations open.

---

## Phase 5 — UX & business polish (weeks 5–10)

Features customers and ops will feel.

| # | Item                                                                            | Effort | Ref     |
| - | ------------------------------------------------------------------------------- | ------ | ------- |
| 1 | Admin review moderation UI                                                       | M      | G-4     |
| 2 | Admin user management (invite, suspend, role hint)                              | M      | G-6     |
| 3 | Auto-refund on `Return::markRefunded` for Razorpay orders                        | S      | G-13    |
| 4 | Return confirmation + refund-issued customer notifications                       | S      | —       |
| 5 | "Cart abandonment" reminder email (24 h after add-to-cart with no order)         | M      | —       |
| 6 | Low-stock alert email to admin (threshold cross event)                           | S      | —       |
| 7 | Multiple-shipment support for split orders                                       | L      | —       |
| 8 | Manual inventory adjustment log (with reason)                                    | M      | —       |
| 9 | Bulk CSV import for products                                                     | M      | —       |
| 10 | Order dashboard filter presets (today, unshipped, refunded, held)              | S      | —       |
| 11 | Command palette actions (jump to order by number, jump to customer by email)  | S      | —       |
| 12 | Dark-mode audit of storefront (currently admin-only)                             | M      | —       |

**Exit criteria:** ops team + support team can handle daily operations without SQL access.

---

## Phase 6 — Growth & scale (weeks 11–20)

Features that unlock new markets and volume.

| # | Item                                                                            | Effort | Ref     |
| - | ------------------------------------------------------------------------------- | ------ | ------- |
| 1 | Stripe live integration (international cards)                                    | L      | G-2     |
| 2 | PayPal live integration                                                          | L      | G-2     |
| 3 | Multi-currency support (INR + USD to start)                                     | XL     | —       |
| 4 | Tax engine (GST slabs, HSN mapping, tax-inclusive vs -exclusive pricing)         | XL     | G-8     |
| 5 | Delhivery adapter production-hardening                                           | M      | G-3     |
| 6 | BlueDart / DTDC / Xpressbees / Shadowfax adapters                               | L each | G-3     |
| 7 | International shipping                                                            | XL     | —       |
| 8 | Real-time carrier rates at checkout                                              | M      | —       |
| 9 | First-class brand entity + brand landing pages + brand filter                    | M      | G-5     |
| 10 | Meilisearch / Algolia full-text search on shop page                             | M      | —       |
| 11 | Multi-language (start with Hindi in India, then more)                            | L      | —       |
| 12 | Admin analytics — cohort, retention, LTV, funnel                                | XL     | —       |

**Exit criteria:** app usable outside India; multi-language ready; supports > 100k orders/month.

---

## Phase 7 — Platform & DX (weeks 21+)

Investments in engineering leverage.

| # | Item                                                                            | Effort |
| - | ------------------------------------------------------------------------------- | ------ |
| 1 | Adopt Laravel Fortify (retire hand-rolled auth flows)                            | L      |
| 2 | 2FA for admin + optional 2FA for customer                                        | M      |
| 3 | Migrate to Redis for session / cache / queue; adopt Horizon                      | M      |
| 4 | Docker + docker-compose committed to repo                                        | S      |
| 5 | Laravel Sanctum for a future mobile app / third-party API                        | M      |
| 6 | Spatie/laravel-permission for role granularity                                    | M      |
| 7 | Modular codebase (`app/Modules/*`) if the code grows beyond ~200 classes         | XL     |
| 8 | Drop legacy attribute tables (once all reads migrate to `option_types` JSON)     | M      |
| 9 | Fix `orders.coupan_code` typo (with backfill)                                    | S      |
| 10 | Fix `client.reset-pasword` route name typo                                       | XS     |
| 11 | Playwright / Cypress E2E smoke suite                                             | L      |
| 12 | Static analysis (PHPStan/Larastan level 6+, Psalm as second opinion)             | M      |

---

## Nice-to-haves (unscheduled)

- Progressive Web App / installable manifest.
- Native mobile app.
- Live chat widget (Tawk.to, Crisp, custom).
- Recommendations engine (related products, "customers also bought").
- Loyalty programme (points, tiers).
- Gift cards.
- Affiliate / referral programme.
- Subscription orders (weekly / monthly).
- B2B pricing (customer group-based price lists).
- Marketplace mode (multi-vendor).

---

## Change management

- Every roadmap item that ships must:
  1. Update [25 Feature Matrix](25-feature-matrix.md) status.
  2. Remove or close the referenced entry in [26 Known Limitations](26-known-limitations.md).
  3. Add a test to the growing suite (once tests exist).
  4. Add a manual QA note to [21 QA Report § 3](21-qa-report.md) if applicable.
  5. Bump the "Last updated" line in [README](README.md).

---

## Effort scale reference

| Symbol | Rough size                | Example                                                           |
| ------ | ------------------------- | ----------------------------------------------------------------- |
| XS     | < 1 dev-day               | A config change, a route rename                                    |
| S      | 1–3 dev-days              | A single new notification with wiring                              |
| M      | 1 dev-week                | A new admin CRUD page                                              |
| L      | 2–4 dev-weeks             | A new payment gateway integration end-to-end                        |
| XL     | > 1 month                 | Multi-currency, tax engine, modular restructure                     |
