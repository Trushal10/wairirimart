# 25 — Feature Matrix

Single-page snapshot of every functional area in Vue-Ecommerce. Use this as the "does it have X?" checklist.

Legend:
- ✅ Live — implemented and tested end-to-end.
- 🟡 Partial — implemented with a known gap or stub; usable but not complete.
- ❌ Not implemented — planned or explicitly out-of-scope.
- N/A — Not applicable.

---

## 1. Customer-facing features

| Feature                              | Status | Notes                                                                                       |
| ------------------------------------ | :----: | ------------------------------------------------------------------------------------------- |
| Product browse (grid, filters, search) | ✅     | Bootstrap Blade shop page                                                                    |
| Product detail with gallery + zoom   | ✅     | PhotoSwipe + Drift                                                                          |
| Product variants (size, colour, …)   | ✅     | Multi-attribute matrix with per-variant stock                                                |
| Product reviews (submit)             | ✅     | Auth required; defaults `is_approved=false`                                                  |
| Product reviews (admin approval UI)  | ❌     | Must approve via DB — see [26 Known Limitations G-4](26-known-limitations.md)                |
| Categories (hierarchical)            | ✅     | `parent_id` self-FK                                                                          |
| Brands (first-class)                 | ❌     | Only free-text `products.brand`. User declined a Brand model.                                 |
| Wishlist                             | ✅     | Per-customer, variant-aware, unique index                                                    |
| Cart (session-based)                 | ✅     | Guest + auth; not cross-device                                                               |
| Cart persistence across devices      | ❌     | Would need server-side cart-for-customer table                                               |
| Coupons                              | ✅     | Fixed / percent, min-order, max-discount, usage limit, per-customer, dates                    |
| Multi-step checkout                  | ✅     | Signin → cart → address → payment                                                            |
| Multiple addresses per customer      | ✅     | `customer_addresses` (home / work)                                                          |
| Guest checkout                       | ❌     | Signin is mandatory                                                                          |
| Terms of service acceptance          | ✅     | `agree_tos` server-enforced                                                                  |
| Order confirmation page              | ✅     | `/order/confirmation/{orderNo}`                                                              |
| Invoices (customer)                  | ✅     | HTML; print / save-as-PDF                                                                    |
| Order tracking (public)              | ✅     | `/track/{orderNo}` with email verification + session grant                                   |
| Order tracking (live polling)        | ✅     | `/track/{orderNo}/live` JSON                                                                 |
| Returns (customer)                   | ✅     | Request within 14-day window; strict state machine                                          |
| Cancel own return                    | ✅     | Only in `requested` state                                                                    |
| Profile management                   | ✅     | Name, email, phone, avatar, password, addresses                                              |
| Password change with existing-pw     | 🟡     | Assumed implemented, not re-verified — see [21 QA § 2.2](21-qa-report.md)                    |
| Email OTP verification (register)    | ✅     |                                                                                              |
| Forgot password (email OTP)          | ✅     | `hash_equals` compare                                                                        |
| Login (email OR phone)               | ✅     |                                                                                              |
| Google OAuth login                   | 🟡     | Works, but email-match takeover risk (G-12)                                                  |
| Facebook / Apple OAuth               | ❌     |                                                                                              |
| Two-factor authentication (customer) | ❌     |                                                                                              |
| Newsletter subscription              | ❌     | Footer placeholder only                                                                      |
| Chat widget / live support           | ❌     |                                                                                              |
| Mobile app                           | ❌     |                                                                                              |
| PWA install                          | ❌     |                                                                                              |
| Multi-currency                       | ❌     | INR only; symbol hardcoded                                                                   |
| Multi-language                       | ❌     | English only                                                                                 |

## 2. Payments

