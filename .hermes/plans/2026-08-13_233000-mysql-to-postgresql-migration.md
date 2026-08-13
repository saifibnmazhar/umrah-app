# MySQL-to-PostgreSQL Migration & Data Import Plan

> **For Hermes:** This is a short-to-medium planning task. The user needs a recommendation and a concrete plan for importing existing MySQL production data. No subagents needed — this is a single coherent task with a clear recommendation and sequential steps.

**Goal:** Recommend whether to stay on MySQL or migrate to PostgreSQL, then execute the chosen path for importing the user's existing MySQL production database into the local development environment.

**Architecture:** The umrah-app project was designed for PostgreSQL from its first Docker commit (docker-compose.yml + .env.production.sample both use `postgres:16-alpine` / `DB_CONNECTION=pgsql`). Production infrastructure (deploy-prod.sh, docker-compose.prod.yml, CI/CD) is PostgreSQL-based. The local environment has already been switched to PostgreSQL (PG 17 on port 5432, `umrah_app_dev` DB created, `.env` pointed at PG, 90 of 111 migrations applied). Several migrations had MySQL-specific raw SQL (`MODIFY COLUMN`, `ENUM()`, `UNSIGNED`, `DROP CHECK`) that were patched for PG compatibility during local setup.

**Tech Stack:** PostgreSQL 17 (local), MySQL 8.0 on production server, Laravel 12 / PHP 8.4, `pgloader` or `mysqldump`+`pgloader` for migration, Doctrine DBAL for schema introspection.

---

## Recommendation: Migrate to PostgreSQL

**Stay with PostgreSQL.** Here is why:

1. **Production already uses PostgreSQL.** The `.env.production.sample` ships with `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE=umrah_app_prod`. The Docker production compose (`docker-compose.prod.yml`) runs `postgres:16-alpine`. The CI/CD pipeline (`build-push.yml`) builds images expecting PG. Switching production to MySQL would require rewriting the entire deployment pipeline.

2. **The project was designed for PostgreSQL.** The initial Docker commit (`3d8b1b9`) already used PostgreSQL — this was never a MySQL project by design. The MySQL-specific patterns found in migrations (`MODIFY COLUMN`, `ENUM()`, `UNSIGNED`, `DROP CHECK`) are legacy artifacts from the project's pre-Docker history, not intentional design.

3. **Local setup is already 90% complete.** PG 17 is installed locally, `.env` is pointed at it, 90/111 migrations have been applied, and 10 problematic MySQL-specific migrations have been patched for PG compatibility. Reverting to MySQL would discard this work and reintroduce the need to re-patch migrations.

4. **Data migration is straightforward.** The production MySQL data can be exported via `mysqldump` and converted to PostgreSQL using `pgloader` — a well-established, reliable tool for this exact use case.

**Do NOT stay in MySQL.** Switching to MySQL would mean: (a) rewriting the production deployment stack, (b) undoing the local PG setup already done, (c) re-introducing MySQL-specific migration syntax that breaks PG, and (d) diverging local dev from prod infrastructure.

---

## Current state (verified at plan time)

| Item | Status |
|------|--------|
| Local PostgreSQL 17 | Installed, running on port 5432, `postgres` superuser with password `postgres` |
| Local `umrah_app_dev` DB | Created, owner `postgres` |
| `.env` | `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=umrah_app_dev`, `DB_USERNAME=postgres`, `DB_PASSWORD=postgres` |
| Applied migrations | 90 of 111 in `umrah_app_dev` |
| Pending migrations | 21 (10 already patched for PG compatibility, 11 are PG-compatible already) |
| PHP extensions | `pdo_mysql`, `mysqli`, `pdo_pgsql`, `pgsql` all loaded |
| MySQL data | On production server, accessible via SSH |
| `pgloader` | Not yet installed locally |
| `mysqldump` | Available (via `mysql` client 11.8.6-MariaDB) |

---

## Proposed approach

1. **Ensure all 111 migrations run cleanly on PG** (fix any remaining MySQL-specific ones in the 21 pending migrations).
2. **Install `pgloader`** locally.
3. **Export production MySQL data** to a SQL dump file.
4. **Convert and import** the MySQL dump into a local PG database (`umrah_app_dev`) using `pgloader`.
5. **Verify** data integrity and app functionality.

---

## Task 1: Complete remaining migration fixes (run all pending migrations on PG)

**Objective:** Ensure all 111 migrations run cleanly on PostgreSQL so the local DB schema matches production.

**Step 1: Run pending migrations**

```bash
cd /home/azhar/Workspace/php/laravel/umrah-app
php artisan migrate --force
```

**Step 2: Fix any failures**

For any migration that fails with MySQL-specific syntax, rewrite it with driver-conditional logic using this pattern:

