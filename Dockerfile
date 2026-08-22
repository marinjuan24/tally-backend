FROM richarvey/nginx-php-fpm:3.1.6

# Copiar los archivos del proyecto al contenedor
COPY . /var/www/html

# Configurar el directorio raíz público de Laravel
ENV WEBROOT /var/www/html/public
ENV COMPOSER_ALLOW_SUPERUSER 1

# CORRECCIÓN AQUÍ: Ignorar restricciones de versión de PHP en el despliegue
RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-reqs

# Dar permisos correctos a las carpetas de almacenamiento de Laravel
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80