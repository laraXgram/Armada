FROM alpine:3.22 AS build

ARG BOT_API_REF=master
ARG JOBS=""

RUN apk add --no-cache alpine-sdk linux-headers git zlib-dev openssl-dev gperf cmake

RUN git clone --recursive https://github.com/tdlib/telegram-bot-api /src \
    && cd /src \
    && git checkout "$BOT_API_REF" \
    && git submodule update --init --recursive \
    && mkdir build \
    && cd build \
    && cmake -DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX:PATH=/usr/local .. \
    && cmake --build . --target install -j "${JOBS:-$(nproc)}" \
    && strip /usr/local/bin/telegram-bot-api

FROM alpine:3.22

LABEL org.opencontainers.image.source="https://github.com/laraxgram/armada"
LABEL org.opencontainers.image.description="Telegram's official Bot API server, built from source for LaraGram Armada."

RUN apk add --no-cache libstdc++ openssl zlib su-exec

COPY --from=build /usr/local/bin/telegram-bot-api /usr/local/bin/telegram-bot-api
COPY docker-entrypoint /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint \
    && mkdir -p /var/lib/bot-api /tmp/bot-api

EXPOSE 8081/tcp 8082/tcp

ENTRYPOINT ["docker-entrypoint"]
