# Lokali PHP + Apache aplinka, artima domenai.lt hostingui
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers

COPY php.ini /usr/local/etc/php/conf.d/varzybos.ini
