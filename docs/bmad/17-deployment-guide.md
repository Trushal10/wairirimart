# 17 — Deployment Guide

## 1. Deployment targets supported

| Target                                              | Effort | Notes                                                         |
| --------------------------------------------------- | ------ | ------------------------------------------------------------- |
| Bare VPS (Ubuntu 22.04+) with LEMP                  | Low    | Recommended for single-tenant                                 |
| Laravel Forge                                       | Low    | 1-click Nginx + PHP-FPM + queue + scheduler wiring            |
| Laravel Vapor (AWS Lambda)                          | Medium | Requires Vapor CLI + moving file storage to S3                |
| Docker Compose (custom)                             | Medium | No docker-compose.yml is committed — write your own            |
| Shared hosting (cPanel / Plesk)                     | Medium | Works, but queue worker / scheduler need cron hacks           |

No CI/CD pipeline (`.github/workflows/*.yml` or `.gitlab-ci.yml`) is committed. Adding one is a [27 Roadmap](27-roadmap.md) item.

---

## 2. System requirements

| Component     | Minimum                             |
| ------------- | ----------------------------------- |
| PHP           | 8.2 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `xml`, `bcmath`, `intl`, `gd` or `imagick`, `zip`, `redis` (if using Redis) |
| Composer      | 2.6+                                |
| Node.js       | 18+ (for `npm run build`)           |
| MySQL         | 8.0+ (or MariaDB 10.6+)             |
| Web server    | Nginx 1.20+ or Apache 2.4+ (mod_rewrite) |
| SSL cert      | Any (LetsEncrypt free)              |
| RAM           | 2 GB min; 4 GB recommended          |
| Disk          | 10 GB min; 30 GB recommended        |
| Redis (opt.)  | 6.0+                                |

---

## 3. First-time deployment (bare VPS)

### 3.1 Prepare the server
```bash
# Ubuntu 22.04+
sudo apt update && sudo apt install -y nginx mysql-server php8.2-fpm \
  php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip \
  php8.2-bcmath php8.2-intl php8.2-gd php8.2-redis composer nodejs npm certbot python3-certbot-nginx
```

### 3.2 Clone the code
```bash
sudo mkdir -p /var/www/vue-ecommerce
sudo chown -R $USER:www-data /var/www/vue-ecommerce
cd /var/www/vue-ecommerce
git clone <repo-url> .
```

### 3.3 Install deps + build
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build   # outputs to public/build/
```

### 3.4 Configure environment
```bash
cp .env.example .env
php artisan key:generate
# Edit .env — set APP_URL, DB_*, MAIL_*, RAZORPAY_*, SHIPROCKET_*, etc.
# See [18 Environment Configuration](18-environment-config.md) for the full list.
```

### 3.5 Set up MySQL
```bash
sudo mysql -e "CREATE DATABASE vue_ecommerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'vue_ecommerce'@'localhost' IDENTIFIED BY 'STRONG_PW_HERE';"
sudo mysql -e "GRANT ALL ON vue_ecommerce.* TO 'vue_ecommerce'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

php artisan migrate --force
# Do NOT run: php artisan db:seed  (seeds a well-known admin/password — production risk)
```

Create the first admin manually:
```bash
php artisan tinker --execute="
  App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@yourbrand.com',
    'password' => bcrypt('CHANGE_ME_STRONG_PW'),
    'role' => 'admin',
    'is_active' => 1,
  ]);
