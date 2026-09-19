# 18 — Environment Configuration

Central reference for every environment variable the app reads. Grouped by concern; **do not commit secrets**.

The committed `.env.example` documents Laravel defaults with production-safe values (see [17 Deployment Guide](17-deployment-guide.md) for local dev overrides).

---

## 1. Core Laravel

| Variable                | Example                       | Notes                                                        |
| ----------------------- | ----------------------------- | ------------------------------------------------------------ |
| `APP_NAME`              | `Vue-Ecommerce`               | Used in mail from-name and page titles                        |
| `APP_ENV`               | `production`                  | Never set to `local`/`staging` in prod                        |
| `APP_KEY`               | `base64:...`                  | **Master encryption key**. Generate via `php artisan key:generate`. Losing this = losing all encrypted data. |
| `APP_DEBUG`             | `false`                       | `true` reveals stack traces — never on prod                    |
| `APP_URL`               | `https://yourbrand.com`       | Used by URL::to(), notifications, redirect URIs               |
| `APP_LOCALE`            | `en`                          | Only English strings exist today                              |
| `APP_FALLBACK_LOCALE`   | `en`                          |                                                              |
| `APP_MAINTENANCE_DRIVER`| `file`                        | Set to `redis` to share maintenance mode across nodes         |
| `PHP_CLI_SERVER_WORKERS`| `4`                           | For `php artisan serve` only                                  |
| `BCRYPT_ROUNDS`         | `12`                          | Password hashing cost. Higher = slower.                       |
| `LOG_CHANNEL`           | `stack`                       | See `config/logging.php`                                      |
| `LOG_STACK`             | `single`                      | Or `daily`                                                    |
| `LOG_LEVEL`             | `warning`                     | prod: warning. dev: debug.                                    |

## 2. Database

| Variable       | Example        | Notes                                                       |
| -------------- | -------------- | ----------------------------------------------------------- |
| `DB_CONNECTION`| `mysql`        | Also `sqlite`, `pgsql`, `sqlsrv`                            |
| `DB_HOST`      | `127.0.0.1`    |                                                             |
| `DB_PORT`      | `3306`         |                                                             |
| `DB_DATABASE`  | `vue_ecommerce`| ⚠️ committed .env.example uses `business_card` — change in prod |
| `DB_USERNAME`  | `vue_ecommerce`|                                                             |
| `DB_PASSWORD`  | (secret)       |                                                             |

## 3. Session

| Variable                 | Example       | Notes                                                     |
| ------------------------ | ------------- | --------------------------------------------------------- |
| `SESSION_DRIVER`         | `database`    | Switch to `redis` for multi-node deployments              |
| `SESSION_LIFETIME`       | `120`         | Minutes                                                   |
| `SESSION_ENCRYPT`        | `true`        | Encrypts session payloads at rest                         |
| `SESSION_PATH`           | `/`           |                                                           |
| `SESSION_DOMAIN`         | `null`        | Set to `.yourbrand.com` for subdomain-shared sessions     |
| `SESSION_SECURE_COOKIE`  | `true`        | HTTPS-only cookies. Required in production.               |
| `SESSION_SAME_SITE`      | `lax`         | CSRF defence                                              |

## 4. Cache

| Variable      | Example        | Notes                                                    |
| ------------- | -------------- | -------------------------------------------------------- |
| `CACHE_STORE` | `database`     | Also `redis`, `memcached`, `array`, `file`, `null`       |
| `CACHE_PREFIX`| (blank)        | Set to a per-env prefix if sharing Redis                  |

## 5. Queue

| Variable            | Example      | Notes                                                          |
| ------------------- | ------------ | -------------------------------------------------------------- |
| `QUEUE_CONNECTION`  | `database`   | Also `sync`, `redis`, `sqs`, `beanstalkd`                       |

Notifications, tracking sync, and any future jobs all use this queue.

## 6. Cache/Session tuning knobs (if using Redis)

