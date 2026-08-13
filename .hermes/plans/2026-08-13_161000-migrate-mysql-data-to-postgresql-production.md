# Migrate Old MySQL Production Data to PostgreSQL for New Production Server

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Migrate the existing MySQL production database to PostgreSQL so the new production server (which runs PostgreSQL 16 via Docker) can serve live data with the current codebase.

**Architecture:** The old production server used MySQL. The current codebase, Docker configuration, CI/CD, and all non-raw-SQL migrations have already been migrated to PostgreSQL 16 (see commit 3d8b1b9 "feat: add Docker multi-stage build"). However, commit eec233239735ba6d61 (merge of dev at Aug 8 2026) still contains **MySQL-specific raw SQL** in several migrations that will fail on PostgreSQL. The plan is: (1) audit and fix those MySQL-specific migrations so they are PostgreSQL-compatible, (2) create a one-time migration script to convert the MySQL dump to a PostgreSQL dump, (3) test the converted data loads cleanly against a fresh PostgreSQL instance, and (4) document the production cutover procedure.

**Tech Stack:** MySQL (source), PostgreSQL 16 (target), PHP 8.3 / Laravel 12 artisan CLI, pgloader or custom conversion script, Docker Compose for local verification.

---

## Current Context / Assumptions

1. **Old production DB:** MySQL, contains `Umrah_database` (per `create_db.php`), with the schema that existed at commit eec2332.
2. **New production server:** Docker Compose with `postgres:16-alpine` (docker-compose.prod.yml:29), app connects via `DB_CONNECTION=pgsql`.
3. **MySQL-specific migrations at commit eec2332** — these will error when run against PostgreSQL:
   - `2026_05_16_000001_make_passenger_status_id_nullable.php` — `ALTER TABLE passengers MODIFY COLUMN passenger_status_id BIGINT UNSIGNED NULL`
   - `2026_05_23_054838_change_owner_type_to_string_in_documents_table.php` — `ENUM('customer', 'passenger')` and `VARCHAR(255)`
   - `2026_06_25_110800_update_visa_submissions_decimal_precision.php` — `DECIMAL(14,6)` via `MODIFY`
   - `2026_06_26_000001_update_fingerprints_cost_precision.php` — `DECIMAL(14,6)` via `MODIFY`
   - `2026_07_02_000001_allow_negative_balance_in_invoices_table.php` — backtick-qualified `balance` column, `DECIMAL(14,6)` / `UNSIGNED`
   - `2026_07_25_000001_add_awaiting_group_and_double_ticket_columns.php` — `ENUM(...)` and `VARCHAR(255)`

   Note: the versions of these files visible in the current working tree (HEAD = 3a6c9f0) may differ from eec2332 if they were fixed on a branch. Verify against the current working tree before editing.

4. The `config/database.php` `mysql` connection config uses `useCurrentSchema` / Laravel defaults that are portable via `Schema::table()`, but the `DB::statement()` calls with raw `MODIFY COLUMN` / `ENUM` syntax are hardcoded MySQL.
5. Data conversion concerns: MySQL `ENUM` columns, `BIGINT UNSIGNED`, `utf8mb4`, backtick quoting, and zero-dates (`'0000-00-00'`) all need handling when converting to PostgreSQL.
6. There is no existing migration that creates a fresh `migrations` baseline for a converted DB — a fresh production PostgreSQL volume starts empty, so all migrations must run from scratch on the target (not just data copy).

## Proposed Approach

Two sub-problems:
- **A. Schema compatibility:** Make every migration runnable on PostgreSQL so a fresh `php artisan migrate --force` succeeds on the new prod DB.
- **B. Data migration:** Convert the MySQL logical dump to a PostgreSQL-compatible dump and load it.

Strategy:
- Rewrite the MySQL-specific raw SQL in the flagged migrations to use DBAL-portable `Schema::table()` calls where possible, and `DB::statement()` with PostgreSQL-compatible syntax guarded by a connection check, OR split into separate DB-specific statements.
- For `ENUM` types: PostgreSQL native `ENUM` requires a separate `CREATE TYPE` + `DROP TYPE`. Convert to using Laravel's portable `$table->enum()` in `Schema::table()` instead of raw SQL — this lets Laravel's grammar handle the dialect.
- For `UNSIGNED` / `MODIFY COLUMN`: use `$table->unsignedBigInteger(...)->nullable()->change()` via `doctrine/dbal` (already a dependency per composer.json: `"doctrine/dbal": "^4.4"`).
- For the data dump conversion: use a targeted tool — `pgloader` is the canonical choice for MySQL→PostgreSQL. If `pgloader` is unavailable on the target host, fall back to a mysqldump with proper escaping + sed transformation.
- Verify with SQLite-in-memory test suite (`php artisan test`) for migrations, and a local Docker PostgreSQL instance for the full data load.

