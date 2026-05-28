#!/bin/bash
set -e

# Clear stale bootstrap cache (prevents "Class not found" from old package discovery)
echo "[entrypoint] Clearing bootstrap cache..."
rm -f /var/www/html/bootstrap/cache/packages.php
rm -f /var/www/html/bootstrap/cache/services.php
# config.php cacheado congela env() — eliminarlo permite que tests/bootstrap.php
# imponga sqlite ANTES de Laravel cargar config. Sin esto, pest --coverage ejecuta
# contra la BD pgsql cacheada.
rm -f /var/www/html/bootstrap/cache/config.php

# Install dependencies if vendor is missing OR if path packages are not linked
# (handles fresh anonymous volumes AND the case where autoload.php exists but
# path packages like maya/shared-auth-laravel were dropped from the volume)
if [ ! -f /var/www/html/vendor/autoload.php ] || [ ! -d /var/www/html/vendor/maya/shared-auth-laravel ]; then
    # Sync only maya/* path packages in lock (handles stale lock when new shared package is added)
    composer update "maya/*" --no-install --no-interaction --ignore-platform-reqs --no-scripts 2>/dev/null || true
    echo "[entrypoint] vendor incomplete. Running composer install..." --no-scripts
    composer install --optimize-autoloader --no-interaction --ignore-platform-reqs --no-scripts
fi

# Ensure storage directories exist (may be missing on fresh clone)
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chmod -R 775 storage
chown -R www-data:www-data storage 2>/dev/null || true

# Regenerate package discovery cache matching installed packages
echo "[entrypoint] Running package:discover..."

# Fix laravel-queue-rabbitmq Consumer::$currentJob visibility (Laravel 13 compat)
sed -i 's/protected \$currentJob;/public \$currentJob;/' \
  /var/www/html/vendor/vladimir-yuldashev/laravel-queue-rabbitmq/src/Consumer.php 2>/dev/null || true

php artisan package:discover --ansi 2>/dev/null || true

# Ensure public storage symlink exists for media file serving
php artisan storage:link --force 2>/dev/null || true

exec "$@"
