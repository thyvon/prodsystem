# Base PHP-FPM image
FROM php:8.3-fpm-alpine

# ------------------------
# Install system dependencies, PHP extensions, Node.js, Chromium
# ------------------------
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
    chromium \
    nss \
    freetype \
    harfbuzz \
    ttf-freefont \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring intl gd zip opcache \
    && apk del icu-dev oniguruma-dev

# ------------------------
# Enable OPcache
# ------------------------
COPY ./docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# ------------------------
# Install Composer globally
# ------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ------------------------
# Set working directory
# ------------------------
WORKDIR /var/www

# ------------------------
# Copy composer files and install PHP dependencies (with Wasabi S3 adapter)
# ------------------------
COPY composer.json composer.lock ./

# ------------------------
# Copy rest of the application
# ------------------------
COPY . .

# ------------------------
# Environment variable for Puppeteer/Chromium
# ------------------------
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

# ------------------------
# Expose PHP-FPM port
# ------------------------
EXPOSE 9000

# ------------------------
# Run PHP-FPM
# ------------------------
CMD ["php-fpm"]
