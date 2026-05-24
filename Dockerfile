FROM php:8.2-apache

WORKDIR /var/www/html

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Disable conflicting MPM modules
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork

# Copy project
COPY . /var/www/html

EXPOSE 80