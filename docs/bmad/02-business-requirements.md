# 02 — Business Requirements Document (BRD)

This document captures the business context, stakeholders, and functional/non-functional requirements that the current implementation of Vue-Ecommerce satisfies. It is derived by reverse-engineering the codebase — it is descriptive, not aspirational. Anything that is planned but not built is called out under **§ 6 Gaps**.

---

## 1. Business context

### 1.1 Problem statement
Small and mid-sized brands in India need a self-hosted eCommerce platform that:
- Supports **Cash on Delivery (COD)** and **Razorpay** (the two dominant payment methods in India).
- Integrates with **Shiprocket** (India's largest multi-carrier logistics aggregator) so a single AWB assignment can dispatch through any partner courier.
- Handles **product variants** (size + colour + …) with per-variant stock.
- Supports **returns and refunds** as a first-class workflow, not an afterthought.
- Is deployable to a low-cost LAMP/LEMP host (shared hosting or a VPS) — no Kubernetes, no Redis mandatory, no external state.

Vue-Ecommerce is built to meet this niche without the SaaS lock-in of Shopify/BigCommerce or the complexity of Magento.

### 1.2 Target market
- Indian D2C brands in fashion, home goods, gifting, and lifestyle.
- Order volumes up to ~10,000 orders/month per instance (single MySQL / single PHP-FPM node).
- INR-only pricing; Indian tax logic (see § 6 gap: not implemented).

### 1.3 Business goals (achieved by v1)
- **G1** — Sell physical goods online with online + COD payment.
- **G2** — Fulfil orders through one primary courier (Shiprocket) with tracking visible to the customer.
- **G3** — Let a single non-technical admin manage catalog, orders, and returns via a web panel.
- **G4** — Send customers transactional email (and optionally SMS) at order placement and shipping.
- **G5** — Support promotions via coupon codes and let customers save favourites (wishlist).

---

## 2. Stakeholders

| Stakeholder            | Role                                                                                     | Primary interface                                     |
| ---------------------- | ---------------------------------------------------------------------------------------- | ----------------------------------------------------- |
| Store owner / merchant | Decides pricing, promotions, courier partnerships, brand voice.                          | Admin panel (`/admin/*`)                              |
| Ops / fulfilment staff | Assigns AWBs, prints labels, requests pickups, handles returns.                          | Admin panel — Orders & Returns sections               |
| Customer support       | Views order status, refunds, contact form inquiries.                                     | Admin panel — Orders, Contacts, Returns               |
| Customer               | Browses, purchases, tracks, returns.                                                     | Storefront (`/`, `/shop`, `/checkout`, `/track`, …)   |
| Developer / integrator | Extends the codebase (new gateway, new courier, new notification).                       | Codebase directly + this BMAD documentation           |
| DevOps / sysadmin      | Deploys, backs up, monitors, runs the queue worker & scheduler.                          | Server shell + Laravel logs                           |

There is **no distinction between "store manager" and "admin"** in the current implementation. Any authenticated user with `role='admin'` (from the `users` table) has full access to every admin page. See [14 Auth & RBAC](14-authentication-rbac.md).

---

## 3. Functional requirements (implemented)

### FR-C — Catalog
- **FR-C1** Products can be created, edited, soft-deleted, and listed via admin.
- **FR-C2** A product can belong to multiple categories (M:N via `product_categories`).
- **FR-C3** A product can have unlimited variants defined by attribute values (size, colour, …). Each variant has its own price, stock, SKU, weight, image, and a JSON `options` snapshot.
- **FR-C4** Categories are hierarchical (`parent_id` self-referencing). Featured flag controls homepage exposure.
- **FR-C5** Products have SEO fields: `meta_title`, `meta_description`, `meta_keywords`, `canonical_url`, `og_image`.
- **FR-C6** Products have media (image or video) with primary flag and priority ordering.
- **FR-C7** Products carry a free-text `brand` string. Brand as a first-class entity is **not** implemented.

### FR-CART — Cart
- **FR-CART1** Guests and signed-in customers can add products (with variants) to a cart stored in the session (`CartService`).
- **FR-CART2** Cart contents are lost on session expiry (120 min default) and are **not** persisted across devices.
- **FR-CART3** Cart supports quick-add, remove, and quantity update — all rate-limited (60 req/min).

### FR-CHK — Checkout
- **FR-CHK1** Customer must be signed in to place an order (redirected to login if not).
- **FR-CHK2** Customer selects a saved address or enters a new one (`customer_addresses`).
- **FR-CHK3** Customer chooses payment method from active gateways in `payment_gateways` table.
- **FR-CHK4** Customer must tick "agree to terms" (`agree_tos`); enforced server-side.
- **FR-CHK5** Customer can apply a coupon; discount is recomputed server-side at order commit.
- **FR-CHK6** Order totals (sub_total, shipping, discount, total) are computed on the server — client cannot tamper.

### FR-PAY — Payments
- **FR-PAY1** Razorpay Standard Checkout: order created via SDK, callback verified with HMAC-SHA256 signature.
- **FR-PAY2** Razorpay webhook (`/webhooks/razorpay`) accepts `payment.authorized`, `payment.captured`, `payment.failed`, `refund.created`, `refund.processed` events with idempotent deduplication by webhook event ID and refund ID.
- **FR-PAY3** COD path skips payment gateway; order goes straight to `pending` / `paid` at COD-collection time (implementation: `Payment.status='paid'` after successful delivery — verified in [12 Order Management](12-order-management.md)).
- **FR-PAY4** Refund is initiated by admin from the order detail page; a Razorpay refund is created and reconciled via webhook.
- **FR-PAY5** Refund total is capped at the original payment amount (over-refund is logged and truncated, not silently absorbed).
- **FR-PAY6** Client-side amount, currency, and order-ID tampering is rejected (verified against Razorpay's API server-side).

### FR-SHIP — Shipping
- **FR-SHIP1** Admin can check serviceability for an order pincode via the courier API.
- **FR-SHIP2** Admin can assign an AWB code (Shiprocket) to a shipment.
- **FR-SHIP3** Admin can request pickup and generate a shipping label PDF.
- **FR-SHIP4** Admin can cancel a shipment.
- **FR-SHIP5** Shiprocket tracking webhook (`/webhooks/shiprocket`) updates shipment status; signature verification (`X-Shiprocket-Signature`) supported.
- **FR-SHIP6** A scheduled job (`SyncActiveShipmentsJob`) polls active shipments every 15 minutes as a fallback for missed webhooks.
- **FR-SHIP7** Customer sees tracking info on `/track/{orderNo}` after email verification.

### FR-ORD — Orders
- **FR-ORD1** Order number is a unique, human-readable string (e.g. `ORD-000123`).
- **FR-ORD2** Order line items capture the variant's price, name, SKU, and attribute snapshot at purchase time.
- **FR-ORD3** Stock is decremented atomically on order commit using `lockForUpdate()` for both `products.stock` and `product_variants.stock`.
- **FR-ORD4** Order status history is logged in `order_status_histories` with source (system / admin / webhook / customer).
- **FR-ORD5** Customer can view their orders and download invoices as a print-friendly HTML page.

### FR-RET — Returns
- **FR-RET1** Customer can request a return within the returns window (default 14 days) from `/returns/{orderNo}/new`.
- **FR-RET2** Return has a strict state machine: `requested → approved|rejected → received → refunded|closed → cancelled`.
- **FR-RET3** On `markReceived`, stock is restocked on both `products.stock` and the correct `product_variants.stock`.
- **FR-RET4** `markRefunded` only allowed from `received` (not from `approved`), enforced by strict assertion.

### FR-CUST — Customer accounts
- **FR-CUST1** Customer registers with name, email, phone, password.
- **FR-CUST2** Registration issues an OTP; email must be verified before login is unlocked.
- **FR-CUST3** Login accepts email **or** phone as identifier.
- **FR-CUST4** Google OAuth sign-in (login existing account or create new).
- **FR-CUST5** Password reset via email OTP (60-min TTL).
- **FR-CUST6** Customer can manage multiple delivery addresses (home / work).
- **FR-CUST7** Customer can update profile, avatar, and password (existing-password verification — assumed but see [21 QA Report § MEDIUM](21-qa-report.md)).

### FR-CO — Coupons & wishlist
- **FR-CO1** Admin creates coupons with type (fixed / percent), value, min-order, max-discount, usage limit, per-customer scoping, start/end dates.
- **FR-CO2** Customer applies coupon at checkout; discount is recomputed at order commit; coupon usage is atomically incremented.
- **FR-CO3** Customer toggles wishlist items (per product + variant, unique).

### FR-REV — Reviews
- **FR-REV1** Customer submits star rating (1–5), title, review text, optional image.
- **FR-REV2** Reviews default `is_approved=false`; admin approves/spam-flags. **Note**: there is no admin CRUD UI for reviews as of v1 (see § 6 Gaps).

### FR-NOTIFY — Notifications
- **FR-NOTIFY1** Order placed → email to customer.
- **FR-NOTIFY2** Order shipped → email + SMS to customer (once, guarded by `meta.ship_notified_at`).
- **FR-NOTIFY3** Order status changed → email to customer.
- **FR-NOTIFY4** Contact form submitted → email to store owner.
- **FR-NOTIFY5** OTP → email (SMS driver optional).

### FR-ADM — Admin
- **FR-ADM1** Command palette (⌘K / Ctrl+K) with keyboard-driven search.
- **FR-ADM2** Dashboard with revenue KPI, order-status donut, low-stock alert card.
- **FR-ADM3** CSV export for orders and payments.
- **FR-ADM4** Payment gateway + courier partner configuration UI (with credentials encryption).
- **FR-ADM5** Audit log of admin actions (`admin_activity_logs`).

---

## 4. Non-functional requirements

### 4.1 Performance
- **NFR-PERF1** Cart/wishlist mutations rate-limited to 60/min per IP.
- **NFR-PERF2** Order placement rate-limited to 10/min per IP.
- **NFR-PERF3** Contact form rate-limited to 5/min per IP.
- **NFR-PERF4** Webhook endpoints rate-limited to 120/min per IP.
- **NFR-PERF5** Notifications are queued (`ShouldQueue`), never blocking user-facing requests.

### 4.2 Security
- **NFR-SEC1** All admin & customer passwords hashed with bcrypt (rounds=12 default).
- **NFR-SEC2** Payment gateway and courier credentials encrypted at rest (Laravel Encrypter, JSON blob).
- **NFR-SEC3** CSRF on all POST/PUT/DELETE except explicitly whitelisted webhooks.
- **NFR-SEC4** HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy headers set globally.
- **NFR-SEC5** Razorpay callback + webhook verify HMAC signatures.
- **NFR-SEC6** Shiprocket webhook accepts HMAC-SHA256 in header (query-string tokens rejected).
- **NFR-SEC7** Login errors are generic (blocks user-enumeration).
- **NFR-SEC8** Constant-time OTP comparison (`hash_equals`).

### 4.3 Reliability
- **NFR-REL1** Order-placement is transactional; stock is decremented under `lockForUpdate()`.
- **NFR-REL2** Payment callback ↔ webhook race is protected by `lockForUpdate()` on the payment row.
- **NFR-REL3** Refund and webhook events are deduped by ID.
- **NFR-REL4** Return state transitions are strictly enforced (illegal transitions throw).

### 4.4 Compliance
- **NFR-COMP1** Cookies are `SameSite=lax`, HTTP-only, `Secure` in production (config-driven).
- **NFR-COMP2** No credit-card data is stored on our servers (Razorpay-hosted checkout).
- **NFR-COMP3** No PII (email, phone, address) is emitted in error responses.

### 4.5 Observability
- **NFR-OBS1** Log driver is `stack` (single-file `laravel.log` by default; can add Slack/Papertrail).
- **NFR-OBS2** Admin actions are recorded in `admin_activity_logs` with IP, user agent, and before/after JSON diff.
- **NFR-OBS3** Failed queue jobs are persisted in `failed_jobs`.

### 4.6 Scalability envelope (single-node)
- Suitable for < ~10k orders/month on a 2 vCPU / 4 GB VPS with MySQL 8, PHP-FPM 8.2, Nginx.
- Beyond that, migrate cache/session/queue to Redis, add a second app node behind a load balancer.

---

## 5. Assumptions

- **A1** — The store is INR-only; no multi-currency support is required.
- **A2** — Prices in the DB are **tax-inclusive**; no GST calculation is performed at checkout.
- **A3** — Shipping cost is computed by the courier or set at checkout (default flat) — the DB has a `shipping` column but the calculation engine is minimal.
- **A4** — The store operates in a single Indian pincode-range serviced by Shiprocket.
- **A5** — All customers can read English; the site is not translated.
- **A6** — Admin users are trusted; there is no admin role-based access control beyond "is admin".

---

## 6. Gaps (not yet implemented)

| ID    | Gap                                                                                                       | Impact                                          | Owner |
| ----- | --------------------------------------------------------------------------------------------------------- | ----------------------------------------------- | ----- |
| G-1   | No automated test suite (empty `tests/` folders).                                                          | Regression risk on every change.                | Eng   |
| G-2   | Stripe & PayPal are stubs.                                                                                | International cards not accepted.                | Eng   |
| G-3   | Delhivery, Blue Dart, DTDC, Xpressbees, Shadowfax adapters are stubs.                                     | Only Shiprocket is production-usable.            | Eng   |
| G-4   | No admin CRUD for reviews (moderation must be done via DB).                                               | Reviews cannot be approved from UI.              | Prod  |
| G-5   | No brand entity (only free-text field).                                                                   | Cannot filter shop by brand.                     | Prod  |
| G-6   | No admin user management (no invite / suspend / role assign).                                             | Cannot revoke access without DB edit.            | Ops   |
| G-7   | No 2FA for admin login.                                                                                  | Password-only admin auth.                        | Sec   |
| G-8   | No tax engine (GST slabs, HSN mapping).                                                                  | Not GST-compliant for B2B invoicing.             | Fin   |
| G-9   | No CI/CD pipeline committed.                                                                             | Manual deploy every time.                        | Ops   |
| G-10  | OTP stored plaintext in `password_resets`.                                                               | If DB leaks, OTPs are visible.                   | Sec   |
| G-11  | COD stock is decremented at order placement (not on payment), enabling COD-drain fraud.                  | Inventory can be tied up by fake orders.         | Prod  |
| G-12  | Google OAuth first()-by-email risks account takeover; no explicit merge policy.                          | Attacker with same email may bind their Google. | Sec   |
| G-13  | Return refund does not auto-trigger Razorpay refund from `markRefunded` action.                          | Admin must run refund action separately.         | Ops   |

Full list in [26 Known Limitations](26-known-limitations.md).

---

## 7. Success criteria

The current implementation is considered to meet business requirements when:

1. A customer can complete a purchase via Razorpay in under 5 clicks from the cart page.
2. An admin can assign an AWB, generate a label, and dispatch a shipment in under 90 seconds.
3. A customer can request a return and see its status without contacting support.
4. All customer-facing writes are idempotent under a page-reload or duplicate-submit.
5. All admin-facing writes are logged in `admin_activity_logs`.
6. Zero PII appears in error messages or logs on production.

Verification of these criteria is the QA team's responsibility — see [20 Testing Strategy](20-testing-strategy.md).
