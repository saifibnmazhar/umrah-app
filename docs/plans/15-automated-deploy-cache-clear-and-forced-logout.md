# Plan: Automated Post-Deploy Cache Clear, CSRF Tripwire, and Forced Global Logout

**Status: PLAN ONLY — DO NOT IMPLEMENT until explicitly approved.**

Target: `umrah.binmishaltravels.com` (Laravel 12 + Docker Compose prod stack, `deploy-prod.sh`).

## Problem

After **every** deployment, state-changing requests fail with `419 CSRF token mismatch`:

- Ticket issuance (e.g. `POST /bookings/{booking}/passengers/{passenger}/ticket-issue`) throws CSRF mismatch.
- The visa workflow "just fails" the same way.
- Affected users must **re-login** and the symptom disappears.

Current manual workaround, applied ad hoc after each deploy:

```bash
php artisan config:clear && php artisan view:clear && php artisan route:clear
# then the affected user re-logs in
```

`php artisan optimize:clear` followed by `php artisan optimize` does **not** fix it (re-creating
the caches re-breaks it), which is the behaviour that ties the incident to cache *presence*.

Desired end state: **the deploy does all of this automatically**, and **every logged-in user is
deliberately logged out** at the end of a successful deployment (re-login is now intended product
behaviour, not a workaround).

## Verified findings (evidence)

1. **`config:cache` is exonerated.** Round 1 testing on the live container: the CSRF probe returned
   `403` (pass) in live, `config:cache`d, and `config:cleared` states, and `php artisan config:show
   session` / `config:show app` output was byte-identical cached vs uncached.
2. **`route:cache` cannot break CSRF or drop middleware.** The routing callback built by
   `withRouting()` — including the `then:` callback that registers
   `routes/booking-cancellation.php` (`bootstrap/app.php:22-25`) — is executed while *building* the
   cache (`ApplicationBuilder.php:198-265`), so the cached file contains every route with its
   middleware group intact. At runtime the cached file is required wholesale
   (`RouteServiceProvider.php:56-60, 141-152`); the live route files are not re-read
   (`RouteServiceProvider.php:159-170`).
3. **`view:cache` cannot serve a stale token.** Compiled Blade templates call `csrf_token()` at
   render time; the token is never baked into a compiled view.
4. **Re-login being required means the session itself is gone**, not merely the token. A missing
   session yields a new empty `_token`, so any open page's meta token mismatches on the next POST.
5. **Sessions do NOT live in the cache database.** With `SESSION_CONNECTION` unset, the redis session
   driver is cache-backed (`SessionManager.php:136-145`) and the store's connection is set to `null`,
   which `RedisManager::connection(null)` resolves to the **`'default'` connection**
   (`RedisManager.php:83-97`) = `REDIS_DB` → **Redis DB 0**. The application cache uses
   `REDIS_CACHE_CONNECTION=cache` (`config/cache.php:77`) → `REDIS_CACHE_DB=1`
   (`config/database.php:175`) → **DB 1**.
   ⇒ **`php artisan cache:clear` does NOT log anyone out** (`RedisStore::flush()` calls `flushdb()`
   on the cache connection, `RedisStore.php:282-287`). A dedicated session-aware command is required.
6. **`app/` contains no direct `Redis::` usage**, so Redis DB 0 holds session keys only — flushing
   that DB is effectively surgical.
7. **Redis has no volume** (`docker-compose.prod.yml:85-117`; only `app_storage`, `db_data`,
   `postgres_data` are declared) and runs `--maxmemory 128mb --maxmemory-policy allkeys-lru`. Any
   Redis recreation or restart loses all sessions, and LRU pressure can evict them.
8. **Watchtower can restart the app container outside `deploy-prod.sh`** (`com.centurylinklabs.watchtower`
   label, `docker-compose.prod.yml:24-25`), so anything that must happen on every boot belongs in the
   entrypoint, not only in the deploy script.
9. **Env sample drift**: `.env.production.sample:44` says `SESSION_DRIVER=database` while production
   runs `redis`; `:48` sets `SESSION_DOMAIN=umrah.binmishaltravels.com` while production resolves
   `session.domain = null`.

## Goals

- G1. A successful `deploy-prod.sh` run leaves the app in the known-good state: **no** config, route,
  or view cache.
- G2. No manual artisan commands are needed after a deploy.
- G3. Container boots (including Watchtower restarts) never build caches, so no boot can
  re-introduce the incident.
- G4. A deploy that leaves CSRF broken is **detected and reported loudly** during the deploy.
- G5. After a successful deploy, **every logged-in user is logged out** and must re-login.

