# syntax=docker/dockerfile:1.7

FROM node:24-alpine AS frontend-build
WORKDIR /src
RUN corepack enable
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY apps/web/package.json apps/web/package.json
COPY upstream/chatwoot/package.json upstream/chatwoot/package.json
RUN corepack install && pnpm install --frozen-lockfile --ignore-scripts
COPY apps/web apps/web
COPY upstream/chatwoot upstream/chatwoot
COPY scripts/validate-chatwoot-build.sh scripts/validate-chatwoot-build.sh
RUN pnpm web:chatwoot

FROM composer:2 AS api-build
WORKDIR /app
COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts
COPY apps/api ./
RUN composer dump-autoload --classmap-authoritative --no-dev --no-interaction

FROM php:8.4-fpm-alpine AS api-runtime
WORKDIR /var/www/html
RUN apk add --no-cache icu-libs libcurl libpq libxml2 libzip oniguruma \
    && apk add --no-cache --virtual .build-dependencies \
        $PHPIZE_DEPS curl-dev icu-dev libxml2-dev libzip-dev oniguruma-dev postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" curl intl mbstring opcache pcntl pdo_pgsql simplexml zip \
    && apk del .build-dependencies
COPY infrastructure/production/php.ini /usr/local/etc/php/conf.d/twoteam.ini
COPY --from=api-build /app ./
COPY --from=frontend-build /src/apps/web/dist/chatwoot public/build/chatwoot
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]

FROM nginx:1.29-alpine AS web-runtime
COPY infrastructure/production/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend-build /src/apps/web/dist/chatwoot /var/www/html/public/build/chatwoot
EXPOSE 8080
HEALTHCHECK --interval=10s --timeout=3s --retries=6 \
  CMD wget --quiet --tries=1 --spider http://127.0.0.1:8080/up || exit 1
