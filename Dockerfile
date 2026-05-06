FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev curl \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite

COPY . /var/www/html/

EXPOSE 80

RUN apt-get update && apt-get install -y python3 python3-pip python3-psycopg2

RUN docker-php-ext-install pdo pdo_pgsql pgsql
