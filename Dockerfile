# Use the official PHP image with Apache web server
FROM php:8.2-apache

# Install PostgreSQL extensions so PHP can connect to CockroachDB
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Copy all your TickeTech files into the web server
COPY . /var/www/html/

# Expose port 80
EXPOSE 80
