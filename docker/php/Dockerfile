# syntax=docker/dockerfile:1
# Eventor backend image: FrankenPHP + PHP 8.4. Targets: dev (code is bind-mounted), prod (code baked in).
FROM dunglas/frankenphp:1-php8.4 AS base

WORKDIR /app

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip postgresql-client \
 && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions @composer pdo_pgsql intl sodium opcache zip apcu pcntl gd

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    SERVER_NAME=:80

COPY docker/php/Caddyfile /etc/caddy/Caddyfile
COPY docker/php/Caddyfile.worker /etc/caddy/Caddyfile.worker

# ---------------------------------------------------------------- dev
FROM base AS dev
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/dev.ini "$PHP_INI_DIR/conf.d/zz-eventor.ini"

# ---------------------------------------------------------------- prod
FROM base AS prod
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/prod.ini "$PHP_INI_DIR/conf.d/zz-eventor.ini"
ENV APP_ENV=prod APP_DEBUG=0
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress
COPY backend/ ./
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && APP_SECRET=build php bin/console cache:warmup --env=prod \
 && chown -R www-data:www-data var 2>/dev/null || true
CMD ["--config", "/etc/caddy/Caddyfile.worker", "--adapter", "caddyfile"]
