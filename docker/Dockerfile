FROM composer:lts as deps
WORKDIR /app
RUN --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-interaction

FROM php:8.2-apache as final
LABEL authors="Eugene"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=deps app/vendor/ /var/www/html/vendor
COPY ./main.php /var/www/html/main.php
COPY ./db.txt /var/www/html/db.txt
COPY ./p_db.txt /var/www/html/p_db.txt
USER www-data