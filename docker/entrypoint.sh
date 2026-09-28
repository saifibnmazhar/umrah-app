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

# Config, route and view caches are intentionally NOT built here.
#
# Caching them on every container boot left deployments serving stale cached
# config/routes/views, which broke POST requests with 419 CSRF mismatches.
# The app runs correctly (only marginally slower) without them.
#
# Instead, clear any stale copy that was baked into an image or left behind by
# a manual run. This is what protects restarts that never run a deploy script
# (a server reboot, `docker restart`, `docker compose restart`). No `|| true`:
# with `set -e` a failed clear fails the boot loudly rather than serving stale
# state.
echo "Clearing Laravel caches..."
php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan view:clear --no-interaction

# Run migrations unless MIGRATE=false.
# Do NOT swallow errors: a failed migration must fail loudly in the logs
# instead of silently leaving the app with an incomplete schema.
if [ "$MIGRATE" != "false" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction
fi

exec "$@"
