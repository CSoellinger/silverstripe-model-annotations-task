ARG PHP_VERSION=8.0
FROM composer:2 AS composer
FROM mlocati/php-extension-installer:1.5 as phpei
FROM php:$PHP_VERSION-apache-buster

# Copy composer and install-php-extensions from other images
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=phpei /usr/bin/install-php-extensions /usr/bin/install-php-extensions

# Create a local user
ARG LOCAL_USER
ARG LOCAL_USER_ID
RUN useradd -ms /bin/bash -l -u "$LOCAL_USER_ID" "$LOCAL_USER"

# Install some tools
RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        apt-transport-https \
        software-properties-common \
        ca-certificates \
        git \
        nano \
        cron \
        gnupg \
        ssh-client \
        unzip \
        p7zip

# Install php extensions
RUN install-php-extensions \
        ast \
        apcu \
        bcmath \
        mysqli \
        pdo_mysql \
        intl \
        ldap \
        ssh2 \
        imagick \
        gd \
        soap \
        tidy \
        xsl \
        redis \
        pcov \
        zip \
        exif \
        gmp \
        memcached \
        opcache \
        sqlsrv \
        pdo_sqlsrv

RUN apt-get purge -y \
        apt-transport-https \
        software-properties-common \
        gnupg \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux
# RUN touch "$PHP_INI_DIR/conf.d/php-custom.ini" \
#     && echo "error_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED" >> "$PHP_INI_DIR/conf.d/php-custom.ini" \
#     && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
