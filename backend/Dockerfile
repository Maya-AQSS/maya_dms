FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash git curl libpng-dev libxml2-dev zip unzip \
    postgresql-dev oniguruma-dev linux-headers

RUN docker-php-ext-install \
    pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd sockets

# ─── WeasyPrint (Python) — PDF/UA tagged accessible PDF generation ─
# Requisito legal de accesibilidad (UNE-EN 301549 / WCAG / PDF/UA).
# Dependencias nativas: Pango (text shaping, ft2 integrado), HarfBuzz, Cairo,
# fontconfig, gdk-pixbuf (imágenes), libffi.
RUN apk add --no-cache \
    python3 py3-pip \
    pango harfbuzz \
    cairo gdk-pixbuf \
    fontconfig \
    libffi shared-mime-info \
    ttf-dejavu ttf-liberation \
    font-roboto font-roboto-flex font-roboto-mono \
    font-opensans \
    font-noto font-noto-extra \
    font-inconsolata \
    font-droid font-mononoki \
 && fc-cache -f \
 && pip3 install --no-cache-dir --break-system-packages 'weasyprint~=68.0' \
 && weasyprint --version

# ─── Fuentes Google adicionales (Inter / Lora / Merriweather) ────────
# Alpine apk no las empaqueta; descargamos las variable fonts oficiales
# del repo Google Fonts (OFL) — incluyen todos los pesos en un único
# archivo. Las añadimos al catálogo del editor via `config/theme_fonts.php`.
RUN mkdir -p /usr/share/fonts/google \
 && cd /usr/share/fonts/google \
 && wget -q -O 'Inter.ttf'        'https://github.com/google/fonts/raw/main/ofl/inter/Inter%5Bopsz%2Cwght%5D.ttf' \
 && wget -q -O 'Inter-Italic.ttf' 'https://github.com/google/fonts/raw/main/ofl/inter/Inter-Italic%5Bopsz%2Cwght%5D.ttf' \
 && wget -q -O 'Lora.ttf'         'https://github.com/google/fonts/raw/main/ofl/lora/Lora%5Bwght%5D.ttf' \
 && wget -q -O 'Lora-Italic.ttf'  'https://github.com/google/fonts/raw/main/ofl/lora/Lora-Italic%5Bwght%5D.ttf' \
 && wget -q -O 'Merriweather.ttf' 'https://github.com/google/fonts/raw/main/ofl/merriweather/Merriweather%5Bopsz%2Cwdth%2Cwght%5D.ttf' \
 && wget -q -O 'Merriweather-Italic.ttf' 'https://github.com/google/fonts/raw/main/ofl/merriweather/Merriweather-Italic%5Bopsz%2Cwdth%2Cwght%5D.ttf' \
 && for f in *.ttf; do test -s "$f" || (echo "Missing $f"; exit 1); done \
 && fc-cache -f \
 && fc-list | grep -iE 'inter|lora|merri' | head -10


# Redis + pcov via PECL. pcov se usa para coverage en `php artisan test --coverage`.
# Es mucho más rápido que xdebug en modo coverage y no afecta a runs normales.
# Redis + pcov: descargados desde GitHub directamente.
# pecl.php.net tiene caídas frecuentes (HTTP 504 con redirect roto en 2026-05).
# Las versiones se fijan aquí para builds reproducibles.
ENV PHPREDIS_VERSION=6.2.0
ENV PCOV_VERSION=1.0.12
RUN apk add --no-cache $PHPIZE_DEPS \
    && curl -fsSL "https://github.com/phpredis/phpredis/archive/refs/tags/${PHPREDIS_VERSION}.tar.gz" | tar -xz -C /tmp \
    && cd /tmp/phpredis-${PHPREDIS_VERSION} \
    && phpize && ./configure --enable-redis && make -j$(nproc) && make install \
    && curl -fsSL "https://github.com/krakjoe/pcov/archive/refs/tags/v${PCOV_VERSION}.tar.gz" | tar -xz -C /tmp \
    && cd /tmp/pcov-${PCOV_VERSION} \
    && phpize && ./configure --enable-pcov && make -j$(nproc) && make install \
    && docker-php-ext-enable redis pcov \
    && rm -rf /tmp/phpredis-${PHPREDIS_VERSION} /tmp/pcov-${PCOV_VERSION} \
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
