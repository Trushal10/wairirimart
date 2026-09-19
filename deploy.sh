#!/usr/bin/env bash
#
# Production deploy / post-pull optimisation.
#
# Why this exists: a request that does almost nothing (a 404) was measured at
# ~400ms of server time on production before any page logic ran, while the
# homepage's own work — every query and the full Blade render — accounts for
# well under 50ms. That gap is framework bootstrap being redone on every
# request: config files re-merged, routes re-compiled, Blade views re-compiled,
# and the classmap walked instead of hashed. The commands below are what turn
# that off. Skipping them costs more page-speed than any amount of front-end
# tuning can win back.
#
# Usage on the server, from the project root:
#     bash deploy.sh
#
set -euo pipefail

echo "==> Dependencies (no dev packages, authoritative classmap)"
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --prefer-dist

echo "==> Database migrations"
php artisan migrate --force

echo "==> Rebuilding caches"
# optimize:clear first so a stale cache from the previous release can never
# survive into this one.
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Public storage symlink"
php artisan storage:link || true

echo "==> Responsive image derivatives"
# Generates the WebP sizes that the srcset helper offers, including the 720w
# step that mobile LCP picks. Safe to re-run: existing, current files are
# skipped.
php artisan images:optimize

echo
echo "Deploy complete."
echo
echo "Two things this script cannot do for you — both are server config, and"
echo "together they are worth more than everything above:"
echo
echo "  1. PHP OPcache must be enabled, with opcache.validate_timestamps=0 in"
echo "     production. Without it PHP recompiles every source file on every"
echo "     request, which is the bulk of that ~400ms bootstrap."
echo "       opcache.enable=1"
echo "       opcache.memory_consumption=256"
echo "       opcache.max_accelerated_files=20000"
echo "       opcache.validate_timestamps=0    # then reload PHP on each deploy"
echo
echo "  2. HTTP/2. The origin currently answers HTTP/1.1, which caps the"
echo "     browser at ~6 parallel connections for the whole page and is why"
echo "     the hero image spent so long queued behind CSS and JS. This needs a"
echo "     vhost/CDN change ('Protocols h2 http/1.1'), not .htaccess."
