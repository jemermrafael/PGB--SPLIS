# Coolify: Build Pack = Dockerfile (required for MySQL 8.4; Nixpacks PHP cannot use caching_sha2_password).
# Mount storage at: /var/www/html/storage

FROM node:20-alpine AS frontend-builder
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        bash \
        mariadb-client \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd zip bcmath opcache intl pcntl \
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

COPY .docker/nginx.conf /etc/nginx/nginx.conf
COPY .docker/php-uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini
COPY .docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf
COPY .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY --from=frontend-builder /app/public/build ./public/build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache vendor

VOLUME ["/var/www/html/storage"]

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisor.conf"]
