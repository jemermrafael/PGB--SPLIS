# 1. Build stage for frontend assets
FROM public.ecr.aws/docker/library/node:20-alpine AS frontend-builder
WORKDIR /app

COPY package*.json ./
RUN npm install

# Laravel 13 + Tailwind v4: Vite plugin only (no postcss/tailwind config files)
COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/

RUN npm run build

# 2. Main execution stage
FROM public.ecr.aws/docker/library/php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor curl libpng-dev libjpeg-turbo-dev freetype-dev zip libzip-dev git bash mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd zip bcmath opcache

RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

COPY .docker/nginx.conf /etc/nginx/nginx.conf
COPY .docker/php-uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini
COPY .docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf
COPY .docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

COPY --from=frontend-builder --chown=www-data:www-data /app/public/build ./public/build

COPY --from=public.ecr.aws/docker/library/composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Persist uploads (PDFs, etc.) across redeploys — mount in Coolify/Docker Compose
VOLUME ["/var/www/html/storage/app"]

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisor.conf"]