## Non-goals

- Not fixing the underlying session-loss cause (see §Open follow-ups). This plan makes the deploy
  deterministic and the symptom invisible to users; it does not add Redis persistence.
- Not adding `optimize`/`config:cache` performance tuning back.
- Not changing the login, ticket, or visa workflows themselves.

## Design decisions (confirmed with the user)

- **D1.** The three clears live in `deploy-prod.sh` (runs after the health check), *not* in the
  entrypoint. Reason: the entrypoint should not build state that the deploy then has to tear down.
- **D2.** The entrypoint stops building caches entirely. This is the durable fix and closes the
  Watchtower hole (finding 8). Until the next image build, D1 keeps the deploy path correct.
- **D3.** The CSRF tripwire is **warn-only**: on `419` it prints a detailed diagnostic (including the
  manual fix commands) and the deploy **continues**. It must never abort a deploy.
- **D4.** Forced logout is an explicit, intended post-deploy step implemented as a driver-aware
  Artisan command, run **last** so it only happens after a fully successful deploy.
- **D5.** Order matters: the probe runs **before** the session flush, otherwise the probe's own
  session would be destroyed and the probe would report a false `419`.
- **D6.** Users with open forms lose unsaved work on deploy. Accepted: it is the intended behaviour
  and matches what already happens in practice.
- **D7.** The clears deliberately do **not** use `|| true` (the script runs `set -Eeuo pipefail`): a
  failed clear must abort the deploy, not silently leave the broken state in place.

## Steps (implementation order)

### 1. `docker/entrypoint.sh` — stop building caches

Delete lines 16-18:

```sh
php artisan config:cache --no-interaction || true
php artisan route:cache --no-interaction || true
php artisan view:cache --no-interaction || true
```

Keep everything else untouched: `set -e`, the `mkdir`/`chown` block (lines 4-14), the `MIGRATE`
guard (lines 20-26), and `exec "$@"`. Add a short comment stating that caches are intentionally not
built at boot because stale cached config/routes/views have caused post-deploy `419`s, and that
`deploy-prod.sh` clears them on deploy.

Effect: takes effect only after the image is rebuilt. D1 covers the gap in the meantime.

### 2. `app/Services/SessionInvalidator.php` (new)

Thin, testable service that invalidates every active session for the **currently configured**
session driver and returns a small stats array (`['driver' => ..., 'store' => ..., 'invalidated' => n]`).

```php
namespace App\Services;

use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\FileSessionHandler;

class SessionInvalidator
{
    public function invalidate(): array
    {
        $handler = app('session')->driver()->getHandler();

        if ($handler instanceof CacheBasedSessionHandler) {
            // redis / memcached / dynamodb / apc: flush the store the sessions
            // actually occupy (for redis: the 'default' connection = DB 0).
            $store = $handler->getCache()->getStore();
            $store->flush();

            return ['driver' => config('session.driver'), 'store' => $store::class, 'invalidated' => null];
        }

        if ($handler instanceof DatabaseSessionHandler) {
            $deleted = DB::connection(config('session.connection'))
                ->table(config('session.table'))->delete();

            return ['driver' => 'database', 'store' => config('session.table'), 'invalidated' => $deleted];
        }

        if ($handler instanceof FileSessionHandler) {
            // delete every file in config('session.files')
            ...
        }

        // cookie / array / null / unknown: nothing persistent to invalidate
        return ['driver' => config('session.driver'), 'store' => null, 'invalidated' => 0];
    }
}
```

Notes / constraints:
- Do **not** reach for `Cache::flush()` or `php artisan cache:clear` — those flush the cache DB
  (DB 1), not the session DB (finding 5).
- Keep it driver-aware so a future switch to `SESSION_DRIVER=database` keeps working.
- Unknown handler types must return a no-op result rather than throwing, so a deploy can never die
  on this step.

### 3. `app/Console/Commands/FlushSessions.php` (new)

Auto-discovered (same as the existing commands in `app/Console/Commands/`; no registration needed —
`ticket-fares:expire` is scheduled in `bootstrap/app.php:45` with no extra wiring).

```php
protected $signature = 'sessions:flush {--force : Skip the confirmation prompt}';
protected $description = 'Invalidate all active sessions, forcing every user to log in again';
```

- Without `--force`, confirm interactively (this logs everyone out; guard against a stray manual run).
- Resolve `SessionInvalidator`, print a one-line result including driver/store, return
  `Command::SUCCESS` / `Command::FAILURE`.

