FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev pkg-config libssl-dev \
    && docker-php-ext-install zip pdo pdo_mysql opcache \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN mkdir -p var/cache var/log && chmod -R 777 var

RUN php -d variables_order=EGPCS bin/console cache:clear --env=prod || true

CMD php -S 0.0.0.0:${PORT} -t public
