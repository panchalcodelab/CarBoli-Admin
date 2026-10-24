FROM node:22-bookworm-slim AS frontend

WORKDIR /var/www/html

COPY package*.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.4-fpm-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=frontend /var/www/html/public/build ./public/build
COPY . .

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/carboli-entrypoint
RUN chmod +x /usr/local/bin/carboli-entrypoint

ENTRYPOINT ["carboli-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web

COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
