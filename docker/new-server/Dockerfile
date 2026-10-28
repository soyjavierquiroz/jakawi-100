ARG COMPOSER_IMAGE=composer@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc
ARG PHP_IMAGE=php@sha256:a49524d3cec4a77d1807d0878e5bb38146116ae5489ec60c367aee53bb9b0699
ARG NGINX_IMAGE=nginx@sha256:47928bf67eb62a1863cb08d9ecb52d3e704304ba614eaee17884204677702951
FROM ${COMPOSER_IMAGE} AS composer
FROM ${PHP_IMAGE} AS php-base
RUN apk add --no-cache icu-libs libpq libzip libpng libjpeg-turbo libwebp oniguruma \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev oniguruma-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j1 pdo_pgsql pgsql intl zip gd exif bcmath opcache mbstring \
 && apk del .build-deps
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY docker/new-server/php.ini /usr/local/etc/php/conf.d/zz-jakawi.ini
COPY docker/new-server/fpm.conf /usr/local/etc/php-fpm.d/zzz-jakawi.conf

FROM php-base AS dependencies
COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins --optimize-autoloader

FROM dependencies AS app
ARG SOURCE_REVISION=unknown
LABEL org.opencontainers.image.source="https://github.com/soyjavierquiroz/jakawi-100" \
      org.opencontainers.image.revision=${SOURCE_REVISION}
COPY --chown=www-data:www-data app/ ./
RUN sh scripts/check-production-artifact.sh public \
 && test -s public/build/manifest.json \
 && composer dump-autoload --no-dev --no-scripts --no-plugins --optimize \
 && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]

FROM ${NGINX_IMAGE} AS web
ARG SOURCE_REVISION=unknown
LABEL org.opencontainers.image.source="https://github.com/soyjavierquiroz/jakawi-100" \
      org.opencontainers.image.revision=${SOURCE_REVISION}
COPY docker/new-server/nginx.conf /etc/nginx/conf.d/default.conf
COPY app/public/ /var/www/html/public/
RUN test -s /var/www/html/public/build/manifest.json \
 && test ! -e /var/www/html/public/hot \
 && find /var/www/html/public -type d -exec chmod 755 {} \; \
 && find /var/www/html/public -type f -exec chmod 644 {} \;
