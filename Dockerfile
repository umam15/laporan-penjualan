FROM php:8.2-apache

# Ekstensi PDO yang dibutuhkan aplikasi (pdo_pgsql untuk DB transaksi, pdo_sqlite untuk settings.db)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libsqlite3-dev \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# Hardening Apache/PHP yang TIDAK bergantung pada .htaccess:
#  - data/, lib/, partials/ ditolak langsung oleh konfigurasi server (403)
#  - tanpa daftar isi folder, tanpa versi server/PHP di header
#    (nama zz-* agar dimuat setelah security.conf bawaan image)
RUN printf '%s\n' \
      '<DirectoryMatch "^/var/www/html/(data|lib|partials)(/|$)">' \
      '    Require all denied' \
      '</DirectoryMatch>' \
      '<Directory /var/www/html>' \
      '    Options -Indexes' \
      '</Directory>' \
      'ServerTokens Prod' \
      'ServerSignature Off' \
      > /etc/apache2/conf-available/zz-hardening.conf \
    && a2enconf zz-hardening \
    && printf 'expose_php = Off\n' > /usr/local/etc/php/conf.d/hardening.ini

COPY . /var/www/html/

# Set permission aman untuk seluruh app: readable oleh www-data,
# tidak bergantung pada permission file di host saat build.
# Lalu data/ dibuat writable khusus (tempat settings.db dibuat otomatis).
RUN chown -R root:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 750 {} \; \
    && find /var/www/html -type f -exec chmod 640 {} \; \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod 775 /var/www/html/data

EXPOSE 80
