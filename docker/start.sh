#!/bin/sh
DB_DIR="$(dirname "${DB_PATH:-/var/www/html/database/profiles.db}")"

mkdir -p "$DB_DIR"
chown -R www-data:www-data "$DB_DIR"
chmod -R 775 "$DB_DIR"

php /var/www/html/database/seed.php

chown -R www-data:www-data "$DB_DIR"
chmod -R 775 "$DB_DIR"

php-fpm -D
nginx -g 'daemon off;'