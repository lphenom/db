FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql zip

# Install SQLite for tests
RUN apt-get update && apt-get install -y libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer (pinned version)
COPY --from=composer:2.7.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy source
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

CMD ["php-fpm"]

