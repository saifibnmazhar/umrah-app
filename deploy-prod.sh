#!/bin/bash

set -Eeuo pipefail

# ============================================================
# Generic Laravel Docker deployment script
#
# Expected structure:
#
# /var/www/<domain>/web/
# ├── deploy-prod.sh
# ├── docker-compose.prod.yml
# └── .env.production
#
# The same script can be used for every Laravel project.
#
# This file mirrors the copy that actually runs on the production
# server (/var/www/umrah.binmishaltravels.com/web). The server
# directory is NOT a git clone, so changes have to be applied there
# by hand or by copying this file over.
# ============================================================

# ------------------------------------------------------------
# Paths
# ------------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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

compose() {
  docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    "$@"
}

# Verify that CSRF verification actually works in the running container.
#
# The probe itself lives in the image (/usr/local/bin/csrf-probe.sh) so this
# script, a manual `docker exec`, and supervisord's every-boot run all share a
# single implementation. It prints its own verdict (passed, a 419 warning with
# fix hints, or unverified) and always exits 0.
#
# The `test -f` guard handles an image that predates the script — including a
# rollback to an older IMAGE_TAG: then we warn and continue rather than failing
# the deploy at the last step. The second guard handles exec itself failing
# (container gone), which must not abort the run either.
csrf_probe() {
  if compose exec -T app test -f /usr/local/bin/csrf-probe.sh; then
    compose exec -T app /usr/local/bin/csrf-probe.sh http://localhost \
      docker-compose.prod.yml ||
      echo "WARNING: the CSRF probe could not be executed in the container."
  else
    echo "WARNING: /usr/local/bin/csrf-probe.sh is missing from the running"
    echo "container (image predates the probe, or exec failed)."
    echo "CSRF handling is unverified."
  fi
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
  grep -E '^MIGRATE=' "$ENV_FILE" |\
    tail -1 |\
    cut -d '=' -f2- |\
    tr -d '\r' |\
    tr '[:upper:]' '[:lower:]' ||\
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
#
# The entrypoint runs its chown and its own migrations BEFORE
# supervisord/nginx start, so the healthcheck only turns "healthy"
# after that. Waiting here guarantees every artisan command below
# runs against a fully started application.
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
# Config, route and view caches are never built by the entrypoint, and a
# deployment must never leave any of them behind: serving cached config/routes/
# views is what produced post-deploy 419 CSRF mismatches.
#
# No `|| true` here on purpose — the script runs with `set -Eeuo pipefail`, so a
# failed clear aborts the deploy loudly instead of leaving the broken state in
# production.
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Clearing Laravel caches"
echo "========================================"

compose exec -T app \
  php artisan config:clear --no-interaction

compose exec -T app \
  php artisan route:clear --no-interaction

compose exec -T app \
  php artisan view:clear --no-interaction

# ------------------------------------------------------------
# CSRF tripwire
#
# Performs a real CSRF-protected POST from inside the container and reports the
# result. This never aborts the deploy (the application is already live by this
# point); a 419 is surfaced as a loud warning with the manual fix instead.
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Verifying CSRF handling"
echo "========================================"

# The probe prints its own verdict: passed, a 419 warning with the manual
# fix, or "unverified". It never fails, so this call cannot abort the deploy.
csrf_probe

# ------------------------------------------------------------
# Log everyone out
#
# Runs last so it only happens after a fully successful deploy. Every logged-in
# user is redirected to the login page afterwards — this is intended behaviour,
# not a side effect.
#
# `sessions:flush` targets the store the active session driver actually uses.
# For the redis driver that is the "default" connection (REDIS_DB), NOT the
# cache connection, so `php artisan cache:clear` would not work here.
#
# Guarded because sessions:flush only exists in images built after this
# feature: an image that predates it (or a rollback to an older IMAGE_TAG)
# warns instead of failing the deploy at the last step.
# ------------------------------------------------------------

echo ""
echo "========================================"
echo " Forcing all users to log in again"
echo "========================================"

if compose exec -T app test -f /var/www/html/app/Console/Commands/FlushSessions.php; then
  compose exec -T app \
    php artisan sessions:flush --force --no-interaction
else
  echo "WARNING: this image predates sessions:flush — nobody was logged out."
fi

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