## Step-by-Step Plan

### Phase 1: Audit current working-tree state of flagged migrations

- **Task 1.1:** Read each of the 6 flagged migrations from the current working tree (HEAD 3a6c9f0), not eec2332.
  - Files: `database/migrations/2026_05_16_000001_make_passenger_status_id_nullable.php`, `database/migrations/2026_05_23_054838_change_owner_type_to_string_in_documents_table.php`, `database/migrations/2026_06_25_110800_update_visa_submissions_decimal_precision.php`, `database/migrations/2026_06_26_000001_update_fingerprints_cost_precision.php`, `database/migrations/2026_07_02_000001_allow_negative_balance_in_invoices_table.php`, `database/migrations/2026_07_25_000001_add_awaiting_group_and_double_ticket_columns.php`

- **Task 1.2:** Grep the entire current `database/migrations/` tree for any remaining MySQL-only raw SQL (`MODIFY`, `ENUM(`, backticks, `UNSIGNED`, `utf8mb4`, `'0000-00-00'`) and record all hits.

**Files:** (read-only; no edits yet)
- Task 1.1: 6 migration files
- Task 1.2: `grep -rnE 'MODIFY|ENUM\(|`|UNSIGNED|utf8mb4'` in `database/migrations/`

### Phase 2: Fix MySQL-specific migrations for PostgreSQL compatibility

For each flagged migration, rewrite raw SQL to DBAL-portable or PG-compatible statements.

- **Task 2.1:** Fix `2026_05_23_054838_change_owner_type_to_string_in_documents_table.php`
  - **Objective:** Replace `ENUM('customer', 'passenger')` and `VARCHAR(255)` raw SQL with portable Schema builder calls.
  - **Files:** `database/migrations/2026_05_23_054838_change_owner_type_to_string_in_documents_table.php`
  - **Step 1 (test):** Write PHPUnit test `tests/Feature/MigrationPgCompatibilityTest.php` that runs `php artisan migrate:fresh` against PostgreSQL and asserts the documents table has `owner_type` column. Run against local Docker PG — expect migrate to succeed. (For now, can run `php artisan migrate --pretend --database=pgsql` to check SQL output.)
  - **Step 2:** Run `php artisan migrate --pretend --database=pgsql` — confirm the ENUM/MODIFY statement appears.
  - **Step 3:** Rewrite using `$table->enum('owner_type', ['customer', 'passenger'])->change();` instead of raw `DB::statement`. Use `doctrine/dbal` `change()` method.
  - **Step 4:** Re-run `--pretend` — confirm no MySQL syntax.
  - **Step 5:** Commit: `git add database/migrations/2026_05_23_054838_change_owner_type_to_string_in_documents_table.php && git commit -m "fix: make documents owner_type migration PostgreSQL-compatible"`

- **Task 2.2:** Fix `2026_05_16_000001_make_passenger_status_id_nullable.php`
  - **Objective:** Replace `ALTER TABLE passengers MODIFY COLUMN passenger_status_id BIGINT UNSIGNED NULL` with DBAL `change()`.
  - **Files:** `database/migrations/2026_05_16_000001_make_passenger_status_id_nullable.php`
  - **Step 1:** Run `php artisan migrate:pretend --database=pgsql` — confirm MySQL syntax.
  - **Step 2:** Rewrite using `$table->unsignedBigInteger('passenger_status_id')->nullable()->change();`.
  - **Step 3:** Re-run pretend — confirm portable SQL.
  - **Step 4:** Commit.

- **Task 2.3:** Fix `2026_06_25_110800_update_visa_submissions_decimal_precision.php`
  - **Objective:** Replace `ALTER TABLE visa_submissions MODIFY ... DECIMAL(14,6) NULL` with DBAL `decimal(...)->nullable()->change()`.
  - **Files:** `database/migrations/2026_06_25_110800_update_visa_submissions_decimal_precision.php`
  - **Step 1:** Pretend migrate — confirm MySQL syntax.
  - **Step 2:** Rewrite to `$table->decimal('agent_commission', 14, 6)->nullable()->change();` (and the 3 other columns). Guard with `if (Schema::hasColumn(...))` to be idempotent.
  - **Step 3:** Confirm pretend output.
  - **Step 4:** Commit.

