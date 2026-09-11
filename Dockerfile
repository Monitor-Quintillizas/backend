FROM php:8.2-apache

# Instalar PDO MySQL y Python con psutil
RUN apt-get update && apt-get install -y \
    python3 \
    python3-psutil \
    && docker-php-ext-install pdo pdo_mysql

# Copiar los archivos del backend
COPY . /var/www/html/

# Configurar permisos para Apache
RUN chown -R www-data:www-data /var/www/html/