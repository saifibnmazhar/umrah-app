# AUDIT REPORT - umrah-app

**Project:** Umrah App (Laravel 12.66.0, PHP 8.4 target / 8.5 CLI locally, MySQL 8.0 prod, MariaDB 12.3 local dev)
**Repo:** `C:\Users\cdrmo\projects\umrah-app` - **Audit date:** 2026-10-05
**Mode:** read-only on all application source; the only new files are the reports in `audit/`.
**Result:** 113 findings (13 HIGH, 63 MEDIUM, 24 LOW, 13 INFO) + 1 withdrawn false positive.

---

## 1. Method

Phases executed in order; every finding was re-read in the source before it was written down (file + line numbers are exact).

| # | Phase | Output |
|---|---|---|
| 0 | Baseline and inventory | versions, dependency counts, git-clean check |
| 1 | Secrets and configuration | tracked-tree secret scan (0 hits), `.env*` review (values redacted) |
| 2 | Database schema and integrity | live `SHOW TABLES/INDEXES/KEY_COLUMN_USAGE` dumps, FK/PK audit, orphan tables |
| 3 | Performance (static) | query-pattern search, payload sizes, caching, dashboard query counts |
| 4 | Performance (runtime, read-only) | `EXPLAIN` against the local dev DB (SELECT/SHOW only) |
| 5 | Architecture and code quality | controller sizes, duplication, dead code, service layer |
| 6 | Blade / XSS / raw output | all 153 views: `{!! !!}`, `x-html`, `innerHTML`, `@json` |
| 7 | Alpine.js and Tailwind | `x-show`/`x-cloak`, `fetch` (CSRF + error handling), dead `tailwind.config.js` |
| 8 | Routes and API surface | `route:list --json` -> 350 routes, middleware matrix |
| 9 | Authorization matrix | per-route auth/role/none buckets + controller `abort(403)`/`ensureBranchAccess` cross-check |
| 10 | Testing | full PHPUnit run (723 passed), coverage mapping against findings |
| 11 | Configuration and deps | `composer audit --locked`, `npm audit`, nginx/session/queue config |
| 12 | Error handling and observability | `bootstrap/app.php` exception renderers, `Log::` usage, silent catches |
| 13 | Maintainability | size metrics, dead modules, duplicated helpers, stale docs |
| 14 | Verification pass | every HIGH/MEDIUM re-read; three claims corrected (section 4) |

**Tools used:** PowerShell 5.1 (inventory, counting, middleware bucketing), file-level reading/search of the source, `php artisan route:list --json` (350 routes dumped), `php artisan test` (723 tests), `composer audit --locked`, `npm audit`, MariaDB CLI (`SHOW INDEX`, `SHOW CREATE`, read-only `EXPLAIN SELECT`), and three delegated deep-dive agents (frontend, business logic, inventory) whose claims were re-verified before inclusion. No package was installed, no migration was run, no application file was modified.

---

## 2. Route and authorization matrix (Phase 8-9)

| Bucket | Count | Notes |
|---|---:|---|
| `auth` + `role:` | 264 | intended posture |
| `auth` only (no role) | 53 | see section 3 - several are money/PII endpoints |
| `role:` only (no `auth`) | 26 | all from `routes/booking-cancellation.php`; guests get **403**, not a login redirect (`CheckRole.php:12-15`) |
| guest | 7 | `login` GET/POST, `logout`, `_session/ping`, `up`, `storage/*` |
| public mutating | 0 | no state-changing GET route exists (the stub at `routes/web.php:139` `abort(404)`s) |

The cancellation route file is loaded **twice**: `routes/web.php:618` (`require`, outside the `auth` group that ends at `:616`) **and** `bootstrap/app.php:24-27` (`then:` with `Route::middleware('web')`). Laravel silently overwrites the duplicate URIs - today harmless, but the cancellation routes' middleware stack then depends on load order. *(SEC-04)*

---

## 3. Highest-value findings with evidence

### 3.1 Authorization (16 findings, 3 HIGH)

**AUTH-01 (HIGH) - financial JSON for every role.** `routes/web.php:221` (`/api/bookings/passengers`) sits in the plain `auth` group opened at `:84`. `BookingController::passengerData()` emits `'profit' => (float)($p->profit ?? 0)` (`:549`), `'refund_payable'` (`:550`), invoice `total_amount/balance/paid_amount` (`:571-573`), `'cost'` (`:592`), summary `total_package_value`/`total_due` (`:631-634`) and `'profit_breakdown'` (`:803`). The only gate is cosmetic: `resources/views/bookings/index.blade.php:125` (`$canViewFinancialColumns`) drops table columns while the payload is unchanged. Any authenticated role can script the endpoint across all bookings.

