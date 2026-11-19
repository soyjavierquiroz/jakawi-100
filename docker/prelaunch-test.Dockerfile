# Reuse verified lock-identical test dependencies without rebuilding unrelated frontend stages.
FROM sha256:929e848599631ef2b862c45bf620ce6dcb53912e1a189dc7b0509faa27f0d7ef AS test
USER root
WORKDIR /var/www/html
RUN rm -rf app bootstrap config database resources routes tests public storage
COPY --chown=www-data:www-data app/ ./
RUN test "$(sha256sum composer.lock | cut -d' ' -f1)" = eadd67f9e5da8ca88ecf9c58906ce8a13b33d01793d80d77fc13abc64b8c1b3b \
 && cp .env.example .env \
 && composer dump-autoload --optimize --no-scripts \
 && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]
