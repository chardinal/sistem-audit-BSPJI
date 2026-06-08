# ============================================================
# Sistem Audit BSPJI — Dockerfile
# Stack: PHP 8.2 + Apache + Composer
# ============================================================
FROM php:8.2-apache

# Install system deps + ekstensi PHP
RUN apt-get update && apt-get install -y \
        unzip curl git \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Aktifkan mod_rewrite Apache
RUN a2enmod rewrite

# Konfigurasi Apache: aktifkan AllowOverride untuk .htaccess
RUN echo '<Directory /var/www/html/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

# Salin semua file proyek ke web root
COPY . /var/www/html/

# Install Composer dependencies
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} + \
    && find /var/www/html -type f -exec chmod 644 {} + \
    && chmod -R 775 /var/www/html/config

EXPOSE 80
