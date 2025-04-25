#!/bin/sh

# Generate APP_SECRET if not provided
if [ -z "$APP_SECRET" ]; then
    export APP_SECRET=$(openssl rand -hex 32)
    echo "Generated new APP_SECRET"
fi

# Run database migrations
php /app/bin/console doctrine:migrations:migrate --no-interaction

# Start php-fpm in the background
php-fpm &

# Start nginx
nginx -g "daemon off;"
