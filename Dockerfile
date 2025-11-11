# syntax=docker/dockerfile:1.7

FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libgmp-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gmp \
    && docker-php-ext-install gmp pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction

COPY . .

RUN chmod +x /app/entrypoint.sh

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["./entrypoint.sh"]
