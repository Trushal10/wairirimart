# 19 — Queue & Cron Jobs

## 1. Queue

### 1.1 Configuration
- Driver: `QUEUE_CONNECTION` env var. Defaults to `database`.
- Backing tables: `jobs`, `job_batches`, `failed_jobs`.
- Recommended production driver: `redis` for lower latency + built-in job visibility with Horizon (not installed).

### 1.2 Queue worker
Run as a supervisor / systemd service:

```bash
php artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-time=3600
```

- `--tries=3` — retry a failing job up to 3 times.
- `--timeout=90` — each job has 90 s max.
- `--sleep=3` — 3 s pause when idle.
- `--max-time=3600` — restart worker after 1 h (memory hygiene).

Systemd unit shown in [17 Deployment Guide § 3.9](17-deployment-guide.md).

### 1.3 Jobs currently dispatched
| Trigger                                                | Job / Notification                                                       |
| ------------------------------------------------------ | ------------------------------------------------------------------------ |
| Order placed (COD + Razorpay)                          | `Notification::send($customer, new OrderPlaced($order))` (queued)         |
| Order shipped (AWB assigned)                           | `Notification::send($customer, new OrderShipped(...))` (queued) + SMS     |
| Order status changed (admin)                           | `Notification::send($customer, new OrderStatusChanged(...))` (queued)     |
| Contact form submitted                                 | `Notification::send($ownerEmail, new ContactNotification(...))` (queued)  |
| OTP required (register / login / forgot)               | `Notification::send($customer, new SendOtpNotification(...))` (queued)    |
| Scheduled tracking sync                                | `SyncActiveShipmentsJob` (queued, cron-triggered every 15 min)            |

### 1.4 Failed jobs
- Persisted to `failed_jobs`.
- Retry with `php artisan queue:retry all` or `php artisan queue:retry <uuid>`.
- Clear with `php artisan queue:flush` (careful — permanent).
- Monitor `failed_jobs` table depth as a health signal.

### 1.5 Idempotency of jobs
All notification jobs are guarded at dispatch time (not job time):
- OrderPlaced — guarded by atomic `payment.meta.order_placed_notified_at`.
- OrderShipped — guarded by `shipment.meta.ship_notified_at`.
- Others (OrderStatusChanged, ContactNotification, SendOtpNotification) can fire multiple times if triggered multiple times — recipients tolerate this.

`SyncActiveShipmentsJob` is not itself idempotent — it's scheduled `withoutOverlapping(15)` (15-min lock) so concurrent runs cannot occur.

---

## 2. Scheduler

`routes/console.php` registers cron entries. The Laravel scheduler is triggered by a single system cron:

```
* * * * * cd /var/www/vue-ecommerce && php artisan schedule:run >> /dev/null 2>&1
```

### 2.1 Registered schedule
Currently ONE recurring job is registered:

| Cadence            | Handler                                              | Notes                                                                 |
| ------------------ | ---------------------------------------------------- | --------------------------------------------------------------------- |
| Every 15 minutes   | `SyncActiveShipmentsJob` (dispatched to queue)        | `withoutOverlapping(15)` — cannot double-fire. Batches 100 shipments per run. Safety net for missed webhooks. |

### 2.2 Recommended additions (roadmap)
| Cadence            | Purpose                                                                       |
| ------------------ | ----------------------------------------------------------------------------- |
| Every 5 minutes    | Prune abandoned carts older than 24 h (marketing re-engagement pipeline)      |
| Every hour         | Warn admin of low-stock variants that dropped in the last hour                |
| Every hour         | Retry `failed_jobs` up to N times before human intervention                   |
| Every night 03:00  | Delete OTP/password_reset rows > 24 h                                          |
| Every night 03:15  | Delete `sessions` rows > 30 days                                               |
| Every night 03:30  | Delete `admin_activity_logs` rows > 180 days                                   |
| Every day 09:00    | Email admin the daily orders + refunds report                                  |
| Every 15 minutes   | Retry stuck `pending` payments where `created_at < now - 15 min` (idempotent) |
| Every night 04:00  | Prune soft-deleted products/orders older than 90 days if desired               |

