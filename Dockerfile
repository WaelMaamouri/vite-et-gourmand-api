FROM php:8.2-cli

# System deps + PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create cache/log dirs + permissions (avoids 500 errors)
RUN mkdir -p var/cache var/log && chmod -R 777 var

# Warm up cache (won't fail build if env missing)
RUN php -d variables_order=EGPCS bin/console cache:clear --env=prod || true

# Render provides PORT
CMD php -S 0.0.0.0:${PORT} -t public
