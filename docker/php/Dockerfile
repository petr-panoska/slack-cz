FROM php:8.4.1-fpm-alpine

# Build args — host user's uid/gid, so bind-mounted writes are owned by the dev user.
ARG UID=1000
ARG GID=1000

# Switch to the dev php.ini (asserts on, friendlier errors). Override per-env if needed.
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Build & runtime deps in a single layer. `netcat-openbsd` is needed by the
# php-fpm healthcheck (compose.yaml). `bash` is required by composer's `--diagnose`.
RUN apk add --no-cache \
        bash \
        netcat-openbsd \
        libpq-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        freetype-dev

# PHP extensions in one layer: pdo_pgsql/pdo_mysql, intl, gd (with jpeg/webp/freetype), exif.
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j$(nproc) pdo_pgsql pdo_mysql intl gd exif

# Composer from the official multi-stage image — always current, no commit pin.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Create an `app` user/group matching the host uid/gid passed at build.
# Bind-mounted files written by this user end up owned by the host user — no chmod needed.
RUN addgroup -g ${GID} -S app \
 && adduser  -u ${UID} -G app -S app \
 && mkdir -p /var/www/html/public/uploads \
             /var/www/html/public/media/cache \
             /var/www/html/var \
 && chown -R app:app /var/www/html

USER app
WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
