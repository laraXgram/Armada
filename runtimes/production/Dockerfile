ARG PHP_VERSION=8.5

FROM ubuntu:24.04 AS base

ARG PHP_VERSION

ENV DEBIAN_FRONTEND=noninteractive
ENV LANG=C.UTF-8
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y gnupg gosu curl ca-certificates unzip git supervisor nginx ffmpeg \
    && curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ noble main" > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y \
        libgd3 \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-gmp \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-mongodb \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-msgpack \
        php${PHP_VERSION}-igbinary \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-swoole \
        php${PHP_VERSION}-memcached \
        php${PHP_VERSION}-imagick \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

FROM base AS vendor

WORKDIR /app

COPY . .

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM node:24-alpine AS assets

WORKDIR /app

COPY --from=vendor /app .

RUN mkdir -p public/build bootstrap/ssr \
    && if [ -f package.json ]; then \
        if [ -f package-lock.json ]; then npm ci; else npm install; fi \
        && npm run build; \
    fi

FROM base

ARG INSTALL_NODE=0
ARG NODE_VERSION=24

ENV PHP_VERSION=${PHP_VERSION}
ENV ARMADA_ROLE=web

WORKDIR /var/www/html

RUN if [ "$INSTALL_NODE" = "1" ]; then \
    curl -fsSL https://deb.nodesource.com/setup_$NODE_VERSION.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*; \
fi

RUN userdel -r ubuntu 2>/dev/null || true
RUN groupadd --force -g 1000 armada \
    && useradd -m -s /bin/bash -g 1000 -u 1000 armada \
    && mkdir -p /var/lib/bot-api /run/php \
    && chown armada:armada /var/lib/bot-api

COPY --from=vendor --chown=armada:armada /app /var/www/html
COPY --from=assets --chown=armada:armada /app/public/build /var/www/html/public/build
COPY --from=assets --chown=armada:armada /app/bootstrap/ssr /var/www/html/bootstrap/ssr

COPY docker/production/php.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-armada.ini
COPY docker/production/php.ini /etc/php/${PHP_VERSION}/fpm/conf.d/99-armada.ini
COPY docker/production/nginx.conf /etc/nginx/sites-available/default
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/entrypoint /usr/local/bin/armada-entrypoint

RUN chmod +x /usr/local/bin/armada-entrypoint \
    && sed -i \
        -e 's/^user = .*/user = armada/' \
        -e 's/^group = .*/group = armada/' \
        -e 's/^listen.owner = .*/listen.owner = www-data/' \
        -e 's/^;clear_env = .*/clear_env = no/' \
        -e "s#^listen = .*#listen = /run/php/php-fpm.sock#" \
        /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf

EXPOSE 8000/tcp

ENTRYPOINT ["armada-entrypoint"]
