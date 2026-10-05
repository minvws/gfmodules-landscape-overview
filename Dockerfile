FROM php:8.5-fpm

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

ARG NO_DEV=true

RUN if [ "$NO_DEV" = "true" ]; then composer install --no-dev --no-scripts --no-progress; else composer install --no-scripts --no-progress; fi

RUN echo "{\"version\": \"${RELEASE_VERSION}\", \"git_ref\": \"${GIT_SHA}\"}" > /app/public/version.json

# Expose ports
EXPOSE 80

# Start PHP-FPM and Caddy
CMD ["sh", "-c", "php-fpm -D && caddy run --config /app/Caddyfile --adapter caddyfile"]

