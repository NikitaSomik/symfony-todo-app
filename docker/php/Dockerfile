FROM php:8.4-fpm-alpine

ARG UID=1000
ARG GID=1000
ENV UID=${UID}
ENV GID=${GID}

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers shadow \
    && apk --no-cache add \
        curl git bash \
        libpq-dev postgresql-dev \
        zip unzip libzip-dev \
        icu-dev \
    && pecl install xdebug redis \
    && docker-php-ext-enable xdebug redis \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql zip intl \
    && usermod -u $UID www-data \
    && groupmod -g $GID www-data \
    && apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

###> recipes ###
###< recipes ###

WORKDIR /var/www/app

USER www-data

EXPOSE 9000

CMD ["php-fpm"]