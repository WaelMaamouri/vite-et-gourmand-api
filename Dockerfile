FROM php:8.4-cli

# System deps + PHP extensions (mysql + intl + zip + opcache) + mongodb (pecl)
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev pkg-config libssl-dev libicu-dev autoconf g++ make \
    && docker-php-ext-install zip pdo pdo_mysql opcache intl \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy project
COPY . .

# Install PHP deps WITHOUT running symfony auto-scripts during build
# (Render build env doesn't have runtime env vars yet -> avoids cache:clear crash)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && composer dump-autoload --optimize --no-interaction

# Cache/log dirs + permissions (avoid 500 errors)
RUN mkdir -p var/cache var/log && chmod -R 777 var

# Warm cache (won't fail build if env not available)
RUN php -d variables_order=EGPCS bin/console cache:clear --env=prod || true

# Render provides PORT
CMD sh -lc 'php bin/console doctrine:migrations:migrate --no-interaction --env=prod || true; php -S 0.0.0.0:${PORT} -t public'
