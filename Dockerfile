# Stage 1: Build the application
FROM cgr.dev/chainguard/laravel:latest-dev AS builder

ARG TARGETARCH

# Set working directory
WORKDIR /app

# Switch to root to install dependencies and patch libcurl
USER root

# Install dependencies needed for patching
RUN apk add --no-cache patchelf

# Download and patch libcurl-impersonate based on architecture
RUN if [ "$TARGETARCH" = "amd64" ]; then \
        VERSION="v1.2.1"; \
        FILENAME="libcurl-impersonate-${VERSION}.x86_64-linux-gnu.tar.gz"; \
        URL="https://github.com/lexiforest/curl-impersonate/releases/download/${VERSION}/${FILENAME}"; \
        curl -L "$URL" | tar -xz -C /usr/lib libcurl-impersonate.so.4.8.0; \
        mv /usr/lib/libcurl-impersonate.so.4.8.0 /usr/lib/libcurl-impersonate.so; \
        patchelf --set-soname libcurl.so.4 /usr/lib/libcurl-impersonate.so; \
    elif [ "$TARGETARCH" = "arm64" ]; then \
        VERSION="v1.2.1"; \
        FILENAME="libcurl-impersonate-${VERSION}.aarch64-linux-gnu.tar.gz"; \
        URL="https://github.com/lexiforest/curl-impersonate/releases/download/${VERSION}/${FILENAME}"; \
        curl -L "$URL" | tar -xz -C /usr/lib libcurl-impersonate.so.4.8.0; \
        mv /usr/lib/libcurl-impersonate.so.4.8.0 /usr/lib/libcurl-impersonate.so; \
        patchelf --set-soname libcurl.so.4 /usr/lib/libcurl-impersonate.so; \
    fi

# Copy application files into the builder container
COPY ./app /app

# Switch to root to adjust permissions for php user
USER root

RUN mkdir -p /app/var/database

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

# Stage 2: Final container with Nginx
FROM cgr.dev/chainguard/nginx:latest-dev

# Copy PHP binary and FPM from the builder stage
COPY --from=builder /usr/bin/php* /usr/bin/
COPY --from=builder /usr/sbin/php-fpm /usr/sbin/php-fpm

# Copy PHP configuration
COPY --from=builder /etc/php /etc/php

# Copy PHP extension modules
COPY --from=builder /usr/lib/php /usr/lib/php

# Copy missing shared libraries required by PHP (e.g., libxml2, libsodium, libonig)
COPY --from=builder /usr/lib/libxml2.so.16* /usr/lib/
COPY --from=builder /usr/lib/libsodium.so.26* /usr/lib/
COPY --from=builder /usr/lib/libonig.so.5* /usr/lib/

# Copy the application from the builder stage
COPY --from=builder /app /app

# Copy the patched libcurl-impersonate library
COPY --from=builder /usr/lib/libcurl-impersonate.so /usr/lib/libcurl-impersonate.so

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

# Set the entrypoint to the shell script
ENTRYPOINT ["/entrypoint.sh"]