| Feature                            | Status | Notes                                                                            |
| ---------------------------------- | :----: | -------------------------------------------------------------------------------- |
| Razorpay (UPI, cards, wallets)     | ✅     | Signature verification, amount/currency assertion, webhook dedup                  |
| Cash on delivery (COD)             | ✅     | Stock decremented at placement (⚠️ COD drain risk — G-11)                        |
| Stripe                             | 🟡     | Class exists, not wired                                                          |
| PayPal                             | 🟡     | Class exists, not wired                                                          |
| Refunds (Razorpay)                 | ✅     | Cap at payment amount, dedup by refund_id                                        |
| Refunds (COD)                      | 🟡     | Recorded only; no money movement                                                 |
| Partial refunds                    | ✅     | Admin picks amount                                                               |
| Saved payment methods              | ❌     | Every payment goes through gateway modal                                         |
| Multiple payments per order        | ❌     | Schema supports; logic assumes 1:1                                                |
| Auto-refund on return              | ❌     | Admin must invoke refund separately (G-13)                                       |
| Tax calculation (GST)              | ❌     | No tax engine                                                                    |
| Discount codes at cart / product level | ✅ | Coupon codes only (no automatic discounts)                                        |

## 3. Shipping / logistics

| Feature                            | Status | Notes                                                                            |
| ---------------------------------- | :----: | -------------------------------------------------------------------------------- |
| Shiprocket integration             | ✅     | Serviceability, AWB, label, pickup, tracking sync, cancel, webhook                |
| Delhivery integration              | 🟡     | Adapter present, less-tested                                                     |
| Blue Dart, DTDC, Xpressbees, Shadowfax | 🟡 | Stubs only                                                                        |
| Manual dispatch (no third-party)   | ✅     | `delivery_partners.is_third_party=false` (Local Delivery seeded)                  |
| Serviceability check by pincode    | ✅     | Per-order button                                                                 |
| Multi-shipment orders              | ❌     | Schema supports; UI assumes 1:1                                                  |
| International shipping             | ❌     |                                                                                  |
| Real-time carrier rates at checkout| ❌     | Fixed / manual shipping cost                                                     |
| Print shipping label               | ✅     | Via courier API; PDF URL stored                                                  |
| Bulk label print                   | ❌     |                                                                                  |
| Pickup scheduling                  | ✅     | Via courier API                                                                  |
| Return-to-origin (RTO) tracking    | ✅     | Via shipment status                                                              |
| Webhook-based tracking             | ✅     | Shiprocket only                                                                  |
| Fallback polling for tracking      | ✅     | `SyncActiveShipmentsJob` every 15 min                                            |

## 4. Order management

| Feature                            | Status | Notes                                                                            |
| ---------------------------------- | :----: | -------------------------------------------------------------------------------- |
| Atomic stock decrement             | ✅     | `lockForUpdate()` on product + variant                                            |
| Coupon consumption (atomic)        | ✅     | Conditional UPDATE                                                               |
| Order status history               | ✅     | With source (system / admin / webhook / customer)                                |
| Order cancellation                 | ✅     | Admin only; does NOT restore stock automatically                                 |
| Order splits                       | ❌     |                                                                                  |
| Order edits (post-placement)       | ❌     | Only status can change                                                           |
| Reorder / clone previous order     | ❌     |                                                                                  |
| Auto-cancel stale `pending` orders | ❌     |                                                                                  |
| Backorder support                  | ❌     |                                                                                  |
| Order notes (private / customer)   | 🟡     | `orders.comment`-style field exists on `order_status_histories`                   |

## 5. Returns

| Feature                            | Status | Notes                                                                            |
| ---------------------------------- | :----: | -------------------------------------------------------------------------------- |
| Customer requests return           | ✅     |                                                                                  |
| Strict state machine               | ✅     | requested → approved / rejected → received → refunded / cancelled                |
| Restock on received                | ✅     | Both product and variant                                                         |
| Return window (14 days default)    | ✅     | Configurable via env                                                             |
| Auto-refund on refunded            | ❌     | Admin must invoke refund separately                                              |
| Partial-item return                | ✅     | `return_items.quantity`                                                          |
| Return by reason category          | ✅     | `reason` column with 5 fixed values                                              |
| Return media upload                | ✅     | `photo` column                                                                   |
| Return label generation            | ❌     | Not integrated with courier                                                      |

