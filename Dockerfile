# PHP-Version: immer der neueste stabile Branch (Stand 2026-11-04: 8.5 → 8.5.12)
ARG PHP_VERSION=8.5

# --- Stufe 1: Frontend-Assets (Vite) ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json .npmrc ./
RUN npm ci
COPY . .
RUN npm run build

# --- Stufe 2: Composer (gleiche PHP-Version wie Runtime) ---
FROM serversideup/php:${PHP_VERSION}-cli-alpine AS vendor
# Das Image läuft als www-data, /app gehört aber root.
USER root
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Stufe 3: Runtime ---
FROM serversideup/php:${PHP_VERSION}-fpm-nginx-alpine
ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    PHP_OPCACHE_ENABLE=1
USER root
RUN install-php-extensions intl gd zip bcmath pdo_pgsql pdo_mysql
USER www-data
WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=vendor /app /var/www/html
COPY --chown=www-data:www-data --from=assets /app/public/build /var/www/html/public/build
