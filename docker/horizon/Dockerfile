# Runs `php artisan horizon` against the app code mounted in from the host
# (see the `horizon` service in compose.yaml) — the app itself still runs
# natively via Herd; this container only exists to keep the queue worker
# running automatically instead of someone starting `php artisan horizon`
# by hand in a terminal.
FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        postgresql-libs \
        libzip \
        icu-libs \
        libpng \
        libjpeg-turbo \
        freetype \
        libwebp \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd bcmath intl zip pcntl posix exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

CMD ["php", "artisan", "horizon"]
