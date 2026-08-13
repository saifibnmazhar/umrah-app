# Revert PostgreSQL Migration: Return to MySQL for New Production Server

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Revert all PostgreSQL-specific infrastructure changes so the new production server runs MySQL (matching the old server and its existing data), undoing the recent commits that migrated the project to PostgreSQL.

**Architecture:** The previous developer converted the project from MySQL to PostgreSQL (commits 3d8b1b9 onward: Dockerfile installs `pdo_pgsql`, Docker Compose uses `postgres:16-alpine`, CI uses a PostgreSQL service container, all migrations target PG). The new production server has **not been deployed yet**. The old production server and its data remain on MySQL. The plan reverts every PostgreSQL-specific change back to MySQL without touching application business logic, then updates docs.

**Tech Stack:** MySQL 8.0 (Docker `mysql:8.0` image), PHP 8.3 with `pdo_mysql`, Laravel 12, Docker Compose, GitHub Actions CI/CD.

---

## Current Context / Assumptions

1. **Old production server:** MySQL, running live data in database `Umrah_database` (per `create_db.php`). **Not yet migrated.**
2. **New production server:** Not deployed yet. The current committed code targets PostgreSQL — must be reverted to MySQL before first deploy.
3. **Commits that introduced PostgreSQL** (from `git log --oneline`):
   - `3d8b1b9 feat: add Docker multi-stage build, GitHub CI/CD pipeline, and production deployment setup`
   - `0ce198d docs: add development handbook in docs/`
   - `62f9fda docs: add AGENTS.md with project conventions, TDD workflow, and best practices`
