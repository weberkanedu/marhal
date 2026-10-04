# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# base: PHP 8.4 + Nginx (serversideup/php) + Node (Vite/Wayfinder build için)
# ---------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx AS base

USER root
RUN install-php-extensions intl bcmath gd
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
USER www-data

# ---------------------------------------------------------------------------
# dev: kaynak kod docker-compose ile bağlanır (bkz. docker-compose.yml)
# ---------------------------------------------------------------------------
FROM base AS dev

# vendor/node_modules Docker volume'larında tutulur; ilk oluşturulduklarında
# www-data'ya ait olmaları için klasörleri imajda hazırlıyoruz.
USER root
RUN mkdir -p /var/www/html/vendor /var/www/html/node_modules \
    && chown www-data:www-data /var/www/html/vendor /var/www/html/node_modules
USER www-data

# ---------------------------------------------------------------------------
# build: bağımlılıklar + frontend derlemesi
# ---------------------------------------------------------------------------
FROM base AS build

WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && npm run build \
    && rm -rf node_modules

# ---------------------------------------------------------------------------
# production: Dokploy bu hedefi kullanır
# ---------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx AS production

USER root
RUN install-php-extensions intl bcmath gd
USER www-data

ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

# Staging'de (APP_ENV=staging) açılışta yeni demo veri paketlerini yükler; migration'lardan (50-…) sonra çalışır.
COPY --chmod=755 docker/entrypoint.d/60-demo-data.sh /etc/entrypoint.d/60-demo-data.sh
