FROM php:8.2-apache

RUN a2enmod rewrite headers \
    && docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/logs /var/www/html/storage/sessions \
    && chown -R www-data:www-data /var/www/html/storage

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

EXPOSE 80
