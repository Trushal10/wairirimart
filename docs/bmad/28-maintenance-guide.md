# 28 — Maintenance Guide

Runbook for keeping Vue-Ecommerce healthy in production. Written for a solo sysadmin or a small ops team.

---

## 1. Health monitoring (what to watch)

| Signal                           | Where                                        | Action if red                                     |
| -------------------------------- | -------------------------------------------- | ------------------------------------------------- |
| HTTP 5xx rate                    | Web server logs / Sentry / uptime monitor    | Check `storage/logs/laravel.log` for stack trace  |
| `/up` endpoint 200               | Uptime monitor (Pingdom / UptimeRobot)        | Restart PHP-FPM if returning 5xx                  |
| `failed_jobs` table depth        | `SELECT COUNT(*) FROM failed_jobs`           | Retry with `php artisan queue:retry all` after fixing cause |
| `jobs` table depth               | `SELECT COUNT(*) FROM jobs`                  | Check queue worker is running                     |
| Queue worker running             | `systemctl status vue-ecommerce-queue`       | Restart the service                                |
| Scheduler firing                  | `sudo tail -f /var/log/syslog | grep CRON`    | Check crontab entry                                |
| Disk usage on `storage/logs/`    | `du -sh storage/logs`                        | Rotate / archive logs                              |
| Disk usage on `public/uploads/`  | `du -sh public/uploads`                      | Offload old images to S3 or delete unused         |
| Slow queries                      | MySQL slow query log                         | Add index / optimise                               |
| DB connections                    | `SHOW PROCESSLIST`                            | Increase pool / kill idle                          |
| Razorpay callback failures        | `laravel.log` search for `razorpay`           | Verify secret matches dashboard                    |
| Shiprocket webhook failures       | `laravel.log`                                | Verify webhook URL + signature                     |

---

## 2. Recommended alerts

Set these up on your monitoring stack:

1. **`/up` down for > 2 min** — email + Slack + phone.
2. **HTTP 5xx > 1 % in a 5-min window** — Slack.
3. **`failed_jobs` count > 5** — Slack.
4. **`jobs` count > 100 for > 5 min** — Slack (worker likely stuck).
5. **DB connections > 80 % of `max_connections`** — Slack.
6. **Disk > 85 % on any partition** — Slack.
7. **Certificate expires in < 14 days** — email.
8. **Razorpay signature-mismatch spike** — email (potential attack or misconfig).
9. **Login rate-limit hits > 100 / hour** — Slack (potential brute-force).

---

## 3. Log management

### Levels
- Prod: `LOG_LEVEL=warning` — writes warning, error, critical, alert, emergency.
- Dev: `LOG_LEVEL=debug`.

### Rotation
Choose one:

**Option A — Laravel daily channel:**
```env
LOG_STACK=daily
```
Then Laravel writes `laravel-YYYY-MM-DD.log` and keeps the last 14 (default).

**Option B — Logrotate:**
`/etc/logrotate.d/vue-ecommerce`:
```
/var/www/vue-ecommerce/storage/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
    su www-data www-data
}
```

### Redaction rules
- Do NOT log request bodies verbatim if they include `password`, `otp`, `credentials`, or payment card data.
- Use `->except(['password', 'password_confirmation', 'otp'])` before logging.
- Prefer structured logging (`Log::info('order_placed', ['order_no' => $order->order_no])`).

---

## 4. Database maintenance

### Nightly backup
Simplest approach — cron + mysqldump:
```
0 2 * * * mysqldump --single-transaction --quick --routines vue_ecommerce | gzip > /var/backups/mysql/vue_ecommerce-$(date +\%F).sql.gz
0 3 * * * find /var/backups/mysql -name '*.sql.gz' -mtime +30 -delete
```

Ship backups off-server (S3, rsync, restic).

### Weekly integrity check
```bash
mysqlcheck --optimize --databases vue_ecommerce
```

### Slow query log
Enable in `my.cnf`:
```
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 1
```
Review weekly.

### Restore test
Restore last night's backup to a staging DB every quarter.

---

## 5. File backups

Back up:
- `public/uploads/` — product / slider media.
- `storage/app/public/` — anything else in the public disk.
- `.env` — encrypted, off-server.

Do NOT back up: `storage/logs/`, `storage/framework/`, `bootstrap/cache/`, `vendor/`, `node_modules/`.

Recommended tool: **restic** to an S3 bucket:
```bash
restic -r s3:s3.amazonaws.com/your-bucket/backups backup public/uploads storage/app/public .env
restic forget --keep-daily 14 --keep-weekly 8 --keep-monthly 12 --prune
```

---

## 6. Cache & config warm-up (after every deploy)

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Or, in a single command:
```bash
php artisan optimize    # runs config:cache + route:cache + view:cache
```

Don't call `optimize` in dev — hot-reload of edited files won't work with route/view cache.

---

## 7. Zero-downtime deploys

- Use symlink-swap (Deployer / Envoy) to swap the `current` symlink atomically.
- Reload PHP-FPM to pick up new opcache (`sudo systemctl reload php8.2-fpm`).
- Restart the queue worker (`sudo systemctl restart vue-ecommerce-queue`).
- Old requests finish on the previous release; new requests hit the new one.

---

## 8. Rotating secrets

### `APP_KEY`
If `APP_KEY` is compromised, you must:
1. Generate a new key: `php artisan key:generate --show` (do NOT overwrite `.env` yet).
2. Write a one-shot Artisan command that:
   - Iterates `payment_gateways` and `delivery_partners`.
   - Decrypts `credentials` with the **old** key.
   - Re-encrypts with the **new** key.