4. **PostgreSQL-specific changes by file:**
   - `Dockerfile:31-32` — installs `pdo_pgsql`, `pgsql` extensions (not `pdo_mysql`)
   - `docker-compose.yml:28-46` — `db` service uses `postgres:16-alpine` with PG env vars / healthcheck
   - `docker-compose.prod.yml:29` — `db` service uses `postgres:16-alpine`
   - `docker-compose.prod.yml:5-17` — app env from `.env.production` (PG-specific defaults)
   - `.env.production.sample` — `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `POSTGRES_*` vars
   - `.env.example` — currently shows `DB_CONNECTION=sqlite` (commented MySQL template)
   - `.github/workflows/build-push.yml` — CI `test-php` job uses `postgres:16-alpine` service, `extensions: pdo_pgsql, pgsql`, `DB_CONNECTION=pgsql`
   - `docs/07-ci-cd.md` — documents PostgreSQL CI service
   - `docs/02-architecture.md` — states "Database (prod): PostgreSQL 16 + Redis 7"
   - `docs/03-dev-environment.md` — references PostgreSQL ports
   - `README.md` — mentions PostgreSQL in Docker section
   - `AGENTS.md` — references PostgreSQL in multiple places (tech stack table, Docker section, troubleshooting)
   - `DEPLOYMENT.md` — uses `pg_isready`, `pg_dump`, `postgres:16-alpine`
   - `deploy-prod.sh` — `pg_isready` healthcheck
   - `docker/entrypoint.sh` — `MIGRATE=true` default; otherwise DB-agnostic
   - `config/database.php:4` — `use Pdo\Mysql;` import remains (PG config already present as `'pgsql'` block)
   - `create_db.php` — references MySQL with hardcoded password (pre-existing, not PG-related)

5. **Note on `create_db.php`:** This file references MySQL with a hardcoded password (`0183304fh`). It was likely created during the old MySQL era. It is not imported/referenced by any committed code. The plan includes deciding whether to keep, fix, or delete it.

6. **MySQL-specific migrations:** At commit eec233239735ba6d (merge of `dev`), 6 migrations contained MySQL-specific raw SQL (`MODIFY COLUMN`, `ENUM(...)`, backticks, `UNSIGNED`). These may have been partially fixed toward PG compatibility in later commits. The plan includes auditing and restoring these to MySQL-compatible form, since they were originally MySQL.

## Proposed Approach

Revert is a **file-by-file replacement** of PostgreSQL-specific config back to MySQL equivalents — NOT a git reset (the PostgreSQL commit 3d8b1b9 also introduced the Docker multi-stage build, CI/CD pipeline, and deployment scripts we want to keep). Instead: edit each PostgreSQL-specific file/field back to MySQL.

Strategy:
- **Dockerfile:** swap `pdo_pgsql` / `pgsql` / `libpq-dev` → `pdo_mysql` / `mysqlnd`.
- **Docker Compose (dev + prod):** swap `postgres:16-alpine` → `mysql:8.0`; swap PG env vars → MySQL env vars (`MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`).
- **env files:** `.env.production.sample` and `.env.example` → `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_PORT=3306`.
- **CI/CD workflow:** swap PostgreSQL service container → MySQL, swap PHP extensions, swap env vars and sed commands.
- **Docs:** update all references from PostgreSQL back to MySQL across AGENTS.md, docs/02, docs/03, docs/07, DEPLOYMENT.md, README.md, deploy-prod.sh.
- **Migrations:** audit all 108+ migrations for PG-isms and restore MySQL compatibility (especially the 6 flagged raw-SQL files).
- **Verification:** run `php artisan migrate:fresh` against local MySQL container; run PHPUnit; run `docker compose config` and `docker compose -f docker-compose.prod.yml config` validation.

## Step-by-Step Plan

### Phase 1: Audit all PostgreSQL-specific references

- **Task 1.1:** Grep the entire repo for PostgreSQL references.
  - Command: `grep -rnE 'pgsql|postgres|pdo_pgsql|libpq|pg_isready|pg_dump|POSTGRES' . --include='*.php' --include='*.yml' --include='*.yaml' --include='*.md' --include='*.sh' --include='*.conf' --include='.env*'`
  - Record all hits with file paths and line numbers.
- **Task 1.2:** Grep for MySQL-compatible migration syntax that may have been changed for PG.
  - Command: `grep -rnE 'MODIFY|ENUM\(|UNSIGNED|DROP CHECK|DROP CONSTRAINT' database/migrations/ | head -60`
  - Compare against the known 6 MySQL-specific migration files from commit eec2332.

### Phase 2: Revert the Dockerfile

- **Task 2.1:** Read current `Dockerfile` lines 17-36.
  - Files: `Dockerfile:17-36`
- **Task 2.2:** Replace PostgreSQL extension installations with MySQL extensions.
  - **Modify:** `Dockerfile:19-36`
  - Change the `apk add` line: remove `libpq-dev`, add `mysql-dev` / `default-mysqld-dev` equivalent. On Alpine, use `mariadb-dev` (provides `pdo_mysql` / `mysqli` headers).
  - Change `docker-php-ext-install`: `pdo_pgsql pgsql` → `pdo_mysql mysqli`
  - Full new block:
    ```dockerfile
    RUN apk add --no-cache \
            mariadb-dev \
            icu-dev \
            oniguruma-dev \
            libzip-dev \
            zip \
            unzip \
            curl \
            nginx \
            supervisor \
        && docker-php-ext-configure intl \
        && docker-php-ext-install -j"$(nproc)" \
            pdo_mysql \
            mysqli \
            intl \
            mbstring \
            zip \
        && docker-php-ext-enable opcache
    ```
- **Task 2.3:** Commit the Dockerfile change.
  - Command: `git add Dockerfile && git commit -m "revert: switch PHP extensions from PostgreSQL to MySQL"`

### Phase 3: Revert docker-compose.yml (dev)

- **Task 3.1:** Read current `docker-compose.yml:28-46` (db service).
  - Files: `docker-compose.yml`
- **Task 3.2:** Replace the PostgreSQL `db` service with MySQL.
  - **Modify:** `docker-compose.yml:28-54`
  - New db service:
    ```yaml
  db:
    image: mysql:8.0
    container_name: ${DB_CONTAINER_NAME:-umrah_app_db_dev}
    command: --default-authentication-plugin=mysql_native_password --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
    environment:
      - MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-root_password}
      - MYSQL_DATABASE=${DB_DATABASE:-umrah_app_dev}
      - MYSQL_USER=${DB_USERNAME:-umrah_app_user}
      - MYSQL_PASSWORD=${DB_PASSWORD:-dev_password}
    ports:
      - "127.0.0.1:${DB_EXPOSE_PORT:-3306}:3306"
    networks:
      - backend
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -u$$MYSQL_USER --password=$$MYSQL_PASSWORD"]
      interval: 10s
      timeout: 5s
      retries: 10
    volumes:
      - mysql_data_dev:/var/lib/mysql
    restart: unless-stopped
    ```
  - Update `environment:` on the app service lines 9-15: `DB_HOST=db`, `DB_PORT=3306`, `DB_CONNECTION=mysql`.
  - Update `volumes:` block: replace `postgres_data_dev` → `mysql_data_dev`.
- **Task 3.3:** Commit.
  - Command: `git add docker-compose.yml && git commit -m "revert: dev Docker Compose from PostgreSQL to MySQL"`

### Phase 4: Revert docker-compose.prod.yml

- **Task 4.1:** Read current `docker-compose.prod.yml` fully (already read — 65 lines).
- **Task 4.2:** Replace PostgreSQL `db` service with MySQL 8.0; update `app` env references.
  - **Modify:** `docker-compose.prod.yml:28-44` (db service)
  - New db service:
    ```yaml
  db:
    image: mysql:8.0
    container_name: ${DB_CONTAINER_NAME:-umrah_app_db}
    env_file: .env.production
    command: --default-authentication-plugin=mysql_native_password --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --explicit-defaults-for-timestamp=1
    volumes:
      - umrah_app_mysql_data:/var/lib/mysql
    ports:
      - "127.0.0.1:${DB_EXPOSE_PORT:-3306}:3306"
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -u$$MYSQL_USER --password=$$MYSQL_PASSWORD"]
      interval: 10s
      timeout: 5s
      retries: 10
    restart: unless-stopped
    networks:
      - backend
    depends_on:
      redis:
        condition: service_healthy
  ```
  - Update `volumes:` block: `umrah_app_pg_data` → `umrah_app_mysql_data`.
- **Task 4.3:** Commit.
  - Command: `git add docker-compose.prod.yml && git commit -m "revert: prod Docker Compose from PostgreSQL to MySQL"`

### Phase 5: Revert .env.production.sample

- **Task 5.1:** Read current `.env.production.sample` (already read — 60 lines).
- **Task 5.2:** Replace PostgreSQL-specific database variables with MySQL equivalents.
  - **Modify:** `.env.production.sample:12-24`
  - New block:
    ```
    DB_CONNECTION=mysql
    DB_HOST=db
    DB_PORT=3306
    DB_DATABASE=umrah_app_prod
    DB_USERNAME=techcandle_umrah
    DB_PASSWORD=***

    # MySQL container vars (consumed by the mysql:8.0 image on FIRST init of an
    # empty volume only; must mirror DB_DATABASE / DB_USERNAME / DB_PASSWORD above)
    MYSQL_ROOT_PASSWORD=***
    MYSQL_DATABASE=umrah_app_prod
    MYSQL_USER=techcandle_umrah
    MYSQL_PASSWORD=***
    ```
  - Remove the `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` lines (lines 20-24).
- **Task 5.3:** Commit.
  - Command: `git add .env.production.sample && git commit -m "revert: production env from PostgreSQL to MySQL config"`

### Phase 6: Revert .env.example

- **Task 6.1:** Read current `.env.example` (already read — 65 lines).
- **Task 6.2:** Update the commented MySQL template to be uncommented and active (the old server used MySQL on port 3306).
  - **Modify:** `.env.example:23-28`
  - Change:
    ```
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=laravel
    DB_USERNAME=root
    DB_PASSWORD=
    ```
  - (Uncomment the MySQL lines that were commented out.)
- **Task 6.3:** Commit.
  - Command: `git add .env.example && git commit -m "revert: .env.example defaults to MySQL instead of SQLite"`

### Phase 7: Revert CI/CD GitHub Actions workflow

- **Task 7.1:** Read current `.github/workflows/build-push.yml` (already read — 107 lines).
- **Task 7.2:** Replace PostgreSQL service container with MySQL, swap PHP extensions and env setup.
  - **Modify:** `.github/workflows/build-push.yml:14-58` (entire test-php job)
  - New job:
    ```yaml
  test-php:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: umrah_test
          MYSQL_USER: test
          MYSQL_PASSWORD: test
        ports:
          - 3306:3306
        options: >-
          --health-cmd "mysqladmin ping -h 127.0.0.1 -utest --password=test"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 10
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: pdo_mysql, mysqli, intl, mbstring, zip
          coverage: none
          tools: composer:v2

      - name: Install Composer dependencies
        run: composer install --no-interaction --no-progress --prefer-dist

      - name: Setup .env
        run: |
          cp .env.example .env
          sed -i 's/DB_CONNECTION=sqlite/DB_CONNECTION=mysql/' .env
          echo "DB_HOST=127.0.0.1" >> .env
          echo "DB_PORT=3306" >> .env
          echo "DB_DATABASE=umrah_test" >> .env
          echo "DB_USERNAME=test" >> .env
          echo "DB_PASSWORD=test" >> .env
          php artisan key:generate

      - name: Run migrations and tests
        run: |
          php artisan migrate --no-interaction
          vendor/bin/phpunit
    ```
- **Task 7.3:** Commit.
  - Command: `git add .github/workflows/build-push.yml && git commit -m "revert: CI/CD from PostgreSQL to MySQL service container"`

### Phase 8: Revert AGENTS.md

- **Task 8.1:** Read relevant sections of AGENTS.md (lines 1-40, 290-370, 390-450).
- **Task 8.2:** Replace PostgreSQL references with MySQL in:
  - Tech Stack table (line ~13)
  - Docker section (line ~422)
  - Troubleshooting section (pg-related commands)
  - Testing section (SQLite is correct for tests — keep as-is)
  - `.env.example` reference (already changing in Task 6)
- **Task 8.3:** Commit.
  - Command: `git add AGENTS.md && git commit -m "revert: AGENTS.md database references from PostgreSQL to MySQL"`

### Phase 9: Revert docs/

- **Task 9.1:** Update `docs/02-architecture.md` (line ~13: "Database (prod): PostgreSQL 16" → "Database (prod): MySQL 8.0").
- **Task 9.2:** Update `docs/03-dev-environment.md` (lines referencing PostgreSQL ports 5433, postgres:16-alpine).
- **Task 9.3:** Update `docs/07-ci-cd.md` (lines 22-29: PostgreSQL service container → MySQL; line 33: extension names).
- **Task 9.4:** Commit all docs changes.
  - Command: `git add docs/ && git commit -m "revert: documentation database references from PostgreSQL to MySQL"`

### Phase 10: Revert DEPLOYMENT.md and deploy-prod.sh

- **Task 10.1:** Update `DEPLOYMENT.md`:
  - Line 158: `postgres:16-alpine` → `mysql:8.0` in prerequisites.
  - Lines 164-166: Troubleshooting section about `pg_isready`/Postgres — replace with MySQL equivalent (`mysqladmin ping`).
  - Line 214: `pg_dump` → `mysqldump`.
  - Add MySQL-specific notes: `MYSQL_ROOT_PASSWORD`, first-init behavior.
- **Task 10.2:** Update `deploy-prod.sh`:
  - Line 39: `pg_isready` → `mysqladmin ping -h 127.0.0.1 -u"$DB_USERNAME" --password="$DB_PASSWORD"`.
  - Any other PG-specific commands.
- **Task 10.3:** Commit.
  - Command: `git add DEPLOYMENT.md deploy-prod.sh && git commit -m "revert: deployment docs and script from PostgreSQL to MySQL"`

### Phase 11: Revert README.md

- **Task 11.1:** Update `README.md` Docker section (lines ~134-171):
  - Line 164: `(app + PostgreSQL)` → `(app + MySQL)`
  - Line 165: `(app + PostgreSQL + Redis)` → `(app + MySQL + Redis)`
  - Any other PG references.
- **Task 11.2:** Commit.
  - Command: `git add README.md && git commit -m "revert: README Docker references from PostgreSQL to MySQL"`

### Phase 12: Audit and restore MySQL-compatible migrations

- **Task 12.1:** Check the 6 migrations that originally had MySQL-specific raw SQL against the current working tree to see if they were changed for PG compatibility.
  - Files: All 6 migration files from Phase 1.2.
  - For each: read current version, compare to what it looked like at commit eec2332.
  - If a migration was changed toward PG (e.g. `DROP CHECK IF EXISTS` dual-syntax, or `ENUM` removed), restore the MySQL version.
  - **Concrete fixes:**
    - `2026_05_23_054838_change_owner_type_to_string_in_documents_table.php`: Restore `ENUM('customer', 'passenger')` syntax (was the original MySQL form).
    - `2026_05_16_000001_make_passenger_status_id_nullable.php`: Restore `MODIFY COLUMN` (MySQL) syntax.
    - `2026_06_25_110800_update_visa_submissions_decimal_precision.php`: Restore `MODIFY ... DECIMAL(14,6)` with MySQL `DROP CHECK`/`DROP CONSTRAINT` dual try/catch kept (it's harmless).
  - For all 6: keep the `try/catch` dual-syntax for `DROP CHECK`/`DROP CONSTRAINT` since that pattern works on both MySQL and PG — no change needed there.
- **Task 12.2:** Grep ALL 108+ migrations for PostgreSQL-specific syntax (`::regclass`, `::bigint`, PG-specific functions, schema-qualified names, `generate_series`, etc.).
  - Command: `grep -rnE '::regclass|::bigint|pg_|generate_series|information_schema' database/migrations/`
  - Fix any hits.
- **Task 12.3:** Commit migration fixes.
  - Command: `git add database/migrations/ && git commit -m "restore: MySQL-compatible raw SQL in migrations"`

### Phase 13: Handle create_db.php

- **Task 13.1:** Decide on `create_db.php`:
  - **Option A:** Delete it — `git rm create_db.php` (not referenced anywhere, contains a hardcoded password `0183304fh`).
  - **Option B:** Fix it — update the hardcoded password and keep as a utility script.
  - **Recommendation:** Delete it. It contains a credential in plaintext, is not referenced by any code, and the database will be created by the MySQL Docker image's `MYSQL_DATABASE` env var.
  - Command: `git rm create_db.php && git commit -m "chore: remove obsolete create_db.php with hardcoded credentials"`

### Phase 14: Local verification

- **Task 14.1:** Validate Docker Compose configs parse cleanly.
  - Command: `docker compose config --quiet && echo OK`
  - Command: `docker compose -f docker-compose.prod.yml config --quiet && echo OK`
  - Expected: both succeed with no YAML errors.
- **Task 14.2:** Build the dev stack with MySQL.
  - Command: `docker compose up -d --build`
  - Expected: `db` (MySQL) becomes healthy; `app` starts.
- **Task 14.3:** Run migrations against MySQL.
  - Command: `docker compose exec app php artisan migrate:fresh --force`
  - Expected: All 108 migrations run successfully, no SQL errors.
- **Task 14.4:** Run the test suite.
  - Command: `php artisan test`
  - Expected: All tests pass (tests use SQLite in-memory per phpunit.xml — unchanged).
- **Task 14.5:** Run Pint for code style.
  - Command: `vendor/bin/pint`
  - Expected: no formatting errors.

## Files Likely to Change

- **Modify:** `Dockerfile:19-36` (PHP extensions)
- **Modify:** `docker-compose.yml:9-15, 28-54` (app env + db service)
- **Modify:** `docker-compose.prod.yml:28-66` (db service + volumes)
- **Modify:** `.env.production.sample:12-24` (DB vars + remove POSTGRES)
- **Modify:** `.env.example:23-28` (uncomment MySQL, remove sqlite)
- **Modify:** `.github/workflows/build-push.yml:14-58` (CI mysql service)
- **Modify:** `AGENTS.md:13, 297, 310, 357, 383, 422` (tech stack, Docker, troubleshooting)
- **Modify:** `docs/02-architecture.md:13` (prod DB line)
- **Modify:** `docs/07-ci-cd.md:22-29, 33` (mysql service, extensions)
- **Modify:** `docs/03-dev-environment.md:21-22, 63, 93` (ports, prod section)
- **Modify:** `DEPLOYMENT.md:158, 164-166, 214` (mysql commands)
- **Modify:** `deploy-prod.sh:39` (pg_isready → mysqladmin)
- **Modify:** `README.md:164-165` (Docker section)
- **Modify:** 6 migration files (restore MySQL raw SQL where changed)
- **Delete:** `create_db.php` (obsolete, hardcoded password)

## Tests / Validation

| Verification | Command | Success Criteria |
|---|---|---|
| Migration audit complete | `grep -rnE 'pgsql\|postgres\|pdo_pgsql' .` (excluding vendor/) | Only `config/database.php` pgsql connection block remains (kept — it's harmless and Laravel ships it by default) |
| Docker dev config valid | `docker compose config --quiet` | Exit 0 |
| Docker prod config valid | `docker compose -f docker-compose.prod.yml config --quiet` | Exit 0 |
| MySQL container starts | `docker compose up -d db` then `docker compose ps` | `db` service is "healthy" |
| Fresh migrations on MySQL | `docker compose exec app php artisan migrate:fresh --force` | All tables created, no SQL errors |
| PHPUnit test suite | `php artisan test` | All tests pass |
| Pint formatting | `vendor/bin/pint --test` | No changes needed |

## Risks, Tradeoffs, and Open Questions

### Risks
1. **Data type differences:** MySQL `ENUM` is not a standard type. The 6 migrations that use `ENUM(...)` raw SQL must use MySQL `ENUM` syntax. If any migration was partially rewritten for PG (e.g. ENUM replaced with a string column), it must be restored.
2. **Docker layer cache:** The Dockerfile extension change means a full rebuild — the `ghcr.io` image tag will change. Any Watchtower-based auto-deploy must pull the new image.
3. **CI/CD downtime:** The GitHub Actions workflow change means the next push to `main` triggers a MySQL-based test run instead of PostgreSQL. Ensure the MySQL service container healthcheck is correct.
4. **Backtick quoting in migrations:** Some raw-SQL migrations may use backticks (MySQL identifier quoting). These are MySQL-native and fine to keep — just ensure no PG-specific syntax like `::` casts or `DROP CHECK IF EXISTS` that PG requires but MySQL doesn't understand. (The `DROP CHECK IF EXISTS` is actually MySQL 8.0.29+ compatible; the dual try/catch handles older versions.)
5. **Zero-value dates:** MySQL allows `'0000-00-00'`; PostgreSQL does not. If any data or migration inserted such dates, they need handling. (For a fresh deploy, this is only relevant to existing data being copied from the old server — see Open Question 1.)

### Tradeoffs
- **Delete vs. keep `create_db.php`:** Deleting is cleaner (removes hardcoded credentials), but if the user has muscle-memory for running it, keeping a fixed version is friendlier. Recommendation is to delete since the MySQL Docker image auto-creates the database via `MYSQL_DATABASE`.
- **MySQL 8.0 vs. MariaDB 10.11:** MySQL 8.0 matches the old server. The `mysql_native_password` authentication plugin is needed because PHP's `pdo_mysql` in the Alpine image may not support `caching_sha2_password` without a TLS handshake. This matches what the old server likely uses.

### Open Questions
1. **What MySQL version runs on the old production server?** If it's MySQL 5.7, the `mysql:8.0` Docker image is still compatible for migration (dump from 5.7 → load into 8.0 works).
2. **Does the old server use `caching_sha2_password` (MySQL 8+) or `mysql_native_password`?** The `command: --default-authentication-plugin=mysql_native_password` in the Docker service handles this — set it for the container.
3. **Are there any MySQL-specific indexes or engine specifications** (e.g. `ENGINE=InnoDB`, `ROW_FORMAT=COMPRESSED`) in the old schema that need to be preserved? For a fresh deploy this is not an issue — the MySQL Docker image defaults to InnoDB.

## Execution Handoff

Plan complete and saved. Ready to execute using subagent-driven-development — I'll dispatch a fresh subagent per task with two-stage review (spec compliance then code quality). Shall I proceed?
