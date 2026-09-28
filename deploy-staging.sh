#!/bin/bash

set -Eeuo pipefail

# ============================================================
# Generic Laravel Docker deployment script for STAGING
# Mirrors deploy-prod.sh — uses docker-compose.staging.yml
#
# Expected structure:
#
# /var/www/staging-umrah.binmishaltravels.com/web/
# ├── deploy-staging.sh
# ├── docker-compose.staging.yml
# └── .env.staging
#
# This file mirrors the copy that actually runs on the staging server.
# That directory is NOT a git clone, so changes have to be applied there
# by hand or by copying this file over.
# ============================================================

# ------------------------------------------------------------
# Paths
# ------------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.staging.yml"
ENV_FILE="${SCRIPT_DIR}/.env.staging"

# ------------------------------------------------------------
# Validate required files
# ------------------------------------------------------------

if [[ ! -f "$COMPOSE_FILE" ]]; then
  echo "ERROR: docker-compose.staging.yml not found:"
  echo "$COMPOSE_FILE"
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: .env.staging not found:"
  echo "$ENV_FILE"
  echo ""
  echo "Copy .env.staging.sample to .env.staging and configure it."
  exit 1
fi

# ------------------------------------------------------------
# Docker Compose helper
# ------------------------------------------------------------

compose() {
  docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    "$@"
}

# ------------------------------------------------------------
# Load staging environment variables
# ------------------------------------------------------------
# Source the env file so bash can access variables like MIGRATE
# .env.staging with quoted values (APP_NAME="Umrah App Staging")
# is safe to source.
set -a
# shellcheck source=/dev/null
source "$ENV_FILE"
set +a

# ------------------------------------------------------------
# Deployment information
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Laravel Docker Deployment (Staging)"
echo "========================================"
echo "Directory : $SCRIPT_DIR"
echo "Compose   : $COMPOSE_FILE"
echo "Env file  : $ENV_FILE"
echo "========================================"
echo ""

# ------------------------------------------------------------
# Validate Compose configuration
# ------------------------------------------------------------

echo "Validating Docker Compose configuration..."

compose config --quiet

echo "Compose configuration is valid."

# ------------------------------------------------------------
# Show project name
# ------------------------------------------------------------

PROJECT_NAME="$(
  compose config --format json |
    python3 -c '
import json
import sys

data = json.load(sys.stdin)
print(data.get("name", "unknown"))
'
)"

echo ""
echo "Docker project: $PROJECT_NAME"
echo ""

# ------------------------------------------------------------
# Pull latest application image
# ------------------------------------------------------------

echo "========================================"
echo " Pulling application image"
echo "========================================"

compose pull app

# ------------------------------------------------------------
# Stop application
#
# We intentionally use `stop` instead of `down`.
#
# This preserves:
# - volumes
# - networks
# - containers
#
# Most importantly, it only affects THIS Compose project.
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Stopping application"
echo "========================================"

compose stop app || true

# ------------------------------------------------------------
# Start database
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Starting database"
echo "========================================"

compose up -d db

# ------------------------------------------------------------
# Wait for database health
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Waiting for database"
echo "========================================"

DB_WAIT_TIMEOUT="${DB_WAIT_TIMEOUT:-120}"
DB_WAIT_INTERVAL="${DB_WAIT_INTERVAL:-3}"

ELAPSED=0

while true; do

  DB_CONTAINER="$(compose ps -q db)"

  if [[ -z "$DB_CONTAINER" ]]; then
    echo "Database container has not been created yet."
  else

    DB_STATUS="$(
      docker inspect \
        --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}no-healthcheck{{end}}' \
        "$DB_CONTAINER" \
        2>/dev/null || true
    )"

    if [[ "$DB_STATUS" == "healthy" ]]; then
      echo "Database is healthy."
      break
    fi

    if [[ "$DB_STATUS" == "no-healthcheck" ]]; then
      echo "WARNING: Database has no healthcheck."
      break
    fi

    echo "Database status: ${DB_STATUS:-starting}"
  fi

  if ((ELAPSED >= DB_WAIT_TIMEOUT)); then
    echo ""
    echo "ERROR: Database did not become healthy within ${DB_WAIT_TIMEOUT} seconds."
    echo ""

    compose ps
    exit 1
  fi

  sleep "$DB_WAIT_INTERVAL"
  ELAPSED=$((ELAPSED + DB_WAIT_INTERVAL))
