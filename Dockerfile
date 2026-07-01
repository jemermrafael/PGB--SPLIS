# 1. Build frontend assets
FROM public.ecr.aws/docker/library/node:20-alpine AS frontend-builder
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm install

COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/


RUN npm run build

# 2. Runtime (matches working Coolify pattern: single PHP stage + composer install)
FROM public.ecr.aws/docker/library/php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor curl libpng-dev libjpeg-turbo-dev freetype-dev zip libzip-dev git unzip bash mariadb-client icu-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd zip bcmath opcache intl pcntl

RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

COPY .docker/nginx.conf /etc/nginx/nginx.conf
COPY .docker/php-uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=frontend-builder --chown=www-data:www-data /app/public/build ./public/build

COPY --from=public.ecr.aws/docker/library/composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache vendor

# Entrypoint setup
RUN echo '#!/bin/sh' > /usr/local/bin/docker-entrypoint.sh \
    && echo 'ENV_FILE=/usr/local/etc/php-fpm.d/zz-docker-env.conf' >> /usr/local/bin/docker-entrypoint.sh \
    && echo 'echo "; Docker environment variables" > $ENV_FILE' >> /usr/local/bin/docker-entrypoint.sh \
    && echo 'echo "[www]" >> $ENV_FILE' >> /usr/local/bin/docker-entrypoint.sh \
    && echo 'printenv | while IFS="=" read -r key value; do' >> /usr/local/bin/docker-entrypoint.sh \
    && echo '  case "$key" in' >> /usr/local/bin/docker-entrypoint.sh \
    && echo '    *" "*|*"("*|*")"*|"") continue ;;' >> /usr/local/bin/docker-entrypoint.sh \
    && echo '  esac' >> /usr/local/bin/docker-entrypoint.sh \
    && echo '  printf "env[%s] = %s\n" "$key" "\"$value\"" >> $ENV_FILE' >> /usr/local/bin/docker-entrypoint.sh \
    && echo 'done' >> /usr/local/bin/docker-entrypoint.sh \
    && echo 'exec "$@"' >> /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh



ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

VOLUME ["/var/www/html/storage"]

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisor.conf"]
# Start via Supervisor to run Nginx and PHP-FPM together
COPY .docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisor.conf"]

# Start via Supervisor to run Nginx and PHP-FPM together
#COPY .docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf
#CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisor.conf"]