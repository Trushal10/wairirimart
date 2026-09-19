# 03 — Product Requirements Document (PRD)

Where the [BRD](02-business-requirements.md) captures the **why**, this PRD captures the **what** — the concrete features, screens, and acceptance criteria that describe the product as delivered in v1.

---

## 1. Personas

### 1.1 Meera — Shopper (primary persona)
- 22–45 y, urban India, shops on mobile 70 % of the time.
- Trusts COD, but happily uses Razorpay when the site looks legit.
- Expects tracking, easy returns, and email/SMS updates.
- Abandons cart if checkout is > 4 steps.

### 1.2 Rohan — Store owner
- Runs a small D2C brand with 200–2000 SKUs.
- Uploads new products weekly, manages 10–100 orders/day.
- Wants a WhatsApp-shareable order tracking link.
- Cannot code; drives everything through admin UI.

### 1.3 Priya — Ops / fulfilment
- Prints labels, packs orders, assigns pickups.
- Handles 3–5 returns per week; needs an at-a-glance status view.

### 1.4 Aakash — Support
- Reads contact-form submissions, refunds problem orders.
- Never touches server config.

---

## 2. Storefront features

### 2.1 Homepage — `/`
- Hero slider (admin-managed via `sliders` + `slider_media`).
- Featured categories (up to 4, driven by `categories.featured=1`).
- Featured products section.
- Footer with contact info, social links, newsletter placeholder.

**Acceptance:** Slider auto-plays; featured section renders at least 4 products or an empty state.

### 2.2 Shop — `/shop`
- Product grid, paginated (default 12 per page).
- Sidebar filters: category, price range, attributes (colour, size).
- Search by `q` query-string param.
- Empty state for no results.
- Accessible pagination controls.

**Acceptance:** Search preserves the query in the URL; empty state has a "clear filters" link.

### 2.3 Category page — `/categories`
- Lists all top-level categories with images.
- Clicking a category opens `/shop?category=<slug>`.

### 2.4 Product detail — `/product-detail/{slug}`
- Image gallery with zoom (PhotoSwipe).
- Variant picker (radio for each attribute; disabled combos are grey).
- Live price + stock update on variant change.
- Add-to-cart, add-to-wishlist buttons.
- Description, return policy, brand, SKU.
- Reviews section (approved reviews only) + submit-review form (authed only).
- Related products.

**Acceptance:** Variant switch updates price and stock badge without a full page reload (jQuery-based).

### 2.5 Cart — `/shopping-cart`
- Line items with image, name, variant options, unit price, quantity spinner, subtotal.
- Remove button per item.
- Order summary: subtotal, shipping (if computed), coupon discount, total.
- "Proceed to checkout" button.

**Acceptance:** Quantity update triggers server recompute (no client-side total).

### 2.6 Checkout — `/checkout`
- Requires sign-in; guests are redirected to `/login`.
- Address section: pick a saved address or add new (`customer_addresses`).
- Payment section: radio for each active gateway (COD, Razorpay …).
- Coupon input: `POST /apply-coupon` — validates code, shows discount.
- Terms-of-service checkbox (`agree_tos`) — server-enforced.
- Place-order button posts to `POST /order`.

