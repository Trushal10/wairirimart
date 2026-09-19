# 24 — Third-Party Integrations

## 1. Integrations at a glance

| Category         | Provider          | Status                | Config key(s)                                     | Notes                                                     |
| ---------------- | ----------------- | --------------------- | ------------------------------------------------- | --------------------------------------------------------- |
| Payment          | **Razorpay**      | ✅ Live                | `services.razorpay.*`                              | Primary online gateway (India). SDK: `razorpay/razorpay` |
| Payment          | Stripe            | 🟡 Stub               | `services.stripe.*`                                | Class exists, not wired                                    |
| Payment          | PayPal            | 🟡 Stub               | `services.paypal.*`                                | Class exists, not wired                                    |
| Payment          | COD               | ✅ Live                | (in-app only)                                      | Cash on delivery                                          |
| Courier          | **Shiprocket**    | ✅ Live                | `services.shiprocket.*`                            | Primary logistics aggregator (India)                       |
| Courier          | Delhivery         | 🟡 Adapter present    | `services.delhivery.*`                             | Less-tested; no webhook                                    |
| Courier          | Blue Dart         | 🟡 Stub               | (via `delivery_partners`)                          |                                                            |
| Courier          | DTDC              | 🟡 Stub               | (via `delivery_partners`)                          |                                                            |
| Courier          | Xpressbees        | 🟡 Stub               | (via `delivery_partners`)                          |                                                            |
| Courier          | Shadowfax         | 🟡 Stub               | (via `delivery_partners`)                          |                                                            |
| SMS              | MSG91             | ⚙️ Configurable       | `services.msg91.*`                                 | Template ID required                                       |
| SMS              | Twilio            | ⚙️ Configurable       | `services.twilio.*`                                | International                                              |
| SMS              | Fast2SMS          | ⚙️ Configurable       | `services.fast2sms.*`                              |                                                            |
| SMS              | Log (dev)         | ✅ Default             | (none)                                             | Writes to `laravel.log`                                    |
| Email            | SMTP / SES / Postmark / Mailgun / Log | ⚙️ Configurable | `config/mail.php`                        | Log driver is dev-default                                  |
| OAuth            | **Google**        | ⚠️ Partial            | `services.google.*`                                | Socialite-based; callback has open-issue (email takeover) |
| Bot protection   | reCAPTCHA v3      | ⚙️ Configured         | `services.v3-recaptcha-*`                          | Not currently wired to any form                            |
| Notifications    | Slack             | ⚙️ Configured         | `services.slack.*`                                 | Not currently wired                                        |
| Storage          | AWS S3            | ⚙️ Configurable       | `filesystems.disks.s3`                             | Default disk is `local`                                    |

Legend:
- ✅ Live = tested end-to-end in production shape.
- 🟡 Stub / partial = class exists; needs implementation before production use.
- ⚙️ Configurable = provider works when configured; not wired by default.
- ⚠️ Partial = works but has known limitations (see [21 QA Report](21-qa-report.md)).

---

## 2. Razorpay

**Purpose:** Online payments (UPI, cards, netbanking, wallets) for India.

**SDK:** `razorpay/razorpay ^2.9` (Composer).

**Config source:** DB (`payment_gateways` where `code='razorpay'`); env fallbacks in `config/services.php`.

**Env vars:**
- `RAZORPAY_KEY`
- `RAZORPAY_SECRET`
- `RAZORPAY_WEBHOOK_SECRET`

**Routes involved:**
- `POST /razorpay/callback` — customer-auth, throttle:20,1.
- `POST /webhooks/razorpay` — CSRF-exempt, throttle:120,1, HMAC signature required.

**Files:**
- `app/Services/Payment/RazorpayGateway.php` — adapter.
- `app/Http/Controllers/Client/PaymentController.php` — `razorpayCallback`, `razorpayWebhook`.

**Dashboard config required:**
1. Webhook URL: `https://yourbrand.com/webhooks/razorpay`.
2. Enable events: `payment.authorized`, `payment.captured`, `payment.failed`, `refund.created`, `refund.processed`.
3. Set `webhook_secret` and copy to `RAZORPAY_WEBHOOK_SECRET` env / DB credentials.

**See:** [10 Payment Architecture § 3](10-payment-architecture.md).

---

## 3. Shiprocket

