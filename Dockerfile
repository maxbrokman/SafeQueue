FROM php:7.4.6-fpm-alpine

WORKDIR /var/www/html

RUN apk update && apk upgrade && apk add --no-cache \
    $PHPIZE_DEPS \
    && rm -r /var/cache/apk/*

# Composer
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm -rf composer-setup.php

COPY . /var/www/html

CMD ["php-fpm"]