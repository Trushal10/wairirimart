# 16 — Notification System

## 1. Overview

Vue-Ecommerce sends transactional notifications on business events (orders, shipments, OTPs, contact submissions). Notifications flow through Laravel's built-in `Notification` framework and are dispatched via the queue for reliability.

- **Channels supported:** Mail (SMTP / SES / Postmark / Mailgun / log), SMS (msg91 / fast2sms / twilio / log driver).
- **Contract:** every notification `implements ShouldQueue` — never blocks a user-facing request.
- **Config:** transports configured via `.env` (see [18 Environment](18-environment-config.md)).

---

## 2. Notification catalogue

| Class                              | Trigger                                                                            | Channels        | Recipient       |
| ---------------------------------- | ---------------------------------------------------------------------------------- | --------------- | --------------- |
| `App\Notifications\OrderPlaced`    | Order committed (COD path + Razorpay callback path, guarded exactly-once)          | Mail (queued)   | Customer        |
| `App\Notifications\OrderShipped`   | AWB assigned (`ShipmentService::assignAwb()` → `notifyCustomerShipped()`)         | Mail (queued) + SMS | Customer   |
| `App\Notifications\OrderStatusChanged` | Order status updated by admin (`Admin\OrderController::update`)                | Mail (queued)   | Customer        |
| `App\Notifications\SendOtpNotification` | OTP issued (registration, forgot-password, login of unverified account)       | Mail (+ SMS)    | Customer        |
| `App\Notifications\ContactNotification` | Contact form submitted                                                        | Mail (queued)   | Store owner (from `settings.email`) |

All 5 classes are queued via `implements ShouldQueue`. The queue worker (`php artisan queue:work`) processes them off the request thread.

---

## 3. Delivery guarantees

### 3.1 Exactly-once (OrderPlaced)

The order-placed notification is guarded by an **atomic UPDATE** on `payment.meta.order_placed_notified_at`:

```php
$rowsUpdated = DB::table('payments')
    ->where('id', $payment->id)
    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '\$.order_placed_notified_at')) IS NULL")
    ->update(['meta->order_placed_notified_at' => now()]);

if ($rowsUpdated === 1) {
    Notification::send($customer, new OrderPlaced($order));
}
```

- If both the Razorpay callback and webhook race to mark a payment `paid`, only one of them succeeds in flipping `order_placed_notified_at` from NULL → timestamp — the other's UPDATE affects zero rows and no notification is dispatched.

### 3.2 Exactly-once (OrderShipped)

Guarded by `shipment.meta.ship_notified_at`. Same pattern.

### 3.3 At-most-once vs at-least-once

- Queue worker retries failed jobs (`--tries=3` recommended).
- Notifications should be **idempotent at the recipient level** — sending twice would spam the user but is not catastrophic.
- The exactly-once guards above elevate the critical order/shipment mails to true exactly-once.

---

## 4. Mail transports

- Config: `config/mail.php`.
- Driver: `MAIL_MAILER` env var (`smtp`, `ses`, `postmark`, `mailgun`, `log`, `array`, `sendmail`, `failover`, `roundrobin`).
- Default in dev: `log` → writes rendered email to `storage/logs/laravel.log`.

### Recommended production drivers
- Small volume (< 100/day): SMTP via Postmark/SendGrid/SES.
- Medium volume: Amazon SES (cheap + reliable at scale).
- High volume: SES + a warm-up plan.

### From address / name
- `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`.
- Must match your verified sender domain for SPF/DKIM to pass.

---

## 5. SMS driver

- Service: `App\Services\SmsService`.
- Driver picked by `SMS_DRIVER` env var: `log | msg91 | fast2sms | twilio`.
- Default in dev: `log`.
- **When SMS fires:**
  - OrderPlaced (COD + Razorpay callback path): after email.
  - OrderShipped: inside `notifyCustomerShipped()`.
  - OTP: optionally, alongside email.

### Provider-specific config
- **MSG91:** `services.msg91.auth_key`, `services.msg91.template_id` — templates must be pre-approved on the MSG91 dashboard.
- **Fast2SMS:** `services.fast2sms.api_key`.
- **Twilio:** `services.twilio.sid`, `services.twilio.token`, `services.twilio.from`.

### Failure handling
- SMS failures should not fail the order path. `SmsService::send()` wraps calls in try/catch and logs errors.

---

## 6. Notification content

### 6.1 OrderPlaced
- Subject: `"Your order {order_no} has been placed"`.
- Body includes: order number, items summary, total, shipping address, ETA.

### 6.2 OrderShipped
- Subject: `"Your order {order_no} has shipped"`.
- Body: courier name, AWB, tracking URL, ETA.

### 6.3 OrderStatusChanged
- Subject: `"Update on your order {order_no}"`.
- Body: human-readable status label (e.g. "Confirmed", "Delivered", "Cancelled").

