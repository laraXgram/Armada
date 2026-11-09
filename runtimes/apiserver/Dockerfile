FROM ubuntu:24.04

LABEL org.opencontainers.image.source="https://github.com/laraxgram/armada"
LABEL org.opencontainers.image.description="The LaraGram API Server for LaraGram Armada."

ARG PHP_VERSION=8.5
ARG APISERVER_VERSION="^0.1"

ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /opt/laragram-server

RUN apt-get update \
    && apt-get install -y gnupg gosu curl ca-certificates unzip \
    && curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ noble main" > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gmp \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-swoole \
        php${PHP_VERSION}-xml \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# The MTProto schema is compiled at build time so the server never needs to write to its own code...
RUN composer require --no-interaction --no-progress --prefer-dist --optimize-autoloader "laraxgram/apiserver:${APISERVER_VERSION}" \
    && php vendor/laraxgram/mtproto/bin/compile-tl.php \
    && ln -s /opt/laragram-server/vendor/bin/laragram-server /usr/local/bin/laragram-server \
    && rm -rf /root/.composer/cache

COPY docker-entrypoint /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint \
    && mkdir -p /var/lib/bot-api /tmp/bot-api

EXPOSE 8081/tcp 8082/tcp

ENTRYPOINT ["docker-entrypoint"]
