FROM php:5.6-apache

# Debian Stretch is EOL; use archive mirrors so apt works for legacy PHP 5.6 images.
RUN set -eux; \
    sed -i 's|http://deb.debian.org/debian|http://archive.debian.org/debian|g' /etc/apt/sources.list; \
    sed -i 's|http://security.debian.org/debian-security|http://archive.debian.org/debian-security|g' /etc/apt/sources.list; \
    sed -i '/stretch-updates/d' /etc/apt/sources.list; \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until; \
    echo 'Acquire::AllowInsecureRepositories "true";' > /etc/apt/apt.conf.d/99allow-insecure-repos; \
    apt-get -o Acquire::Check-Valid-Until=false -o Acquire::AllowInsecureRepositories=true update; \
    apt-get install -y --allow-unauthenticated --no-install-recommends \
      zlib1g-dev libzip-dev libpng-dev libjpeg-dev libjpeg62-turbo-dev libfreetype6-dev unzip msmtp; \
    rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/
RUN docker-php-ext-install mysqli zip pdo_mysql gd

RUN a2enmod rewrite

COPY --from=composer:2.2 /usr/bin/composer /usr/local/bin/composer

# Nastavení PHP, aby používalo msmtp pro odesílání mailů
RUN echo "sendmail_path = \"/usr/bin/msmtp -t\"" > /usr/local/etc/php/conf.d/mail.ini