## 6. Admin panel

| Feature                            | Status | Notes                                                                            |
| ---------------------------------- | :----: | -------------------------------------------------------------------------------- |
| Dashboard (KPIs, chart, donut, low-stock) | ✅ |                                                                                  |
| Products CRUD                      | ✅     | With variants, gallery, SEO                                                      |
| Categories CRUD                    | ✅     | With subcategories                                                               |
| Orders list + detail + actions     | ✅     |                                                                                  |
| Returns management                 | ✅     | Approve / reject / receive / refund                                              |
| Payments list                      | ✅     | View / delete only                                                               |
| Coupons CRUD + toggle              | ✅     |                                                                                  |
| Sliders CRUD                       | ✅     | With media manager                                                               |
| Contact inquiries list             | ✅     | View / delete only                                                               |
| Store settings                     | ✅     | One-row form                                                                     |
| Payment gateway config             | ✅     | Encrypted credentials; toggle + set default + test connection                    |
| Courier partner config             | ✅     | Same UX as payment gateways                                                      |
| CSV export (orders, payments)      | ✅     | Streamed with chunked cursor                                                     |
| Sales report (KPIs + chart + top N)| ✅     | See [29 Reports Module](29-reports-module.md)                                    |
| Orders report (paginated + filters)| ✅     |                                                                                  |
| Payments report (gross/refunds/fees/net + gateway breakdown) | ✅ |                                                                             |
| Shipping report (by courier + trend) | ✅ |                                                                                  |
| Profit & Loss report               | ✅     | Auto-computes Revenue, COGS, Shipping Cost, Gateway Fees, Discounts, Refunds, Taxes, Other Expenses, Gross/Operating/Net Profit, Margin |
| Operating Expenses CRUD            | ✅     | Feeds P&L "Other Expenses (Period)"                                              |
| Export formats: CSV / Excel / PDF / Print | ✅ | Zero-dep; Excel via HTML masquerade, PDF via browser print                    |
| Command palette (⌘K)               | ✅     |                                                                                  |
| Reviews moderation UI              | ❌     |                                                                                  |
| Brand management UI                | ❌     |                                                                                  |
| Admin users management             | ❌     |                                                                                  |
| Role-based access (sub-roles)      | ❌     | Only `role=admin` vs not                                                         |
| Audit log viewer                   | ❌     | Data captured; no UI                                                             |
| Bulk actions on lists              | ❌     |                                                                                  |
| Inline edit in lists               | ❌     |                                                                                  |
| Dark mode toggle                   | ✅     | Global ThemeToggle component                                                     |
| Print shipping label from admin    | ✅     | Via courier API                                                                  |
| Manual inventory adjustment log    | ❌     | Adjustments overwrite `stock` without history                                    |

## 7. Notifications

| Notification                        | Channel(s)      | Status |
| ----------------------------------- | --------------- | :----: |
| OrderPlaced (customer)              | Mail + SMS      | ✅     |
| OrderShipped (customer)             | Mail + SMS      | ✅     |
| OrderStatusChanged (customer)       | Mail            | ✅     |
| SendOtp (customer)                  | Mail + SMS      | ✅     |
| ContactNotification (store owner)   | Mail            | ✅     |
| OrderPlaced (admin)                 | Mail            | ❌     |
| ReturnRequested (customer / admin)  | Mail            | ❌     |
| RefundIssued (customer)             | Mail            | ❌     |
| PasswordChanged (customer)          | Mail            | ❌     |
| NewReview (admin)                   | Mail            | ❌     |
| LowStockAlert (admin)               | Mail            | ❌ (dashboard card only) |
| CartAbandonment (customer)          | Mail            | ❌     |

## 8. Security

