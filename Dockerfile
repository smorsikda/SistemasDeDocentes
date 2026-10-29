FROM php:8.2-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_mysql \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/apache-entrypoint.sh /usr/local/bin/docentes-apache-entrypoint
COPY docker/apache-docentes.conf /etc/apache2/conf-available/docentes-security.conf
RUN chmod 0755 /usr/local/bin/docentes-apache-entrypoint
RUN a2enconf docentes-security

WORKDIR /var/www/html/docentes
COPY . /var/www/html/docentes/

EXPOSE 80
CMD ["docentes-apache-entrypoint"]
