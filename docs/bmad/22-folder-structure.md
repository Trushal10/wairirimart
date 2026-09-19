# 22 — Folder Structure

Top-level layout of the Vue-Ecommerce codebase, following the Laravel 12 convention with app-specific extensions.

```
Vue-Ecommerce/
├── app/
│   ├── Console/
│   │   └── Commands/                # Custom Artisan commands (currently empty)
│   ├── Helper/
│   │   ├── CommonHelper.php         # File uploads, slug, validation rule helpers
│   │   └── SeoHelper.php            # JSON-LD structured data
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/               # 12 controllers — admin panel
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── CategoryController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── PaymentController.php
│   │   │   │   ├── PaymentGatewayController.php
│   │   │   │   ├── CourierPartnerController.php
│   │   │   │   ├── ReturnController.php
│   │   │   │   ├── CouponController.php
│   │   │   │   ├── SliderController.php
│   │   │   │   ├── SettingController.php
│   │   │   │   ├── ReportController.php
│   │   │   │   ├── ContactController.php
│   │   │   │   ├── SearchController.php
│   │   │   │   └── ProfileController.php
│   │   │   ├── Auth/                # 8 controllers — admin auth (Laravel scaffold)
│   │   │   │   ├── AuthenticatedSessionController.php
│   │   │   │   ├── RegisteredUserController.php
│   │   │   │   ├── PasswordResetLinkController.php
│   │   │   │   ├── NewPasswordController.php
│   │   │   │   ├── PasswordController.php
│   │   │   │   ├── EmailVerificationPromptController.php
│   │   │   │   ├── EmailVerificationNotificationController.php
│   │   │   │   ├── VerifyEmailController.php
│   │   │   │   └── ConfirmablePasswordController.php
│   │   │   ├── Client/              # 20 controllers — customer storefront
│   │   │   │   ├── HomeController.php
│   │   │   │   ├── ShopController.php
│   │   │   │   ├── CategoryController.php
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── CartController.php
│   │   │   │   ├── ShoppingCartController.php
│   │   │   │   ├── CheckoutController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── PaymentController.php
│   │   │   │   ├── TrackingController.php
│   │   │   │   ├── ReturnController.php
│   │   │   │   ├── ReviewController.php
│   │   │   │   ├── WishlistController.php
│   │   │   │   ├── ProfileController.php
│   │   │   │   ├── CustomerAddressController.php
│   │   │   │   ├── RegisterUserController.php
│   │   │   │   ├── LoginUserController.php
│   │   │   │   ├── ForgotPasswordController.php
│   │   │   │   ├── OtpController.php
│   │   │   │   ├── SocialAuthController.php
│   │   │   │   └── CouponController.php
│   │   │   ├── HomeController.php     # Admin dashboard
│   │   │   ├── InvoiceController.php  # Customer + admin invoice
│   │   │   ├── AboutController.php
│   │   │   └── SitemapController.php
│   │   ├── Middleware/
│   │   │   ├── AdminOnly.php
│   │   │   ├── ClientAuthMiddleware.php
│   │   │   ├── SecurityHeaders.php
│   │   │   ├── TrustProxies.php
│   │   │   └── HandleInertiaRequests.php
│   │   └── Requests/                # 23 FormRequests
│   │       ├── ProductRequest.php
│   │       ├── CategoryRequest.php
│   │       ├── CouponRequest.php
│   │       ├── SliderRequest.php
│   │       ├── SettingRequest.php
│   │       ├── ProfileRequest.php
│   │       ├── ProfileUpdateRequest.php
│   │       ├── OrderRequest.php
│   │       ├── MediaRequest.php
│   │       ├── PaymentGatewayRequest.php
│   │       ├── CourierPartnerRequest.php
│   │       ├── Auth/
│   │       │   └── LoginRequest.php
│   │       └── Client/
│   │           ├── LoginUserRequest.php
│   │           ├── RegisterUserRequest.php
│   │           ├── ResetPasswordRequest.php
│   │           ├── VerifyOtpRequest.php
│   │           ├── ProfileRequest.php
│   │           ├── CustomerAddressRequest.php
│   │           ├── ContactRequest.php
│   │           ├── ReviewRequest.php
│   │           ├── AddToCartRequest.php
│   │           ├── OrderRequest.php
│   │           └── ReturnRequest.php
│   ├── Jobs/
│   │   └── SyncActiveShipmentsJob.php
│   ├── Models/                      # 29 Eloquent models
│   │   ├── User.php
│   │   ├── Customer.php
│   │   ├── CustomerAddress.php
│   │   ├── Product.php
│   │   ├── ProductVariant.php
│   │   ├── ProductVariantValue.php
│   │   ├── ProductMedia.php
│   │   ├── ProductCategory.php
│   │   ├── Category.php
│   │   ├── Attribute.php
│   │   ├── AttributeValue.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── OrderReturn.php
│   │   ├── OrderStatusHistory.php
│   │   ├── Payment.php
│   │   ├── PaymentGateway.php
│   │   ├── Shipment.php
│   │   ├── DeliveryPartner.php
│   │   ├── Coupon.php
│   │   ├── Wishlist.php
│   │   ├── Review.php
│   │   ├── Slider.php
│   │   ├── SliderMedia.php
│   │   ├── Setting.php
│   │   ├── PasswordReset.php
│   │   ├── AdminActivityLog.php
│   │   └── Contact.php
│   ├── Notifications/
│   │   ├── OrderPlaced.php
│   │   ├── OrderShipped.php
│   │   ├── OrderStatusChanged.php
│   │   ├── SendOtpNotification.php
│   │   └── ContactNotification.php
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── Services/                    # service classes
│       ├── CartService.php
│       ├── CouponService.php
│       ├── OtpService.php
│       ├── SmsService.php
│       ├── ShipmentService.php
│       ├── ShiprocketService.php
│       ├── ShippingService.php
│       ├── AuditLogger.php
│       ├── Reports/
│       │   ├── ReportFilters.php         # request → normalised filter DTO
│       │   ├── ReportService.php         # sales / orders / payments / shipping / pnl aggregations
│       │   └── ReportExporter.php        # CSV, Excel .xls, PDF/print
│       ├── Payment/
│       │   ├── PaymentGatewayManager.php
│       │   ├── AbstractPaymentGateway.php
│       │   ├── RazorpayGateway.php
│       │   ├── StripeGateway.php        # STUB
│       │   ├── PayPalGateway.php         # STUB
│       │   └── CodGateway.php
│       └── Courier/
│           ├── CourierManager.php
│           ├── AbstractCourierAdapter.php
│           ├── ShiprocketAdapter.php
│           ├── DelhiveryAdapter.php
│           ├── BlueDartAdapter.php       # STUB
│           ├── DtdcAdapter.php           # STUB
│           ├── XpressbeesAdapter.php     # STUB
│           └── ShadowfaxAdapter.php      # STUB
├── bootstrap/
│   ├── app.php                       # Framework bootstrap + middleware
│   ├── providers.php
│   └── cache/                        # Framework cache (git-ignored)
├── config/                           # Laravel configs (services, auth, session, etc.)
│   ├── auth.php
│   ├── cache.php
│   ├── cors.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   ├── mail.php
│   ├── queue.php
│   ├── services.php                  # All third-party keys + business config
│   └── session.php
├── database/
│   ├── factories/
│   │   ├── UserFactory.php
│   │   ├── ProductFactory.php
│   │   └── ProductCategoryFactory.php
│   ├── migrations/                   # 40+ migration files
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── CategorySeeder.php
│   │   ├── ProductSeeder.php
│   │   ├── ProductCategorySeeder.php
│   │   ├── ProductMediasSeeder.php
│   │   ├── DeliveryPartnerSeeder.php
│   │   └── PaymentGatewaySeeder.php
│   └── database.sqlite               # Optional local SQLite DB
├── docs/
│   └── bmad/                         # This documentation set
├── public/
│   ├── index.php                     # Front controller
│   ├── build/                        # Vite output (JS + CSS bundles)
│   ├── client/                       # Legacy storefront theme assets
│   │   ├── css/  js/  fonts/  images/
│   ├── uploads/                      # User-uploaded media (products, sliders)
│   └── storage → ../storage/app/public  # Symlink via `php artisan storage:link`
├── resources/
│   ├── css/
│   │   └── style.css                 # Tailwind entry + custom theme
│   ├── js/                           # Inertia + Vue admin app
│   │   ├── app.js                    # App entry: Inertia + Vue + Ziggy
│   │   ├── ziggy.js                  # Auto-generated routes (Ziggy)
│   │   ├── Components/
│   │   │   ├── ui/                   # Button, Input, Select, Modal, DataTable, ...
│   │   │   ├── common/               # FormField, ComponentCard, Pagination, ...
│   │   │   ├── dashboard/            # KpiCard, RevenueChart, StatusDonut
│   │   │   ├── reports/              # ReportTabs, FilterBar, SelectDropdown, ExportButtons,
│   │   │   │                         # MetricTile, MultiSeriesChart, HBarList, PnlBreakdown
│   │   │   ├── Sidebar.vue
│   │   │   ├── Header.vue
│   │   │   ├── Breadcrum.vue
│   │   │   ├── ThemeToggle.vue
│   │   │   └── CommonGridShape.vue
│   │   ├── Composables/
│   │   │   ├── useCommandPalette.js
│   │   │   ├── useShortcuts.js
│   │   │   └── useToast.js
│   │   ├── Layouts/
│   │   │   └── MainLayout.vue
│   │   ├── Pages/
│   │   │   ├── Dashboard.vue
│   │   │   ├── Auth/
│   │   │   │   ├── Login.vue
│   │   │   │   ├── Register.vue
│   │   │   │   ├── ForgotPassword.vue
│   │   │   │   └── ResetPassword.vue
│   │   │   ├── Profile/
│   │   │   │   └── Index.vue
│   │   │   └── Admin/
│   │   │       ├── Product/           # Index.vue, Create.vue
│   │   │       ├── Category/          # Index.vue, Create.vue
│   │   │       ├── Order/             # Index.vue, Detail.vue
│   │   │       ├── Return/            # Index.vue, Detail.vue
│   │   │       ├── Payment/           # Index.vue
│   │   │       ├── Coupon/            # Index.vue, Create.vue
│   │   │       ├── Slider/            # Index.vue, Create.vue, MediaCreate.vue
│   │   │       ├── Contact/           # Index.vue
│   │   │       ├── Setting/           # Index.vue
│   │   │       ├── Reports/           # Sales.vue, Orders.vue, Payments.vue, Shipping.vue, Pnl.vue
│   │   │       ├── Expenses/          # Index.vue, Form.vue, Create.vue, Edit.vue
│   │   │       └── Settings/
│   │   │           ├── PaymentGateways/  # Index.vue, Edit.vue
│   │   │           └── Couriers/         # Index.vue, Edit.vue
│   │   └── Utils/
│   │       └── compressImage.js
│   └── views/                        # Blade templates (storefront + shared)
│       ├── app.blade.php             # Inertia root
│       ├── welcome.blade.php
│       ├── layouts/
│       │   └── client.blade.php      # Storefront master layout
│       ├── components/
│       │   ├── header.blade.php
│       │   ├── footer.blade.php
│       │   ├── filter.blade.php
│       │   └── product-card.blade.php
│       ├── client/
│       │   ├── index.blade.php       # Homepage
│       │   ├── shop.blade.php
│       │   ├── product.blade.php
│       │   ├── category.blade.php
│       │   ├── shopping-cart.blade.php
│       │   ├── checkout.blade.php
│       │   ├── order-confirmation.blade.php
│       │   ├── wishlist.blade.php
│       │   ├── contact.blade.php
│       │   ├── about.blade.php
│       │   ├── quick.blade.php
│       │   ├── 404.blade.php
│       │   ├── client-auth/
│       │   │   ├── login.blade.php
│       │   │   ├── profile.blade.php
│       │   │   ├── forgotPassword.blade.php
│       │   │   └── resetPassword.blade.php
│       │   ├── returns/
│       │   │   ├── create.blade.php
│       │   │   └── show.blade.php
│       │   └── track/
│       │       ├── index.blade.php
│       │       └── show.blade.php
│       ├── admin/
│       │   └── labels/
│       │       └── shipping.blade.php
│       ├── invoices/
│       │   └── show.blade.php        # Invoice template (customer + admin)
│       ├── errors/                   # Custom 4xx/5xx pages (if added)
│       └── auth/                     # Admin auth Blade (Laravel scaffold)
├── routes/
│   ├── web.php                       # Client + admin + webhooks
│   ├── auth.php                      # Admin auth (Fortify-style)
│   ├── client-auth.php               # Customer auth (OAuth, login, register, reset)
│   └── console.php                   # Scheduler + custom commands
├── storage/
│   ├── app/                          # Local disk uploads
│   │   ├── public/                   # Symlinked from public/storage
│   │   └── ...
│   ├── framework/                    # Views cache, sessions, jobs, tests
│   └── logs/
│       └── laravel.log               # App log
├── tests/
│   ├── TestCase.php
│   ├── Feature/                      # (empty)
│   └── Unit/                         # (empty)
├── vendor/                           # Composer deps (git-ignored)
├── node_modules/                     # NPM deps (git-ignored)
├── .env                              # Local secrets (git-ignored)
├── .env.example                      # Committed template
├── .gitignore
├── artisan                           # Laravel CLI
├── composer.json
├── composer.lock
├── package.json
├── package-lock.json
├── phpunit.xml                       # PHPUnit config
├── vite.config.js
├── tailwind.config.js                # (Tailwind v4 uses CSS-first config)
└── README.md
```

