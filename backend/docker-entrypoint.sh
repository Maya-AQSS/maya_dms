#!/bin/bash
set -e

# Clear stale bootstrap cache (prevents "Class not found" from old package discovery)
echo "[entrypoint] Clearing bootstrap cache..."
rm -f /var/www/html/bootstrap/cache/packages.php
rm -f /var/www/html/bootstrap/cache/services.php

# Install dependencies if vendor is missing OR if path packages are not linked
# (handles fresh anonymous volumes AND the case where autoload.php exists but
# path packages like maya/shared-auth-laravel were dropped from the volume)
if [ ! -f /var/www/html/vendor/autoload.php ] || [ ! -d /var/www/html/vendor/maya/shared-auth-laravel ]; then
    echo "[entrypoint] vendor incomplete. Running composer install..."
    composer install --optimize-autoloader --no-interaction --ignore-platform-reqs
fi

# Ensure storage directories exist (may be missing on fresh clone)
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chmod -R 775 storage
chown -R www-data:www-data storage 2>/dev/null || true

# Regenerate package discovery cache matching installed packages
echo "[entrypoint] Running package:discover..."
php artisan package:discover --ansi 2>/dev/null || true

exec "$@"
