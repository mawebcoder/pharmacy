# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4

FROM composer:2.8 AS composer

# ---------------------------------------------------------------------------
# base: PHP runtime + extensions shared by the "app" and "supervisor" targets
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base

ARG UID=1000
ARG GID=1000

ENV COMPOSER_HOME=/tmp/composer \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=1

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache \
        bash \
        fcgi \
        git \
        mysql-client \
        shadow \
        tzdata \
        unzip \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        zip \
    # Run as the host user so files created in the mounted project stay editable.
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data \
    && apk del shadow \
    && mkdir -p /var/www/html \
    && chown -R www-data:www-data /var/www/html

COPY php/conf.d/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# app: php-fpm serving the Laravel application (+ Node for Vite / TALL assets)
# ---------------------------------------------------------------------------
FROM base AS app

RUN apk add --no-cache nodejs npm

COPY php/php-fpm.d/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf

USER www-data

EXPOSE 9000 5173

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

CMD ["php-fpm", "--nodaemonize"]

# ---------------------------------------------------------------------------
# supervisor: Horizon (queues) + scheduler
# ---------------------------------------------------------------------------
FROM base AS supervisor

RUN apk add --no-cache supervisor

COPY supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY supervisor/conf.d/ /etc/supervisor/conf.d/

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD supervisorctl -c /etc/supervisor/supervisord.conf status | grep -qv RUNNING && exit 1 || exit 0

CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
