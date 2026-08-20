# AGENTS.md

> **For agents working in this repo.** This file documents conventions, tooling,
> and workflows for the **umrah-app** Laravel project. Read it before making
> changes.
>
> For the full development handbook (onboarding, architecture, domain reference,
> CI/CD details), see [docs/README.md](docs/README.md).

---

## 1. Project Overview

**Umrah App** is a Laravel 12 web application for umrah travel agency management.
It handles bookings, passengers, visas, tickets, fingerprints, invoices, payments,
vouchers, reports, branches, banks, airlines, routes, and currency rates.

### Tech Stack

| Layer              | Technology                                  |
|--------------------|---------------------------------------------|
| Backend            | Laravel 12, PHP 8.4                         |
| Database (prod)    | MySQL 8.0 + Redis 7                         |
| Database (local)   | SQLite (in-memory for tests)                |
| Frontend           | Blade templates, Vite 7, Tailwind CSS v4, Alpine.js |
| Testing            | PHPUnit 11                                  |
| Code Style         | Laravel Pint (PSR-12 + Laravel preset)      |
| Containerization   | Docker (multi-stage: Node 22 + PHP 8.4-fpm-alpine) |
| CI/CD              | GitHub Actions → ghcr.io + Watchtower       |
| Deployment         | ISPConfig server, `deploy-prod.sh`          |

### Architecture

- **MVC** pattern with Eloquent ORM
- Controllers in `app/Http/Controllers/`
- Models in `app/Models/` (~50 models)
- Migrations in `database/migrations/` (~70 files)
- Blade views in `resources/views/` (organized by domain: bookings, fares, visas, fingerprints, reports, invoices, settings, etc.)
- Routes in `routes/web.php`
- 13 reusable Blade components in `resources/views/components/`

### UI References

The `ui-references/` folder contains original HTML/JS design files. **Do not modify
files in this folder.** Use them as references only for new implementations. The
actual Blade templates live in `resources/views/`.

---

## 2. Tool Setup

### Prerequisites

- PHP 8.4+
- Composer 2.x
- Node.js 22+
- MySQL 8.0 (for production parity)
- Redis 7 (for production caching/queues)

### Local Environment

#### Option A: Docker (recommended)

```bash
docker compose up -d       # Start app + MySQL 8.0 (port 8080)
```

App: `http://localhost:8080`
MySQL: `127.0.0.1:3306`

#### Option B: Local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev
```

### Production Environment

```bash
bash docker/scripts/setup-env.sh   # Creates .env.production from sample
php artisan key:generate           # Or in-container
./deploy-prod.sh                   # Pulls image, migrates, restarts
```

---

## 3. Development Workflow (TDD-First)

This project follows a **test-driven development (TDD)** workflow. Every feature
or bug fix should start with a failing test.

### The TDD Cycle

```
1. Write a failing test
2. Run it to confirm failure
3. Write the minimal code to make it pass
4. Run the test to confirm success
5. Refactor (improve code quality while keeping tests green)
6. Run the full test suite
7. Commit
```

### Running Tests

```bash
# Run all tests
php artisan test
# or: vendor/bin/phpunit

# Run a specific test file
php artisan test tests/Feature/BookingEditPackagePreloadTest.php
# or: vendor/bin/phpunit tests/Feature/BookingEditPackagePreloadTest.php

# Run a specific test method
php artisan test --filter=test_blade_renders_preselected_package_id
# or: vendor/bin/phpunit --filter=test_blade_renders_preselected_package_id

