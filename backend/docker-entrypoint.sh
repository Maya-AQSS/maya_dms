#!/bin/bash
set -e

# Install dependencies if vendor is missing (handles fresh anonymous volumes)
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "[entrypoint] vendor/autoload.php not found. Running composer install..."
    composer install --optimize-autoloader --no-interaction
fi

exec "$@"
