#!/bin/bash
set -e

# ---- Site identity (multi-site on one host) ----
# COMPOSE_PROJECT_NAME defaults to THIS script's directory name (the site
# folder), which is already unique per site. Override it explicitly only for
# a NEW site before first deploy. NEVER change it (or the data volume name it
# prefixes) for an existing site after first deploy - the app would start
# against an empty DB.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-$(basename "$SCRIPT_DIR")}"
export APP_PORT="${APP_PORT:-8000}"
export DB_EXPOSE_PORT="${DB_EXPOSE_PORT:-3306}"
export DB_CONTAINER_NAME="${DB_CONTAINER_NAME:-umrah_app_db}"
export REDIS_CONTAINER_NAME="${REDIS_CONTAINER_NAME:-umrah_app_redis}"
export DB_USERNAME="${DB_USERNAME:-techcandle_umrah}"
export DB_DATABASE="${DB_DATABASE:-umrah_app_prod}"

echo "Starting safe deployment..."

# Pull new image first
echo "Pulling latest image..."
docker compose -f docker-compose.prod.yml pull app

# Stop containers gracefully (keeps volumes)
echo "Stopping containers..."
docker compose -f docker-compose.prod.yml stop db
docker compose -f docker-compose.prod.yml stop app || true

# Wait for clean shutdown
sleep 5

# Start database first
echo "Starting database..."
docker compose -f docker-compose.prod.yml up -d db

# Wait for DB health
echo "Waiting for database..."
until docker compose -f docker-compose.prod.yml exec db mysqladmin ping -h 127.0.0.1 -P 3306 -u"$${DB_USERNAME:-techcandle_umrah}" --password="$${DB_PASSWORD}" -d "$${DB_DATABASE:-umrah_app_prod}" >/dev/null 2>&1; do
  echo "Still waiting..."
  sleep 3
done

# Start/Recreate app (reuses existing volumes)
echo "Starting application..."
docker compose -f docker-compose.prod.yml up -d app

# Run post-deploy fixes
echo "Setting storage permissions..."
docker compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache || true

# echo "Running migrations..."
# docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

echo "Deployment completed safely!"