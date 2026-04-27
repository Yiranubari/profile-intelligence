#!/bin/sh

php /var/www/html/database/seed.php

# Make sure the seeded database is writable by php-fpm's user
chown -R www-data:www-data /var/www/html/database
chmod -R 775 /var/www/html/database

php-fpm -D
nginx -g 'daemon off;'