## 1. Where to find things (cheat sheet)

| Looking for                             | Location                                                       |
| --------------------------------------- | -------------------------------------------------------------- |
| Add a route                             | `routes/web.php` (or `routes/client-auth.php`, `routes/auth.php`) |
| Add a controller action                 | `app/Http/Controllers/{Admin,Client,Auth}/*.php`                |
| Add validation                          | `app/Http/Requests/`                                            |
| Add a DB table                          | `database/migrations/` (new file `YYYY_MM_DD_HHMMSS_create_*.php`) |
| Add a model                             | `app/Models/`                                                   |
| Add business logic                      | `app/Services/`                                                 |
| Add a payment gateway                   | `app/Services/Payment/` (subclass `AbstractPaymentGateway`)     |
| Add a courier                           | `app/Services/Courier/` (subclass `AbstractCourierAdapter`)     |
| Add a notification                      | `app/Notifications/` (implements `ShouldQueue`)                 |
| Add a queued job                        | `app/Jobs/`                                                     |
| Add a cron entry                        | `routes/console.php` (`Schedule::job(...)` / `Schedule::call(...)`) |
| Add an admin Vue page                   | `resources/js/Pages/Admin/`                                     |
| Add a Vue component                     | `resources/js/Components/`                                      |
| Add a storefront page                   | `resources/views/client/`                                       |
| Add a Blade layout / component          | `resources/views/layouts/` or `resources/views/components/`     |
| Add a static asset                      | `public/client/` (legacy) or `resources/css`/`resources/js`     |
| Change theme colours                    | `resources/css/style.css` (Tailwind v4 CSS-first)               |

