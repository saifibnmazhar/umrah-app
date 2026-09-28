# Plan: Automated Post-Deploy Cache Clear and Forced Global Logout

**Status: IMPLEMENTED (Phases 1-4) + follow-up implemented (419 keep-alive).** See
[Round 2](#round-2-the-419s-were-idle-session-expiry-not-caches) for the corrected root cause.

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

> **Round 2 correction.** The premise above — that *cache presence* is what breaks CSRF — did not
> survive the deploy. The clears shipped, `bootstrap/cache/config.php` and `routes-*.php` were absent
> afterwards, and users still hit 419. See Round 2 below for the measured cause and the fix that
> replaced it.

## Round 2: the 419s were idle session expiry, not caches

### What the deployed clears actually proved

Round 1's hypothesis was falsified by the thing it was supposed to fix:

- The deploy log shows `config:clear` / `route:clear` / `view:clear` running, and afterwards neither
  `bootstrap/cache/config.php` nor `bootstrap/cache/routes-*.php` existed — yet 419s continued.
- Infrastructure was exonerated at the same time: 0 container restarts, `evicted_keys: 0` on Redis,
  the `sessions` table exists, and Redis DB 0 held ~637 session keys.
- The user-visible fix was **always re-login**, never a cache clear. The clears correlated with the
  recovery because the recovery happened at the same time, not because they caused it.

### The measured cause

`expired_keys` on Redis was **253126 over 32 days ≈ 7910/day**. With ~637 live sessions and a
120-minute idle window, expected expiries are `637 × 12/h = 7644/day`. The observed rate matches the
**`SESSION_LIFETIME` idle timer** almost exactly — sessions were not evicted, not flushed, not lost:
they simply aged out after two hours without a request. An open tab keeps its `_token` from page load,
so the next POST carried a token whose session no longer existed → `419`.

Two things the earlier rounds had wrong, both now corrected here:

1. *"No `sessions` table migration exists in this repo."* False —
   `database/migrations/0001_01_01_000000_create_users_table.php` creates it alongside `users`. It is
   simply unused, because production runs `SESSION_DRIVER=redis`.
2. The cache clears are still correct hygiene (a deploy must not leave stale caches), but they are
   **not** the 419 fix and never were.

### The fix shipped with this change

| # | Change | Where |
| --- | --- | --- |
| 1 | Keep-alive route `GET /_session/ping` → `204`, `X-CSRF-TOKEN` header, `Cache-Control: no-store`. In the `web` group but **not** behind `auth`, so it works for the guest's own `login` session too. | `routes/web.php` (`session.ping`) |
| 2 | Heartbeat: `fetch` every **10 min**, plus on `focus`, on `visibilitychange`→visible, and on `pageshow` when `event.persisted` (bfcache restore). 60 s debounce so several tabs don't stampede. | `resources/js/app.js` |
| 3 | Token re-sync: the ping response writes `X-CSRF-TOKEN` into `<meta name="csrf-token">` **and** every `input[name=_token]`. | `resources/js/app.js` |
| 4 | Six views baked `{{ csrf_token() }}` into inline JS at render time (a token frozen until reload). They now read the live meta tag. | `bookings/index`, `cancelled-bookings/confirm`, `passengers/edit`, `passengers/show` |
| 5 | Graceful 419 + forensics: a render callback logs `CSRF token mismatch` with the fields needed to tell the three failure modes apart, then redirects (guest → `route('login')` with the message as an `email` field error; authenticated → `back()`; JSON → real `419`). | `bootstrap/app.php` |
| 6 | `SESSION_LIFETIME=720` (provisional, user-approved) in both env samples. Read at `config/session.php:35` → `SessionManager.php:180` (Redis TTL) and `StartSession.php:259,272,227` (cookie expiry). | `.env.production.sample`, `.env.staging.sample` |

### Why one request per tab is enough

All three artefacts are refreshed on *every* request, not just at login:

- `StartSession::saveSession()` runs on every request → `CacheBasedSessionHandler::write()` →
  `Cache::put($id, $data, lifetime*60)` → **the Redis TTL is reset**.
- `StartSession::addCookieToResponse()` → **cookie `expires` is reset** (`now + lifetime*60`).
- `VerifyCsrfToken` refreshes the `XSRF-TOKEN` cookie on GET too (`isReading()`), and the ping's
  `X-CSRF-TOKEN` header re-syncs the meta/hidden-field copies the form actually submits.

So a tab that is merely *open* and visible stays alive indefinitely; a backgrounded tab is covered by
the `focus`/`visibilitychange`/`pageshow` listeners the moment the user returns.

### Reading the log line

`grep 'CSRF token mismatch' storage/logs/*.log` yields, per incident:

| Field | Meaning |
| --- | --- |
| `session_cookie=false` | The cookie itself is gone (idle past `SESSION_LIFETIME`). |
| `session_cookie=true`, `user_id=null` | Cookie survived, session destroyed → infrastructure (restart/flush). |
| `session_cookie=true`, `user_id` set | Session is fine; the form carried a stale token (another tab logged out, or a bfcache restore). Only the redirect helps. |

Also logged: `config_cache` / `route_cache` (so a stale-cache regression is visible), `lifetime`,
`driver`, `input_token` vs `session_token` (hashed, 12 chars), `has_auth_key`, `cf_ray`.

### What is *not* the cause (re-verified)

`SessionManager::createRedisDriver()` (`:136-147`) calls `setConnection(config('session.connection'))`
= `null` → Redis **DB 0**, while the app cache uses `REDIS_CACHE_CONNECTION=cache` → `REDIS_CACHE_DB=1`.
`createCacheHandler()` (`:174-182`) `clone`s the cache Repository, and `Repository::__clone()` deep-clones
the store (`Repository.php:987`) — so the `setConnection(null)` applies only to the session's own store.
⇒ `php artisan cache:clear` and any `Cache::flush()` hit **DB 1** and cannot log anyone out; the
`SessionInvalidator` comment about flushing the session store specifically is correct.

## Verified findings (evidence)

1. **`config:cache` is exonerated.** Round 1 testing on the live container: the CSRF outcome was
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
7. **Redis had no volume** (`docker-compose.prod.yml` declared only `app_storage` and `db_data`)
   and ran `--maxmemory 128mb --maxmemory-policy allkeys-lru`. Any Redis recreation or restart
   lost all sessions, and LRU pressure could evict them.
   *(`redis_data` + `--appendonly yes --appendfsync everysec` have since been added — see §Follow-ups.)*
8. **Watchtower could restart the app container outside `deploy-prod.sh`** (`com.centurylinklabs.watchtower`
   label, `docker-compose.prod.yml:23-26`), so anything that must happen on every boot belongs in the
   entrypoint, not only in the deploy script.
   *(Nothing runs Watchtower on either server — the label is kept only to match the live copy and is
   inert; boots are now server reboot / `docker restart` / `compose restart`. Nothing to do here.)*
9. **Env sample drift** *(resolved)*: `.env.production.sample` said `SESSION_DRIVER=database` while
   production ran `redis`, and set `SESSION_DOMAIN=umrah.binmishaltravels.com` while production
   resolved `session.domain = null`. Both samples now read `SESSION_DRIVER=redis` (`:61`); the
   production sample reads `SESSION_DOMAIN=null` (`:66`).

## Goals

- G1. A successful `deploy-prod.sh` run leaves the app in the known-good state: **no** config, route,
  or view cache.
- G2. No manual artisan commands are needed after a deploy.
- G3. Container boots (server reboot, `docker restart`, `docker compose restart`) never build caches,
  so no boot can re-introduce the incident.
- G4. After a successful deploy, **every logged-in user is logged out** and must re-login.

## Non-goals

- Not fixing the underlying session-loss cause (see §Follow-ups). This plan makes the deploy
  deterministic and the symptom invisible to users; it does not add Redis persistence.
- Not adding `optimize`/`config:cache` performance tuning back.
- Not changing the login, ticket, or visa workflows themselves.

## Design decisions (confirmed with the user)

- **D1.** The three clears live in `deploy-prod.sh` (runs after the health check), *not* in the
  entrypoint. Reason: the entrypoint should not build state that the deploy then has to tear down.
- **D2.** The entrypoint stops building caches entirely. This is the durable fix and closes the
  restart hole (finding 8, since closed for good — no Watchtower runs any more). Until the next
  image build, D1 keeps the deploy path correct.
- **D3.** Forced logout is an explicit, intended post-deploy step implemented as a driver-aware
  Artisan command, run **last** so it only happens after a fully successful deploy.
- **D4.** Users with open forms lose unsaved work on deploy. Accepted: it is the intended behaviour
  and matches what already happens in practice.
- **D5.** The clears deliberately do **not** use `|| true` (the script runs `set -Eeuo pipefail`): a
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

### 4. `deploy-prod.sh` — post-health block

Insert a new section **after** the application health-wait loop:

```sh
# Clear config/route/view caches so a deploy never leaves the app in the state
# that produces 419 CSRF mismatches. Fails the deploy if a clear errors.
compose exec -T app php artisan config:clear --no-interaction
compose exec -T app php artisan route:clear  --no-interaction
compose exec -T app php artisan view:clear   --no-interaction
```

Then add a **final** section just before "Final status":

```sh
# Force every logged-in user to re-login (intended post-deploy behaviour).
compose exec -T app php artisan sessions:flush --force --no-interaction
```

Final deploy order: `pull` → `stop app` → `up db` → wait db → `up redis` → `up app` → chown →
migrate → wait health → **clear caches** → **`sessions:flush`** → status.

### 5. Tests

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

### 6. Documentation

- `DEPLOYMENT.md:225-231` — rewrite "Reset Laravel cache": the deploy now clears config/route/view
  automatically; the manual equivalent is the three `*:clear` commands (never `config:cache` /
  `route:cache` / `optimize` in production); document `php artisan sessions:flush` and the warning
  that it logs everyone out.
- `docs/03-dev-environment.md:174-195` — delete "Cache for production (optimization)" and the note
  claiming the entrypoint caches on startup; replace with a short "why we do not cache in
  production" paragraph and point at the deploy script. Keep the generic "Clear specific caches"
  section, but annotate `cache:clear` with the warning that it does **not** clear sessions.
- `.env.production.sample:61` — `SESSION_DRIVER=redis` to match production reality. (`database` would
  work — `0001_01_01_000000_create_users_table.php` does create the `sessions` table — but production
  has never used it, and `SessionInvalidator` has a branch for it.)
- `.env.production.sample:66` — reconcile `SESSION_DOMAIN` with production (`null`) so the sample
  stops implying a cookie-domain dependency that does not exist.

## Verification (before/while deploying)

1. `php artisan test --filter=SessionInvalidator` and the new entrypoint/deploy guard tests.
2. `bash -n deploy-prod.sh` (syntax) and `sh -n docker/entrypoint.sh`.
3. `php artisan sessions:flush --force` on a staging/local Redis: confirm `redis-cli -n 0 --scan`
   shows session keys gone while `redis-cli -n 1 DBSIZE` (app cache) is untouched.
4. On the first real deploy, confirm from the log: migrations ran → clears ran → "sessions
   flushed" line, and that a logged-in browser is redirected to `/login`.

## Risks and mitigations

| Risk | Mitigation |
| --- | --- |
| A clear fails mid-deploy | `set -Eeuo pipefail` aborts before users are told anything; the app is still up and the clears are re-runnable by hand |
| Flushing DB 0 could drop non-session keys | `app/` has no `Redis::` usage (verified), so DB 0 is session-only; a future default-connection user must revisit this step |
| Deploys during active work discard unsaved forms | Intended (D4); deploy announcements should warn staff |
| Entrypoint change needs a rebuild | `deploy-prod.sh` clears are live immediately, so behaviour is correct before the new image ships |
| `sessions:flush` run by hand accidentally | Interactive confirmation unless `--force`; deploy always passes `--force` |

## Acceptance criteria

1. `docker/entrypoint.sh` contains no cache-building artisan calls.
2. After `deploy-prod.sh` completes, `bootstrap/cache/config.php` and `bootstrap/cache/routes-v7.php`
   do not exist, and `storage/framework/views` has been cleared.
3. Immediately after a successful deploy, any previously logged-in browser session is anonymous and
   is redirected to the login page.
4. `php artisan test` passes (including the new tests), `bash -n deploy-prod.sh` is clean.

## Follow-ups

- **Redis persistence is in the repo but not on the server.** Both compose files declare a
  `redis_data` volume and `--appendonly yes --appendfsync everysec`, but the production server has
  **not been patched yet** — on the last inspection `aof_enabled` was still `0` and there was no
  `redis_data` mount. Until Phase 6 lands, a Redis recreation still drops every session (which is a
  mass logout, not a 419 — the session cookie would survive with an empty session). Also still open:
  raise `maxmemory` / revisit `allkeys-lru`. Moving to MySQL is now a one-line config change
  (`SESSION_DRIVER=database`) — `0001_01_01_000000_create_users_table.php` already creates the
  `sessions` table.
- **`SESSION_LIFETIME=720` is provisional.** Set now on the user's instruction rather than on
  measurement. B6: 24 h after deploy, tabulate `CSRF token mismatch` log lines by `session_cookie`
  true/false. `session_cookie=true` in volume ⇒ idle expiry was not the dominant mode and the 720
  should be re-examined; `session_cookie=false` dominating before and dropping after ⇒ confirmed.
- **Untested combination.** `route:cache` / `view:cache` with a *pre-existing* session was never
  exercised (findings 2 and 3 argue they cannot cause a token mismatch). Round 2 independently
  falsified the cache-presence premise, so this is closed unless a 419 reappears **and** the new log
  line points at `config_cache=true` / `route_cache=true`.
- **Failure forensics — done.** `bootstrap/app.php` now has a render callback typed on
  `Symfony\Component\HttpKernel\Exception\HttpException` filtering `getStatusCode() === 419` that
  logs `CSRF token mismatch` with session id, user id, route, both token hashes, and the cache-state
  flags. Note for anyone extending it: `Handler::prepareException()` converts
  `TokenMismatchException` → `HttpException(419)` *before* render callbacks run, so a callback typed
  on `TokenMismatchException` never fires; and there is a pre-existing, separate bug — the
  `ModelNotFoundException` callback is typed correctly but the same conversion happens for it
  (`NotFoundHttpException`), so it is dead code. No test asserts its message; fixing it is out of
  scope for this plan.
