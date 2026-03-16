FROM php:8.4-fpm-alpine

RUN apk update && apk add \
    git \
    curl \
    unzip \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    chromium \
    nodejs \
    npm

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    gd \
    zip

RUN pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium