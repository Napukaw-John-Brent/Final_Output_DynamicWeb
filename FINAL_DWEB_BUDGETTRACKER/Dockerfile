FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork \
    && docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads/avatars \
    && chown -R www-data:www-data /var/www/html/uploads

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

EXPOSE 80
