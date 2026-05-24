FROM php:8.2-apache

WORKDIR /var/www/html

COPY . /var/www/html

# Disable ALL other MPMs first
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true

# Enable ONLY prefork (required for PHP Apache module)
RUN a2enmod mpm_prefork

# Enable common Apache features
RUN a2enmod rewrite

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql