#!/bin/bash
set -euo pipefail

# ============================================================
# Staging deployment script
# Run this on the staging server after GitHub Actions has
# built and pushed the staging-<sha> image to ghcr.io.
#
# Usage:
#   IMAGE_TAG=staging-<sha> ./deploy-staging.sh
# Or:
#   ./deploy-staging.sh  (uses IMAGE_TAG env or 'staging')
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Auto-derive COMPOSE_PROJECT_NAME from the web directory path if not set
# /var/www/umrah.binmishaltravels.com/web → umrah-binmishaltravels-com
if [ -z "${COMPOSE_PROJECT_NAME:-}" ]; then
    WEB_DIR="$SCRIPT_DIR"
    DOMAIN_PATH=$(echo "$WEB_DIR" | sed 's|/var/www/||' | sed 's|/web$||')
    if [ -n "$DOMAIN_PATH" ]; then
        COMPOSE_PROJECT_NAME=$(echo "$DOMAIN_PATH" | sed 's|/|-|g')
    else
        COMPOSE_PROJECT_NAME=$(basename "$WEB_DIR")
    fi
    export COMPOSE_PROJECT_NAME
fi

IMAGE_TAG=${IMAGE_TAG:-staging}

echo "🚀 Deploying to staging (image: ghcr.io/saifibnmazhar/umrah-app:${IMAGE_TAG})"

# Ensure .env.staging exists
if [ ! -f "$SCRIPT_DIR/.env.staging" ]; then
    echo "❌ .env.staging file not found"
    echo "   Copy .env.staging.sample to .env.staging and configure it"
    exit 1
fi

# Update IMAGE_TAG in env file
sed -i "s/^IMAGE_TAG=.*/IMAGE_TAG=${IMAGE_TAG}/" "$SCRIPT_DIR/.env.staging"

# Load staging env vars for docker compose
# Extract DB_TYPE for profile selection (Compose --profile needs shell env)
# All other vars are loaded via --env-file flag in the compose() function

# IMPORTANT: Do NOT use:
#   source .env.production
# Laravel .env files are not Bash scripts.

# Read DB_TYPE from .env.staging for profile selection
DB_TYPE=$(grep -E '^DB_TYPE=' "$SCRIPT_DIR/.env.staging" | tail -1 | cut -d'=' -f2- | tr -d '\r' || true)
if [ -z "$DB_TYPE" ]; then
    DB_TYPE=mysql
fi

compose() {
  docker compose \
    --env-file "$SCRIPT_DIR/.env.staging" \
    -f "$SCRIPT_DIR/docker-compose.staging.yml" \
    --profile "${DB_TYPE}" \
    "$@"
}

# Pull the new image
echo "📥 Pulling image..."
compose pull app

# Stop existing containers
echo "🛑 Stopping existing containers..."
compose down

# Remove old images to free disk space (keep latest)
docker image prune -f --filter "until=24h" || true

# Start new containers
echo "▶️  Starting containers..."
compose up -d

# Wait for app to be healthy
echo "⏳ Waiting for containers to start..."
sleep 15

# Run migrations
echo "📦 Running migrations..."
compose exec -T app php artisan migrate --force

# Run seeders (for fresh staging DBs — ignores duplicates)
echo "🌱 Running seeders..."
compose exec -T app php artisan db:seed --force 2>/dev/null || true

# Health check
echo "🏥 Health check..."
STAGING_URL=$(grep '^APP_URL=' "$SCRIPT_DIR/.env.staging" | cut -d'=' -f2-)
if [ -z "$STAGING_URL" ]; then
    APP_PORT=$(grep -E '^APP_PORT=' "$SCRIPT_DIR/.env.staging" | tail -1 | cut -d'=' -f2- | tr -d '\r' || true)
    STAGING_URL="http://localhost:${APP_PORT:-8001}"
fi
curl -sf "${STAGING_URL}/up" && echo "" || {
    echo "❌ Health check failed"
    compose logs --tail=50 app
    exit 1
}

echo "✅ Staging deployment complete"
echo "   URL: ${STAGING_URL}"
