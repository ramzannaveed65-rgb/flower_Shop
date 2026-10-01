# =====================================================
# Flower Shop backend for Railway
# PHP 8.2 + Apache (same web server as XAMPP)
# Site paths stay the same as on your PC:
#   https://YOUR-APP.up.railway.app/flower_shop/api/...
#   https://YOUR-APP.up.railway.app/flower_shop/admin/
# =====================================================
FROM php:8.2-apache

# MySQL driver, Apache modules, PHP limits, Apache listens on Railway's $PORT
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers setenvif \
 && { echo 'upload_max_filesize = 8M'; \
      echo 'post_max_size = 10M'; \
      echo 'date.timezone = Asia/Karachi'; \
      echo 'expose_php = Off'; } > /usr/local/etc/php/conf.d/flower.ini \
 && sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html>\n  Options FollowSymLinks\n  AllowOverride All\n  Require all granted\n</Directory>\nServerName localhost\n' \
      > /etc/apache2/conf-available/flower.conf \
 && a2enconf flower

# Railway sets PORT itself; 8080 is the fallback
ENV PORT=8080

# The app
COPY . /var/www/html/flower_shop/
COPY docker/root-index.php /var/www/html/index.php
COPY docker/entrypoint.sh /usr/local/bin/flower-entrypoint

# Keep a copy of the photos in the image: on first start they are copied
# into the Railway volume (which starts empty)
RUN sed -i 's/\r$//' /usr/local/bin/flower-entrypoint \
 && chmod +x /usr/local/bin/flower-entrypoint \
 && mkdir -p /opt/seed_uploads \
 && cp -r /var/www/html/flower_shop/uploads/. /opt/seed_uploads/ \
 && rm -rf /var/www/html/flower_shop/docker /var/www/html/flower_shop/Dockerfile

EXPOSE 8080
CMD ["flower-entrypoint"]
