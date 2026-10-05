# AUDIT - INVENTORY

Everything below was counted directly from the working tree on 2026-10-05 (read-only).

---

## 1. Code

| Metric | Value |
|---|---|
| App PHP files (`app/**`) | **198** - **22,620 LOC** |
| Models (`app/Models`) | **60** |
| Controllers | **57** (`app/Http/Controllers`, incl. 6 in subfolders) |
| Services (`app/Services`) | 17 (payment, cancellation, refund, invoice, voucher, ticket, profit, visa, ... ) |
| Queries (`app/Queries`) | 1 (`BookingPassengerQuery`, 595 LOC) |
| Middleware | auth stack + `CheckActive`, `CheckRole`, `TrustProxies` |
| Migrations | **149** |
| Seeders / factories | seeders present; **1 factory** (`UserFactory`) |

**Largest app files (LOC):**

| File | LOC |
|---|---:|
| `app/Http/Controllers/BookingController.php` | 3,020 |
| `app/Http/Controllers/PassengerController.php` | 1,092 |
| `app/Http/Controllers/TicketIssueController.php` | 957 |
| `app/Http/Controllers/ProfitLossReportController.php` | 788 |
| `app/Services/ProfitCalculationService.php` | 783 |
| `app/Http/Controllers/TicketRequestController.php` | 772 |
| `app/Http/Controllers/TicketFareController.php` | 668 |
| `app/Http/Controllers/FingerprintController.php` | 616 |
| `app/Queries/BookingPassengerQuery.php` | 595 |
| `app/Http/Controllers/BranchWiseReportController.php` | 562 |

33 transaction sites (`DB::transaction` + `beginTransaction`) across 17 files. `ensureBranchAccess` is defined in **7** controllers (duplicated - `MAINT-07`).

## 2. Routes (350)

Route files: `routes/web.php` (~650 LOC), `routes/booking-cancellation.php` (26 routes), plus `console.php`/`api.php` wiring in `bootstrap/app.php`.

| Bucket | Count |
|---|---:|
| `auth` + `role:` | 264 |
| `auth` only | 53 |
| `role:` only (guest -> 403) | 26 |
| guest | 7 |
| public mutating | 0 |

