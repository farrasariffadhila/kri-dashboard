# Single image: nginx serves the Vue build and forwards /api and /sanctum to PHP-FPM (Laravel).
# Same origin for SPA and API, so Sanctum's session cookie works without CORS.

# --- 1. Frontend build ---
FROM node:22-alpine AS frontend
WORKDIR /build
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# --- 2. Runtime ---
FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx \
    && docker-php-ext-install pdo_mysql opcache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-kri.ini"
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-kri.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /app/backend

# Dependencies first so code changes don't invalidate this layer.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY backend/ ./
COPY data-generator/output/ /app/data-generator/output/
COPY --from=frontend /build/dist/ /app/frontend/
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover \
    && rm /usr/bin/composer \
    && chmod +x /usr/local/bin/entrypoint

# Demo defaults: SQLite inside the container, rebuilt and re-seeded on every start.
ENV APP_NAME="KRI Dashboard" \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=id \
    APP_TIMEZONE=Asia/Jakarta \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/backend/database/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    KRI_REPORTING_MONTH=2026-09 \
    KRI_DEMO_MODE=true \
    KRI_RESET_ON_BOOT=true \
    PORT=8080

EXPOSE 8080
ENTRYPOINT ["entrypoint"]