- **Task 2.4:** Fix `2026_06_26_000001_update_fingerprints_cost_precision.php`
  - **Objective:** Same pattern as 2.3 — make `cost` column precision change portable.
  - **Files:** `database/migrations/2026_06_26_000001_update_fingerprints_cost_precision.php`
  - **Step 1–3:** Same as 2.3; use `$table->decimal('cost', 14, 6)->change()`.
  - **Step 4:** Commit.

- **Task 2.5:** Fix `2026_07_02_000001_allow_negative_balance_in_invoices_table.php`
  - **Objective:** Replace backtick-qualified `balance` and `UNSIGNED` raw SQL.
  - **Files:** `database/migrations/2026_07_02_000001_allow_negative_balance_in_invoices_table.php`
  - **Step 1:** Pretend migrate — confirm backtick/UNSIGNED syntax.
  - **Step 2:** Rewrite to `$table->decimal('balance', 16, 6)->default(0)->change();` (remove UNSIGNED to allow negative balance — that's the actual intent of this migration per its name "allow negative").
  - **Step 3:** Confirm.
  - **Step 4:** Commit.

- **Task 2.6:** Fix `2026_07_25_000001_add_awaiting_group_and_double_ticket_columns.php`
  - **Objective:** Replace `ENUM(...)` raw SQL for `issued_tickets.status` and `packages.ticket_fare_id`.
  - **Files:** `database/migrations/2026_07_25_000001_add_awaiting_group_and_double_ticket_columns.php`
  - **Step 1:** Read the file — determine which parts use raw SQL vs Schema builder.
  - **Step 2:** Rewrite ENUM changes using `$table->enum('status', [...])->change();` and `$table->unsignedBigInteger('ticket_fare_id')->nullable()->change();`.
  - **Step 3:** Confirm with pretend.
  - **Step 4:** Commit.

- **Task 2.7:** Run the MySQL-specific grep again — confirm 0 hits for raw MySQL syntax.
  - Command: `grep -rnE 'MODIFY|ENUM\(|`|UNSIGNED|utf8mb4' database/migrations/` — expected: no output.

### Phase 3: Verify all migrations run clean on PostgreSQL

- **Task 3.1:** Spin up local PostgreSQL 16 container (mirrors dev docker-compose.yml `db` service).
  - Command: `docker compose up -d db` (from project root).
- **Task 3.2:** Create `.env.testing.pg` with `DB_CONNECTION=pgsql`, pointing at `127.0.0.1:5433`, a fresh test DB, user `umrah_app_user`, password `dev_password`.
  - Files: Create `.env.testing.pg` (`.gitignore`'d — not committed).
- **Task 3.3:** Run full migration from scratch against PostgreSQL.
  - Command: `php artisan migrate:fresh --database=pgsql --force`
  - Expected: SUCCESS — no SQLSTATE errors, all tables created.
  - If any migration fails, fix it (this is the catch-all for migrations not caught in Phase 2).
- **Task 3.4:** Run `php artisan migrate --pretend --database=pgsql` to review the generated SQL for any remaining dialect issues.
- **Task 3.5:** Run the PHPUnit test suite against PostgreSQL to confirm schema + factories work.
  - Command: `php artisan test` (uses SQLite by default per phpunit.xml — temporarily override DB connection or add a CI-style PG test run).
  - For this verification: set env to PG, run `php artisan migrate:fresh --database=pgsql`, then `vendor/bin/phpunit --configuration phpunit.xml --env=testing` with `DB_CONNECTION=pgsql` set in the `.env.testing.pg`.

### Phase 4: Data migration from MySQL to PostgreSQL

- **Task 4.1:** Obtain MySQL production dump.
  - Command on old prod server: `mysqldump -u root -p Umrah_database > umrah_mysql_dump_$(date +%F).sql`
  - If a dump already exists in `.opencode/issue-findungs/` or a backup location, locate it. Check `.opencode/` directory.
- **Task 4.2:** Inspect the dump for problematic MySQL constructs:
  - `ENGINE=InnoDB`, `DEFAULT CHARSET=utf8mb4`, `COLLATE=utf8mb4_unicode_ci`
  - `CREATE DATABASE` / `USE` statements
  - `ENUM(...)` definitions
  - Backtick-quoted identifiers
  - `'0000-00-00 00:00:00'` zero-dates
  - `ON UPDATE CURRENT_TIMESTAMP` on non-timestamp columns
- **Task 4.3:** Install/prepare `pgloader` on a Linux host.
  - Command: `apt-get install -y pgloader` (Debian/Ubuntu)
  - If unavailable, install via `fqlt` or use the Docker image: `docker run --rm -v $(pwd):/data dimitri/pgloader`
- **Task 4.4:** Write a `pgloader` load file.
  - Files: Create `docker/scripts/load-mysql-to-pg.load`
  - Content:
    ```
    LOAD DATABASE FROM mysql://root:PASSWORD@localhost/Umrah_database
         INTO postgresql://techcandle_umrah:PASSWORD@localhost:5432/umrah_app_prod
         WITH data including drop, create tables, create indexes, reset sequence
         SET PostgreSQL WITHOUT strict concurrency
         SET work_mem to '16MB', maintenance_work_mem to '256MB'
         BEFORE LOAD DO
         $$ CREATE SCHEMA IF NOT EXISTS public; $$
    ```
- **Task 4.5:** Run `pgloader` to convert and load the data.
  - Command: `pgloader docker/scripts/load-mysql-to-pg.load`
  - Expected: summary output showing rows copied for each table, no fatal errors.
- **Task 4.6: If `pgloader` fails or is unavailable, use mysqldump + sed fallback.**
  - Files: Create `docker/scripts/mysql-dump-to-pg.sh`
  - Steps in script:
    1. `mysqldump --compatible=postgresql --default-character-set=utf8mb4 Umrah_database > raw_mysql.sql` (the `--compatible=postgresql` flag handles some quoting)
    2. Strip `ENGINE=...`, `DEFAULT CHARSET=...`, backtick quotes, `CREATE DATABASE`, `USE`
    3. Convert `ENUM` column definitions to `VARCHAR`
    4. Replace zero-dates `0000-00-00 00:00:00` → `1970-01-01 00:00:00`
    5. Load into PG via `psql`
  - This is a fallback only.

### Phase 5: Post-migration validation

- **Task 5.1:** Verify row counts match between MySQL dump and PostgreSQL.
  - Query MySQL: `SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='Umrah_database';`
  - Query PostgreSQL: `SELECT table_name, reltuples::bigint FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname='public' AND c.relkind='r';`
  - Compare counts for each table.
- **Task 5.2:** Verify foreign keys and constraints are intact.
  - `SELECT conname, conrelid::regclass FROM pg_constraint WHERE contype='f';`
- **Task 5.3:** Reset migration state on the converted database so the new server's migrations are tracked.
  - If pgloader created the schema, the `migrations` table may not match. Run: `php artisan migrate:install --database=pgsql` then manually insert the migration records (or run `php artisan migrate:fresh` on a copy of the converted DB to re-confirm all migrations pass without destructive changes).
- **Task 5.4:** Smoke-test the Laravel app against the converted database.
  - Boot the app (`php artisan serve`) with `DB_CONNECTION=pgsql` pointing at the converted DB.
  - Hit a few key routes (dashboard, bookings index, invoices index) and confirm no SQL errors in `storage/logs/laravel.log`.

### Phase 6: Documentation & production cutover

- **Task 6.1:** Document the migration process.
  - Files: Update `DEPLOYMENT.md` with a new section "## Data Migration: MySQL → PostgreSQL" covering prerequisites, pgloader command, fallback, and validation steps.
  - Also update `.env.production.sample` comments if needed to note MySQL→PGM conversion is a one-time pre-deploy step.
- **Task 6.2:** Document production cutover procedure.
  - Files: Update `DEPLOYMENT.md` "## Production Cutover Procedure" — steps to switch DNS, take the old MySQL server offline at cutover, run migrations on the converted PG DB, and deploy new image via `./deploy-prod.sh`.
- **Task 6.3:** Clean up the obsolete `create_db.php` one-liner (it references MySQL with a hardcoded password).
  - Files: Delete `create_db.php` (it is not referenced anywhere in the codebase — confirmed no PHP imports include it).
  - Command: `git rm create_db.php && git commit -m "chore: remove obsolete MySQL create_db.php helper"`

## Files Likely to Change

- **Modify:** `database/migrations/2026_05_16_000001_make_passenger_status_id_nullable.php`
- **Modify:** `database/migrations/2026_05_23_054838_change_owner_type_to_string_in_documents_table.php`
- **Modify:** `database/migrations/2026_06_25_110800_update_visa_submissions_decimal_precision.php`
- **Modify:** `database/migrations/2026_06_26_000001_update_fingerprints_cost_precision.php`
- **Modify:** `database/migrations/2026_07_02_000001_allow_negative_balance_in_invoices_table.php`
- **Modify:** `database/migrations/2026_07_25_000001_add_awaiting_group_and_double_ticket_columns.php`
- **Create:** `docker/scripts/load-mysql-to-pg.load` (pgloader config)
- **Create:** `docker/scripts/mysql-dump-to-pg.sh` (fallback conversion script)
- **Create:** `tests/Feature/MigrationPgCompatibilityTest.php` (tests migration runs clean on PG)
- **Modify:** `DEPLOYMENT.md` (add migration + cutover docs)
- **Delete:** `create_db.php` (obsolete MySQL helper with hardcoded password)

## Tests / Validation

| Verification | Command | Success Criteria |
|---|---|---|
| No raw MySQL SQL in migrations | `grep -rnE 'MODIFY\|ENUM(\|UNSIGNED\|\`' database/migrations/` | No output |
| Migrations run on PostgreSQL | `php artisan migrate:fresh --database=pgsql --force` against local PG | All tables created, no SQLSTATE errors |
| PHPUnit tests pass (PG) | `DB_CONNECTION=pgsql php artisan test` | All green |
| Data row counts match | Compare MySQL vs PostgreSQL table counts | ±0% mismatch |
| App smoke test | Browse `/bookings`, `/invoices` with PG DB | 200 OK, no SQL errors in logs |
| pgloader conversion | `pgloader docker/scripts/load-mysql-to-pg.load` | Rows copied, 0 fatal errors |

## Risks, Tradeoffs, and Open Questions

### Risks
1. **Zero-dates:** MySQL allows `'0000-00-00'`; PostgreSQL rejects them. `pgloader` auto-converts by default; the sed fallback must handle this manually.
2. **ENUM columns:** MySQL `ENUM` has no direct PostgreSQL equivalent. `pgloader` maps `ENUM` → `VARCHAR` by default; the sed fallback must also convert all `ENUM(...)` to `VARCHAR(255)`.
3. **Unsigned integers:** MySQL `BIGINT UNSIGNED` → PostgreSQL `BIGINT` loses the constraint; acceptable for this app (no overflow concern at current data volumes).
4. **`ON UPDATE CURRENT_TIMESTAMP` on datetime columns:** MySQL behavior; PostgreSQL differs. Verify no data relies on this.
5. **Foreign key validation:** PostgreSQL enforces FK constraints strictly; if the MySQL dump has orphaned rows, the PG load will fail. pgloader has a `create no fks` option for a lenient load — use only if needed and validate afterward.

### Tradeoffs
- **pgloader vs sed fallback:** pgloader is more robust and handles type conversion semantically. The sed fallback is more transparent but fragile. Use pgloader first; document sed only as a last resort.
- **Portable migrations vs DB-specific:** Rewriting raw SQL to `Schema::table()` `change()` calls adds a `doctrine/dbal` dependency for column modifications (already present in composer.json), but makes migrations portable across MySQL and PostgreSQL. This is the right long-term choice.

### Open Questions
1. Q: Does the old production MySQL server still have a reachable dump, or only live data?
   - A: If only live data, obtain a fresh `mysqldump` from the running server before cutover.
2. Q: Are there any `ON UPDATE CURRENT_TIMESTAMP` defaults on non-timestamp columns in the MySQL schema?
   - A: Need to inspect the dump; if found, convert to explicit `DEFAULT NULL` in PG and rely on Laravel timestamps.
3. Q: Is `pdo_pgsql` / `pdo_mysql` available in the local dev environment for the cross-DB test?
   - A: The current `.env` uses `DB_CONNECTION=pgsql` already (confirmed), and the Docker dev stack runs PostgreSQL, so PG testing is feasible.

## Execution Handoff

Plan complete and saved. Ready to execute using subagent-driven-development — I'll dispatch a fresh subagent per task with two-stage review (spec compliance then code quality). Shall I proceed?
