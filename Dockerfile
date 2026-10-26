FROM php:8.2-apache

# GD sirve para generar miniaturas de las portadas: una imagen de
# 900x1400 reducida por el navegador a 165 px sale dentada, porque el
# filtro rápido del escalado no promedia los píxeles. Redimensionarla
# en el servidor da un resultado nítido y además pesa mucho menos.
RUN apt-get update && apt-get install -y \
    unrar-free \
    unzip \
    libzip-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install mysqli pdo pdo_mysql zip gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html
