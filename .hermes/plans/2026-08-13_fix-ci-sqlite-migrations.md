# Fix CI/CD Test Pipeline on SQLite (PostgreSQL Migrations) Implementation Plan

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Make the GitHub Actions CI test job (`test-php`) pass by fixing all migrations that use PostgreSQL-specific `ALTER TABLE ... ADD CONSTRAINT ... CHECK` syntax which crashes on SQLite (the test database).

**Architecture:** ~25 migrations across the codebase use raw SQL `ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)` statements that are PostgreSQL-specific. SQLite cannot add CHECK constraints via `ALTER TABLE`. The fix wraps each offending `DB::statement(...)` call in a `DB::getDriverName() !== 'sqlite'` guard, preserving the constraint on production PostgreSQL while skipping it on SQLite test runs. Also fixes the null-unsafe Blade view and test setup that prevent the `BookingEditPackagePreloadTest` from passing.

**Tech Stack:** PHP 8.3, Laravel 12, PHPUnit 11, SQLite (tests), PostgreSQL 16/17 (prod), GitHub Actions

---

## Current State Analysis

- **Local DB:** PostgreSQL 17 is installed and running (cluster `17/main` on port 5432, status: `online`).
- **CI DB:** GitHub Actions `test-php` job spins up a `postgres:16-alpine` service, but PHPUnit uses SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml`).
- **Root cause of CI failure:** Migrations use `DB::statement('ALTER TABLE ... ADD CONSTRAINT ... CHECK ...')` which is valid PostgreSQL but crashes on SQLite with `syntax error: near "CONSTRAINT"`.
- **Affected files:** ~25 migration files (listed below) that contain this pattern.
- **Secondary failures:**
  - `BookingEditPackagePreloadTest` doesn't use `RefreshDatabase` trait (imported but not used) — the test was designed for an older schema.
  - `bookings/edit.blade.php` line 4 calls `auth()->user()->roles` (originally) without null-safety — crashes when rendering views without an authenticated user.
  - `ExampleTest` expects HTTP 200 on `GET /` but the route is behind `auth` middleware (returns 302 redirect to login).

## Files Likely to Change

### Migrations (wrap DB::statement in driver guard)
All of these files contain `DB::statement('ALTER TABLE ... ADD CONSTRAINT ... CHECK ...')`:

- `database/migrations/2026_05_04_150011_create_fingerprint_charges_table.php`
- `database/migrations/2026_05_04_150023_create_visa_agent_costs_table.php`
- `database/migrations/2026_05_04_150037_create_visa_selling_prices_table.php`
- `database/migrations/2026_05_06_000001_create_ticket_fares_table.php`
- `database/migrations/2026_05_06_000002_create_group_tickets_table.php`
- `database/migrations/2026_05_07_000001_create_flight_date_gap_table.php`
- `database/migrations/2026_05_07_000002_create_packages_table.php`
- `database/migrations/2026_05_07_083510_rename_flight_date_gap_to_flight_date_gaps_table.php`
- `database/migrations/2026_05_08_000002_create_bookings_table.php`
- `database/migrations/2026_05_08_000003_create_passengers_table.php`
- `database/migrations/2026_05_09_000001_create_currency_rates_table.php`
- `database/migrations/2026_05_09_000004_create_payments_table.php`
- `database/migrations/2026_05_09_000005_create_vouchers_table.php`
- `database/migrations/2026_05_10_000002_add_service_charge_to_packages_table.php`
- `database/migrations/2026_05_11_102742_create_visa_submissions_table.php`
- `database/migrations/2026_05_11_102746_create_cancelled_submissions_table.php`
- `database/migrations/2026_05_12_000002_create_fingerprints_table.php`
- `database/migrations/2026_05_14_000001_add_value_columns_to_passengers_and_bookings.php`
- `database/migrations/2026_05_17_000002_update_invoices_table.php`
- `database/migrations/2026_05_26_000001_fix_packages_service_charge_check.php`
- `database/migrations/2026_06_10_100001_add_status_to_visa_submissions_and_drop_from_passengers.php`
- `database/migrations/2026_06_21_000001_add_offer_price_and_increase_precision_to_issued_tickets.php`
- `database/migrations/2026_06_25_110800_update_visa_submissions_decimal_precision.php`
- `database/migrations/2026_06_26_000001_update_fingerprints_cost_precision.php`
- `database/migrations/2026_07_02_000001_allow_negative_balance_in_invoices_table.php`

### Non-migration fixes
- `tests/Feature/BookingEditPackagePreloadTest.php` — add `use RefreshDatabase;` trait
- `tests/Feature/ExampleTest.php` — expect 302 (redirect) instead of 200
- `resources/views/bookings/edit.blade.php` — already fixed (null-safe `optional()` + `$canApplyDiscount`), keep as-is

### CI workflow (optional improvement)
- `.github/workflows/build-push.yml` — already has PostgreSQL service; no changes needed since tests use SQLite

---

## Step-by-Step Tasks

### Task 1: Write a script to patch all migration files

**Objective:** Create a sed/Python script that finds every `DB::statement('ALTER TABLE ... ADD CONSTRAINT ... CHECK ...')` and wraps it in `if (DB::getDriverName() !== 'sqlite') { ... }`.

**Files:**
- Create: `scripts/fix_sqlite_check_constraints.py` (temporary, used once)

**Step 1: Write the script**

```python
#!/usr/bin/env python3
"""Patch all migrations to guard ALTER TABLE CHECK constraints for SQLite compatibility."""
import re
import glob
import os

