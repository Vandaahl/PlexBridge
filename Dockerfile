# Start with the Chainguard Laravel base image
FROM cgr.dev/chainguard/laravel:latest-dev

# Set working directory
WORKDIR /app

# Copy application files into the container
COPY ./app /app

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev --prefer-dist --optimize-autoloader

# Create the settings.json file with placeholder content
RUN mkdir -p var
RUN if [ ! -f var/settings.json ]; then echo '{"settings":{"services":["letterboxd"]}}' > var/settings.json; fi
RUN if [ ! -f var/trakt-token-data.json ]; then echo '{}' > var/trakt-token-data.json; fi

# Expose application port
EXPOSE 8000
