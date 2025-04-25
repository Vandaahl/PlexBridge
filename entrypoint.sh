#!/bin/sh

# Generate APP_SECRET if not provided
if [ -z "$APP_SECRET" ]; then
    export APP_SECRET=$(openssl rand -hex 32)
    echo "Generated new APP_SECRET"
fi

# Create database directory if it doesn't exist
mkdir -p /app/var/database
echo "Current user is: $(whoami)"
echo "User details: $(id)"
chown $(whoami):$(whoami) /app/var/database
chmod 755 /app/var/database

# Run database migrations
php /app/bin/console doctrine:migrations:migrate --no-interaction

# Start php-fpm in the background
php-fpm &

# Start nginx
nginx -g "daemon off;"