**All 53 auth-only routes** (the audit's authorization backlog): `/` (home), `GET /dashboard`, `GET/POST /bookings`, `GET /bookings/create|show|edit`, `GET /bookings/{id}/download-all-docs|print`, `POST /bookings/{id}/passengers|payment`, `PUT/PATCH /bookings/{id}`, `POST /passengers`, `GET /passengers`, `GET /passengers/create`, `GET /passengers/{id}`, `PUT/PATCH /passengers/{id}`, `DELETE /passengers/{id}`, `POST /passengers/{id}/documents`, `GET /passengers/{id}/documents/{doc}/download`, `GET /passengers/{id}/download-all-docs`, `GET /passengers/{id}/edit`, the whole `passenger-statuses` resource (6), `POST /documents/upload`, `POST /documents/passenger/upload`, `GET /documents/{doc}/download`, `DELETE /documents/{doc}`, `POST /bookings/{id}/payment`, `GET /api/bookings/passengers`, `GET /api/bookings/search-invoice`, `POST /api/bookings/calculate-type`, `GET /api/bookings/fingerprint-charge`, `GET /api/customers/search`, `GET /api/ticket-fares/baggage|filter|flight-date-gap`, `GET /ticket-fares/options`, `POST /api/banks/quick-create`, `POST /ticket-requests`, `GET /reports/branch-wise`, `GET /invoices/{id}/print`, `GET /settings`, `GET /settings/package/{package}`, `GET /fingerprint-charges`, `GET/DELETE visa-selling-prices[...]`, `GET packages/{id}/toggle-active` (404 stub), `POST /diagnostics/upload-failure`.

Route loading oddity: `routes/booking-cancellation.php` is required twice (`routes/web.php:618` outside the auth group, and `bootstrap/app.php:24-27`).

## 3. Frontend

| Metric | Value |
|---|---|
| Blade views | **153** - **40,027 LOC** (top: `bookings/index` 7,507; `bookings/show` 1,925; `bookings/edit` 1,323; `tickets/add-confirmation` 1,212; `passengers/index` 1,091; `bookings/create` 969; `reports/profit-loss` 966) |
| Layouts / includes | 1 layout (`layouts/app`, extended by 120 views), 14 `@include` |
| Components / partials | 13 components (**0 used**), 7 partials (1 unused: `bank-form-modal`) |
| `@php` / `@json` | 104 / 71 (0 raw echoes, 0 `json_encode` in views) |
| Alpine | 72 `x-data` (62 distinct lines), 22 named factories, 483 `x-show` (39 uncloaked), 196 `x-if`, 80 `x-for`, 762 `x-text`, **1 `x-html`**, 249 `x-cloak` |
| Forms | 143 total (8 GET filters, 112 with `@csrf`, 23 without - all JS-intercepted) |
| `fetch` | 131 sites (70 state-changing, **0 missing CSRF**, 40 with no `catch`/`try`) |
| JS | `resources/js/app.js` 196 LOC (imports `booking.js`), **`booking.js` 5,136 LOC**, `bootstrap.js` 4 (axios), `utils/duration.js` 113; 0 `AbortController` usage |
| CSS | `resources/css/app.css` 75 LOC (Tailwind v4 via `@import "tailwindcss"` + `@theme`; 23 lines duplicated verbatim); **dead** `tailwind.config.js` 29 LOC |
| Build | Vite 7 (`input: app.css, app.js`), `@tailwindcss/vite`, laravel-vite-plugin; `npm run build` / `npm run dev` |
| Reference material | `ui-references/` (read-only by policy - never modified) |

## 4. Database (live dev, read-only)

| Metric | Value |
|---|---|
| Tables / columns | **69 / 680** |
| Foreign keys | **166** (all indexed) |
| Indexes | **262** (69 PRIMARY) |
| Migrations | **149** |
| Largest tables (rows) | `issued_ticket_logs` 1,248 - `passengers` 1,240 - `visa_update_logs` 2,554 - `documents` 3,161 - `vouchers`/`payments` 1,780 - `bookings` 794 - `invoices` 793 - `customers` 846 |
| Domain volumes | 794 bookings, 1,240 passengers, 1,285 visa submissions, 1,802 payments, 3,161 documents, 81 ticket fares, 57 packages, 53 routes (airport/route table) |
| Index gaps proven | `documents(owner_type,owner_id)`, `payments(created_at)`, `bookings(created_at)` all `EXPLAIN type=ALL` (see `AUDIT-DATABASE.md`) |

Raw dumps kept as evidence at the repo root: `audit-tmp-routes.md`, `audit-tmp-tablesize.txt`, `audit-tmp-indexes.txt`, `audit-tmp-fks.txt`, `audit-tmp-columns.txt`.

## 5. Dependencies

**PHP (`composer.json` requires - 8):** `php ^8.2`, `laravel/framework ^12.0` (installed **12.66.0**), `doctrine/dbal ^4.4`, `laravel/tinker`, **`livewire/livewire ^4.4` - 0 references in `app/`, `resources/`, `routes/` (unused dependency)**, `monicahq/laravel-cloudflare ^4.1` (powers `TrustProxies`), `setasign/fpdf` + `setasign/fpdi` (PDF export).

**Advisories:** `composer audit --locked` = 4 advisories / 3 packages (laravel/framework <12.69 - mitigated by `APP_DEBUG=false`; league/commonmark med+high - no markdown usage; league/flysystem low).

**JS (`package.json` - all devDependencies, no runtime deps):** `vite ^7.0.7`, `tailwindcss ^4.0.0` + `@tailwindcss/vite`, `alpinejs ^3.14.0`, `laravel-vite-plugin`, `axios ^1.11.0`, `concurrently`. **Advisories:** `npm audit` = 2 (axios high - dev only; esbuild low - dev server).

## 6. Tests

115 files: 98 `Feature/`, 14 `Unit/`, 2 `Concerns/`, base `TestCase.php`. Suite: **723 passed / 0 failed / 2,833 assertions / 304 s** against MySQL `umrah_test` (`phpunit.xml`). 40 authorization assertions across 13 files; 0 payload-redaction, 0 document-authz, 0 payment-authz, 0 concurrency tests (see `AUDIT-TESTING.md`).

## 7. Config / deploy artifacts

* `docker-compose.yml`, `docker-compose.prod.yml`, `Dockerfile` (multi-stage: Node 22 build + PHP 8.4-fpm-alpine with nginx/supervisord), `docker/entrypoint.sh`, `docker/nginx/conf.d/default.conf`, `docker/scripts/setup-env.sh`.
* `.github/workflows/build-push.yml` (test-php, test-js, build+push to ghcr.io).
* `deploy-prod.sh`, `deploy-staging.sh`, `deploy-prod-v2.sh`, `deploy-production.sh`.
* `phpunit.xml`, `pint.json`-less Pint defaults, `EditorConfig`, `AGENTS.md`, `docs/`.

## 8. Tools used for this audit

PowerShell 5.1 (counts, middleware bucketing, file scans) - file-level reading/search of all source - `php artisan route:list --json` (350 routes) - `php artisan test` (723 tests) - `composer audit --locked` - `npm audit` - MariaDB CLI (`SHOW TABLES/INDEX/KEY_COLUMN_USAGE`, `SHOW CREATE`, read-only `EXPLAIN SELECT`) - three delegated deep-dive agents (frontend, business logic, inventory) whose claims were re-verified line-by-line.

**Nothing was modified:** no package install, no migration, no code edit; `git status` shows only the 5 `audit-tmp-*` evidence files and the new `audit/` folder as untracked.

## 9. Not audited

Git history secrets (excluded by decision), production/Cloudflare edge config (repo files only), load/stress testing, dynamic exploitation, `ui-references/` parity, accessibility/E2E, business-requirement correctness, production data volumes (local dev DB only).
