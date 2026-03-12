FROM php:8.1-alpine
# Install build deps + runtime libs
RUN apk add --no-cache \
    git \
    unzip \
    bash \
    libzip-dev \
    sqlite-dev \
    oniguruma-dev \
    libffi-dev \
    mariadb-connector-c-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite zip \
    && echo "ffi.enable=true" > /usr/local/etc/php/conf.d/ffi.ini
# Install Composer (pinned version 2.7.7)
COPY --from=composer:2.7.7 /usr/bin/composer /usr/bin/composer
WORKDIR /app