### 4. `deploy-prod.sh` — CSRF probe helper (warn-only)

Add a `csrf_probe()` function next to `compose()` (after line 79). It runs entirely inside the
container and prints either an HTTP status code or `PROBE_ERROR`:

```sh
csrf_probe() {
  compose exec -T app sh -c '
    headers=$(curl -sS -D - -o /tmp/_csrf_probe.html http://localhost/login 2>/dev/null) || { echo PROBE_ERROR; exit 0; }
    token=$(sed -n "s/.*name=\"csrf-token\" content=\"\([^\"]*\)\".*/\1/p" /tmp/_csrf_probe.html | head -n 1)
    cookie=$(printf "%s\n" "$headers" | sed -n "s/^[Ss]et-[Cc]ookie: \([^;]*\).*/\1/p" | tr -d "\r" | head -n 1)
    rm -f /tmp/_csrf_probe.html
    if [ -z "$token" ] || [ -z "$cookie" ]; then echo PROBE_ERROR; exit 0; fi
    curl -sS -o /dev/null -w "%{http_code}" -X POST http://localhost/login \
      -H "X-CSRF-TOKEN: $token" \
      -H "Cookie: $cookie" \
      -H "Content-Type: application/x-www-form-urlencoded" \
      --data "email=deploy-probe@example.invalid&password=deploy-probe" 2>/dev/null || echo PROBE_ERROR
  ' 2>/dev/null || echo "PROBE_ERROR"
}
```

Implementation constraints (all learned the hard way during diagnosis):
- `curl` is available in the image (the existing healthcheck uses it).
- The `Cookie:` header must be passed **manually**: `SESSION_SECURE_COOKIE=true`, and curl refuses to
  send `Secure` cookies over plain `http://`. Reading the cookie from the `Set-Cookie` response header
  (rather than hardcoding `umrah-app-session`) keeps it correct if the cookie name ever changes.
- Use `sed`, never `grep -P` — the container's `grep` is busybox.
- `set -e` safety: the function must never exit non-zero; the caller treats any failure as
  `PROBE_ERROR`.

Host-side interpretation (warn-only per D3):

| Result | Action |
| --- | --- |
| `200` / `302` / `401` / `403` / `422` / `429` | `OK` — CSRF verification passed (invalid credentials and login throttling are expected non-419 outcomes) |
| `419` | `WARNING` + "POST requests will fail with 419 until caches are cleared" + the three manual `*:clear` commands; deploy continues |
| `PROBE_ERROR` | `WARNING` naming which part failed (no meta token / no `Set-Cookie` / curl failed); deploy continues |

### 5. `deploy-prod.sh` — post-health block

Insert a new section **after** the application health-wait loop (i.e. after line 319, before
"Setting Laravel permissions" at line 322):

```sh
# Clear config/route/view caches so a deploy never leaves the app in the state
# that produces 419 CSRF mismatches. Fails the deploy if a clear errors.
compose exec -T app php artisan config:clear --no-interaction
compose exec -T app php artisan route:clear  --no-interaction
compose exec -T app php artisan view:clear   --no-interaction

# CSRF tripwire (warn only, never aborts)
PROBE_STATUS="$(csrf_probe)"
# ... case statement per the table above ...
```

Then keep the existing `chown` block (lines 330-334) and `migrate` block (lines 346-366) as they are,
and add a **final** section after migrations, just before "Final status" (line 368):

```sh
# Force every logged-in user to re-login (intended post-deploy behaviour).
compose exec -T app php artisan sessions:flush --force --no-interaction
```

Final deploy order: `pull` → `stop app` → `up db` → wait db → `up redis` → `up app` → wait health →
**clear caches** → **CSRF probe (warn)** → chown → migrate → **`sessions:flush`** → status.

### 6. Tests

- `tests/Unit/SessionInvalidatorTest.php` — new. `phpunit.xml` gives `SESSION_DRIVER=array` and
  `CACHE_STORE=array` and a real MySQL `umrah_test` database, so the test can drive each branch by
  overriding config at runtime:
  - redis/cache-backed branch: set `session.driver=redis`, keep the `array` cache store, assert the
    backing store's `flush()` ran (spy/partial mock on the store) and the returned stats shape.
  - database branch: create a `sessions` table in the test DB, insert rows, assert the delete count.
  - file branch: point `session.files` at a temp dir with files, assert they are removed.
  - unknown/no-op branch (e.g. `array` driver) returns `invalidated = 0` and does not throw.
