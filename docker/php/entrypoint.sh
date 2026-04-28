#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if grep -Eq '^APP_KEY=$' .env 2>/dev/null; then
    php artisan key:generate --force
fi

exec php artisan serve --host=0.0.0.0 --port=8000