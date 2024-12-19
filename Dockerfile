# Stage 1: Build the Laravel application
FROM cgr.dev/chainguard/laravel:latest-dev AS builder

# Set working directory
WORKDIR /app

# Copy application files into the builder container
COPY ./app /app

# Log and show the name of the user inside the container
RUN whoami > /tmp/debug.log && cat /tmp/debug.log

# Switch to root to adjust permissions for php user
USER root

# Change ownership to php user and php group
RUN chown -R php:php /app
RUN chmod -R 755 /app

# Switch back to the php user
USER php

# Set the environment variable to 'prod' for production
ENV APP_ENV=prod

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev --prefer-dist --optimize-autoloader

# Create the settings.json file with placeholder content
RUN mkdir -p var
RUN if [ ! -f var/settings.json ]; then echo '{"settings":{"services":["letterboxd"]}}' > var/settings.json; fi
RUN if [ ! -f var/trakt-token-data.json ]; then echo '{}' > var/trakt-token-data.json; fi

# Stage 2: Final container with Nginx and Laravel application
FROM cgr.dev/chainguard/nginx:latest-dev

# Copy the PHP binaries and necessary files from the builder stage
COPY --from=builder /usr/bin/php /usr/bin/php
COPY --from=builder /usr/sbin/php-fpm /usr/sbin/php-fpm
COPY --from=builder /etc/php /etc/php

# Copy additional required PHP libraries
COPY --from=builder /usr/lib /usr/lib
COPY --from=builder /usr/lib64 /usr/lib64
COPY --from=builder /lib /lib
COPY --from=builder /lib64 /lib64
COPY --from=builder /var/lib /var/lib

# Copy the Laravel application from the builder stage
COPY --from=builder /app /app

# Log and show the name of the user inside the container
RUN whoami > /tmp/debug.log && cat /tmp/debug.log

# Copy custom Nginx configuration
COPY ./nginx.conf /etc/nginx/nginx.conf

# Copy the entrypoint script into the container
COPY ./entrypoint.sh /entrypoint.sh

# Switch to root to adjust permissions for nginx user
USER root

# Set the script as executable
RUN chmod +x /entrypoint.sh

# Create the necessary directory and set proper permissions
RUN mkdir -p /run/nginx && chown -R nginx:nginx /run/nginx

USER nginx

# Expose the port used by Nginx
EXPOSE 8080

# Default command to run Nginx
#CMD ["-c", "/etc/nginx/nginx.conf", "-e", "/dev/stderr", "-g", "daemon off;"]

# Set the entrypoint to the shell script
ENTRYPOINT ["/entrypoint.sh"]