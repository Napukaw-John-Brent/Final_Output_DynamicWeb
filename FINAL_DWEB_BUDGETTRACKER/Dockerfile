FROM php:8.1-apache

RUN a2dismod mpm_event || true \
    && a2enmod mpm_prefork || true

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads/avatars \
    && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80
