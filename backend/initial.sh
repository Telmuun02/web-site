#!/bin/sh

php artisan config:clear
php artisan route:clear
php artisan cache:clear

php-fpm -D

exec supervisord -n -c /app/supervisor.conf
