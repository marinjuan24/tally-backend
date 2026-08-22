FROM serversideup/php:8.4-fpm-nginx

# Cambiar al usuario root
USER root

# Copiar archivos del proyecto
COPY --chown=www-data:www-data . /var/www/html

WORKDIR /var/www/html

# Variables de entorno
ENV COMPOSER_ALLOW_SUPERUSER=1
# Ejecuta migraciones automáticamente al arrancar (feature de serversideup)
ENV AUTORUN_ENABLED=true

# Instalar dependencias de producción
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Limpiar caché que pueda haber quedado de build
RUN php artisan config:clear 2>/dev/null || true

# Asegurar permisos correctos
RUN chown -R www-data:www-data /var/www/html/vendor \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage

# Regresar al usuario por defecto
USER www-data
