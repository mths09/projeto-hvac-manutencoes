FROM php:8.2-apache

RUN apt-get update     && apt-get install -y --no-install-recommends libonig-dev     && rm -rf /var/lib/apt/lists/*     && docker-php-ext-install -j2 mysqli mbstring     && a2enmod rewrite headers     && printf '%s\n'         '<Directory /var/www/html>'         '    AllowOverride All'         '    Require all granted'         '</Directory>'         '<Directory /var/www/html/backend/uploads>'         '    AllowOverride None'         '    Require all denied'         '</Directory>'         > /etc/apache2/conf-available/hvac.conf     && a2enconf hvac     && printf '%s\n'         'upload_max_filesize=25M'         'post_max_size=135M'         'max_file_uploads=5'         'max_execution_time=120'         'max_input_time=120'         > /usr/local/etc/php/conf.d/hvac.ini

WORKDIR /var/www/html
COPY . /var/www/html/

EXPOSE 80
CMD ["apache2-foreground"]