# Run with verbose output
php artisan test -v
```

### TDD Example

**Step 1: Write failing test**

```php
// tests/Feature/MyFeatureTest.php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_returns_expected_data(): void
    {
        // Arrange
        // Act
        $response = $this->get('/my-feature');
        // Assert
        $response->assertOk()
                 ->assertSee('expected content');
    }
}
```

**Step 2: Run to confirm failure**
```bash
php artisan test --filter=test_feature_returns_expected_data
# Expected: FAIL — route not found or controller missing
```

**Step 3: Write minimal implementation**

Create the route in `routes/web.php`:
```php
Route::get('/my-feature', [MyFeatureController::class, 'index']);
```

Create the controller:
```php
// app/Http/Controllers/MyFeatureController.php
class MyFeatureController extends Controller
{
    public function index()
    {
        return view('my-feature.index', ['data' => 'expected content']);
    }
}
```

Create a minimal view:
```blade
{{-- resources/views/my-feature/index.blade.php --}}
<div>expected content</div>
```

**Step 4: Run to confirm pass**
```bash
php artisan test --filter=test_feature_returns_expected_data
# Expected: PASS
```

**Step 5: Run full suite**
```bash
php artisan test
```

---

## 4. Code Style & Conventions

### PSR-12 + Laravel Pint

The project uses Laravel Pint for code formatting. Before committing, always run:

```bash
vendor/bin/pint
```

This applies PSR-12 + Laravel preset formatting. Pint uses default rules
(no `pint.json` config file exists; defaults are sufficient).

### EditorConfig

- **Charset:** UTF-8
- **Line endings:** LF (Unix)
- **Indentation:** 4 spaces (2 spaces for `.yml`/`.yaml`)
- **Trailing whitespace:** Trim (except in Markdown)
- **Final newline:** Ensure present

### Naming Conventions

- **Models:** PascalCase, singular (e.g., `Booking`, `Passenger`, `VisaAgent`)
- **Migrations:** `YYYY_MM_DD_HHMMSS_descriptive_name.php`
- **Controllers:** PascalCase, suffixed with `Controller` (e.g., `BookingController`)
- **Routes:** snake_case names (e.g., `booking.index`, `booking.edit`)
- **Views:** dot-notation paths matching route structure (e.g., `bookings.edit`, `reports.profit-loss`)
- **Database tables:** plural snake_case (e.g., `bookings`, `passengers`, `visa_submissions`)

### Blade Templates

- Use `route()` helper for all internal links
- Include navigation via `@include('partials.nav')`
- Use Alpine.js (`x-data`, `x-show`, `x-model`, `@click`, `:class`) for interactivity
- Reference `ui-references/` for design — do not modify files in that folder

---

## 5. Testing Conventions

### Test Structure

```
tests/
├── Feature/          # HTTP-level tests (routes, controllers, views)
│   ├── Auth/         # Authentication tests (if present)
│   └── ExampleTest.php
├── Unit/             # Isolated unit tests
│   └── ExampleTest.php
└── TestCase.php      # Base test case
```

### Best Practices

1. **Use `RefreshDatabase`** for tests that interact with the database:
   ```php
   use Illuminate\Foundation\Testing\RefreshDatabase;

   class MyTest extends TestCase
   {
       use RefreshDatabase;
       // ...
   }
   ```

2. **Manual schema for test-only tables** (established pattern from `BookingEditPackagePreloadTest`):
   ```php
   protected function setUp(): void
   {
       parent::setUp();
       Schema::create('temp_table', function ($table) {
           $table->id();
           $table->string('name');
           $table->timestamps();
       });
   }
   ```

3. **View rendering tests**: Pass all required variables explicitly:
   ```php
   $view = view('bookings.edit', [
       'booking' => $booking,
       'packages' => collect([]),
       // ... all required variables
   ]);
   $html = $view->render();
   $this->assertStringContainsString('expected', $html);
   ```

4. **Factories**: Only `UserFactory` currently exists. Create factories for new models as needed:
   ```bash
   php artisan make:factory ModelFactory --model=Model
   ```

5. **Database for tests**: PHPUnit config uses SQLite in-memory by default:
   - `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`

### What Not to Do

- **Do not** test trivial getters/setters
- **Do not** skip `RefreshDatabase` when testing DB-dependent code — use it
- **Do not** modify files in `ui-references/`

---

## 6. Git Workflow

### Branch Strategy

- **Trunk-based**: Work is committed directly to `main`
- Create a feature branch for larger changes:
  ```bash
  git checkout -b feat/my-feature
  ```

### Commit Messages

Use **Conventional Commits**:

```
feat: add new report type
fix: resolve null pointer in BookingController
chore: update composer dependencies
docs: add deployment notes to README
refactor: extract booking query to repository
test: add coverage for invoice export
```

### Before Pushing

1. Ensure all tests pass: `php artisan test`
2. Run Pint: `vendor/bin/pint`
3. Ensure build passes: `npm run build`
4. Validate Docker: `docker compose config --quiet`

---

## 7. Commit Checklist

Before each commit, verify:

- [ ] **Tests written** — New/changed code is covered by tests (TDD-first)
- [ ] **Tests pass** — `php artisan test`
- [ ] **Code formatted** — `vendor/bin/pint`
- [ ] **Build passes** — `npm run build`
- [ ] **Docker validates** — `docker compose -f docker-compose.yml config --quiet`
- [ ] **Docker prod validates** — `docker compose -f docker-compose.prod.yml config --quiet`
- [ ] **Commit message** follows Conventional Commits format
- [ ] **One logical change** per commit (frequent, small commits preferred)

---

## 8. CI/CD

### Continuous Integration

Push to `main` (or open a PR to `main`) to trigger CI

**GitHub Actions workflow:** `.github/workflows/build-push.yml`

Jobs (run in parallel where possible):

1. **test-php** — Sets up PHP 8.4, starts MySQL 8.0 service, runs migrations + PHPUnit
2. **test-js** — Node 22, `npm ci`, `npm run build`
3. **build** — After tests pass: Docker Buildx, login to ghcr.io, build + push image

### Container Registry

- Images pushed to: `ghcr.io/mostafiz-8bits/umrah-app`
- Tags: `latest` + `sha-<short-sha>`
- Build cache: `buildx-cache:latest` in registry

### Continuous Deployment

- **Watchtower** is labeled on the prod app container (`com.centurylinklabs.watchtower=true`)
- Pushes to `main` trigger CI → ghcr.io — Watchtower auto-pulls and restarts within ~5 minutes
- Manual deploy: `./deploy-prod.sh` (pulls image, migrates, restarts safely)

### Production Compose

```bash
docker compose -f docker-compose.prod.yml up -d
```

Services: app (ghcr.io image), MySQL 8.0, Redis 7

---

## 9. Docker

### Development

```bash
docker compose up -d          # Build + start app + MySQL 8.0
docker compose down           # Stop and remove containers
docker compose logs -f app    # Follow app logs
docker compose exec app bash  # Shell into app container
```

Ports:
- App: `8080` → nginx (port 80 in container)
- MySQL: `3306` → 3306

### Production

```bash
docker compose -f docker-compose.prod.yml up -d    # Start prod stack
docker compose -f docker-compose.prod.yml down     # Stop prod stack
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

