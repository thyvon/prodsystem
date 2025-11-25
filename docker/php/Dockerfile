# -----------------------------------------------------------
# Dockerfile (Debian, PHP 8.3 FPM) - optimized for low-RAM servers
# -----------------------------------------------------------

FROM php:8.3-fpm

# Non-interactive apt
ARG DEBIAN_FRONTEND=noninteractive

# Puppeteer/Chromium path for Browsershot
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

# Set working directory
WORKDIR /var/www

# -----------------------------------------------------------
# 1) Install system deps, build tools, fonts, chromium, nodejs
# -----------------------------------------------------------
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    ca-certificates \
    wget \
    curl \
    gnupg2 \
    xz-utils \
    unzip \
    tar \
    git \
    zip \
    procps \
    nodejs \
    npm \
    chromium \
    fontconfig \
    fonts-dejavu-core \
    fonts-liberation \
    fonts-noto-core \
    fonts-khmeros-core \
    fonts-poppins \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype-dev \
    libzip-dev \
    zlib1g-dev \
    libicu-dev \
    libonig-dev \
    dpkg \
    apt-utils \
 && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------
# 2) Install wkhtmltopdf 0.12.6 (Buster .deb)
# -----------------------------------------------------------
RUN apt-get update \
 && apt-get install -y --no-install-recommends xfonts-75dpi xfonts-base wget \
 && set -eux; \
    WK_VER="0.12.6"; \
    WK_DEB="wkhtmltox_${WK_VER}-1.buster_amd64.deb"; \
    wget -q "https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/${WK_VER}/${WK_DEB}" -O "/tmp/${WK_DEB}"; \
    dpkg -i "/tmp/${WK_DEB}" || (apt-get update && apt-get install -y -f --no-install-recommends); \
    rm -f "/tmp/${WK_DEB}"; \
    # global symlinks
    if [ -f /usr/local/bin/wkhtmltox/bin/wkhtmltopdf ]; then \
        ln -sf /usr/local/bin/wkhtmltox/bin/wkhtmltopdf /usr/local/bin/wkhtmltopdf; \
        ln -sf /usr/local/bin/wkhtmltox/bin/wkhtmltoimage /usr/local/bin/wkhtmltoimage; \
    fi \
 && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# -----------------------------------------------------------
# 3) Configure & install PHP extensions
# -----------------------------------------------------------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql \
    mbstring \
    intl \
    gd \
    zip \
    opcache

# -----------------------------------------------------------
# 4) Copy opcache config (optional)
# -----------------------------------------------------------
# COPY ./docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# -----------------------------------------------------------
# 5) Install Composer
# -----------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -----------------------------------------------------------
# 6) Copy app files (composer first for caching)
# -----------------------------------------------------------
COPY composer.json composer.lock ./
# RUN composer install --no-dev --optimize-autoloader --no-interaction --no-plugins --no-scripts
COPY . .

# -----------------------------------------------------------
# 7) Optional: copy custom fonts from project
# -----------------------------------------------------------
RUN mkdir -p /usr/local/share/fonts/custom \
 && cp /var/www/public/fonts/*.ttf /usr/local/share/fonts/custom/ 2>/dev/null || true \
 && fc-cache -f -v

# -----------------------------------------------------------
# 8) Clean up
# -----------------------------------------------------------
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# -----------------------------------------------------------
# 9) Expose and run PHP-FPM
# -----------------------------------------------------------
EXPOSE 9000
CMD ["php-fpm"]
