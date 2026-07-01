#!/bin/sh
set -e

ENV_FILE=/usr/local/etc/php-fpm.d/zz-docker-env.conf
echo "; Docker environment variables" > "$ENV_FILE"
echo "[www]" >> "$ENV_FILE"
printenv | while IFS="=" read -r key value; do
  case "$key" in
    *" "*|*"("*|*")"*|"") continue ;;
  esac
  printf 'env[%s] = "%s"\n' "$key" "$value" >> "$ENV_FILE"
done

cd /var/www/html

# Writable paths on the mounted storage volume (Coolify read-only root elsewhere)
mkdir -p \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/nginx/client_body \
  storage/nginx/proxy \
  storage/nginx/fastcgi \
  storage/nginx/uwsgi \
  storage/nginx/scgi

chmod -R 775 storage 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Remove stale pid files from earlier configs (nginx now uses pid /dev/null)
rm -f storage/nginx/nginx.pid 2>/dev/null || true

# Wait for MySQL (up to ~60s) when DB_HOST is set
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "mysql" ]; then
  echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
  MYSQL_PWD="${DB_PASSWORD:-}"
  export MYSQL_PWD
  for i in $(seq 1 30); do
    if mysqladmin ping -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-root}" --silent 2>/dev/null; then
      echo "MySQL is ready."
      break
    fi
    if [ "$i" -eq 30 ]; then
      echo "Warning: MySQL not reachable; continuing anyway."
    fi
    sleep 2
  done
  unset MYSQL_PWD
fi

php artisan package:discover --ansi --no-interaction
php artisan storage:link --force 2>/dev/null || true
php artisan migrate --force --no-interaction 2>/dev/null || true

if [ "$APP_ENV" = "production" ]; then
  php artisan config:cache --no-interaction 2>/dev/null || true
  php artisan route:cache --no-interaction 2>/dev/null || true
  php artisan view:cache --no-interaction 2>/dev/null || true
fi

exec "$@"
