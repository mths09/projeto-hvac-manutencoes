FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install -j2 mysqli mbstring \
    && rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork headers \
    && test "$(apache2ctl -M 2>/dev/null | grep -c 'mpm_.*_module')" -eq 1 \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        '<Directory /var/www/html/backend/uploads>' \
        '    AllowOverride None' \
        '    Require all denied' \
        '</Directory>' \
        > /etc/apache2/conf-available/hvac.conf \
    && a2enconf hvac \
    && printf '%s\n' \
        'upload_max_filesize=25M' \
        'post_max_size=130M' \
        'max_file_uploads=5' \
        'max_execution_time=120' \
        'max_input_time=120' \
        > /usr/local/etc/php/conf.d/hvac.ini \
    && apache2ctl configtest

WORKDIR /var/www/html
COPY . /var/www/html/

EXPOSE 8080

CMD ["sh", "-c", "port=\"${PORT:-8080}\"; sed -i \"s/^Listen 80$/Listen ${port}/\" /etc/apache2/ports.conf; sed -i \"s/<VirtualHost \\\\*:80>/<VirtualHost *:${port}>/\" /etc/apache2/sites-available/000-default.conf; rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf; a2enmod mpm_prefork headers; test \"$(apache2ctl -M 2>/dev/null | grep -c 'mpm_.*_module')\" -eq 1 || exit 1; mkdir -p /var/www/html/backend/uploads; chown -R www-data:www-data /var/www/html/backend/uploads; exec apache2-foreground"]
