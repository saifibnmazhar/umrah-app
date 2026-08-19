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

IMAGE_TAG=${IMAGE_TAG:-staging}

echo "🚀 Deploying to staging (image: ghcr.io/saifibnmazhar/umrah-app:${IMAGE_TAG})"

# Ensure .env.staging exists
if [ ! -f .env.staging ]; then
    echo "❌ .env.staging file not found"
    echo "   Copy .env.staging.sample to .env.staging and configure it"
    exit 1
fi

# Update IMAGE_TAG in env file
sed -i "s/^IMAGE_TAG=.*/IMAGE_TAG=${IMAGE_TAG}/" .env.staging

# Load staging env vars for docker compose
set -a
source .env.staging
set +a

# Set defaults for docker compose project isolation
export COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME:-umrah-app-staging}

# Pull the new image
echo "📥 Pulling image..."
docker compose -f docker-compose.staging.yml pull

# Stop existing containers
echo "🛑 Stopping existing containers..."
docker compose -f docker-compose.staging.yml down

# Remove old images to free disk space (keep latest)
docker image prune -f --filter "until=24h" || true

# Start new containers
echo "▶️  Starting containers..."
docker compose -f docker-compose.staging.yml up -d

# Wait for app to be healthy
echo "⏳ Waiting for containers to start..."
sleep 15

# Run migrations
echo "📦 Running migrations..."
docker compose -f docker-compose.staging.yml exec -T app php artisan migrate --force

# Run seeders (for fresh staging DBs — ignores duplicates)
echo "🌱 Running seeders..."
docker compose -f docker-compose.staging.yml exec -T app php artisan db:seed --force 2>/dev/null || true

# Health check
echo "🏥 Health check..."
STAGING_URL=$(grep '^APP_URL=' .env.staging | cut -d'=' -f2-)
if [ -z "$STAGING_URL" ]; then
    STAGING_URL="http://localhost:${APP_PORT:-8001}"
fi
curl -sf "${STAGING_URL}/up" && echo "" || {
    echo "❌ Health check failed"
    docker compose -f docker-compose.staging.yml logs --tail=50 app
    exit 1
}

echo "✅ Staging deployment complete"
echo "   URL: ${STAGING_URL}"
