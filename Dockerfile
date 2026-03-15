FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unrar-free \
    unzip \
    libzip-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html
