# Switch Local Development to PostgreSQL Implementation Plan

> **For Hermes:** This is a short setup task — execute directly, no subagents needed. Bite-sized steps with verification after each.

**Goal:** Switch the local Laravel development environment from SQLite to a locally-installed PostgreSQL 17 instance so migrations run against a real PG database, while keeping PHPUnit tests on SQLite in-memory (fast, isolated, standard Laravel convention).

**Current state (verified at session start):**
- `.env` is set to `DB_CONNECTION=sqlite` (no `database/database.sqlite` file exists, so `php artisan migrate:status` currently errors).
- PostgreSQL 17 is installed and running locally on **port 5432** (cluster `17 main`, status `online`, data dir `/var/lib/postgresql/17/main`). The built-in `postgres` superuser exists (no password set yet).
- PHP has `pdo_pgsql` and `pgsql` extensions loaded (PHP 8.4.24).
- `phpunit.xml` already forces `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` for tests — leave this untouched.
- No migrations contain SQLite-specific logic (grep for `sqlite` in `database/migrations/` → 0 hits), so they will run equally well on PostgreSQL.
- Docker compose (`docker-compose.yml`) maps host port **5433** → container 5432 for its own Postgres service; that is separate from the native PG on 5432 and is not in use now. The native instance on 5432 is what we will use.
- `composer.json` already requires `doctrine/dbal` (needed for column-altering migrations), so no dependency changes required.

**Approach:** Set a password on the built-in `postgres` superuser, create a database `umrah_app_dev` owned by `postgres`, point `.env` at the local PG on `127.0.0.1:5432`, run migrations, then confirm the app boots and queries PG. Tests remain on SQLite in-memory (no change to `phpunit.xml`).

---

## Task 1: Create PostgreSQL role and database

**Objective:** Create a database `umrah_app_dev` owned by the built-in `postgres` superuser role (password `postgres`).

The user specified: use the built-in PostgreSQL **superuser** role named `postgres` **with password** `postgres`. This avoids `trust` auth dependency on `pg_hba.conf` and works regardless of host-based auth rules.

**Commands:**

```bash
sudo -u postgres psql -c "ALTER USER postgres WITH PASSWORD 'postgres';"
sudo -u postgres psql -c "CREATE DATABASE umrah_app_dev OWNER postgres;"
```

Expected: `ALTER ROLE` / `CREATE DATABASE` output.

**Verification:**

```bash
psql -d umrah_app_dev -c "SELECT current_user, current_database();"
```

Expected output:
```
 current_user | current_database
--------------+------------------
 postgres     | umrah_app_dev
(1 row)
```

Password-auth connection test:
```bash
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d umrah_app_dev -c "SELECT 1 AS ok;"
```
Expected output:
```
 ok
----
  1
(1 row)
```

---

## Task 2: Point `.env` at the local PostgreSQL

**Objective:** Update the `.env` file so the app uses the local PostgreSQL instead of SQLite.

**Files to modify:** `/home/azhar/Workspace/php/laravel/umrah-app/.env` (only the DB lines; leave everything else, including APP_URL, intact).

Edit the `DB_` block:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=umrah_app_dev
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

**Note:** Password `postgres` is used (per user requirement: built-in `postgres` superuser with password `postgres`) rather than empty, so this works regardless of `pg_hba.conf` auth method (`scram-sha-256`, `md5`, or `trust`). The `.env` is already in `.gitignore`, so this is safe to edit.

Use the `patch` tool to replace the existing block. The current `.env` has:
```
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

Replace with:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=umrah_app_dev
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

**Verification (config clear + read):**

```bash
php artisan config:clear
php artisan tinker --execute='echo config("database.default") . ":" . config("database.connections.pgsql.database");'
```

Expected output:
```
pgsql:umrah_app_dev
```

---

## Task 3: Run migrations against PostgreSQL

**Objective:** Apply the full migration set to the local PG database.

**Command:**

```bash
php artisan migrate
```

Expected: `Migration completed successfully.` (all ~70 migrations, no errors).

If a migration was already partially applied or fails mid-way due to a re-run, use:
```bash
php artisan migrate:fresh   # drops & re-runs everything (acceptable for local dev DB)
```

**Verification:**

```bash
php artisan migrate:status
```

Expected: a table of migrations with `Ran?` column = `✓` for each, no `✗`.

Cross-check against PG directly:
```bash
psql -d umrah_app_dev -c "\dt" | tail -5
```

Expected: a list of the app's tables (`users`, `bookings`, `passengers`, `migrations`, etc.).

---

## Task 4: Smoke-test the app against PostgreSQL

**Objective:** Confirm the app can connect to PG and serve a request (boot + query).

**Commands:**

```bash
php artisan config:clear
php artisan route:list --compact 2>&1 | head -5    # routes load without DB errors
php artisan tinker --execute='$u = DB::table("users")->count(); echo "users count: $u";'
```

Expected: `users count: 0` (or some integer — the key is no connection error).

Optional (if a dev server is desired):
```bash
php artisan serve --port=8000 &
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/
# Expected: 200
```

---

## Task 5: Confirm the test suite still uses SQLite (in-memory)

**Objective:** Make sure switching the dev DB did not break tests.

**Command:**

```bash
php artisan test --testsuite=Feature
```

(Or at minimum: `php artisan test`)

Expected: tests run against SQLite in-memory (per `phpunit.xml`), green. This may be a subset; the important outcome is no DB-connection failures.

If any test was relying on the dev DB, it will surface here.

---

## Files changed summary

| File | Change |
|------|--------|
| `.env` | `DB_CONNECTION` `sqlite` → `pgsql`; set `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=umrah_app_dev`, `DB_USERNAME=postgres`, `DB_PASSWORD=postgres` |
| *(none)* | PostgreSQL: set password on `postgres` role + created `umrah_app_dev` database (server-level, not in repo) |

No repo files (migrations, config, tests) need changing — the `pgsql` connection block already exists fully in `config/database.php:87-100`.

## Risks & tradeoffs

- **Superuser + password (`postgres`):** convenient for local dev (extension creation, unauthenticated), and the password satisfies `scram-sha-256`/`md5` auth in `pg_hba.conf` regardless of how TCP is configured. This is dev-only. Do not use this pattern in prod — production uses a restricted role via `.env.production`.
- **Port 5432 vs Docker 5433:** The native PG on 5432 is used. If Docker is later started with `docker compose up`, Docker's PG maps to host 5433 (not 5432) — no port collision. The `.env` here targets 5432 (native), which is correct for this task.
- **Tests on SQLite:** Intentionally kept in-memory per `phpunit.xml` and AGENTS.md conventions. This is the standard Laravel setup. Do not switch tests to PG (slower, and the project convention is explicit).

## Open questions

None — the user specified: built-in PostgreSQL superuser role `postgres` with password `postgres`, targeting the existing local PG 17 instance on port 5432.
