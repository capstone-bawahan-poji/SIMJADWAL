FROM php:8.5-fpm-alpine AS builder

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    linux-headers \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    libxml2-dev \
    zlib-dev \
    curl-dev \
    postgresql-dev

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

# gd + zip: phpoffice/phpspreadsheet (xlsx import/export). pcntl: reverb:start.
RUN docker-php-ext-install -j$(nproc) \
    bcmath \
    exif \
    gd \
    intl \
    pcntl \
    pdo \
    pdo_pgsql \
    pgsql \
    zip

WORKDIR /app

COPY . .

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    /var/log/supervisor

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# Vite needs vendor/tightenco/ziggy (resources/js/app.ts), so this stage builds on top of builder.
FROM builder AS frontend-build

# VITE_* are baked into the bundle. .env.production is not in the build context (.dockerignore),
# so docker-compose.production.yml passes them as build args.
ARG VITE_APP_NAME=SIMJADWAL
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https

ENV VITE_APP_NAME=${VITE_APP_NAME} \
    VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

RUN apk add --no-cache nodejs npm \
    && npm ci \
    && npm run build

FROM php:8.5-fpm-alpine AS app

LABEL maintainer="production"

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    curl \
    supervisor \
    icu \
    libpng \
    libjpeg-turbo \
    freetype \
    libwebp \
    libzip \
    oniguruma \
    libpq \
    postgresql-client \
    nginx

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/production/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/production/php.ini /usr/local/etc/php/conf.d/custom.ini

COPY . .

COPY --from=builder /app/vendor ./vendor
COPY --from=frontend-build /app/public/build ./public/build

# Storage permissions
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    /var/log/supervisor

RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

RUN chmod -R 775 \
    storage \
    bootstrap/cache

COPY docker/production/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint
COPY docker/production/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 80: nginx (web + API). 8080: Reverb websocket. Traefik routes both.
EXPOSE 80 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