### 6.4 SendOtpNotification
- Subject: `"Your verification code"`.
- Body: 6-digit code with expiry note.

### 6.5 ContactNotification
- Subject: `"New contact form submission from {name}"`.
- Body: name, email, phone, subject, message.
- Recipient: `settings.email` (store owner), fallback to `MAIL_FROM_ADDRESS`.

---

## 7. Queue behaviour

- All 5 classes implement `ShouldQueue`.
- Queue connection defaults to `database` (`.env: QUEUE_CONNECTION=database`).
- Redis recommended in production for lower latency.
- Failed jobs land in `failed_jobs`; retry with:
  ```bash
  php artisan queue:failed
  php artisan queue:retry {uuid}
  # or
  php artisan queue:retry all
  ```

See [19 Queue & Cron Jobs](19-queue-cron-jobs.md).

---

## 8. Template locations

Notification bodies are defined inside each `App\Notifications\*` class via `toMail()` returning a `Illuminate\Notifications\Messages\MailMessage`:

```php
return (new MailMessage)
    ->subject("Your order {$this->order->order_no} has shipped")
    ->line("Great news! Your order is on its way.")
    ->line("Carrier: {$this->shipment->courier_name}")
    ->line("Tracking: {$this->shipment->awb_code}")
    ->action('Track your order', route('client.track.show', $this->order->order_no))
    ->line('Thanks for shopping with us!');
```

If you need branded HTML, publish and customise the Laravel mail templates:
```bash
php artisan vendor:publish --tag=laravel-mail
# edit resources/views/vendor/mail/*
```

---

## 9. Notification triggers by controller/service

| Trigger                                         | File / method                                                | Notification dispatched                                |
| ----------------------------------------------- | ------------------------------------------------------------ | ------------------------------------------------------ |
| COD order placed                                | `Client\OrderController::save()`                             | `OrderPlaced` (+ SMS)                                   |
| Razorpay callback success                       | `Client\PaymentController::razorpayCallback()`               | `OrderPlaced` (guarded by `order_placed_notified_at`) + SMS |
| Razorpay webhook `payment.captured`             | `Client\PaymentController::razorpayWebhook()`                | `OrderPlaced` (guarded — no-op if callback already fired) |
| Order status update                             | `Admin\OrderController::update()`                            | `OrderStatusChanged`                                    |
| AWB assigned                                    | `App\Services\ShipmentService::assignAwb()` → `notifyCustomerShipped()` | `OrderShipped` (mail + SMS, guarded)          |
| Registration                                    | `Client\RegisterUserController::save()` → `OtpService::issue()` | `SendOtpNotification`                                |
| Forgot password                                 | `Client\ForgotPasswordController::forgotPassword()`           | `SendOtpNotification`                                  |
| Login of unverified account                     | `Client\LoginUserController::loginUser()`                     | `SendOtpNotification` (only after password verify)     |
| Contact form                                    | `Client\HomeController::saveContact()`                       | `ContactNotification`                                  |

---

## 10. Notifications the system does **not** send (gaps)

- **Admin new-order notification** — no email/SMS to ops when an order lands. Currently ops discovers new orders by refreshing the dashboard. Recommend adding an `OrderPlacedAdmin` notification bound to `settings.email`.
- **Return-requested notification** — customer doesn't get an email confirming their return submission; admin doesn't get one either.
- **Refund-issued notification** — no confirmation to customer when refund is initiated.
- **Password-changed notification** — no "your password was changed" email as a security signal.
- **New-review notification** — no admin alert; reviews sit as `is_approved=false` until manually reviewed.
- **Cart-abandonment / re-engagement** — not implemented (would need a scheduled job).
- **Low-stock alert to admin** — dashboard card only; no proactive email.
- **Failed-payment notification** — no email if a callback/webhook flips payment to `failed`.

Add these in your enhancements queue.

---

## 11. Testing notifications locally

- Set `MAIL_MAILER=log` — every notification is rendered to `storage/logs/laravel.log` (search for "Message-ID").
- Or `MAIL_MAILER=array` — capture in memory for a test.
- Or use [Mailpit](https://mailpit.axllent.org/) locally:
  ```
  MAIL_MAILER=smtp
  MAIL_HOST=127.0.0.1
  MAIL_PORT=1025
  ```

For SMS:
- Set `SMS_DRIVER=log` — every message logged.
- To force a real send in staging, set `SMS_DRIVER=twilio` with a test phone number.

---

## 12. Multi-language / templating

Not implemented. All notification content is English-only, hardcoded in the `toMail()` methods. If you add locale support:

- Add a `locale` column to `customers`.
- In each notification `via()` handler, call `$notifiable->notify($notification->locale($customer->locale))`.
- Translate strings via Laravel's `__()` helper.
