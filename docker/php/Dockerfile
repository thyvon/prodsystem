# Base PHP-FPM image
FROM php:8.3-fpm-alpine

# ------------------------
# Install system dependencies + Khmer fonts
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
    fontconfig \
    ttf-dejavu \
    ttf-liberation \
    ttf-droid \
    msttcorefonts-installer \
    && fc-cache -f \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring intl gd zip opcache \
    && apk del icu-dev oniguruma-dev libpng-dev libjpeg-turbo-dev libwebp-dev libzip-dev

# ------------------------
# Install wkhtmltopdf (static binary)
# ------------------------
RUN curl -L -o /tmp/wkhtmltopdf.tar.xz https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.6/wkhtmltox_0.12.6-1.alpine-amd64.tar.xz \
    && mkdir -p /usr/local/bin/wkhtmltopdf \
    && tar -xJf /tmp/wkhtmltopdf.tar.xz -C /usr/local/bin/wkhtmltopdf --strip-components=1 \
    && chmod +x /usr/local/bin/wkhtmltopdf/bin/wkhtmltopdf \
    && ln -s /usr/local/bin/wkhtmltopdf/bin/wkhtmltopdf /usr/local/bin/wkhtmltopdf \
    && rm -rf /tmp/wkhtmltopdf.tar.xz

# ------------------------
# OPcache
# ------------------------
COPY ./docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# ------------------------
# Composer
# ------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ------------------------
# Working directory
# ------------------------
WORKDIR /var/www

# ------------------------
# Copy only composer files first (for better caching)
# ------------------------
COPY composer.json composer.lock ./

# ------------------------
# Copy the rest of the app
# ------------------------
COPY . .

# ------------------------
# Puppeteer fallback (optional, keep for legacy)
# ------------------------
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

EXPOSE 9000

CMD ["php-fpm"]
