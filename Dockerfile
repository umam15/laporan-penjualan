FROM php:8.2-apache

# Ekstensi PDO yang dibutuhkan aplikasi (pdo_pgsql untuk DB transaksi, pdo_sqlite untuk settings.db)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libsqlite3-dev \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY . /var/www/html/

# Folder data/ harus writable oleh web server (tempat settings.db dibuat otomatis)
RUN chown -R www-data:www-data /var/www/html/data \
    && chmod 775 /var/www/html/data

EXPOSE 80
