# 01 — Executive Summary

## What is Vue-Ecommerce?

Vue-Ecommerce is a full-stack B2C online store built for the Indian market. It ships as a single Laravel 12 application that serves two experiences from the same codebase:

- A **customer storefront** rendered with Blade + Bootstrap 5 (SEO-friendly, server-rendered HTML).
- An **administrative back-office** rendered with Inertia.js + Vue 3 (SPA-like admin panel).

The platform covers the entire eCommerce lifecycle: catalog management, cart, checkout, online + cash-on-delivery payments, shipping through third-party couriers, returns/refunds, customer notifications, reviews, coupons, wishlists, and reporting.

## Current release status

**Version:** 1.0 (post-Phase 3 hardening — see [27 Roadmap](27-roadmap.md))
**Environment:** Production-ready code, still requires ops onboarding (Redis, queue worker, HTTPS, secrets).
**Test coverage:** Skeleton only (`tests/Feature`, `tests/Unit` folders present but empty — see [20 Testing Strategy](20-testing-strategy.md)).

## Technology stack

| Layer          | Choice                                                            |
| -------------- | ----------------------------------------------------------------- |
| Language       | PHP 8.2+                                                          |
| Framework      | Laravel 12                                                        |
| Admin UI       | Inertia.js 2.0 + Vue 3.2 + Tailwind CSS 4                         |
| Storefront UI  | Blade + Bootstrap 5.2 + Swiper + PhotoSwipe                       |
| Build tool     | Vite 6                                                            |
| Database       | MySQL 8 (default; SQLite/PostgreSQL supported by config)          |
| Queue          | Database driver (Redis-ready)                                     |
| Cache          | Database driver (Redis-ready)                                     |
| Session        | Database driver                                                   |
| Mail           | SMTP / SES / Postmark / Mailgun / Log (configurable)              |
| Payments       | Razorpay (live), Stripe (stub), PayPal (stub), COD (live)         |
| Shipping       | Shiprocket (live), Delhivery (adapter), 4 more (stubs)            |
| SMS            | Log / MSG91 / Fast2SMS / Twilio (pluggable driver)                |
| Social auth    | Google OAuth via Laravel Socialite                                |

## What's implemented (headline features)

- **Catalog** — Products with variants (attribute matrix), categories with subcategories, gallery, SEO fields, brand, SKU.
- **Cart** — Session-based, supports variants, cross-device unaware.
- **Checkout** — Multi-step (sign-in → cart → address → payment), agree-to-TOS gate, coupon input.
- **Coupons** — Fixed / percent, min-order + max-discount caps, usage limit, per-customer scoping, date window.
- **Payments** — Razorpay callback + webhook (signature + amount/currency verified, race-condition safe), COD, refund path.
- **Shipping** — Shiprocket AWB assignment, label, pickup, tracking sync, webhook. Delhivery adapter present.
- **Orders** — Server-computed totals, atomic stock decrement with `lockForUpdate()`, status machine, invoices.
- **Returns & Refunds** — Requested → Approved → Received → Refunded state machine with strict transitions and stock restoration.
- **Notifications** — OrderPlaced, OrderShipped, OrderStatusChanged, ContactSubmitted (mail, queued).
- **SMS** — Wired for order placement and shipment; driver defaults to `log` (dev-safe).
- **Wishlists** — Per-customer, variant-aware, unique index.
- **Reviews** — Per-product, moderation flag, admin visible.
- **Customer accounts** — Register, login (email or phone + password), OTP verification, Google OAuth, password reset with OTP, multiple addresses.
- **Admin panel** — CRUD for catalog, orders, returns, payments, couriers, coupons, sliders, settings, contact inquiries; command palette (⌘K); CSV exports.
- **Public tracking** — `/track/{orderNo}` with email verification, live polling endpoint.
- **Audit log** — `admin_activity_logs` with IP, user agent, before/after JSON diff.
- **Security** — SecurityHeaders middleware (HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy), rate limiting on hot endpoints, CSRF, encrypted gateway/courier credentials.

## What's **not** implemented (do not assume)

- **Stripe & PayPal payments** — Only skeletons; not wired to a live account or tested end-to-end.
- **Delhivery, Blue Dart, DTDC, Xpressbees, Shadowfax couriers** — Only the Delhivery adapter is meaningfully implemented; the others are stubs.
- **Advanced analytics** — Dashboard shows KPIs and status donut, but no cohort/retention/LTV reporting.
- **Multi-currency / multi-language** — English + INR only. Currency symbol is hardcoded to ₹ in Blade views.
- **Tax engine** — No tax calculation is performed at checkout. Prices are treated as tax-inclusive by convention.
- **Tests** — No feature or unit tests written. `tests/Feature/` and `tests/Unit/` folders exist but contain no test classes.
- **CI/CD** — No pipeline (GitHub Actions, GitLab CI, etc.) is committed.
- **Docker** — Laravel Sail is in `composer.json` (dev dep) but no `docker-compose.yml` is committed.
- **Brand model** — Product has a free-text `brand` column; there is no dedicated brands table / admin CRUD.
- **Admin user management UI** — No page to invite / suspend / role-manage admins; admins are seeded or self-register via `/admin/register` (guarded only by rate limit).
- **Two-factor authentication** — Not implemented.
- **Refund via return** — `Admin\ReturnController::markRefunded` records a refund but does not automatically trigger a Razorpay refund; an admin has to separately hit the refund action on the order.

Refer to [26 Known Limitations](26-known-limitations.md) for the full list.

## Key business metrics (implementation-visible)

- Order state machine has 4 primary states: `pending`, `confirmed`, `delivered`, `canceled`.
- Payment state machine has 3 primary states: `pending`, `paid`, `failed`.
- Return state machine has 6 primary states: `requested`, `approved`, `rejected`, `received`, `refunded`, `cancelled`.
- Low stock threshold defaults to 5 (`services.inventory.low_stock_threshold`).
- Returns window defaults to 14 days (`services.returns.window_days`).
- Session lifetime is 120 minutes; database-backed.
- Password reset OTP is 60-minute TTL (Laravel default) — plaintext in DB (⚠️ known limitation).

## Deployment shape

Single Laravel application deployable to any PHP 8.2+ host (LEMP, LAMP, Docker, Forge, Vapor):

- **Web tier** — Nginx/Apache → PHP-FPM 8.2+ → Laravel.
- **Queue worker** — `php artisan queue:work` (systemd/supervisor) for notifications, shipment sync.
- **Scheduler** — `* * * * * php artisan schedule:run` — required for the tracking sync cron.
- **Database** — MySQL 8 (or MariaDB 10.6+).
- **Static assets** — Served from `public/` via web server; Vite manifest in `public/build/`.

Full details in [17 Deployment Guide](17-deployment-guide.md).

## Handover checklist

If you are taking over this project, do these in order:

1. Read this document top-to-bottom (10 min).
2. Read [26 Known Limitations](26-known-limitations.md) and [21 QA Report](21-qa-report.md) (20 min).
3. Read [04 System Architecture](04-system-architecture.md) (30 min).
4. Get the app running locally per [17 Deployment Guide § Local dev](17-deployment-guide.md) (1 hour).
5. Trace a full customer purchase in your local instance while reading [09 Customer Workflow](09-customer-workflow.md) (30 min).
6. Trace an admin order-management flow while reading [08 Admin Workflow](08-admin-workflow.md) (30 min).
7. Skim [06 API Documentation](06-api-documentation.md) for the full endpoint surface.
8. Review [27 Roadmap](27-roadmap.md) with the product owner and prioritise.