| Feature                            | Status | Notes                                                                            |
| ---------------------------------- | :----: | -------------------------------------------------------------------------------- |
| CSRF protection                    | ✅     | Except webhooks (signed)                                                         |
| HSTS + security headers            | ✅     | SecurityHeaders middleware                                                       |
| Content-Security-Policy            | ❌     | Recommended for defence-in-depth                                                 |
| Rate limiting on hot endpoints     | ✅     |                                                                                  |
| Session encryption                 | ✅     | `SESSION_ENCRYPT=true`                                                           |
| Secure cookies                     | ✅     | `SESSION_SECURE_COOKIE=true` in prod                                             |
| bcrypt password hashing            | ✅     | Rounds=12                                                                        |
| HMAC-verified webhooks             | ✅     | Razorpay + Shiprocket                                                            |
| Encrypted-at-rest secrets          | ✅     | `payment_gateways.credentials`, `delivery_partners.credentials`                   |
| Login enumeration protection       | ✅     | Generic error responses                                                          |
| OTP-bomb protection                | ✅     | Password verified before OTP re-issue                                            |
| Constant-time OTP compare          | ✅     | `hash_equals`                                                                    |
| OTP hashed at rest                 | ❌     | Plaintext in `password_resets.otp` (G-10)                                        |
| 2FA (admin / customer)             | ❌     |                                                                                  |
| Login attempt lockout              | ❌     | Rate limit only                                                                  |
| IP block list                      | ❌     |                                                                                  |
| Google reCAPTCHA on forms          | ❌     | Configured, not wired                                                            |
| Audit log of admin actions         | ✅     | `admin_activity_logs`                                                            |
| Audit log of customer auth events  | ❌     |                                                                                  |

## 9. Performance & scale

| Feature                            | Status | Notes                                                                            |
| ---------------------------------- | :----: | -------------------------------------------------------------------------------- |
| Queued notifications               | ✅     | All 5 notifications `implements ShouldQueue`                                     |
| Queued background jobs             | ✅     | `SyncActiveShipmentsJob`                                                         |
| Scheduler-driven cron              | ✅     | Every 15 min                                                                     |
| Redis session / cache / queue      | 🟡     | Supported via config; default is database                                        |
| CDN for assets                     | 🟡     | Manual (Cloudflare in front works)                                               |
| Image optimisation on upload       | 🟡     | Client-side `compressImage.js`; no server-side resize                            |
| Multi-node deployment              | 🟡     | Needs Redis for sessions + shared storage                                        |
| Horizon dashboard                  | ❌     |                                                                                  |
| Full-text search (Meilisearch/Algolia) | ❌ | LIKE-based only                                                                  |

## 10. Ops & observability

| Feature                            | Status | Notes                                                                            |
| ---------------------------------- | :----: | -------------------------------------------------------------------------------- |
| Health check `/up`                 | ✅     | Laravel built-in                                                                 |
| Log rotation                       | 🟡     | Logrotate config recommended (see [28 Maintenance](28-maintenance-guide.md))     |
| Backups (DB + uploads)             | 🟡     | Manual — automation recommended                                                  |
| Uptime monitoring                  | ❌     | Recommend UptimeRobot / Pingdom on `/up`                                          |
| Error tracking (Sentry)            | ❌     | Recommend Sentry / Bugsnag                                                        |
| APM (New Relic / Datadog)          | ❌     |                                                                                  |
| Deployment automation (CI/CD)      | ❌     |                                                                                  |
| Dockerfile / docker-compose        | ❌     |                                                                                  |
| Kubernetes manifests               | ❌     |                                                                                  |

## 11. Testing

| Feature                            | Status | Notes                                                                            |
| ---------------------------------- | :----: | -------------------------------------------------------------------------------- |
| Unit tests                         | ❌     | 0 tests written                                                                  |
| Feature tests                      | ❌     | 0 tests written                                                                  |
| E2E tests (Playwright / Cypress)   | ❌     |                                                                                  |
| CI pipeline                        | ❌     |                                                                                  |
| Code coverage report               | ❌     |                                                                                  |
| Load testing                       | ❌     |                                                                                  |
| Security scan                      | ❌     |                                                                                  |