**AUTH-02 / AUTH-03 (HIGH) - money movement without privilege.** `POST /bookings/{booking}/payment` (`routes/web.php:186`) has no `role:` (its neighbours at `:185` and `:187` do), and `storePayment()` (`BookingController.php:2697-2716`) never calls `ensureBranchAccess()` - that helper *is* used at `:1888, 2002, 2241, 2286, 2320, 2483, 2879, 2896`. Separately, `bookings.store` (`routes/web.php:155`) zeroes `discount_value` for non-admins at `BookingController.php:1453-1455` but then computes the invoice from the **raw** request at `:1644-1656`, so a non-admin still cuts the invoice they are not allowed to discount.

**AUTH-04..AUTH-06 (MEDIUM) - supplier costs exposed.** `net_fare`/`selling_fare` are embedded in the page at `bookings/index.blade.php:106-107` and serialized at `:3478` *before* `$canViewFinancialColumns` is computed at `:125`; `/api/ticket-fares/filter` (`routes/web.php:222`) returns `'net_fare' => $fare->net_fare` (`TicketFareController.php:376`); `/ticket-fares/options` (`routes/web.php:119`) returns whole `TicketFare` models (`TicketRequestController.php:729-750`) - and `TicketFare` has **no `$hidden`** (only `User.php:37` does).

**AUTH-07..AUTH-14 (MEDIUM) - IDOR and inconsistent branch scoping.** `DocumentController::download` (`:64-86`) performs **no** ownership check; `upload`/`uploadPassenger` (`:15-62`, `:88-129`) accept any `booking_id`/`passenger_id`; `PassengerController::downloadDocument` checks owner (`:450`) but not branch; `downloadAllDocuments` (`:507`) checks neither; `bookings.print` (`routes/web.php:183`, `BookingController.php:2552`) has no `ensureBranchAccess`; `documents.destroy` is role-only (`DocumentController.php:131-140`); `passengers.update-status` omits the branch check every sibling method has (`PassengerController.php:840-889` vs `:75,283,479,774,808`).

**AUTH-10 / AUTH-11 / AUTH-12 (MEDIUM) - auth-only writes and reports.** `Route::resource('passenger-statuses', ...)` (`routes/web.php:151`) is completely ungated while `PassengerStatusController` performs no internal check; `POST /api/banks/quick-create` (`routes/web.php:613`) lets any authenticated user create payment master data (`BankController.php:74-98`); `GET /reports/branch-wise` (`routes/web.php:535`) is auth-only while its siblings at `:533`, `:534` and `:536` require `role:...,Auditor`.

### 3.2 Authentication and configuration (8 findings, 1 HIGH)

**SEC-01 (HIGH) - no login throttling.** `Route::post('/login', [LoginController::class, 'login'])` (`routes/web.php:69`) carries only the `web` middleware; `LoginController`, `bootstrap/app.php` and all providers contain **no** `throttle`/`RateLimiter`. The only throttled routes in the app are three refund endpoints (`routes/web.php:172,175,178`) and `ticket-fares/quick-create`. Credential handling itself is correct (`session()->regenerate()` on success, `CheckActive` invalidates deactivated accounts).

**SEC-07 (MEDIUM) - dependency advisories.** `composer audit --locked`: `laravel/framework` <12.69 (XSS in debug page - installed 12.66.0, **mitigated** by `APP_DEBUG=false` in `.env.production.sample`), `league/commonmark` medium + high (the app performs **0** markdown rendering), `league/flysystem` low (control-character path bypass - relevant because uploaded filenames come from clients). `npm audit`: `axios` high (dev dependency, referenced only from `resources/js/bootstrap.js`) and `esbuild` low (dev server only).

**SEC-02..SEC-06 (LOW).** No security headers/HSTS in `docker/nginx/conf.d/default.conf` (Cloudflare may add them - Needs Verification); guests on role-only routes receive 403 instead of a login redirect; sensitive payloads are logged (`PaymentService.php:20,24,86` log full payment arrays at `info`, `BookingController.php:1661` logs a payment debug line, `bootstrap/app.php:54-59` logs SQL bindings); `DiagnosticController::recordUploadFailure` logs unvalidated `$request->input()` (`routes/web.php:165`).

### 3.3 XSS / CSRF (7 findings)

`x-html` appears exactly once (`bookings/index.blade.php:626`, bound to a DB-derived city code). Client-supplied filenames reach `innerHTML` at four sites (`passengers/show.blade.php:615`, `passengers/edit.blade.php:1260`, `bookings/show.blade.php:1844,1880`) with `display_name = $file->getClientOriginalName()` (`DocumentController.php:117`) - a working `escapeHtml()` helper already exists in the same file (`bookings/show.blade.php:1192-1196`). The only `{!! !!}` in the app lives in an unused component. CSRF is clean: 70/70 state-changing `fetch` calls carry a token and no state-changing GET route exists.

