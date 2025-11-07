FROM php:8.3-fpm-alpine

# Install system dependencies, PHP extensions, Node.js, and npm
RUN apk add --no-cache \
    bash \
    curl \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    nodejs \
    npm \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring intl gd zip opcache \
    && apk del icu-dev oniguruma-dev

# Enable OPcache
COPY ./docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Install Composer globally
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]