Add these to `routes/console.php` using `Schedule::call(...)` or `Schedule::job(...)`.

---

## 3. Console commands

### 3.1 Built-in Laravel commands used
- `php artisan migrate` / `migrate:fresh` / `migrate:rollback`
- `php artisan db:seed`
- `php artisan key:generate`
- `php artisan config:cache` / `route:cache` / `view:cache` / `event:cache`
- `php artisan queue:work` / `queue:listen` / `queue:retry` / `queue:flush` / `queue:failed`
- `php artisan schedule:run` / `schedule:work` (dev)
- `php artisan tinker`
- `php artisan storage:link`
- `php artisan optimize` (bundles config/route/view caching)
- `php artisan pail` (log tail — `laravel/pail` in dev deps)

### 3.2 Custom commands
None currently — `app/Console/Commands/` is effectively empty.

Recommended additions:
- `orders:sync-tracking` — Manual trigger for `SyncActiveShipmentsJob` (useful for ops).
- `payments:reconcile` — Fetch all Razorpay orders older than X min and reconcile local state.
- `keys:rotate` — Re-encrypt `payment_gateways.credentials` and `delivery_partners.credentials` after `APP_KEY` change.
- `stock:reset` — Bulk stock adjustment for a CSV import.
- `sitemap:generate` — If sitemap becomes too heavy for on-request generation.

---

## 4. Monitoring queue + scheduler

### 4.1 Basic health checks
- Queue depth: `SELECT COUNT(*) FROM jobs;` (should be ≈ 0 most of the time).
- Failed jobs: `SELECT COUNT(*) FROM failed_jobs;` (should be 0).
- Last scheduler run: `SELECT MAX(updated_at) FROM shipments WHERE meta->>'$.last_sync' IS NOT NULL;` (proxy).

### 4.2 Alerting suggestions
- Alert if `failed_jobs > 5`.
- Alert if `jobs > 100` for more than 5 minutes (worker stuck?).
- Alert if no `SyncActiveShipmentsJob` ran in the last 30 minutes during business hours.

### 4.3 Laravel Horizon (recommended for Redis)
Not installed. If moving to Redis queue, install `laravel/horizon` for a real-time dashboard, throughput graphs, and configurable retry policies.

---

## 5. Graceful worker restart on deploy

```bash
php artisan queue:restart   # signals workers to exit after current job
sudo systemctl restart vue-ecommerce-queue
```

Signal is picked up by `queue:work`'s polling loop; supervised worker relaunches automatically with fresh code.

---

## 6. Race conditions handled

- `SyncActiveShipmentsJob` uses `withoutOverlapping(15)` → concurrent runs blocked for 15 minutes.
- OrderPlaced / OrderShipped notifications use atomic-UPDATE guards to serialise dispatch.
- Coupon consumption uses conditional UPDATE (`WHERE used_count < usage_limit`).
- Payment status flips use `lockForUpdate()`.

---

## 7. Retention policy (recommended — not yet implemented)

| Table                     | Retention           | Delete after   |
| ------------------------- | ------------------- | -------------- |
| `password_resets`         | until used or 24 h  | 24 h           |
| `password_reset_tokens`   | 60 min              | 60 min         |
| `sessions`                | 30 days             | 30 days        |
| `admin_activity_logs`     | 180 days            | 180 days       |
| `failed_jobs`             | 30 days             | 30 days        |
| `contacts`                | never (ops decides) | manual         |
| `orders` (soft-deleted)   | 90 days after delete| 90 days        |
| `products` (soft-deleted) | 90 days after delete| 90 days        |

Add these as scheduled artisan commands (`php artisan model:prune` for supported models, otherwise custom `Schedule::call()`).

---

## 8. Cost & scaling

- Single queue worker handles ~10 notifications/second on a 2 vCPU VPS.
- If notification volume grows, add more `queue:work` processes (one per CPU core is a good starting point).
- Redis queue can handle 1000s of jobs/second.
- Horizon provides auto-scaling of worker processes based on queue depth.
