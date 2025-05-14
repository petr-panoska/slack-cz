FROM php:5.6-apache
RUN apt-get update && apt-get install -y \
     zlib1g-dev libzip-dev libpng-dev libjpeg-dev libjpeg62-turbo-dev libfreetype6-dev

RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/
RUN docker-php-ext-install mysqli zip pdo_mysql gd

RUN a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

#RUN composer install
#RUN rm -rf temp/cache