migrations_dir = "database/migrations"
# Match both single-line and multi-line DB::statement('ALTER TABLE ... CHECK ...') calls
pattern = re.compile(
    r"DB::statement\('ALTER TABLE [^']+ ADD CONSTRAINT \S+ CHECK \((?:[^'\\]|\\.)*\)'\)",
    re.MULTILINE
)

files = glob.glob(os.path.join(migrations_dir, "*.php"))
changed_files = []

for filepath in files:
    with open(filepath, 'r') as f:
        content = f.read()

    matches = pattern.findall(content)
    if not matches:
        continue

    # Skip already-patched files
    if "getDriverName()" in content:
        continue

    # Ensure DB facade is imported
    if "use Illuminate\\Support\\Facades\\DB;" not in content:
        content = content.replace(
            "use Illuminate\\Support\\Facades\\Schema;",
            "use Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Schema;",
            1
        )

    # Wrap each DB::statement(...) in a driver guard
    def wrap_statement(m):
        sql = m.group(0)
        return f"if (DB::getDriverName() !== 'sqlite') {{\n            {sql}\n        }}"

    new_content = pattern.sub(wrap_statement, content)

    with open(filepath, 'w') as f:
        f.write(new_content)

    changed_files.append(filepath)

print(f"Patched {len(changed_files)} migration files:")
for f in changed_files:
    print(f"  {f}")
```

**Step 2: Run the script**

Run: `python3 scripts/fix_sqlite_check_constraints.py`

Expected output: List of all 25+ migration files patched.

**Step 3: Delete the temporary script**

Run: `rm scripts/fix_sqlite_check_constraints.py`

---

### Task 2: Verify migrations run on SQLite

**Objective:** Confirm all migrations now run successfully on SQLite in-memory.

**Step 1: Clear cached migrations**

```bash
php artisan optimize:clear
```

**Step 2: Run migrations fresh on SQLite**

```bash
php artisan migrate:fresh
```

Expected: All migrations complete without `syntax error: near "CONSTRAINT"`.

**Step 3: Commit**

```bash
git add database/migrations/
git commit -m "fix: guard PostgreSQL CHECK constraints for SQLite test compatibility"
```

---

### Task 3: Fix BookingEditPackagePreloadTest to use RefreshDatabase

**Objective:** The test imports `RefreshDatabase` but doesn't use it, causing it to render views against an incomplete schema.

**Files:**
- Modify: `tests/Feature/BookingEditPackagePreloadTest.php`

**Step 1: Add the trait**

Add `use RefreshDatabase;` inside the class body:

```php
class BookingEditPackagePreloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
```

**Step 2: Run the test**

```bash
php artisan test --filter=BookingEditPackagePreloadTest
```

Expected: 3 passed (or new failures if the Blade view still has null-safety issues — but those were already fixed).

---

### Task 4: Fix ExampleTest to expect redirect

**Objective:** The root route `/` is behind `auth` middleware, so it returns 302, not 200.

**Files:**
- Modify: `tests/Feature/ExampleTest.php`

**Step 1: Update the assertion**

```php
public function test_the_application_returns_a_successful_response(): void
{
    $response = $this->get('/');

    // Root route is behind auth middleware; unauthenticated
    // users are redirected to the login page.
    $response->assertStatus(302);
}
```

**Step 2: Run the test**

```bash
php artisan test --filter=ExampleTest
```

Expected: PASS

---

### Task 5: Run the full test suite

**Objective:** Verify all tests pass.

**Step 1: Run full suite**

```bash
php artisan test
```

Expected: All tests pass (4 passed).

**Step 2: Run Pint for code style**

```bash
vendor/bin/pint
```

Expected: All files formatted to PSR-12 + Laravel preset.

---

### Task 6: Configure PostgreSQL locally and verify

**Objective:** Switch `.env` to use PostgreSQL (running on port 5432 with default `postgres` user) and verify migrations work locally.

**Files:**
- Modify: `.env`

**Step 1: Update `.env` database config**

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=umrah_app_dev
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

**Step 2: Create the database on PostgreSQL**

```bash
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE umrah_app_dev;" 2>&1 || true
```

**Step 3: Run migrations on PostgreSQL**

```bash
php artisan migrate:fresh
```

Expected: All migrations complete without errors (CHECK constraints will be applied since driver is `pgsql`).

**Step 4: Re-run tests on SQLite** (confirm CI-style tests still pass)

```bash
php artisan test
```

Expected: All 4 tests pass.

---

### Task 7: Commit and push

**Step 1: Commit**

```bash
git add tests/ database/migrations/
git commit -m "ci: fix test DB compatibility and null-safety issues"
```

**Step 2: Push to branch**

```bash
git push -u origin ci-cd
```

---

## Verification Steps

1. `php artisan test` — all 4 tests pass
2. `npm run build` — Vite build succeeds (already verified)
3. CI on GitHub Actions — `test-php` job runs `migration + test` and passes

## Risks & Tradeoffs

- **CHECK constraints not enforced on SQLite:** The CHECK constraints are skipped on SQLite (tests only). They remain enforced on PostgreSQL (production). This means test-time validation of negative values won't catch violations, but the constraints still exist in production.
- **Migration idempotency:** The `down()` methods of affected migrations use `DROP CHECK IF EXISTS` with raw SQL that may also fail on SQLite — but since we skip the constraint on SQLite, there's nothing to drop. If this causes errors during `migrate:rollback` in tests, a secondary pass may be needed.
- **`.gitignore` for build artifacts:** `public/build/` is gitignored — the CI workflow's `test-js` job runs `npm ci` + `npm run build` which generates artifacts during the build job, not in tests. No conflict.
