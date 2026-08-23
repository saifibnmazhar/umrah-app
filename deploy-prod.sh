#!/bin/bash

set -Eeuo pipefail

# ============================================================
# Generic Laravel Docker deployment script
#
# Expected structure:
#
# /var/www/<domain>/web/
# ├── deploy.sh
# ├── docker-compose.prod.yml
# └── .env.production
#
# The same script can be used for every Laravel project.
# ============================================================

# ------------------------------------------------------------
# Paths
# ------------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Auto-derive COMPOSE_PROJECT_NAME from the web directory path if not set
# /var/www/umrah.binmishaltravels.com/web → umrah-binmishaltravels-com
if [ -z "${COMPOSE_PROJECT_NAME:-}" ]; then
  WEB_DIR="$SCRIPT_DIR"
  DOMAIN_PATH=$(echo "$WEB_DIR" | sed 's|/var/www/||' | sed 's|/web$||')
  if [ -n "$DOMAIN_PATH" ]; then
    COMPOSE_PROJECT_NAME=$(echo "$DOMAIN_PATH" | sed 's|[/.]|-|g')
  else
    COMPOSE_PROJECT_NAME=$(basename "$WEB_DIR")
  fi
  export COMPOSE_PROJECT_NAME
fi

COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.prod.yml"
ENV_FILE="${SCRIPT_DIR}/.env.production"

# ------------------------------------------------------------
# Validate required files
# ------------------------------------------------------------

if [[ ! -f "$COMPOSE_FILE" ]]; then
  echo "ERROR: docker-compose.prod.yml not found:"
  echo "$COMPOSE_FILE"
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: .env.production not found:"
  echo "$ENV_FILE"
  exit 1
fi

# ------------------------------------------------------------
# Docker Compose helper
#
# IMPORTANT:
# Do NOT use:
#
#   source .env.production
#
# Laravel .env files are not Bash scripts.
# ------------------------------------------------------------

# Read DB_TYPE from .env.production for profile selection
DB_TYPE=$(grep -E '^DB_TYPE=' "$ENV_FILE" | tail -1 | cut -d'=' -f2- | tr -d '\r' || true)
if [ -z "$DB_TYPE" ]; then
  DB_TYPE=mysql
fi

compose() {
  docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    --profile "${DB_TYPE}" \
    "$@"
}

# ------------------------------------------------------------
# Deployment information
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Laravel Docker Deployment"
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
# In .env.production:
#
# MIGRATE=true
#
# Otherwise migrations are skipped.
# ------------------------------------------------------------

MIGRATE_VALUE="$(
  grep -E '^MIGRATE=' "$ENV_FILE" |
    tail -1 |
    cut -d '=' -f2- |
    tr -d '\r' |
    tr '[:upper:]' '[:lower:]' ||
    true
)"

if [[ "$MIGRATE_VALUE" == "true" ]]; then

  echo "Running Laravel migrations"
  compose exec -T app \
    php artisan migrate --force

else

  echo "Skipping Laravel migrations."
  echo "Set MIGRATE=true in .env.production to enable."

fi

# ------------------------------------------------------------
# Wait for application health
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Checking application health"
echo "========================================"

APP_WAIT_TIMEOUT="${APP_WAIT_TIMEOUT:-120}"
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
      "$APP_CONTAINER" \
      2>/dev/null || true
  )"

  if [[ "$APP_STATUS" == "healthy" ]]; then
    echo "Application is healthy."
    break
  fi

  if [[ "$APP_STATUS" == "no-healthcheck" ]]; then
    echo "WARNING: Application has no healthcheck."
    break
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
# Final status
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Deployment completed successfully"
echo "========================================"
echo ""

compose ps

echo ""
echo "Docker project: $PROJECT_NAME"
echo ""