3. Update `.env` to the new key.
4. Deploy.
5. All existing sessions invalidate (users sign back in).

### Third-party secrets
Rotate through the admin UI:
1. Update credentials at the provider (Razorpay dashboard, Shiprocket account, etc.).
2. Admin panel → `/admin/settings/payment-gateways` / `/couriers` → edit credentials.
3. Test connection.
4. Rotate secrets in your `.env` if you also store them there as fallbacks.

### DB password
1. Create new user with new password.
2. Deploy new `.env`.
3. Verify.
4. Drop old user.

---

## 9. Storage growth management

Suggested cleanup jobs (add to scheduler once implemented):

- `password_resets` older than 24 h → delete.
- `password_reset_tokens` older than 60 min → delete.
- `sessions` older than 30 days → delete.
- `admin_activity_logs` older than 180 days → delete (or archive to S3).
- `failed_jobs` older than 30 days → delete after review.
- Soft-deleted `products` / `orders` older than 90 days → hard delete if business allows.

Use `php artisan model:prune` for supported models or `Schedule::call(fn() => DB::table('sessions')->where(...)->delete())`.

---

## 10. Emergency recovery

### App is down (HTTP 500 everywhere)
1. `sudo tail -200 storage/logs/laravel.log`.
2. Common causes:
   - `.env` missing / malformed → restore from backup.
   - `APP_KEY` mismatch with encrypted data → restore old key.
   - Missing composer package → `composer install --no-dev`.
   - Missing built asset → `npm ci && npm run build`.
3. `php artisan up` if in maintenance.

### Database corruption
1. `sudo systemctl stop nginx` (block traffic).
2. Restore from last backup: `zcat backup.sql.gz | mysql vue_ecommerce`.
3. Re-run any migrations that landed after the backup.
4. Communicate downtime + data-loss window to customers.

### Payment gateway broken (Razorpay dashboard says webhooks failing)
1. Check `/webhooks/razorpay` returns 200 with a good payload.
2. Verify `RAZORPAY_WEBHOOK_SECRET` matches Razorpay dashboard.
3. Re-send failed events from Razorpay dashboard once fixed (they retry automatically for 24 h).

### Courier tracking not updating
1. Check `SyncActiveShipmentsJob` runs (`laravel.log`).
2. Manually trigger: `php artisan tinker` → `dispatch(new App\Jobs\SyncActiveShipmentsJob);`.
3. Verify Shiprocket credentials / webhook secret.

### Queue worker dead
```bash
sudo systemctl status vue-ecommerce-queue
sudo journalctl -u vue-ecommerce-queue -n 100
sudo systemctl restart vue-ecommerce-queue
```

---

## 11. Regular hygiene tasks

| Cadence   | Task                                                                      |
| --------- | ------------------------------------------------------------------------- |
| Daily     | Check Sentry / error tracker; check `failed_jobs`; check dashboard KPIs   |
| Weekly    | Verify last backup restores cleanly (to staging)                          |
| Weekly    | `composer audit` — check for CVE in deps                                  |
| Weekly    | Review slow query log                                                      |
| Monthly   | Test disaster-recovery runbook (restore DB + uploads to a clean host)     |
| Monthly   | Rotate at least one non-critical secret to verify the process works       |
| Quarterly | Full security review (see [15 Security](15-security-architecture.md))      |
| Quarterly | Update PHP / MySQL patch versions                                          |
| Quarterly | `npm audit --production` and `composer outdated` for major upgrades       |
| Yearly    | Renew TLS cert (if not auto)                                              |
| Yearly    | Review roadmap; retire completed items; add new ones                       |

---

## 12. Onboarding a new admin

1. In production DB:
   ```php
   php artisan tinker
   App\Models\User::create([
     'name' => 'Full Name',
     'email' => 'ops@yourbrand.com',
     'password' => bcrypt('temp_pw_change_me'),
     'role' => 'admin',
     'is_active' => 1,
   ]);
   ```
2. Email the temp password out-of-band.
3. Ask them to log in and reset via `/admin/profile` (or `/admin/password`).
4. Log the addition in an internal changelog.

Recommended once G-6 is fixed: use the invite flow instead.

---

## 13. Off-boarding an admin

1. Set `is_active = 0` on the `users` row (customary — but note `AdminOnly` middleware doesn't check `is_active` currently; verify).
2. Or delete the row: `App\Models\User::where('email', '...')->delete()`.
3. Rotate any secrets they had access to (Razorpay, Shiprocket, DB, server SSH).

---

## 14. Support & escalation

If you inherit this project and need help:

- Codebase questions → this documentation set is the source of truth.
- Payment / gateway issues → Razorpay support (dashboard has priority ticket).
- Shipping / courier issues → Shiprocket support.
- Laravel framework questions → https://laravel.com/docs/12.x
- Inertia / Vue questions → https://inertiajs.com/ and https://vuejs.org/
- Security disclosure → add a `SECURITY.md` with your contact.

---

## 15. Documentation upkeep

Every merged PR should update relevant docs in `docs/bmad/`:
- New route → update [06 API](06-api-documentation.md).
- New model / column → update [05 Database](05-database-schema.md).
- New integration → add to [24 Third-Party Integrations](24-third-party-integrations.md).
- Feature status change → update [25 Feature Matrix](25-feature-matrix.md).
- Limitation resolved → remove from [26 Known Limitations](26-known-limitations.md).
- New scheduled job → add to [19 Queue & Cron Jobs](19-queue-cron-jobs.md).
- Roadmap item completed → mark done in [27 Roadmap](27-roadmap.md).

Documentation drift is technical debt. Fix it in the same PR as the code.