## 2. Naming conventions

- **Controllers**: PascalCase `Something{Verb}Controller.php`, one file per domain.
- **Models**: singular PascalCase (`Product.php`, `OrderItem.php`).
- **Requests**: PascalCase `{Model}{Verb}Request.php` (`ProductRequest.php`).
- **Services**: PascalCase `{Domain}Service.php` (`CartService.php`).
- **Notifications**: PascalCase past tense event (`OrderPlaced.php`).
- **Migrations**: snake_case `create_{table}_table.php` or `add_{col}_to_{table}_table.php`.
- **Routes**: kebab-case URIs, dot-namespaced route names (`admin.orders.detail`).
- **Vue components**: PascalCase (`DataTable.vue`, `KpiCard.vue`).
- **Blade files**: kebab-case (`shopping-cart.blade.php`).

## 3. Domain groupings

Backend controllers are grouped by **audience** (Admin vs Client vs Auth), not by **domain** (product / order / …). Within each group, one controller per aggregate root. Services are grouped by **domain** (Cart, Coupon, Shipment, Payment/…). This is a deliberate split.

If the app grows further, consider moving to a modular structure (`app/Modules/{Products,Orders,Payments}/{Http,Models,Services}`) using packages like nWidart/laravel-modules.

## 4. What lives outside the app

- `storage/logs/` — application logs (rotate!)
- `storage/framework/` — sessions, views, cache (transient)
- `storage/app/` — private files
- `storage/app/public/` — files linked to public via `storage:link`
- `public/uploads/` — user-uploaded product/slider media (served directly)

Back up: DB + `.env` + `public/uploads/` + `storage/app/public/`. See [28 Maintenance](28-maintenance-guide.md).
