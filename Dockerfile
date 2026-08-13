# syntax=docker/dockerfile:1

#################
# Node build stage
#################
FROM node:22-alpine AS assets

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

#################
# PHP-FPM runtime stage
#################
FROM php:8.4-fpm-alpine3.24 AS app

RUN apk add --no-cache \
        libpq-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        zip \
        unzip \
        curl \
        nginx \
        supervisor \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        mbstring \
        zip \
        gd \
    && docker-php-ext-enable opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

# Copy application code
COPY . .

# Copy built assets from the assets stage
COPY --from=assets /build/public/build /var/www/html/public/build

# Copy Docker configuration
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
COPY docker/nginx/conf.d/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php/conf.d/zz-app.ini /usr/local/etc/php/conf.d/zz-app.ini

# Redis extension (pecl) for the site-wide caching
RUN set -eux \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

RUN set -eux \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

LABEL org.opencontainers.image.source="https://github.com/mostafiz-8bits/umrah-app" \
      org.opencontainers.image.description="Umrah App — Laravel 12 + PostgreSQL application"

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