done

# ------------------------------------------------------------
# Start Redis
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Starting Redis"
echo "========================================"

compose up -d redis

# ------------------------------------------------------------
# Start application
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Starting application"
echo "========================================"

compose up -d app

# ------------------------------------------------------------
# Wait for application health
#
# The entrypoint runs chown and its own migrations BEFORE
# supervisord/nginx start. The healthcheck only turns "healthy"
# after that, so waiting here guarantees the artisan commands
# below never run against a half-started app.
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Waiting for application"
echo "========================================"

APP_WAIT_TIMEOUT="${APP_WAIT_TIMEOUT:-300}"
APP_WAIT_INTERVAL="${APP_WAIT_INTERVAL:-5}"

ELAPSED=0

while true; do

  APP_CONTAINER="$(compose ps -q app)"

  if [[ -z "$APP_CONTAINER" ]]; then
    echo "ERROR: Application container was not created."
    compose ps
    exit 1
  fi

  APP_STATUS="$(
    docker inspect \
      --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}no-healthcheck{{end}}' \
      "$APP_CONTAINER" 2>/dev/null || true
  )"

  if [[ "$APP_STATUS" == "healthy" ]]; then
    echo "Application is healthy."
    break
  fi

  if [[ "$APP_STATUS" == "no-healthcheck" ]]; then
    echo "WARNING: Application has no healthcheck."
    break
  fi

  # Fail fast on an entrypoint crash-loop (e.g. a failing migration)
  # instead of waiting out the full timeout with endless "starting".
  RESTARTS="$(docker inspect --format '{{.RestartCount}}' "$APP_CONTAINER" 2>/dev/null || echo 0)"

  if (( RESTARTS >= 3 )); then
    echo ""
    echo "ERROR: application container has restarted ${RESTARTS}x — entrypoint is crash-looping (usually a migration error)."
    echo ""
    compose logs --tail=60 app || true
    exit 1
  fi

  echo "Application status: ${APP_STATUS:-starting}"

  if ((ELAPSED >= APP_WAIT_TIMEOUT)); then
    echo ""
    echo "ERROR: Application did not become healthy within ${APP_WAIT_TIMEOUT} seconds."
    echo ""
    compose ps
    exit 1
  fi

  sleep "$APP_WAIT_INTERVAL"
  ELAPSED=$((ELAPSED + APP_WAIT_INTERVAL))
done

# ------------------------------------------------------------
# Clear Laravel caches
#
# No `|| true` on purpose: the script runs with `set -Eeuo pipefail`,
# so a failed clear aborts loudly instead of shipping broken state.
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Clearing Laravel caches"
echo "========================================"

compose exec -T app php artisan config:clear --no-interaction
compose exec -T app php artisan route:clear --no-interaction
compose exec -T app php artisan view:clear --no-interaction

# ------------------------------------------------------------
# Fix Laravel permissions
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Setting Laravel permissions"
echo "========================================"

compose exec -T app \
  chown -R www-data:www-data \
  storage \
  bootstrap/cache ||
  true

# ------------------------------------------------------------
# Run migrations if enabled
#
# In .env.staging:
#
# MIGRATE=true
#
# Otherwise migrations are skipped.
# ------------------------------------------------------------

if [[ "$MIGRATE" == "true" ]]; then

  echo ""
  echo "========================================"
  echo " Running Laravel migrations"
  echo "========================================"

  compose exec -T app \
    php artisan migrate --force

else

  echo ""
  echo "========================================"
  echo " Skipping Laravel migrations"
  echo "========================================"
  echo "Set MIGRATE=true in .env.staging to enable."

fi

# ------------------------------------------------------------
# Log everyone out
#
# Runs last, only after a fully successful deploy. Guarded because
# sessions:flush only exists in images built after this feature —
# older images warn instead of failing the deploy.
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Forcing all users to log in again"
echo "========================================"

if compose exec -T app test -f /var/www/html/app/Console/Commands/FlushSessions.php; then
  compose exec -T app php artisan sessions:flush --force --no-interaction
else
  echo "WARNING: this image predates sessions:flush — nobody was logged out."
fi

# ------------------------------------------------------------
# Final status
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Staging deployment completed successfully"
echo "========================================"
echo ""

compose ps

echo ""
echo "Docker project: $PROJECT_NAME"
echo ""
