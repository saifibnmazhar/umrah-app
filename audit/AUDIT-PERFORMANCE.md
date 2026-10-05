# AUDIT - PERFORMANCE

**7 findings:** 4 MEDIUM, 2 LOW, 1 INFO (IDs `PERF-01..07`; canonical table in `AUDIT-FINDINGS.md`).
**Method:** static query-pattern analysis + read-only `EXPLAIN` on the local dev DB + frontend request analysis. **No load test, no APM, no latency baseline exists** - nothing in the repo measures request time, so every impact statement below is structural (query shape), not measured.

---

## 1. Caching posture - `PERF-01` (MEDIUM)

* **Fact:** the entire `app/` tree contains **zero** real cache usage. The only `Cache::` match is a comment (`app/Services/SessionInvalidator.php:19`). No `->remember()`, no `Cache::store(...)` calls. `config/cache.php` default is `database` (`CACHE_STORE`); prod ships Redis but only sessions/queues would use it.
* **Consequence:** every page render re-derives data that changes rarely (currency rates, cities, airlines, classes, packages, dashboard aggregates).
* **Dashboard anatomy (`app/Http/Controllers/DashboardController.php`):** **14 separate `->count()` queries** (`:74-95` and friends) + **12 `selectRaw` aggregate queries** (`:112,146,162,177,302,317,353,374,387,400,416,437`) + 3 grouped sub-queries (`:467,485,503`) per single page load - roughly 30 queries for one view.
* **Fix:** collapse the 14 counts into 1-3 aggregate queries (one query with conditional `COUNT(CASE WHEN ...)`), then wrap the dashboard totals in `Cache::remember('dashboard.totals', 60, ...)`; cache the reference data (cities/airlines/classes/currency rates) for the session.
* **Verify:** enable the query log for a dashboard request -> count drops to <=5 queries; second request within TTL issues 0 aggregate queries; `php artisan test` green.

## 2. Per-request middleware query - `PERF-02` (MEDIUM)

* **Fact:** `app/Http/Middleware/CheckRole.php:13` runs `roles()->whereIn('name', $roles)->exists()` on **every** one of the 264 role-protected requests. The user's roles relation is not memoized for the middleware's use, and nothing caches role names (they change rarely).
* **Fix:** use `auth()->user()->hasAnyRole(...)` against an already-loaded relation (Laravel loads `roles` lazily once per request anyway), or `Cache::remember('roles.'.auth()->id(), ...)` with invalidation on role change.
* **Verify:** query log for a single authenticated page load no longer contains the roles query.

## 3. Missing indexes - `DB-01` / `DB-02` (see `AUDIT-DATABASE.md`)

Shape-level evidence (read-only `EXPLAIN` on live dev data):

| Query | Result today | Growth effect |
|---|---|---|
| `documents` by `owner_type`+`owner_id` (every passenger/booking document list) | `type=ALL`, `rows=3161`, `possible_keys=NULL` | full scan of the largest domain table on every show page |
| `payments` by `created_at` (84 `whereDate('created_at')` call sites across app+routes) | `type=ALL`, `rows=1780` | every date-filtered report scans all payments |
| `bookings` by `created_at` | `type=ALL`, `rows=794` | same for booking reports/lists |

Three `CREATE INDEX` statements fix all three; verify with `EXPLAIN -> ref/range`.

## 4. Frontend request behaviour

### `PERF-03` (MEDIUM, Likely) - 42 call sites, no request guard
`bookings/index.blade.php` defines `loadPassengerData()` at `:3398`, sets `passengersLoading = true` at `:3402` (never used as a guard), and fetches `/api/bookings/passengers` at `:3438`. It is invoked from **42** sites (filters, pagination, modals). `AbortController`/`signal:`/`.abort()` appear **0 times** in `resources/`.
Two quick filter clicks -> overlapping requests; if the *earlier* resolves last, the table shows stale rows while the URL/chips show the new filter - on a screen that launches visa/refund/ticket actions.
**Fix:** one `AbortController` per component (abort before re-fetch, treat `AbortError` as a no-op) or a monotonic request id checked after each `await`.
**Verify:** throttle to 3G, switch a filter twice within ~1s -> final table matches the final URL params.

### `PERF-04` (MEDIUM, Likely) - 40 `fetch` calls with no failure path
131 `fetch(` sites, 60 `.catch(`, 66 `try {` -> **40 with neither**. Worst case: `pending-refunds/index.blade.php` (money movement) contains the string `catch` **zero** times while its refund-confirm request sits at `:318`; `refunds/confirmation.blade.php:269,299,317` chains `.then()` with no terminal handler.
A 500/419/network drop leaves permanent loading panels and **no log** - operators cannot distinguish "no data" from "failed" on refund screens.
**Fix:** one shared `jsonFetch()` helper (injects CSRF, parses JSON, toasts errors); at minimum add terminal `.catch()` to the refund/confirmation pages.
**Verify:** offline reload of `/pending-refunds` -> visible error state instead of a blank panel.

### `PERF-06` (LOW) - payload inlined into every page
`bookings/index.blade.php:4-119` builds (per request, server-side) the package list, flight-date ranges, airlines, classes, refund reasons and the **full fare list including inactive fares** (`:52-82`), then `@json`s it into the page at `:3478`. Every `/bookings` hit ships supplier costs (see `AUTH-04`) and does work most roles never use.
**Fix:** move the assembly into the controller (or fetch fares on demand from a role-gated endpoint).
**Verify:** page HTML no longer contains the fare array; server-side view time drops (measure with `Debugbar`/`timer` locally).

## 5. Synchronous heavy work - `PERF-05` (LOW, Needs Verification)

Queue default is `database` (`config/queue.php`) and no Horizon/Redis queue usage exists; PDF/print/exports run inline in the request (`invoices/print`, report print routes, `ConvertsDocumentsToPdf`). **Needs Verification:** no timing data exists - measure first, then queue only what actually exceeds ~1s.

## 6. Verified clean

* **`PERF-07` (INFO):** spot-checked report controllers (`VisaAgentReportController`, `ProfitLossReportController`) eager-load their relations correctly - no N+1 patterns found in the report layer.
* All 166 FKs are indexed (no FK-driven sort-merge surprises).
* Eloquent payload sizes are modest (largest table is 3,161 rows / 1.6 MB).

## 7. What is *not* known (measurement gaps)

No APM, no query log in prod, no cache-TTL strategy, no queue monitoring, no frontend RUM. Before optimising further: add a cheap baseline (Laravel Telescope in staging or a `DB::listen` slow-query log + `Debugbar` locally), record p95 for `/bookings`, `/dashboard`, `/reports/profit-loss`, then re-measure after `PERF-01..03`.