| Variable                   | Notes                                            |
| -------------------------- | ------------------------------------------------ |
| `REDIS_HOST`               | `127.0.0.1`                                      |
| `REDIS_PASSWORD`           | (secret)                                         |
| `REDIS_PORT`               | `6379`                                           |
| `REDIS_CLIENT`             | `phpredis` (faster) or `predis`                  |

## 7. Filesystem

| Variable            | Example  | Notes                                                     |
| ------------------- | -------- | --------------------------------------------------------- |
| `FILESYSTEM_DISK`   | `local`  | Uploads go to `storage/app/`; use `public` disk if you want public URLs |
| `AWS_ACCESS_KEY_ID` | (secret) | For S3 disk                                               |
| `AWS_SECRET_ACCESS_KEY`| (secret) |                                                        |
| `AWS_DEFAULT_REGION`| `us-east-1` |                                                        |
| `AWS_BUCKET`        | (name)   |                                                           |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` |                                                    |

## 8. Mail

| Variable            | Example                | Notes                                          |
| ------------------- | ---------------------- | ---------------------------------------------- |
| `MAIL_MAILER`       | `smtp` / `ses` / `postmark` / `mailgun` / `log` / `array` / `sendmail` | Dev: `log` |
| `MAIL_HOST`         | `smtp.example.com`     |                                                |
| `MAIL_PORT`         | `587`                  |                                                |
| `MAIL_USERNAME`     | (secret)               |                                                |
| `MAIL_PASSWORD`     | (secret)               |                                                |
| `MAIL_ENCRYPTION`   | `tls`                  |                                                |
| `MAIL_FROM_ADDRESS` | `no-reply@yourbrand.com` | Must match verified sender (SPF/DKIM)        |
| `MAIL_FROM_NAME`    | `${APP_NAME}`          |                                                |

Optional providers (used only if `MAIL_MAILER` selects them):
- `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, `MAILGUN_ENDPOINT`
- `POSTMARK_TOKEN`
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` (for SES)

## 9. Payment gateways

Not in `.env.example` — you must add these explicitly (gateway config is stored in DB via `/admin/settings/payment-gateways`, but env values act as fallbacks used by `config/services.php`).

### Razorpay
| Variable                    | Notes                                     |
| --------------------------- | ----------------------------------------- |
| `RAZORPAY_KEY`              | Test: `rzp_test_...` / Live: `rzp_live_...` |
| `RAZORPAY_SECRET`           |                                           |
| `RAZORPAY_WEBHOOK_SECRET`   | HMAC key for `X-Razorpay-Signature`       |

### Stripe (stub)
| Variable                    | Notes                                     |
| --------------------------- | ----------------------------------------- |
| `STRIPE_PUBLISHABLE_KEY`    | `pk_live_...`                             |
| `STRIPE_SECRET_KEY`         |                                           |
| `STRIPE_WEBHOOK_SECRET`     | `whsec_...`                               |

### PayPal (stub)
| Variable                    | Notes                                     |
| --------------------------- | ----------------------------------------- |
| `PAYPAL_CLIENT_ID`          |                                           |
| `PAYPAL_CLIENT_SECRET`      |                                           |
| `PAYPAL_WEBHOOK_ID`         |                                           |

## 10. Courier partners

### Shiprocket
| Variable                       | Notes                                    |
| ------------------------------ | ---------------------------------------- |
| `SHIPROCKET_EMAIL`             | Login                                    |
| `SHIPROCKET_PASSWORD`          | Login                                    |
| `SHIPROCKET_PICKUP_LOCATION`   | e.g. `Primary`                           |
| `SHIPROCKET_PICKUP_PINCODE`    | e.g. `560001`                            |
| `SHIPROCKET_CHANNEL_ID`        | Configured channel                        |
| `SHIPROCKET_WEBHOOK_SECRET`    | HMAC key for `X-Shiprocket-Signature`     |
| `SHIPROCKET_DEFAULT_LENGTH`    | cm, e.g. `10`                            |
| `SHIPROCKET_DEFAULT_BREADTH`   | cm                                       |
| `SHIPROCKET_DEFAULT_HEIGHT`    | cm                                       |
| `SHIPROCKET_DEFAULT_WEIGHT`    | kg                                       |

### Delhivery
| Variable                              | Notes                                |
| ------------------------------------- | ------------------------------------ |
| `DELHIVERY_API_TOKEN`                 |                                      |
| `DELHIVERY_CLIENT_NAME`               |                                      |
| `DELHIVERY_PICKUP_PINCODE`            |                                      |
| `DELHIVERY_PICKUP_LOCATION_NAME`      |                                      |

## 11. SMS

| Variable            | Example                    | Notes                                        |
| ------------------- | -------------------------- | -------------------------------------------- |
| `SMS_DRIVER`        | `log`                      | Dev-safe default. Prod: `msg91` / `twilio` / `fast2sms` |
| `MSG91_AUTH_KEY`    |                            |                                              |
| `MSG91_TEMPLATE_ID` |                            | Pre-approved template ID                     |
| `FAST2SMS_API_KEY`  |                            |                                              |
| `TWILIO_SID`        |                            |                                              |
| `TWILIO_TOKEN`      |                            |                                              |
| `TWILIO_FROM`       | `+1...`                    | Verified sender number                        |

## 12. Social auth

| Variable                | Example                                                | Notes                              |
| ----------------------- | ------------------------------------------------------ | ---------------------------------- |
| `GOOGLE_CLIENT_ID`      | `...apps.googleusercontent.com`                        |                                    |
| `GOOGLE_CLIENT_SECRET`  |                                                        |                                    |
| `GOOGLE_REDIRECT_URI`   | `https://yourbrand.com/auth/google/callback`           | Must match Google Console          |

