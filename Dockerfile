FROM serversideup/php:8.4-fpm-nginx

USER root

# Instalar extensión pdo_mysql con dependencias del sistema
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libmariadb-dev \
       unzip \
    && docker-php-ext-install pdo_mysql \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data . /var/www/html

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV AUTORUN_ENABLED=true

RUN composer install --no-interaction --optimize-autoloader --no-dev

RUN chown -R www-data:www-data /var/www/html/vendor \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage

USER www-data