### 3.4 Business logic (45 findings, 7 HIGH)

* **Races / locking (L-01, L-02, S-03, M-13):** `RefundController.php:206` reads `refund_payable` **before** the transaction opens at `:83`/`:222`; `CancellationService.php:113` checks the guard, opens the transaction at `:117`, and only writes the terminal status at `:217-225`, with no `lockForUpdate`; `RefundCapService` validates an unlocked `SUM`, so concurrent confirmations can each pass.
* **Money math (M-02, M-05, M-06):** `bdt_amount` is passed through from the client (`PaymentService.php:202-213`, `BookingController.php:2715-2716`); re-issue `refund_adjustment` adds the full customer payment back (`ReIssueController.php:233-243`) while `InvoiceService.php:44` excludes that payment from `paid_amount`; `updatePayment` recomputes `paid_amount` as a plain `payments()->sum('amount')` (`BookingController.php:2849-2859`) instead of using `InvoiceService.php:36-58`.
* **Transactions (T-02..T-07):** `PassengerController::update` performs passenger update, fare recalculation, `VisaSubmission::create`, `syncFinancials` and `recalculateBookingProfit` (`:606-671`) with no transaction and a catch that neither rolls back nor logs. `removePassenger` (`BookingController.php:2489-2510`) force-deletes issued tickets before the FK-restricted passenger delete. **Correction:** the *live* booking-create path *is* transactional (`BookingController.php:1418` -> `:1692`); the non-transactional `BookingService::processBookingWithPassengers` (`BookingService.php:266-298`) has **no callers**.
* **State machines (S-01..S-08):** cancellation, refund, ticket-issue/void and fingerprint status transitions are checked **before** the transaction opens (`RefundController.php:78` vs `:83`; `CancellationService.php:113` vs `:117`; `TicketIssueController.php:69,232`), and `FingerprintController` writes six statuses directly (`:475-585`) bypassing any transition service.
* **Currency and dates (M-02, M-07, M-10, R-01..R-03):** report day boundaries mix UTC (`config/app.php:68`), `Asia/Riyadh` (`TicketIssueController.php:554`) and BD staff expectations; the payment-receiving print converts with the **first-ever** currency rate (`routes/web.php:475,498`); refund/voucher rows are written with `bdt_amount = 0`; the branch-due report renders hardcoded placeholders (`$total_cost ?? 70000`, BIZ M-08).

### 3.5 Database (9 findings, 2 live bugs)

* **DB-03 (MEDIUM, live 500):** `TransactionTypeController` validates `unique:transaction_type,name` (`:29`) and `Rule::unique('transaction_type', ...)` (`:54`), but the table was renamed to `transaction_types` by `2026_05_17_000001`. Proven: `SELECT COUNT(*) FROM transaction_type` returns `ERROR 1146: Table 'binmishal_umrah_local.transaction_type' doesn't exist`. The route is live (`routes/web.php:150`).
* **DB-01 / DB-02 (MEDIUM):** `documents` has **only** a PRIMARY key - `EXPLAIN SELECT id FROM documents WHERE owner_type=... AND owner_id=5` gives `type=ALL, rows=3161, possible_keys=NULL`. Same shape for `payments` (`rows=1780`) and `bookings` (`rows=794`) on `created_at`, which 84 call sites filter with `whereDate('created_at', ...)` (73 in `app/`, 11 in `routes/web.php`).
* **DB-04 (LOW):** `StoreBookingRequest.php:26` / `UpdateBookingRequest.php:19` still validate `exists:offices,id` although `offices` was dropped by `2026_06_12_100003` - currently unreachable because both FormRequests are unused by controllers.
* **DB-05 / DB-06 / DB-07 (MEDIUM):** `Passenger` has no `SoftDeletes` while its dependents do (restrict FKs); `forceDelete` of issued tickets cascades away `issued_ticket_logs`; `cancelled_bookings` has no unique backstop on `booking_id`.
* Schema health is otherwise good: 69 tables, 166 FKs (all indexed), every table has a PRIMARY key, 262 indexes, 149 migrations.

### 3.6 Performance (7 findings)

* **PERF-02:** the application never caches - the only `Cache::` hit in `app/` is a comment (`SessionInvalidator.php:19`). The dashboard issues **14 separate `->count()` calls** plus 12 `selectRaw` aggregate queries per render (`DashboardController.php:74-95,312-346,409`).
* **PERF-03:** `CheckRole.php:13` runs `roles()->whereIn('name', ...)->exists()` on every one of the 264 role-protected requests; the relation is never memoized for middleware.
* **PERF-05 / PERF-06:** `loadPassengerData()` has 42 call sites and no `AbortController` (0 matches in `resources/`), and 40 of 131 `fetch(` sites have neither `.catch` nor `try` - `pending-refunds/index.blade.php` contains **zero** occurrences of `catch`.
* Volumes are still small (794 bookings, 1,240 passengers, 3,161 documents), so today's cost is modest - these are growth risks, with the index gaps provable now.