The `docker/entrypoint.sh` runs on container start:
1. Fixes storage permissions
2. Caches config, routes, views
3. Runs migrations (unless `MIGRATE=false`)

### Dockerfile

Multi-stage build:
1. **Node 22 (alpine)** — builds Vite assets (`npm ci`, `npm run build`)
2. **PHP 8.4-fpm-alpine** — installs PHP extensions (pdo_mysql, mysqli, intl, mbstring, zip, redis), Composer deps, Nginx, Supervisord

---

## 10. Common Tasks

### Run all tests
```bash
php artisan test
```

### Run a single test
```bash
php artisan test --filter=test_method_name
```

### Format code
```bash
vendor/bin/pint
```

### Build frontend assets
```bash
npm run build      # production
npm run dev        # development (watch mode)
```

### Run migrations
```bash
php artisan migrate
php artisan migrate --force          # production (in Docker)
php artisan migrate:rollback         # rollback last batch
```

### Clear all caches
```bash
php artisan optimize:clear
```

### Generate the app key
```bash
php artisan key:generate
```

### Create a model + migration + factory + test
```bash
php artisan make:model ModelName -mft
```

### Create a controller
```bash
php artisan make:controller ControllerName
```

### Access the app container shell
```bash
docker compose exec app bash
# or for prod:
docker compose -f docker-compose.prod.yml exec app bash
```

---

## 11. Troubleshooting

### `.env.production` not found when running docker-compose.prod.yml

This file is created at deploy time by `bash docker/scripts/setup-env.sh`.
It is intentionally not committed to git (`.env.*` is in `.gitignore`).

### Migration failed in Docker

Check the entrypoint logs:
```bash
docker compose -f docker-compose.prod.yml logs app
```

If `MIGRATE=false` is set in `.env.production`, manually run:
```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

### Pint is not available

Ensure dev dependencies are installed:
```bash
composer install --no-interaction --prefer-dist
```

Pint is in `require-dev` — it won't be available in the production Docker image
(intentional: no dev deps in prod).

### Frontend assets not loading

Ensure you've built the assets:
```bash
npm install
npm run build
```

The `public/build/` directory must be populated. In Docker, assets are built
during the Docker image build (Node stage).

### Database connection refused locally

Start the database container:
```bash
docker compose up -d db
```

Or check that local MySQL is running:
```bash
mysqladmin ping -h 127.0.0.1 --password=$DB_PASSWORD
```

### `Failed to load trust proxies from Cloudflare server` (500 on every request)

The globally-prepended `TrustProxies` middleware (`app/Http/Middleware/TrustProxies.php`)
fetches Cloudflare IP ranges via HTTPS at runtime. On Windows PHP (e.g. winget installs),
this fails with `cURL error 60: unable to get local issuer certificate` unless a CA bundle
is configured — `curl.cainfo` and `openssl.cafile` must point at a `cacert.pem`
(download from <https://curl.se/ca/cacert.pem>) in `php.ini`.

Quick local-dev alternative: set `LARAVEL_CLOUDFLARE_ENABLED=false` in `.env` (gitignored)
to make the middleware a no-op. Production keeps it enabled (Docker/Alpine bundles CA certs).
