#!/bin/sh
set -e

# Ensure persistent storage is writable by www-data on every boot,
# even when the storage_data volume was created with root ownership.
mkdir -p \
    storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    storage/app/tmp \
    storage/app/private
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache --no-interaction || true
php artisan route:cache --no-interaction || true
php artisan view:cache --no-interaction || true

# Run migrations unless MIGRATE=false.
# Do NOT swallow errors: a failed migration must fail loudly in the logs
# instead of silently leaving the app with an incomplete schema.
if [ "$MIGRATE" != "false" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction
fi

exec "$@"
