#!/bin/sh

# Start php-fpm in the background
php-fpm &

# Start nginx
nginx -g "daemon off;"
