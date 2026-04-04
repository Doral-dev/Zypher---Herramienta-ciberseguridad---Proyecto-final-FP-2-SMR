FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    curl \
    python3 \
    python3-pip \
    && pip3 install psycopg2-binary \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

EXPOSE 80
