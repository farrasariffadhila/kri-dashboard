#!/bin/sh
set -e
cd /app/backend

# A fresh key per start is fine for the demo: sessions die with the container anyway.
if [ -z "$APP_KEY" ]; then
  APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
  export APP_KEY
fi

# Public URL: Render exposes the hostname; locally fall back to localhost:$PORT.
if [ -n "$RENDER_EXTERNAL_HOSTNAME" ]; then
  export APP_URL="${APP_URL:-https://$RENDER_EXTERNAL_HOSTNAME}"
  export SANCTUM_STATEFUL_DOMAINS="${SANCTUM_STATEFUL_DOMAINS:-$RENDER_EXTERNAL_HOSTNAME}"
else
  export APP_URL="${APP_URL:-http://localhost:$PORT}"
  export SANCTUM_STATEFUL_DOMAINS="${SANCTUM_STATEFUL_DOMAINS:-localhost:$PORT,127.0.0.1:$PORT}"
fi

if [ "$DB_CONNECTION" = "sqlite" ]; then
  touch "$DB_DATABASE"
fi

if [ "$KRI_RESET_ON_BOOT" = "true" ]; then
  echo "Resetting demo data"
  php artisan migrate:fresh --seed --force --no-interaction
else
  php artisan migrate --force --no-interaction
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Files created above belong to root; PHP-FPM workers run as www-data.
chown -R www-data:www-data storage bootstrap/cache database

sed "s/__PORT__/$PORT/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
php-fpm -D
exec nginx -g 'daemon off;'