## 13. Business config

| Variable                  | Default | Notes                                                   |
| ------------------------- | ------- | ------------------------------------------------------- |
| `RETURNS_WINDOW_DAYS`     | `14`    | Read from `services.returns.window_days`. Days after delivery a return can be raised |
| `LOW_STOCK_THRESHOLD`     | `5`     | Read from `services.inventory.low_stock_threshold`. Threshold below which dashboard warns |

## 14. reCAPTCHA (optional)

| Variable                          | Notes                                     |
| --------------------------------- | ----------------------------------------- |
| `RECAPTCHA_V3_SITE_KEY`           | Public site key (Google Console)          |
| `RECAPTCHA_V3_SECRET_KEY`         | Server secret                              |
| `RECAPTCHA_URL`                   | Verification endpoint                     |

## 15. Broadcast / Vite

| Variable              | Notes                                              |
| --------------------- | -------------------------------------------------- |
| `BROADCAST_CONNECTION`| `log` unless you enable Pusher/websockets         |
| `VITE_APP_NAME`       | `${APP_NAME}` — passed to the Vite bundle          |

## 16. Optional services

| Variable                        | Notes                                             |
| ------------------------------- | ------------------------------------------------- |
| `SLACK_BOT_USER_OAUTH_TOKEN`    | Notification channel (if wired)                    |
| `SLACK_ALERT_CHANNEL`           | e.g. `#alerts`                                     |

---

## 17. `.env.example` (committed)

Reproduced for reference. Live version: `d:\xampp8.2\htdocs\Vue-Ecommerce\.env.example`.

```env
APP_NAME=Laravel
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=business_card
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MEMCACHED_HOST=127.0.0.1
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"
```

## 18. Notes on secrets management

- Never commit `.env` to git. `.gitignore` already excludes it.
- Rotate `APP_KEY` requires re-encrypting `credentials` columns — write a one-shot Artisan command.
- Store secrets in your platform's secret manager (Vault, AWS Secrets Manager, Doppler, 1Password) — inject at deploy time via `envsubst` or CI.
- For Forge / Vapor, use the built-in secret editors.
- Rotate any secret after a personnel change.

## 19. Config caching

After changing `.env` in production, always run:
```bash
php artisan config:cache
```
Otherwise Laravel will keep serving the cached values.
