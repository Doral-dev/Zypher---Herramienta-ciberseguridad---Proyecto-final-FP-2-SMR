FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev curl \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite

RUN apt-get update && apt-get install -y python3 python3-pip \
    && pip3 install psycopg2-binary

COPY . /var/www/html/

EXPOSE 80
