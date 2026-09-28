#!/bin/sh
# CSRF probe - verifies that a real CSRF-protected POST is accepted by this app.
#
# Runs in two places:
#   1. From supervisord on every container start (covers restarts that never
#      run a deploy script: a server reboot, `docker restart`, `docker compose
#      restart`).
#   2. From `deploy-prod.sh`, after the health wait and cache clears.
#      `deploy-staging.sh` carries its own inline copy of the same logic.
#
# It reports what it found and ALWAYS exits 0: a probe must never take down a
# container or fail a deployment. The tripwire is informational by design -
# at that point the application is already live.
#
# Usage: csrf-probe.sh [BASE_URL] [COMPOSE_FILE]
#   BASE_URL      defaults to http://localhost (probed from inside the container)
#   COMPOSE_FILE  optional; when set, the 419 fix hints print the exact host
#                 command `docker compose -f <file> exec app ...`
#
# Environment:
#   CSRF_PROBE=false    skip the probe entirely (useful for local dev)
#   CSRF_PROBE_WAIT=n   seconds to wait for readiness first (default 120)
#
# Notes:
# - The session cookie is passed back explicitly with a Cookie header because
#   SESSION_SECURE_COOKIE=true and curl refuses to send Secure cookies over
#   plain http. The cookie is read from the Set-Cookie response header, so a
#   renamed session cookie does not silently break the probe.
# - The token comes from the login page's csrf-token meta tag.
# - sed is used instead of `grep -P` because the container's grep is busybox.

set -eu

BASE_URL="${1:-http://localhost}"
COMPOSE_FILE="${2:-}"
WAIT="${CSRF_PROBE_WAIT:-120}"
TMP_HTML="/tmp/_csrf_probe.$$.html"

cleanup() {
    rm -f "$TMP_HTML"
}
trap cleanup EXIT

warn_unverified() {
    echo "WARNING: CSRF probe could not complete (result: ${1:-empty})."
    echo "${2:-CSRF handling is unverified.}"
}

if [ "${CSRF_PROBE:-}" = "false" ]; then
    echo "CSRF probe skipped (CSRF_PROBE=false)."
    exit 0
fi

# Wait until the web server answers, so supervisord can start this program
# right alongside nginx instead of racing it.
ready=0
elapsed=0
while [ "$elapsed" -le "$WAIT" ]; do
    if curl -fsS -o /dev/null "${BASE_URL}/up" 2>/dev/null; then
        ready=1
        break
    fi
    sleep 2
    elapsed=$((elapsed + 2))
done

if [ "$ready" -ne 1 ]; then
    warn_unverified "PROBE_ERROR" \
        "Could not reach ${BASE_URL}/up within ${WAIT}s. CSRF handling is unverified."
    exit 0
fi

# One request only: the token and the session cookie must come from the same
# response, otherwise the POST would use a session that never saw the token.
headers=$(curl -sS -D - -o "$TMP_HTML" "${BASE_URL}/login" 2>/dev/null) || {
    warn_unverified "PROBE_ERROR" "Could not fetch ${BASE_URL}/login."
    exit 0
}

token=$(sed -n 's/.*name="csrf-token" content="\([^"]*\)".*/\1/p' "$TMP_HTML" | head -n 1)
cookie=$(printf '%s\n' "$headers" |
    tr -d '\r' |
    sed -n 's/^[Ss]et-[Cc]ookie:[[:space:]]*\([^;]*\).*/\1/p' |
    awk 'NF { if (out != "") out = out "; "; out = out $0 } END { print out }')

if [ -z "$token" ] || [ -z "$cookie" ]; then
    warn_unverified "PROBE_ERROR" \
        "Could not extract the csrf-token meta tag or the session cookie from ${BASE_URL}/login."
    exit 0
fi

if ! status=$(curl -sS -o /dev/null -w '%{http_code}' -X POST "${BASE_URL}/login" \
    -H "X-CSRF-TOKEN: ${token}" \
    -H "Cookie: ${cookie}" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    --data "email=deploy-probe@example.invalid&password=deploy-probe" 2>/dev/null); then
    status="PROBE_ERROR"
fi

case "$status" in
    200|302|401|403|422|429)
        echo "CSRF probe passed (HTTP ${status})."
        ;;
    419)
        echo ""
        echo "WARNING: CSRF probe returned 419 - CSRF token mismatch."
        echo ""
        echo "POST requests (ticket issuance, visa workflow) will fail for users."
        echo ""
        echo "If cached config/routes/views came back somehow, clear them:"
        echo ""
        if [ -n "$COMPOSE_FILE" ]; then
            echo "  docker compose -f ${COMPOSE_FILE} exec app php artisan config:clear"
            echo "  docker compose -f ${COMPOSE_FILE} exec app php artisan route:clear"
            echo "  docker compose -f ${COMPOSE_FILE} exec app php artisan view:clear"
        else
            echo "  php artisan config:clear    (run inside the app container)"
            echo "  php artisan route:clear     (run inside the app container)"
            echo "  php artisan view:clear      (run inside the app container)"
        fi
        echo ""
        echo "If the caches are already clear, affected users only need to log in again:"
        echo "their session cookie points at a session that no longer exists."
        echo ""
        echo "Investigate afterwards; deployment and boot continue."
        ;;
    *)
        warn_unverified "$status" \
            "Could not complete a CSRF-protected POST to ${BASE_URL}/login. CSRF handling is unverified."
        ;;
esac

exit 0