**Purpose:** Shipping aggregator — one API call to dispatch through any of India's couriers.

**Auth pattern:** email/password → JWT via `POST /v1/auth/login` (cached, refreshed on 401).

**Env vars:** see [18 Environment § 10](18-environment-config.md).

**Routes involved:**
- Admin action routes for shipment management (see [11 Courier § 3](11-courier-architecture.md)).
- `POST /webhooks/shiprocket` — CSRF-exempt, throttle:120,1, HMAC signature required.

**Files:**
- `app/Services/Courier/ShiprocketAdapter.php` — adapter.
- `app/Services/ShiprocketService.php` — legacy direct calls (may be consolidated into adapter).
- `app/Http/Controllers/Client/PaymentController.php` — `shiprocketWebhook`.

**Dashboard config required:**
1. Webhook URL: `https://yourbrand.com/webhooks/shiprocket`.
2. Signature secret set (`SHIPROCKET_WEBHOOK_SECRET`).
3. Pickup location configured and matching `SHIPROCKET_PICKUP_LOCATION`.

**See:** [11 Courier Architecture § 3](11-courier-architecture.md).

---

## 4. Google OAuth (Socialite)

**Purpose:** Customer "Sign in with Google".

**SDK:** `laravel/socialite ^5.28`.

**Env vars:**
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`

**Routes:**
- `GET /auth/google/redirect`
- `GET /auth/google/callback`

**Files:**
- `app/Http/Controllers/Client/SocialAuthController.php`

**Google Cloud config required:**
1. Create OAuth 2.0 Client ID in Google Console.
2. Authorised redirect URI: `https://yourbrand.com/auth/google/callback`.
3. Consent screen configured with your brand.

**Known issue:** Callback matches by `google_id` OR `email` — an attacker with the same email may bind their Google account to an existing password account. See [15 Security § 16](15-security-architecture.md) and [26 Known Limitations G-12](26-known-limitations.md). Fix planned.

---

## 5. SMS providers

**Contract:** `App\Services\SmsService::send($to, $message, $template?)`.

**Driver env:** `SMS_DRIVER=log|msg91|fast2sms|twilio`.

### 5.1 MSG91
- `MSG91_AUTH_KEY`
- `MSG91_TEMPLATE_ID` (pre-approved DLT template)

### 5.2 Twilio
- `TWILIO_SID`
- `TWILIO_TOKEN`
- `TWILIO_FROM`

### 5.3 Fast2SMS
- `FAST2SMS_API_KEY`

### 5.4 Log (default)
- Any driver value not in the above list falls back to logging the SMS payload to `laravel.log`. Safe for dev.

**Triggers:**
- OTP (register, forgot-password, login of unverified account).
- OrderPlaced (COD + Razorpay callback).
- OrderShipped (in `ShipmentService::notifyCustomerShipped()`).

**Files:**
- `app/Services/SmsService.php`
- `app/Services/OtpService.php` (delegates)

---

## 6. Mail providers

**Config:** `config/mail.php`. Driver selected by `MAIL_MAILER`.

### Supported drivers
- `smtp` — Generic SMTP (Postmark, SendGrid, Zoho, custom).
- `ses` — Amazon SES.
- `postmark` — Postmark.
- `mailgun` — Mailgun.
- `sendmail` — Local sendmail binary.
- `log` — Writes rendered email to `laravel.log` (dev-safe).
- `array` — Captures to memory (tests).
- `failover` / `roundrobin` — Fallback / rotation across configured mailers.

### Recommended production setup
- **SES** for low cost / high volume.
- **Postmark** for perfect deliverability.
- **Verified sender domain** — configure SPF, DKIM, DMARC before going live.

---

## 7. AWS S3 (optional)

**Config:** `config/filesystems.php` disk `s3`.

**Env vars:**
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`
- `AWS_BUCKET`
- `AWS_USE_PATH_STYLE_ENDPOINT`

**Usage:** Set `FILESYSTEM_DISK=s3` to have all uploads (products, sliders, avatars) go to S3 instead of local disk.

**Migration path from local → S3:**
1. Set S3 credentials.
2. Copy `public/uploads/` and `storage/app/public/` to your bucket.
3. Change `FILESYSTEM_DISK=s3`.
4. Update Blade / Vue image URLs (they use `Storage::url()` already, so no template changes needed if paths are stored correctly).

---

## 8. reCAPTCHA v3 (configured, not wired)

**Env vars:**
- `RECAPTCHA_V3_SITE_KEY`
- `RECAPTCHA_V3_SECRET_KEY`
- `RECAPTCHA_URL` (Google verification endpoint)

**Wired into:** Nothing yet. To activate, add:
1. Front-end: Load `https://www.google.com/recaptcha/api.js?render={SITE_KEY}` on forms; call `grecaptcha.execute()`.
2. Back-end: Verify token via `RecaptchaService::verify($token, $expectedAction)` before persisting.

