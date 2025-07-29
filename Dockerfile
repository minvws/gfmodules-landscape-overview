FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    curl \
    ca-certificates \
    caddy \
    unzip \
    git \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Caddyfile
COPY Caddyfile /etc/caddy/Caddyfile

# Install PHP dependencies
WORKDIR /app
COPY . /app
RUN rm /app/services.json
RUN composer install --no-dev --no-scripts --no-progress

# Expose ports
EXPOSE 80

# Start PHP-FPM and Caddy
CMD ["sh", "-c", "php-fpm -D && caddy run --config /app/Caddyfile --adapter caddyfile"]

