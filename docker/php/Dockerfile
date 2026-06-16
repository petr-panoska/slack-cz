FROM php:8.4-fpm-alpine

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN apk add libpq-dev icu-dev
RUN docker-php-ext-install pdo_pgsql pdo_mysql
RUN docker-php-ext-install intl

# GD + EXIF — liip_imagine thumby (čte WebP master, generuje WebP derivatives)
RUN apk add --no-cache libjpeg-turbo-dev libpng-dev libwebp-dev freetype-dev
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype
RUN docker-php-ext-install gd exif

# ImageMagick (HEIC dekód z iPhonů + WebP enkód) + exiftool (čtení datumu/GPS, i z HEIC)
# — foto upload normalizace, viz App\Service\PhotoNormalizer
RUN apk add --no-cache imagemagick imagemagick-heic imagemagick-webp imagemagick-jpeg exiftool

# Foto uploady jsou z mobilů (3–12 MB) — php.ini-development má default jen 2M, takže
# by je PHP odmítlo dřív, než je uvidí Symfony validace. Drž v sync s Assert\File maxSize
# v HighlinePhoto (30M) a se setup-server.sh.
RUN { echo 'upload_max_filesize = 32M'; echo 'post_max_size = 40M'; } > "$PHP_INI_DIR/conf.d/uploads.ini"

RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- --quiet
RUN mv composer.phar /usr/local/bin/composer

RUN apk add --no-cache bash
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.alpine.sh' | bash
RUN apk add symfony-cli

EXPOSE 9000
CMD ["php-fpm"]