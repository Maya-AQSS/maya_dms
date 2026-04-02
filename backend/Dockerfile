FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash git curl libpng-dev libxml2-dev zip unzip \
    postgresql-dev oniguruma-dev linux-headers

RUN docker-php-ext-install \
    pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd sockets

# Redis extension via PECL
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --optimize-autoloader --no-dev --no-interaction 2>/dev/null || true
RUN chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000 8080

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
