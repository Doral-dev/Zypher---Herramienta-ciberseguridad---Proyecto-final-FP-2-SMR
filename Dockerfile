FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev unzip git curl \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

RUN composer install --no-dev --optimize-autoloader

EXPOSE 80
