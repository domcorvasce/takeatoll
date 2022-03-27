FROM php:8.0-cli

WORKDIR /var/www

# Configure extensions for PDO and Postgres
RUN apt update -y && \
    apt install -y libpq-dev && \
    docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
    docker-php-ext-install pdo pdo_pgsql pgsql

CMD php -S 0.0.0.0:8000 -t public