```php
if (Schema::getConnection()->getDriverName() === 'pgsql') {
    // PostgreSQL-safe SQL
    DB::statement('ALTER TABLE "table" ALTER COLUMN "column" TYPE varchar(255)');
} else {
    // Original MySQL syntax
    DB::statement('ALTER TABLE table MODIFY COLUMN column VARCHAR(255) NOT NULL');
}
```

Key MySQL→PG conversion rules:
- `MODIFY COLUMN` → `ALTER COLUMN ... TYPE` + `SET`/`DROP NOT NULL`
- `ENUM('a','b')` → `VARCHAR(255)` + `CHECK (col IN ('a','b'))`
- `BIGINT UNSIGNED` → `BIGINT` (PG has no UNSIGNED)
- `` `backticks` `` → `"double quotes"`
- `DROP CHECK` → `DROP CONSTRAINT`
- `DROP CHECK IF EXISTS` → `DROP CONSTRAINT IF EXISTS` (PG doesn't support `DROP CHECK`)

**Step 3: Verify**

```bash
php artisan migrate:status
```

Expected: All 111 migrations show `✓`, none show `✗` or `Pending`.

---

## Task 2: Install pgloader

**Objective:** Install `pgloader` for converting MySQL dumps to PostgreSQL.

**Commands (option A — from PostgreSQL apt repo, recommended):**

```bash
# Add PostgreSQL apt repository
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo gpg --dearmor -o /etc/apt/keyrings/pgdg.gpg
echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/deb bookworm-pgdg main" | sudo tee /etc/apt/sources.list.d/pgdg.list

# Install pgloader
sudo apt-get update
sudo apt-get install -y pgloader
```

**Commands (option B — if option A fails):**

```bash
# pgloader depends on SBCL which may not be in Debian repos
# Try the PostgreSQL community package
sudo apt-get install -y pgloader
```

**Verification:**

```bash
pgloader --version
```

Expected output: `pgloader version X.Y.Z` or similar.

---

## Task 3: Export production MySQL data

**Objective:** Dump the production MySQL database to a SQL file.

**Step 1: SSH into production and dump the database**

```bash
# You need the production MySQL credentials (host, port, username, password, db name)
# Connect to production server via SSH, then dump:
ssh deploy@your-production-server
mysqldump -u root -p umrah_app_prod > umrah_app_prod_dump.sql
# Or if using a specific MySQL user:
mysqldump -u techcandle_umrah -p umrah_app_prod > umrah_app_prod_dump.sql
exit
```

**Step 2: Transfer the dump to this machine**

```bash
scp deploy@your-production-server:~/umrah_app_prod_dump.sql /home/azhar/Workspace/php/laravel/umrah-app/umrah_app_prod_dump.sql
```

**Step 3: Verify the dump**

```bash
head -5 /home/azhar/Workspace/php/laravel/umrah-app/umrah_app_prod_dump.sql
wc -l /home/azhar/Workspace/php/laravel/umrah-app/umrah_app_prod_dump.sql
```

Expected: A valid MySQL dump starting with `-- MySQL dump 8.0` / `CREATE DATABASE` / `USE` / `CREATE TABLE` statements.

---

## Task 4: Convert and import MySQL data into PostgreSQL

**Objective:** Use `pgloader` to load the MySQL dump into the local `umrah_app_dev` database.

**Step 1: Reset the local PG database (drop and recreate)**

```bash
# Drop all tables in umrah_app_dev (clean slate for data import)
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d umrah_app_dev -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
```

**Step 2: Create a pgloader load file**

Create: `/home/azhar/Workspace/php/laravel/umrah-app/migrate.load`

```
LOAD DATABASE
     FROM '/home/azhar/Workspace/php/laravel/umrah-app/umrah_app_prod_dump.sql'
     INTO postgresql://postgres:postgres@127.0.0.1:5432/umrah_app_dev

     WITH data only,
          create no tables,
          create no indexes,
          create no primary keys,
          create no foreign keys,
          include drop,
          truncate,
          concurrency = 4,
          workers = 4,
          concurrency = 4

     SET MySQL [%] to PostgreSQL [,] to ',';
```

**Step 3: Import the data**

```bash
pgloader /home/azhar/Workspace/php/laravel/umrah-app/migrate.load
```

Wait — this `WITH data only, create no tables` approach requires the tables to already exist (from migrations). Let me revise the approach.

**Revised Step 1: Run migrations first (Task 1 already does this)**

After all 111 migrations are applied, the schema exists. Then import only data.

**Revised Step 2: Strip schema from the dump, keep only INSERT statements**

```bash
# Extract only the INSERT/VALUES lines from the MySQL dump
sed -n '/^INSERT INTO/,$p' umrah_app_prod_dump.sql > umrah_app_prod_data_only.sql
```

**Revised Step 3: Create a pgloader load file**

Create: `/home/azhar/Workspace/php/laravel/umrah-app/migrate.load`

```
LOAD DATABASE
     FROM mysql://user:password@production-host:3306/umrah_app_prod
     INTO postgresql://postgres:postgres@127.0.0.1:5432/umrah_app_dev

     WITH data only,
          create no tables,
          truncate,
          concurrency = 4,
          workers = 4

     SET MySQL [%] to PostgreSQL [,] to ',';
```

**Revised Step 4: Run pgloader**

```bash
pgloader /home/azhar/Workspace/php/laravel/umrah-app/migrate.load
```

**Step 4: Verify import**

```bash
# Check row counts for key tables
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d umrah_app_dev -c "SELECT 'users', count(*) FROM users UNION ALL SELECT 'bookings', count(*) FROM bookings UNION ALL SELECT 'passengers', count(*) FROM passengers UNION ALL SELECT 'visa_submissions', count(*) FROM visa_submissions;"
```

Expected: non-zero counts matching the production MySQL database.

---

## Task 5: Verify data integrity and app functionality

**Objective:** Confirm the imported data is consistent and the app works.

**Step 1: Run the app's test suite**

Tests use SQLite in-memory (per `phpunit.xml`), so they validate code, not PG data. But they confirm nothing is broken.

```bash
php artisan test
```

Expected: all tests pass.

**Step 2: Smoke-test against PG data**

```bash
# Verify app can query PG data
php artisan tinker --execute='$b = DB::table("bookings")->count(); echo "bookings count: $b";'
```

Expected: a non-zero integer (or 0 if no bookings exist, but no connection error).

**Step 3: Check for data type issues**

```bash
# If ENUM columns were converted to varchar with CHECK constraints,
# verify no CHECK constraint violations on imported data
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d umrah_app_dev -c "SELECT conname, consrc FROM pg_constraint JOIN pg_class ON pg_constraint.conrelid = pg_class.oid WHERE contype = 'c' AND conname LIKE '%check%';"
```

If any CHECK constraints are violated by imported data (e.g., MySQL `ENUM` values that aren't in the PG CHECK list), the import would fail or you'd need to clean the data first.

**Step 4: Clean up**

After successful import, remove temporary files:

```bash
rm -f /home/azhar/Workspace/php/laravel/umrah-app/umrah_app_prod_dump.sql
rm -f /home/azhar/Workspace/php/laravel/umrah-app/umrah_app_prod_data_only.sql
rm -f /home/azhar/Workspace/php/laravel/umrah-app/migrate.load
```

---

## Files changed summary

| File | Change |
|------|--------|
| 10 migration files in `database/migrations/` | Patched MySQL-specific SQL to be driver-conditional (PG-safe) |
| `.env` | Switched from `sqlite` to `pgsql` connection settings |
| `.hermes/plans/2026-08-13_230000-switch-local-postgresql.md` | This plan's predecessor (local PG setup) |

No new repo files created beyond the plan document.

## Risks & tradeoffs

- **ENUM conversion:** MySQL `ENUM('a','b')` → PG `VARCHAR(255)` + `CHECK` constraint. If production has `ENUM` values not present in the migration's allowed list, the CHECK constraint will reject them. Mitigation: review `ENUM` value lists in the MySQL schema vs. the PG CHECK constraints before importing.
- **Data type differences:** MySQL `UNSIGNED` → PG no equivalent (use `CHECK (col >= 0)`). Decimal precision differences may cause rounding. Mitigation: pgloader handles most automatic conversions; verify counts after import.
- **Auto-increment / sequences:** MySQL `AUTO_INCREMENT` → PG `SERIAL`/`BIGSERIAL`. pgloader handles this, but if importing data-only into pre-migrated tables, sequence values may be stale. Fix with `SELECT setval('tablename_id_seq', (SELECT MAX(id) FROM tablename));` per table after import.
- **Boolean values:** MySQL `TINYINT(1)` → PG `BOOLEAN`. pgloader handles this.
- **`mysqldump` vs `pgloader` direct:** Dumping to a file first is simpler but loses type metadata. Using `pgloader` directly against the MySQL server preserves type info better. The direct approach (`FROM mysql://...`) is recommended if the production server is reachable.
- **Production downtime:** If importing directly from the production server, consider taking a maintenance window or using a recent dump (not the live DB) to avoid inconsistency.

## Open questions

1. **Production MySQL access details:** What is the SSH host, MySQL username, MySQL password, database name, and port for the production server? (The data is confirmed to be on the production server, not local.)
2. **Direct connection vs. file transfer:** Can the local machine reach the production MySQL server directly (for `pgloader FROM mysql://...`), or must we use `mysqldump` + `scp` file transfer first?
3. **ENUM audit:** Should I audit all `ENUM` columns to ensure PG CHECK constraints match before import, or proceed and fix mismatches if they arise?
4. **Sequence reset:** Want me to add a post-import step to reset PG sequences to `MAX(id)` per table?