- Extend `tests/Unit/SecureSessionCookieTest.php`? No — instead add assertions in a new unit test
  that `docker/entrypoint.sh` no longer contains `config:cache` / `route:cache` / `view:cache`, and
  that `deploy-prod.sh` clears all three and invokes `sessions:flush` after them. These are cheap
  textual guards against the exact regression being fixed (same style as the existing
  `UploadSizeLimitTest.php` / `SecureSessionCookieTest.php`).

### 7. Documentation

- `DEPLOYMENT.md:225-231` — rewrite "Reset Laravel cache": the deploy now clears config/route/view
  automatically; the manual equivalent is the three `*:clear` commands (never `config:cache` /
  `route:cache` / `optimize` in production); document `php artisan sessions:flush` and the warning
  that it logs everyone out.
- `docs/03-dev-environment.md:174-195` — delete "Cache for production (optimization)" and the note
  claiming the entrypoint caches on startup; replace with a short "why we do not cache in
  production" paragraph and point at the deploy script. Keep the generic "Clear specific caches"
  section, but annotate `cache:clear` with the warning that it does **not** clear sessions.
- `.env.production.sample:44` — `SESSION_DRIVER=redis` to match production reality (there is no
  `sessions` table migration in this repo, so `database` would break sessions outright).
- `.env.production.sample:48` — reconcile `SESSION_DOMAIN` with production (`null`) so the sample
  stops implying a cookie-domain dependency that does not exist.

## Verification (before/while deploying)

1. `php artisan test --filter=SessionInvalidator` and the new entrypoint/deploy guard tests.
2. `bash -n deploy-prod.sh` (syntax) and `sh -n docker/entrypoint.sh`.
3. Dry-run the probe block against a healthy container and confirm it prints a non-419 status.
4. `php artisan sessions:flush --force` on a staging/local Redis: confirm `redis-cli -n 0 --scan`
   shows session keys gone while `redis-cli -n 1 DBSIZE` (app cache) is untouched.
5. On the first real deploy, confirm from the log: clears ran → probe status line → migrations →
   "sessions flushed" line, and that a logged-in browser is redirected to `/login`.

## Risks and mitigations

| Risk | Mitigation |
| --- | --- |
| A clear fails mid-deploy | `set -Eeuo pipefail` aborts before users are told anything; the app is still up and the clears are re-runnable by hand |
| Probe false positive (throttling → `429`, layout change → no meta token) | Warn-only, and any non-419 is treated as pass; message states exactly what was seen |
| Flushing DB 0 could drop non-session keys | `app/` has no `Redis::` usage (verified), so DB 0 is session-only; a future default-connection user must revisit this step |
| Deploys during active work discard unsaved forms | Intended (D6); deploy announcements should warn staff |
| Entrypoint change needs a rebuild | `deploy-prod.sh` clears are live immediately, so behaviour is correct before the new image ships |
| `sessions:flush` run by hand accidentally | Interactive confirmation unless `--force`; deploy always passes `--force` |

## Acceptance criteria

1. `docker/entrypoint.sh` contains no cache-building artisan calls.
2. After `deploy-prod.sh` completes, `bootstrap/cache/config.php` and `bootstrap/cache/routes-v7.php`
   do not exist, and `storage/framework/views` has been cleared.
3. The deploy log contains a CSRF probe status line; a `419` prints a detailed warning but the deploy
   still finishes.
4. Immediately after a successful deploy, any previously logged-in browser session is anonymous and
   is redirected to the login page.
5. `php artisan test` passes (including the new tests), `bash -n deploy-prod.sh` is clean.

## Open follow-ups (explicitly out of scope here)

- **Why sessions vanish after deploys.** Redis has no volume and runs `allkeys-lru` at 128mb
  (`docker-compose.prod.yml:85-117`), and `deploy-prod.sh:231` runs `compose up -d redis` on every
  deploy — if the rendered Redis config ever changes, the container is recreated and every session
  is lost. Recommended hardening: add a `redis_data` volume + `appendonly yes`, raise `maxmemory`,
  and/or move sessions to MySQL (requires adding a `sessions` table migration — none exists today).
  Until then, users may occasionally be logged out at random times.
- **Untested combination.** `route:cache` / `view:cache` with a *pre-existing* session was never
  exercised (findings 2 and 3 argue they cannot cause a token mismatch). If a 419 ever reappears,
  run that probe bisect before touching the deploy script again.
- **Failure forensics.** Consider a `TokenMismatchException` render hook in `bootstrap/app.php`
  logging session id, user id, route, and cache-state flags, so any future incident is diagnosable
  from `storage/logs` without needing to be present at deploy time.
