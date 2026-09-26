FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install \
        bcmath \
        intl \
        opcache \
        pdo_pgsql \
        pgsql \
        zip \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM php-base AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl gnupg \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY app/package.json app/package-lock.json ./
RUN npm ci

COPY app/ ./
RUN php artisan route:clear \
    && npm run build \
    && composer dump-autoload --no-dev --optimize \
    && rm -rf node_modules

FROM php-base AS app

ENV APP_ENV=production \
    APP_DEBUG=false

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini
COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && find storage bootstrap/cache -type d -exec chmod 775 {} + \
    && find storage bootstrap/cache -type f -exec chmod 664 {} +

USER www-data

CMD ["php-fpm"]

# Test image: PHPUnit uses Laravel and Composer dependencies only. Keeping it
# separate from the frontend build avoids Node/Vite work on every isolated PHP
# test run; frontend checks run explicitly in their own validation step.
FROM php-base AS test

COPY app/composer.json app/composer.lock* ./
RUN composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY --chown=www-data:www-data app/ ./
RUN cp .env.example .env \
    && composer dump-autoload --optimize

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && touch .phpunit.result.cache \
    && chown www-data:www-data .phpunit.result.cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && find storage bootstrap/cache -type d -exec chmod 775 {} + \
    && find storage bootstrap/cache -type f -exec chmod 664 {} +

USER www-data

CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public
