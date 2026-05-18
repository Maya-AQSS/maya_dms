FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash git curl libpng-dev libxml2-dev zip unzip \
    postgresql-dev oniguruma-dev linux-headers

RUN docker-php-ext-install \
    pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd sockets

# Redis + pcov via PECL. pcov se usa para coverage en `php artisan test --coverage`.
# Es mucho más rápido que xdebug en modo coverage y no afecta a runs normales.
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000 8080

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
