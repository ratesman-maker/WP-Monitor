FROM php:8.5-fpm-alpine

# System dependencies for PHP extensions
RUN apk add --no-cache \
    linux-headers \
    libxml2-dev \
    curl-dev \
    openssl-dev \
    libsodium-dev \
    oniguruma-dev \
    icu-dev \
    libzip-dev \
    gd-dev \
    netcat-openbsd \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        mbstring \
        intl \
        curl \
        xml \
        zip \
        gd \
        sodium \
        opcache

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Entrypoint — wait for DB and start PHP-FPM
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]
