#!/bin/bash
set -e

# Clear stale bootstrap cache (prevents "Class not found" from old package discovery)
echo "[entrypoint] Clearing bootstrap cache..."
rm -f /var/www/html/bootstrap/cache/packages.php
rm -f /var/www/html/bootstrap/cache/services.php

# Install dependencies if vendor is missing (handles fresh anonymous volumes)
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "[entrypoint] vendor/autoload.php not found. Running composer install..."
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
