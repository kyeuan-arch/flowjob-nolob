FROM php:8.2-apache

WORKDIR /var/www/html

COPY . /var/www/html

RUN a2enmod rewrite

RUN docker-php-ext-install pdo pdo_mysql