FROM richarvey/nginx-php-fpm:3.1.6
COPY . /var/www/html
ENV WEBROOT /var/www/html/public
ENV COMPOSER_ALLOW_SUPERUSER 1
RUN composer install --no-interaction --optimize-autoloader --no-dev
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
EXPOSE 80

