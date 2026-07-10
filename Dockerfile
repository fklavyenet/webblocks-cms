# WebBlocks CMS — single-container demo image (SQLite).
# Not a production image: it uses `php artisan serve` and an in-container SQLite
# database so anyone can try the CMS with one command.
FROM php:8.4-cli-bookworm

# PHP extensions the CMS needs that are not bundled in the base image.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
        libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite zip intl gd bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies first for better layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

# Copy the application and build the optimized autoloader.
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && chmod +x docker/entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["docker/entrypoint.sh"]
