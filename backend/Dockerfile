FROM php:8.4-cli-alpine

RUN apk add --no-cache curl git libcurl libxml2 oniguruma sqlite-libs unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS curl-dev libxml2-dev oniguruma-dev sqlite-dev \
    && docker-php-ext-install curl dom mbstring pcntl pdo_sqlite xml xmlwriter \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
