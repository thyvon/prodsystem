# Dockerfile (Debian, PHP 8.3 FPM) - optimized for low-RAM servers
FROM php:8.3-fpm

ARG DEBIAN_FRONTEND=noninteractive
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser
WORKDIR /var/www

# 1) Install runtime deps, build deps, fonts, chromium and tools (minimize extras)
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
    unzip \
    procps \
    nodejs \
    npm \
    chromium \
    fontconfig \
    fonts-dejavu-core \
    fonts-liberation \
    fonts-noto-core \
    fonts-khmeros-core || true \
    # optional: fonts-poppins might not exist on all Debian releases; install if available
 && apt-get install -y --no-install-recommends fonts-poppins || true \
    \
    # build tools for PHP extensions
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype-dev \
    libzip-dev \
    zlib1g-dev \
    libicu-dev \
    libonig-dev \
 && rm -rf /var/lib/apt/lists/*

# 2) Install wkhtmltopdf 0.12.6 (official Debian .deb)
#    Using the buster package which is compatible across Debian-based images
RUN set -eux; \
    WK_VER="0.12.6"; \
    WK_DEB="wkhtmltox_${WK_VER}-1.buster_amd64.deb"; \
    wget -q "https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/${WK_VER}/${WK_DEB}" -O "/tmp/${WK_DEB}" ; \
    dpkg -i "/tmp/${WK_DEB}" || (apt-get update && apt-get install -y -f --no-install-recommends); \
    rm -f "/tmp/${WK_DEB}"; \
    # ensure binary on PATH
    if [ -f /usr/local/bin/wkhtmltox/bin/wkhtmltopdf ]; then \
      ln -sf /usr/local/bin/wkhtmltox/bin/wkhtmltopdf /usr/local/bin/wkhtmltopdf; \
    fi

# 3) Configure & build required PHP extensions (uses docker-php-ext-*)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j "$(nproc)" \
    pdo_mysql \
    mbstring \
    intl \
    gd \
    zip \
    opcache

# 4) Copy opcache config if you have one (optional)
# COPY ./docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# 5) Composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 6) App files: copy composer files first for caching, then rest
COPY composer.json composer.lock ./
# optionally run composer install here if you want to perform dependency install at image build time:
# RUN composer install --no-dev --optimize-autoloader --no-interaction --no-plugins --no-scripts

COPY . .

# 7) Clean up apt caches and temp files (already removed earlier, but safe)
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# 8) Expose and run
EXPOSE 9000
CMD ["php-fpm"]
