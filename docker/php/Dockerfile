FROM php:8.4.19-zts-alpine3.22

# system packages
RUN apk update && apk add \
    git \
    curl \
    unzip \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    chromium \
    nodejs \
    npm

# php extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    gd \
    zip

# redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium