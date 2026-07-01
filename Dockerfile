# 1. Build stage for frontend assets
FROM node:20-alpine AS frontend-builder
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/

RUN npm run build

# 2. Composer dependencies (cached layer; PHP 8.4 matches runtime)
FROM composer:2 AS vendor
WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# 3. Main execution stage
FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor curl libpng-dev libjpeg-turbo-dev freetype-dev zip libzip-dev git unzip bash mariadb-client icu-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql gd zip bcmath opcache intl pcntl

RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

COPY .docker/nginx.conf /etc/nginx/nginx.conf
COPY .docker/php-uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini
COPY .docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf
COPY .docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY composer.json composer.lock ./

COPY --chown=www-data:www-data . .
COPY --from=frontend-builder --chown=www-data:www-data /app/public/build ./public/build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache vendor

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Persist uploads (PDFs, etc.) across redeploys — mount in Coolify/Docker Compose
VOLUME ["/var/www/html/storage/app"]

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisor.conf"]
