#!/usr/bin/env sh
set -e

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Runs on every container start. Reference seeding is manual: see deploy.sh --seed.
php artisan migrate --force
php artisan storage:link || true

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan event:cache || true

exec "$@"
