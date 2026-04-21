#!/bin/sh

php /var/www/html/database/seed.php
php-fpm -D
nginx -g 'daemon off;'
