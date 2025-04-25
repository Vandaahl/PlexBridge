#!/bin/sh

# Create database directory if it doesn't exist
mkdir -p /app/var/database

# Run database migrations
php /app/bin/console doctrine:migrations:migrate --no-interaction

# Start php-fpm in the background
php-fpm &

# Start nginx
nginx -g "daemon off;"
