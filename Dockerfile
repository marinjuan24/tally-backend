FROM serversideup/php:8.4-fpm-nginx

# Cambiar al usuario root para poder configurar carpetas
USER root

# Copiar los archivos del proyecto al contenedor
COPY --chown=www-data:www-data . /var/www/html

WORKDIR /var/www/html

# Configurar variables de entorno
ENV COMPOSER_ALLOW_SUPERUSER=1

# Instalar dependencias de producción
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Crear directorios de storage y symlink
RUN mkdir -p bootstrap/cache \
    && php artisan storage:link --force \
    && chown -R www-data:www-data /var/www/html/vendor \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Regresar al usuario por defecto del contenedor
USER www-data