**Recommended targets:** contact form, register form.

---

## 9. Slack (optional)

**Env vars:**
- `SLACK_BOT_USER_OAUTH_TOKEN`
- `SLACK_ALERT_CHANNEL`

**Usage:** Not wired. To add, extend a notification's `via()` to include the `slack` channel and configure Laravel's Slack notification driver.

**Use case:** Real-time ops alerts (`#alerts`) for high-value orders, refund failures, sync-job failures.

---

## 10. Postmark / Mailgun / SES

Each has env vars documented in [18 Environment](18-environment-config.md). None are wired at code level — configure `MAIL_MAILER` and set the credentials; Laravel handles the rest via `config/mail.php`.

---

## 11. Composer dependencies list

From `composer.json`:

```json
"require": {
  "php": "^8.2",
  "inertiajs/inertia-laravel": "^2.0",
  "laravel/framework": "^12.0",
  "laravel/socialite": "^5.28",
  "laravel/tinker": "^2.10.1",
  "razorpay/razorpay": "^2.9",
  "tightenco/ziggy": "^2.5"
},
"require-dev": {
  "fakerphp/faker": "^1.23",
  "laravel/pail": "^1.2.2",
  "laravel/pint": "^1.13",
  "laravel/sail": "^1.41",
  "mockery/mockery": "^1.6",
  "nunomaduro/collision": "^8.6",
  "phpunit/phpunit": "^11.5.3"
}
```

## 12. NPM dependencies list

From `package.json`:

```json
"dependencies": {
  "@inertiajs/vue3": "^2.0.5",
  "@vueup/vue-quill": "^1.2.0",
  "dropzone": "^6.0.0-beta.2",
  "ziggy": "^2.4.0"
},
"devDependencies": {
  "@popperjs/core": "^2.11.6",
  "@tailwindcss/vite": "^4.0.0",
  "@vitejs/plugin-vue": "^5.2.3",
  "axios": "^1.8.2",
  "bootstrap": "^5.2.3",
  "concurrently": "^9.0.1",
  "laravel-vite-plugin": "^1.2.0",
  "sass": "^1.56.1",
  "tailwindcss": "^4.0.0",
  "vite": "^6.2.2",
  "vue": "^3.2.37"
}
```

---

## 13. External services matrix (deployment checklist)

Before going live, verify each of these:

- [ ] Razorpay live keys entered in admin gateway config; webhook URL registered on Razorpay dashboard with matching secret.
- [ ] Shiprocket live account credentials in admin courier config; pickup location matches; webhook URL registered.
- [ ] SMS driver switched from `log` to `msg91`/`twilio`/`fast2sms`; template ID pre-approved.
- [ ] Mail driver switched from `log` to real SMTP/SES/Postmark; sender domain SPF/DKIM/DMARC verified.
- [ ] Google OAuth client ID / secret configured; redirect URI matches production URL.
- [ ] S3 bucket + IAM user prepared if switching `FILESYSTEM_DISK` to `s3`.
- [ ] Slack alerts (if wanted) — bot token + channel.
- [ ] reCAPTCHA v3 keys (if used) — site key + secret.

---

## 14. Rate limits imposed by providers (be aware)

| Provider   | Limit                                         |
| ---------- | --------------------------------------------- |
| Razorpay   | 1000 API calls/second per key (very generous)  |
| Shiprocket | ~30–100 req/min depending on plan             |
| MSG91      | Depends on plan; typical 5–10 req/sec         |
| Twilio     | ~1 req/sec per phone number (Trust hub varies)|
| SES        | 14 emails/sec (sandbox); higher after warm-up |
| Google     | 100 req/sec per project (OAuth is generous)   |

`SyncActiveShipmentsJob` throttles Shiprocket calls to stay under their limits.