### 3.7 Maintainability (15 findings, 2 HIGH)

* **MAINT-01 (HIGH):** `resources/views/bookings/index.blade.php` is **7,507 lines / 459 KB**, of which lines `2854-7504` are one `<script>` block; it holds 11 modals, 29 `fetch` calls, 42 `loadPassengerData()` calls, and a `@php` data-assembly block at `:4-119` that runs on every view. It is about 31% of all Blade lines.
* **MAINT-02 (HIGH):** `resources/views/invoices/print.blade.php:83` calls `route('invoices.details', ...)` - that name exists nowhere (only `invoices.print` at `routes/web.php:548`), producing an **unconditional 500 on a live, role-unprotected route**. Three more views use undefined route names, and `PackageController` has 7 orphaned methods because `routes/web.php:140-142` is commented out.
* **MAINT-08 / MAINT-09 (MEDIUM):** of 120 `catch (\Exception` blocks, **103 have no `Log::error`/`report()` within the following 15 lines**; raw `$e->getMessage()` is shown to users on money screens (`BookingCancellationActionController.php:38,55,90`, `PassengerCancellationActionController.php:35,52,74`, `RefundController.php:307`, `ReIssueController.php:256`, `TicketRequestController.php:398`).
* Dead weight: 13 unused Blade components plus 1 unused partial (0 `<x-...>` usages in 153 views), a dead `tailwind.config.js`, a duplicated CSS block (`app.css:31-53` vs `:55-73`), a 255-line passenger-form partial duplicated inline at 244 lines, and invoice/voucher modules unreachable behind commented routes.

### 3.8 Testing (6 findings)

Full suite: **723 passed / 0 failed / 2,833 assertions / 304 s**. Coverage of the *domain* is genuinely strong (profit calculation, cancellation, refunds, fare snapshots, effective-date filters). The gaps sit exactly where the security findings live: **0** tests reference document download/upload authorization, **0** reference `bookings.payment.store`, **0** reference concurrency/`lockForUpdate`, and no test asserts that financial fields are withheld from low-privilege roles.

---

## 4. Corrections to earlier interim notes

| Earlier claim | Status |
|---|---|
| "Ticket issue/refund/re-issue routes have no `role:` middleware (A-01)" | **Withdrawn - false positive.** They sit inside `Route::middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff')->group(...)` at `routes/web.php:580`. |
| "Only 9 `DB::transaction` usages app-wide" | **Corrected:** 33 sites (`DB::transaction` + `beginTransaction`) across 17 files. |
| "Booking + passengers created with no transaction (T-01)" | **Corrected:** the live path is transactional (`BookingController.php:1418` -> `:1692`); `BookingService::processBookingWithPassengers` (the non-transactional one) has no callers. Recorded as dead code. |
| "AGENTS.md: ~70 migrations, SQLite in-memory tests, 50 models" | **Stale:** 149 migrations, 60 models, and `phpunit.xml` uses MySQL `umrah_test` (MAINT-14 / TEST-06). |

---

## 5. Recommended sequencing

1. **Week 1 - stop the bleeding:** actions 1-5 in `AUDIT-SUMMARY.md` (login throttle, financial payload gating, payment/document authorization, discount gate, money-path locks).
2. **Week 2 - correctness:** server-side `bdt_amount`, a single `paid_amount` formula, the passenger-update transaction, the two live 500s.
3. **Week 3 - observability and data:** log in the silent catches, stop returning raw exception messages, add the three indexes, fix the disk-mismatched document deletes.
4. **Ongoing - maintainability:** extract the `bookings/index.blade.php` script, delete dead components/modules, centralise authorization helpers, and keep the suite green (`php artisan test`, `vendor/bin/pint`, `npm run build`).

---

## 6. Unaudited areas and limitations

* **Git history secrets** - excluded by decision (current tree only; 0 findings).
* **Production / Cloudflare configuration** - only repository files were read; edge header behaviour is *Needs Verification*.
* **Dynamic exploitation** - no exploit, fuzz or load test was run; concurrency findings are static traces and are labelled accordingly.
* **Production data** - only the local dev database was queried (read-only `SELECT`/`SHOW`/`EXPLAIN`).
* **UI/UX parity with `ui-references/`, accessibility, E2E/browser behaviour and business-requirement correctness** - not assessed.
* **Report location:** `audit/` is a new, untracked folder; nothing under `app/`, `routes/`, `database/`, `config/`, `resources/`, `tests/` or `public/` was modified. Five `audit-tmp-*.txt/md` evidence dumps (route, table-size, index, FK and column dumps) exist at the repository root and can be deleted or moved into `audit/`.