**Acceptance:** Server rejects if TOS unchecked; server recomputes coupon at order time (client's cached amount is discarded).

### 2.7 Order confirmation — `/order/confirmation/{orderNo}`
- Thank-you message + order number.
- Full itemised order (items, address, payment method, totals).
- Link to invoice (`/invoice/{orderNo}`).
- Link to tracking (`/track/{orderNo}`).

**Acceptance:** Ownership-guarded (`customer_id` match); non-owner gets 403.

### 2.8 Tracking — `/track` and `/track/{orderNo}`
- Search by order number.
- If no session grant, requires email verification (last-4 of email or full email — see [15 Security](15-security-architecture.md)).
- Shows status timeline + latest carrier update.
- Live polling endpoint (`/track/{orderNo}/live`) refreshes tracking without full page reload (rate-limited).

### 2.9 Returns
- `GET /returns/{orderNo}/new` — Return-request form (reason dropdown, comment, optional photo).
- `POST /returns/{orderNo}` — Submit request.
- `GET /returns/view/{returnNo}` — View status.
- `POST /returns/view/{returnNo}/cancel` — Cancel (only allowed in `requested` state).

### 2.10 Customer account — `/profile`
- Tabs: profile, addresses, orders, wishlist, password.
- Order history with status and link to detail.
- Add / edit / delete addresses.
- Change password (verifies existing password).

### 2.11 Wishlist — `/wishlist`
- Grid of saved items with quick-add-to-cart.
- Remove button per item.
- Empty state.

### 2.12 Contact — `/contact`
- Standard contact form (name, email, phone, city, subject, message).
- Rate-limited to 5/min per IP.
- On submit, notifies store owner via email (queued).

### 2.13 Informational pages
- `/about` — Company story, mission, team.
- `404` — Custom Blade view with a "back to home" CTA.

---

## 3. Auth flows

### 3.1 Register — `/register`
- Fields: name, email, phone (unique), password + confirmation, terms.
- On submit → OTP is emailed (and SMS'd if driver is live).
- Redirects to `/verification`.

### 3.2 Verify OTP — `/verification`
- 6-digit OTP entry.
- On correct OTP, `email_verified_at` is set; customer is logged in.

### 3.3 Login — `/login`
- Identifier (email or phone) + password.
- Rate-limited to 5/min per IP.
- Password verified **before** any OTP is (re)issued (prevents OTP-bomb).
- Errors are generic (`"Invalid credentials."`) to prevent enumeration.

### 3.4 Forgot password — `/forgot-password`
- Enter email → OTP sent to that email.
- Redirects to `/reset-password?token=…`.
- OTP compared with `hash_equals` (constant time).
- On correct OTP + new password → account password updated.

### 3.5 Google OAuth — `/auth/google/redirect`
- Redirects to Google.
- Callback creates a new customer or logs in existing (by `google_id` or `email`).
- **Known limitation:** email-based match risks takeover — see [15 Security](15-security-architecture.md).

---

## 4. Admin features

Every admin route is behind `auth + admin` middleware; unauthenticated users are redirected to `/admin/login`.

### 4.1 Dashboard — `/admin/dashboard`
- Greeting + today's date.
- KPI cards: revenue (today / week / month), orders count, customers count.
- Revenue chart (line/bar).
- Order-status donut.
- Low-stock alert card (products at or below `LOW_STOCK_THRESHOLD`, default 5).
- Quick links to Orders and Reports.

### 4.2 Products — `/admin/a_products`
- Search (name/SKU/slug), status filter.
- CRUD (Create, Edit, Delete with soft delete).
- Variant editor: attribute grid with per-variant stock, price, SKU, weight, image.
- Gallery uploader (dropzone.js).
- SEO section.
- Export CSV.

### 4.3 Categories — `/admin/a_category`
- CRUD.
- Parent picker for hierarchy.
- Image upload.
- Featured / status toggles.

### 4.4 Orders — `/admin/a_orders`
- List with search (customer name, phone, order#), status filter, export CSV.
- Detail view: line items, customer info, shipping address, payment info, actions.
- Actions: update status, check serviceability, assign AWB, generate label, request pickup, sync tracking, cancel shipment, refund, view invoice.

### 4.5 Returns — `/admin/returns`
- List with status filter.
- Detail view: return items, requested amount, reason, photo, approve / reject / mark-received / mark-refunded actions.

### 4.6 Payments — `/admin/a_payment`
- List of payments across all orders with status filter.
- View / delete.

### 4.7 Coupons — `/admin/a_coupons`
- CRUD + toggle active.

### 4.8 Sliders — `/admin/slider`
- CRUD for the slider set.
- Media manager: upload images / videos with caption and click-through URL.

### 4.9 Contacts — `/admin/a_contacts`
- List with search.
- View / delete.

### 4.10 Settings — `/admin/setting`
- Store name, email, phone, address, city, logo, favicon, social links (JSON).

### 4.11 Payment gateway config — `/admin/settings/payment-gateways`
- Per-gateway edit (mode: test/live, credentials, config, supports, priority).
- Toggle active / set default.
- Test connection button.

### 4.12 Courier partner config — `/admin/settings/couriers`
- Same as payment gateway UI, applied to `delivery_partners`.

### 4.13 Reports — `/admin/reports/*`
- Orders CSV export.
- Payments CSV export.

### 4.14 Command palette
- Global keyboard shortcut (⌘K / Ctrl+K) opens a searchable palette to navigate any admin page or entity.

---

## 5. API surface

Admin and customer endpoints are collectively documented in [06 API Documentation](06-api-documentation.md). Machine-readable route dump: `php artisan route:list`.

---

## 6. Notifications

| Event               | Channel(s)     | To               | Trigger                                                            |
| ------------------- | -------------- | ---------------- | ------------------------------------------------------------------ |
| Order placed        | Mail (queued)  | Customer         | `Client\OrderController::save()` (COD) and Razorpay callback       |
| Order shipped       | Mail + SMS     | Customer         | `ShipmentService::assignAwb()` → `notifyCustomerShipped()`         |
| Order status change | Mail (queued)  | Customer         | `Admin\OrderController::update()`                                  |
| OTP                 | Mail (+ SMS)   | Customer         | Register, forgot-password, login-of-unverified-account             |
| Contact form        | Mail (queued)  | Store owner      | `Client\ContactController::save()`                                 |

Full detail in [16 Notification System](16-notification-system.md).

---

## 7. UI standards

### 7.1 Admin
- **Framework:** Vue 3 + Inertia.js.
- **CSS:** Tailwind CSS 4.0 (custom Outfit font, brand blues, semantic colours, dark-mode capable).
- **Components:** In-house `ui/*` primitives (Button, Input, Select, Modal, DataTable, KpiCard, etc.).
- **Interactions:** Global toast system (`useToast`), keyboard palette (⌘K), skeleton loaders on list pages.

### 7.2 Storefront
- **Framework:** Blade + Bootstrap 5.
- **CSS:** Legacy theme in `public/client/css/`; new tweaks inline in Blade.
- **JS:** jQuery-based `main.js`; Swiper for carousels; PhotoSwipe for gallery; Drift for zoom.

Consistency between the two experiences is **not** a stated requirement; storefront is a themed site, admin is an app.

---

## 8. Acceptance criteria (release gate)

The product ships when:

- **AC-1** — All FR-* items in [BRD § 3](02-business-requirements.md) are demonstrable end-to-end in the browser.
- **AC-2** — All rate limits are active on the routes listed in [15 Security § 3](15-security-architecture.md).
- **AC-3** — Razorpay callback + webhook idempotency is verified with a duplicate-event test (manual).
- **AC-4** — Shiprocket AWB → label → pickup → webhook cycle runs green end-to-end in the sandbox.
- **AC-5** — A full return cycle (request → approve → receive → refund) runs green with stock restoration.
- **AC-6** — All 5 email notifications land in `laravel.log` under `MAIL_MAILER=log`.
- **AC-7** — Admin cannot access customer-only routes and vice versa.
- **AC-8** — `php artisan config:cache && php artisan route:cache && php artisan view:cache` succeed without errors.
- **AC-9** — `php artisan queue:work` picks up and processes one queued notification.
- **AC-10** — All docs in this BMAD folder are up to date with the current commit.
