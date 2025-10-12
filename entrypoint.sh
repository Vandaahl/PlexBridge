#!/usr/bin/env bash
set -e

APP_SECRET_FILE="/app/var/app_secret"

# Reuse a persisted secret if present; otherwise generate once and persist
if [ -z "${APP_SECRET:-}" ]; then
  if [ -f "$APP_SECRET_FILE" ] && [ -s "$APP_SECRET_FILE" ]; then
    export APP_SECRET="$(cat "$APP_SECRET_FILE")"
    echo "Loaded existing APP_SECRET from $APP_SECRET_FILE"
  else
    export APP_SECRET="$(hexdump -n 32 -v -e '1/1 "%02x"' /dev/urandom)"
    echo "$APP_SECRET" > "$APP_SECRET_FILE"
    chmod 600 "$APP_SECRET_FILE"
    echo "Generated and persisted new APP_SECRET at $APP_SECRET_FILE"
  fi
fi

# Run database migrations
php /app/bin/console doctrine:migrations:migrate --no-interaction

# Start php-fpm in the background
php-fpm &

# Start nginx
nginx -g "daemon off;"
