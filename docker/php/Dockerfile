FROM php:8.4-fpm-alpine

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN apk add libpq-dev icu-dev
RUN docker-php-ext-install pdo_pgsql pdo_mysql
RUN docker-php-ext-install intl

# GD + EXIF — highline foto galerie (origin EXIF strip + auto-rotate, liip_imagine thumby)
RUN apk add --no-cache libjpeg-turbo-dev libpng-dev libwebp-dev freetype-dev
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype
RUN docker-php-ext-install gd exif

RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- --quiet
RUN mv composer.phar /usr/local/bin/composer

RUN apk add --no-cache bash
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.alpine.sh' | bash
RUN apk add symfony-cli

EXPOSE 9000
CMD ["php-fpm"]