"
```

### 3.6 Set permissions
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

### 3.7 Cache config for prod
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 3.8 Nginx site
`/etc/nginx/sites-available/vue-ecommerce`:

```nginx
server {
    listen 80;
    server_name yourbrand.com www.yourbrand.com;

    root /var/www/vue-ecommerce/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    charset utf-8;
    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Never execute PHP under /uploads
    location ~ ^/uploads/.*\.php$ {
        return 404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable + TLS:
```bash
sudo ln -s /etc/nginx/sites-available/vue-ecommerce /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d yourbrand.com -d www.yourbrand.com
```

### 3.9 Queue worker (systemd)
`/etc/systemd/system/vue-ecommerce-queue.service`:
```ini
[Unit]
Description=Vue-Ecommerce queue worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/vue-ecommerce
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now vue-ecommerce-queue
```

### 3.10 Scheduler (cron)
```bash
sudo crontab -u www-data -e
# Add:
* * * * * cd /var/www/vue-ecommerce && php artisan schedule:run >> /dev/null 2>&1
```

### 3.11 Symlink storage
```bash
php artisan storage:link
```

### 3.12 Post-deploy smoke test
1. `GET /` — homepage loads.
2. `GET /admin/login` — admin login page loads.
3. `GET /up` — Laravel health check returns 200.
4. Sign in as admin → `GET /admin/dashboard`.
5. `php artisan queue:work --once` — one iteration succeeds.
6. `POST /webhooks/razorpay` with a good signature — returns 200.

---

## 4. Subsequent deploys

Use a deployment script or CI. Minimal steps:

```bash
cd /var/www/vue-ecommerce

php artisan down --render="errors::503"     # maintenance mode

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo systemctl restart vue-ecommerce-queue   # pick up new job code

php artisan up
```

### Zero-downtime pattern (deployer/atomic swap)
1. Clone new release to `/var/www/vue-ecommerce/releases/{timestamp}`.
2. Symlink `storage/` and `.env` from a shared dir.
3. Composer install, npm build, migrate.
4. Swap symlink `current` → new release.
5. Reload PHP-FPM and restart queue worker.

Adopt [Deployer.php](https://deployer.org/) or [Envoy](https://laravel.com/docs/12.x/envoy) for this pattern.

---

## 5. Post-deploy checklist

- [ ] `.env` matches production (APP_ENV=production, APP_DEBUG=false, HTTPS URLs).
- [ ] `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`.
- [ ] `MAIL_MAILER` points at a real transport (not `log`).
- [ ] Razorpay + Shiprocket credentials are `mode=live` in DB.
- [ ] Webhook URLs updated in Razorpay + Shiprocket dashboards.
- [ ] Google OAuth redirect URI updated to production URL.
- [ ] `php artisan storage:link` executed.
- [ ] Nginx serves 404 for `/.env`, `/composer.json`, and `/uploads/*.php`.
- [ ] TLS certificate valid; HSTS header present in response.
- [ ] Queue worker running (`systemctl status vue-ecommerce-queue`).
- [ ] Cron `schedule:run` firing every minute (`sudo tail -f /var/log/syslog | grep CRON`).
- [ ] Backup job configured (see [28 Maintenance](28-maintenance-guide.md)).
- [ ] Log rotation configured (`/etc/logrotate.d/laravel`).

---

## 6. Rollback

If a deploy breaks:

```bash
cd /var/www/vue-ecommerce
php artisan down
git checkout <previous-commit>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate:rollback --force   # only if new migration broke
php artisan config:cache
sudo systemctl restart vue-ecommerce-queue
php artisan up
```

For DB schema changes, prefer forward-compatible migrations (add columns first, backfill, drop later) so rollbacks don't require `migrate:rollback`.

---

## 7. Local development

### Quick start
```bash
git clone <repo-url>
cd Vue-Ecommerce
cp .env.example .env
# Edit .env: set DB_DATABASE, DB_USERNAME, DB_PASSWORD; MAIL_MAILER=log; QUEUE_CONNECTION=sync
composer install
npm ci
php artisan key:generate
php artisan migrate --seed        # seeds a dev admin: admin@admin.com / Pass@123
php artisan storage:link
composer dev
```

`composer dev` runs (from `composer.json`):
- `php artisan serve`
- `php artisan queue:listen --tries=1`
- `npm run dev` (Vite HMR)

App available at http://127.0.0.1:8000.

### Storefront + admin
- Storefront: http://127.0.0.1:8000/
- Admin: http://127.0.0.1:8000/admin/login — `admin@admin.com` / `Pass@123`.

⚠️ Never deploy the seed's default admin credentials.

---

## 8. Docker (recommended community pattern — not committed)

A minimal `docker-compose.yml` you can drop in:

```yaml
services:
  app:
    build: .
    volumes: ["./:/var/www/html"]
    environment:
      DB_HOST: mysql
      REDIS_HOST: redis
    depends_on: [mysql, redis]
    ports: ["8000:80"]

  mysql:
    image: mysql:8
    environment:
      MYSQL_DATABASE: vue_ecommerce
      MYSQL_USER: vue_ecommerce
      MYSQL_PASSWORD: password
      MYSQL_ROOT_PASSWORD: root
    volumes: ["mysql-data:/var/lib/mysql"]

  redis:
    image: redis:7-alpine

  queue:
    build: .
    command: php artisan queue:work
    volumes: ["./:/var/www/html"]
    depends_on: [mysql, redis]

volumes:
  mysql-data:
```

Also consider `laravel/sail` (already in `require-dev`) for a batteries-included local dev environment (`vendor/bin/sail up`).

---

## 9. Environment differences

| Config                | Local dev             | Staging               | Production             |
| --------------------- | --------------------- | --------------------- | ---------------------- |
| APP_ENV               | local                 | staging               | production             |
| APP_DEBUG             | true                  | false                 | false                  |
| LOG_LEVEL             | debug                 | info                  | warning                |
| MAIL_MAILER           | log                   | smtp (sandbox)        | ses / postmark          |
| SMS_DRIVER            | log                   | log or twilio-test    | msg91 / twilio         |
| Razorpay              | test keys             | test keys             | live keys              |
| Shiprocket            | test account          | test account          | live account           |
| QUEUE_CONNECTION      | sync                  | database              | redis                  |
| SESSION_ENCRYPT       | false                 | true                  | true                   |
| SESSION_SECURE_COOKIE | false                 | true (HTTPS)          | true                   |

---

## 10. Health check

Laravel 12's built-in `/up` endpoint returns 200 if the app boots. Point uptime monitors here.

For deep health, extend `/up` with:
- DB connectivity (`DB::select('SELECT 1')`).
- Cache read/write.
- Queue depth (`Queue::size()`).
