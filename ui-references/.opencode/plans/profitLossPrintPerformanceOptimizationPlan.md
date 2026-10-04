# Profit/Loss Report — Print Performance + `issued_date` Plan

> **Status:** Approved — ready to implement (not yet implemented)
> **Date:** 2026-09-29 (revised 2026-09-30)
> **Branch:** `profitLossPrintLoad`
> **Page:** `http://127.0.0.1:8000/reports/profit-loss`
> **Symptom:** With 700–800 customers/passengers, the *Customer Print* and *Passenger Print*
> tabs take many seconds to load in production.

**Related plan:** `profitLossReportPrintQueryOptimizationPlan.md` (earlier optimization pass —
`mapPassengerForPrint()`, SQL-level effective-date filtering). This document covers what is
*still* slow after that work, plus the `issued_date` required/backfill work it depends on.

---

## 0. Locked Decisions

| # | Decision |
|---|---|
| 1 | Backfill = **migration** (auto-runs via `docker/entrypoint.sh`); source = `created_at` only; scope = `issue_type='additional' AND issued_date IS NULL` |
| 2 | Field scope = **`issued_date` only** (re-issue / refund dates untouched) |
| 3 | Server scope = issue + its edit + pending-outbound issue + additional process (+ their edit forms). **Re-issue forms excluded** |
| 4 | Cancelled-booking perf = **Option A** (fix `PRINT_BOOKING_WITHS`, no call-site surgery) |
| 5 | *(any-row/latest-row cancellation alignment)* — **dropped** |
| 6 | No index migration for recommendation #5 below |
| 7 | Dead code: only `'fingerprintCharge'` removal. `bookingsQuery()` `:63`, `mapPassengers()` `:439`, `effectiveComponentValue()` `:153`, `passengerHasEffectiveComponentInRange()` `:165` left as-is |
| 8 | Two-key passenger exclusion — **all 5 sites**, **name-based** |
| 9 | Print branch fix **minimal**: only `:701`; `:716-717` + `$summary` untouched |
| 10 | `additionalTicketEffectiveDate()` — **unchanged** (fallback kept, it is free) |
| 11 | Effective-date filter — **3 sites** (Profit/Loss + Dashboard + BranchWise), additional-only |
| 12 | Sargable rewrite + `bookings(created_at)` index — **deferred follow-up**, marked for later |
| 13 | `EXPLAIN` — **required** verification step after Part B/C, not optional |

---

## 1. Code Path Investigated

| Piece | Location |
|---|---|
| Route | `routes/web.php:348` → `Route::get('/reports/profit-loss/print', ...)` |
| Controller | `app/Http/Controllers/ProfitLossReportController.php:637` → `print()` |
| Eager-load constant | `ProfitLossReportController.php:33-39` → `PRINT_BOOKING_WITHS` |
| Data eager-load constant | `ProfitLossReportController.php:16-31` → `BOOKING_WITHS` |
| Row mappers | `mapCustomersForPrint()` `:446`, `mapPassengersForPrint()` `:489`, `mapPassengerForPrint()` `:467` |
| Exclusion helpers | `excludeCancelledBookings()` `:41-50`, `excludeCancelledPassengers()` `:52-61` |
| Print view | `resources/views/reports/profit-loss-print.blade.php` (`@if($type==='customer')` `:76`) |
| Print buttons | `resources/views/reports/profit-loss.blade.php:211-212`, URL builder `printUrl()` `:940` |
| Profit logic | `app/Services/ProfitCalculationService.php` |
| Report page data API | `ProfitLossReportController::data()` `:518`, `summary()` `:223` |
| Existing query-budget test | `tests/Feature/ReportQueryOptimizationTest.php:711` |

---

## 2. Root Causes Found (corrected during review)

### A. N+1 on `cancelledBooking`

`PRINT_BOOKING_WITHS` (`:33-39`) loads `customer, invoice, fingerprint, fingerprintCharge,
passengers.cancelledPassengers` — **no `cancelledBooking`**. Both mappers call
`isBookingCancelledForProfit()` (`:448`, `:491`), which falls back to a query
(`ProfitCalculationService.php:22-29`):

```php
if ($booking->relationLoaded('cancelledBooking')) { ... }            // NOT taken
return $booking->cancelledBooking()->where('status', ...)->exists(); // 1 query per booking
```

**Cost:** ~1 query per booking per request (**≈800**, not 1,600 — the relation caches after the
first load and the second mapper over the same collection reuses it).

Provably a no-op filter: `excludeCancelledBookings()` `:41-50` / `:705` already removed those
rows in SQL, and at most one non-trashed `cancelled_bookings` row exists per booking, so the
relation (latest-row) and the scope (any-row) agree.

### B. Effective branch is missing eager loads → per-passenger N+1

The passenger print defaults to effective mode (`profit-loss.blade.php:727-734`), so `print()`
takes the branch at `:649` and loads `PRINT_BOOKING_WITHS` at `:664`, then per passenger calls
`calculateEffectiveDateBreakdown()` `:682` → `calculateEffectiveDateProfitDetailed()` `:522`:

| Called | Relation | In `PRINT_BOOKING_WITHS`? |
|---|---|---|
| `effectiveAdditionalTicketProfit()` `:478` | `$passenger->allIssuedTickets` | **No** → 1 query/passenger |
| `passengerReIssues()` `:743` | `$t->reIssuedTickets` | **No** → 1 query/ticket |
| `effectiveRefundProfit()` `:516` | `$t->refundedTickets` | **No** → 1 query/ticket |
| `additionalTicketEffectiveDate()` `:450` | `IssuedTicketLog::…->first()` when `issued_date` NULL | **No** → 1 query/ticket, **0 after A1** |

`allIssuedTickets()` is a plain `HasMany` (`Passenger.php:137-140`), so none of these are
loaded transitively. **Real cost today: O(passengers)**, not the previously-quoted figure.

Additionally `:701` runs `mapCustomersForPrint()` inside the effective branch, which re-triggers
the `cancelledBooking` N+1 (`:448`) even though effective mode requires `$type === 'passenger'`
(`:649`) — and the result is never rendered.

> `BOOKING_WITHS` (`:16`) used by the *data* API **does** include `passengers.allIssuedTickets.*`
> — only `PRINT_BOOKING_WITHS` is missing it.

### C. Non-sargable date filter + missing index — **DEFERRED** (see §4)

`applyDateFilters()` `:79-87` and `print()` `:707-712` use `whereDate('bookings.created_at', …)`,
which wraps the column in `DATE()`. There is **no index on `bookings.created_at`** —
`2026_05_08_000002` (invoice_id unique + FKs) and `2026_09_10_000001` (`booking_branch_id`,
`fingerprint_branch_id` only). → `EXPLAIN` reports `type: ALL`.

**Effective-mode filter** (identical at `ProfitLossReportController:105-108`,
`DashboardController:192-195`, `BranchWiseReportController:328-331`) currently carries a
correlated subquery with a `LIKE` over JSON text per candidate row — addressed by **A4**.

### D. Wasted work in `print()`

- `:701` builds `$customers` in the effective branch — never rendered, always wasted.
- `:716-717` build **both** collections in the plain branch, though the view renders only one
  (`profit-loss-print.blade.php:93` / `:143`).
- `$summary` `:746-762` sums both collections; the view reads only the one for `$type`
  (`:111-114` / `:160-163`).

### E. Report page `data()` — separate but related

`ProfitLossReportController.php:621-627` (customer tab):

```php
$bookingIds = $query->pluck('bookings.id');            // all matching ids
$bookings   = Booking::with(self::BOOKING_WITHS)       // 13 nested relations, ALL rows
                ->whereIn('id', $bookingIds)->get();
$rows       = $this->mapCustomers($bookings, ...);     // map ALL rows
$rows       = array_slice($rows, ($page-1)*$perPage, $perPage); // THEN paginate
```

No `orderBy` either, so pagination is not deterministic. Hydrates and maps everything to show
25 rows.

---

## 3. Implementation Plan

### Part A — `issued_date` required + backfill

**A1 · Migration** `database/migrations/2026_09_30_000001_backfill_additional_ticket_issued_date.php`

```sql
UPDATE issued_tickets SET issued_date = created_at
WHERE issue_type = 'additional' AND issued_date IS NULL
```

- Additional-only: `determineTicketEffectiveDate()` `ProfitCalculationService:699` reads
  `regularTickets()`, so no other issue type's effective date moves.
- No status filter needed: additional tickets are created with `status = 'issued'`
  (`TicketRequestController:575`), never `pending` / `awaiting-group`.
- `issued_ticket_logs` is **not** a valid source — `processAdditional()` never calls `logAction()`.

> **⚠ Visible report delta** (correctness fix; numbers move):
>
> | Site | Today (NULL date) | After A1 |
> |---|---|---|
> | `ProfitLossReportController:388`, `DashboardController:224-225`, `BranchWiseReportController:360-361` — `whereBetween(…) OR whereNull(…)` | always counted, any range | range-filtered → totals may **drop** |
> | `TicketAgentReportController:50`, `:74`, `:138` — `whereBetween(…)` only | excluded | counted if in range → totals may **rise** |
>
> Count `COUNT(*) WHERE issue_type='additional' AND issued_date IS NULL` before running to size
> the delta.

**A2 · Server rules** — `'issued_date' => 'required|date'`

| File | Line |
|---|---|
| `TicketIssueController::issue` | `:42` |
| `TicketIssueController::edit` | `:186` |
| `TicketRequestController::processAdditional` | `:523` |

In `edit()` the booking lookup (`:216`) is hoisted above `validate()` — zero added queries — and
the rule becomes conditional on `status !== 're-issued'` so `:254`
`'re_issue_date' => $validated['issued_date'] ?? $latestRe->re_issue_date` keeps working.

**Not changed:** `TicketIssueController::createPendingOutbound:508` (stub, no date input),
`ReIssueController:47`, `RefundController:62`.

> **⚠ Behaviour notes:** `TicketRequestController:564` is `'issued_date' => $validated['issued_date'] ?? now()`
> — an omitted date silently becomes `now()` today and becomes a **422** after A2.
> `TicketIssueController::issue:74` `array_merge($validated, …)` + Laravel's `validated()` omitting
> absent keys means an `issued` ticket **can** have `issued_date = NULL` today — A2 closes that,
> A1 backfills history.

**A3 · Client `required` + validation**

| File | Lines |
|---|---|
| `resources/views/bookings/index.blade.php` | `required` `:1713`; `validateTicketFareDates:5352-5358`; `handleTicketFareSubmit:6105`, date check `:6115` |
| `resources/views/tickets/add-confirmation.blade.php` | `required` `#inputTravelDate:125`; guard in `confirmProcess():636`; payload `:645` |
| `resources/views/reports/pending-outbound.blade.php` | verify/add `required` `:341`; `handleSubmit():754`; payload `:782`; toasts `:766/:771` |

Re-issue forms (`bookings/index.blade.php` `handleReIssueSubmit` `:5945+`) **untouched**.

**A4 · Drop the correlated log subquery — 3 identical sites**

```sql
-- today  (PL :105-108, Dashboard :192-195, BranchWise :328-331)
COALESCE(it.issued_date, (SELECT itl.created_at FROM issued_ticket_logs itl
    WHERE itl.issued_ticket_id = it.id AND itl.new_data LIKE '%"status":"issued"%'
    ORDER BY itl.created_at DESC LIMIT 1)) BETWEEN ? AND ?
-- becomes
it.issued_date BETWEEN ? AND ?
```

| Site | issue gate | status gate | COALESCE block |
|---|---|---|---|
| `ProfitLossReportController.php` | `:103` | `:104` | `:105-108` |
| `DashboardController.php` | `:190` | `:191` | `:192-195` |
| `BranchWiseReportController.php` | `:326` | `:327` | `:328-331` |

All three are gated `->where('it.issue_type', 'additional')` + `->whereIn('it.status',
['issued','re-issued','refunded'])`, inside an `orWhereExists` on `issued_tickets as it`
correlated to the passenger. The sibling `re_issued_tickets` / `refunded_tickets` blocks
already use plain `whereBetween('…created_at', …)` and are untouched.

Removes the only correlated subqueries in the plan. Correct because `issued_date` is the sole
source for an additional ticket in `issued`/`re-issued`/`refunded`; valid only if the A1
invariant holds (asserted in Part E).

**A5 · `additionalTicketEffectiveDate()` `ProfitCalculationService:442-456` — UNCHANGED**

```php
if ($ticket->issued_date) { return …; }        // :444  zero queries
$log = IssuedTicketLog::where(...)->first();   // :450  dead after A1+A2
```

Post-A1+A2 `:444` always returns → `:450` never runs → **0 queries**, performance not hampered,
so the block is left alone. Keeps `ProfitEffectiveDateComponentsTest:261
test_additional_ticket_falls_back_to_issue_log` (fixture `:276` sets `issued_date => null`,
`:278` creates the log) green. The fallback is logically redundant but free.

---

### Part B — print perf (single commit)

**B1 · Two constants, not three.** `:716` **and** `:717` both always run, so splitting `:703`'s
`with()` by `$type` is unsafe — the mapper you did not choose would lazy-load.

*`PRINT_BOOKING_WITHS` `:33-39` (used at `:703`, shape kept) — **add**:*

- `'cancelledBooking'` → `:448` / `:491` drop ~1 lazy query per booking → 1 eager.
- `'passengers.status'` → Part D.

*New `PRINT_EFFECTIVE_WITHS` (used at `:664`):*

```
customer, passengers.cancelledPassengers, passengers.status,
passengers.allIssuedTickets.ticketFare,
passengers.allIssuedTickets.reIssuedTickets,
passengers.allIssuedTickets.refundedTickets
```

Drops `invoice`, `fingerprint`, `fingerprintCharge` — none are read by `:683-698`.
The three `allIssuedTickets.*` entries are **required additions**, not trims (see §2B).

**B2 · Minimal branch fix** — `:701` becomes:

```php
$customers = collect();
```

**Not a bare deletion:** `$customers` is read unconditionally at `:739` (profit/loss filter),
`:748-753` (`$summary['customer']`) and `:768` (`compact`) — removing the assignment would throw
`Undefined variable` → `ErrorException` → 500 on every effective print.

Output is byte-identical: `:649` guarantees effective mode ⟹ `$type === 'passenger'`, and the
view touches `$customers` / `$summary['customer']` only inside `@if($type==='customer')`
(`profit-loss-print.blade.php:76`, `:93`, `:111-113`).

Bonus: also removes the effective branch's only `cancelledBooking` call (`:701` → `:448`).

`:716-717` and `$summary` `:746-762` **untouched**.

**B3 · Dead eager loads**

| Site | Change |
|---|---|
| `ProfitLossReportController:383` | `['ticketFare','logs']` → `['ticketFare']` (only `:413` reads it) |
| `ProfitLossReportController:554` | `passengers.allIssuedTickets.logs` → remove (`:442` queries the log directly) |
| `ProfitLossReportController:20` | `'fingerprintCharge'` → remove. **Keep `:21 cancelledBooking`** — read by `getCustomerProfitBreakdown:198` ← `mapCustomers:435` (JSON tab) |

**Not done (deliberate):** per-type collection gating, `$summary` restructure, three-way
constant split.

---

### Part C — `data()` pagination

Customer tab `:621-627`: replace pluck-all-ids → hydrate-all → `mapCustomers` → `array_slice`
with SQL-level `paginate()` on the eager-loaded query, and add
`orderBy('bookings.id', 'desc')`. Passenger tab already paginates (`:550`) — add the `orderBy`.
Mapper logic unchanged.

---

### Part D — two-key passenger exclusion (5 sites, name-based)

**Rule:** excluded ⟺ a `cancelled_passengers` row with `status='cancelled'` **OR**
`passenger_status_id` = the `Cancel` status.

**D1 · SQL (4 sites)**

```
AND (t.passenger_status_id IS NULL
     OR t.passenger_status_id <> (SELECT id FROM passenger_statuses WHERE name='Cancel'))
```

| Site | Line |
|---|---|
| `excludeCancelledPassengers()` — callers `:262`, `:535`, `:658` | `ProfitLossReportController:52-61` |
| `summary()` `psum` raw subquery | `ProfitLossReportController:234` |
| inline closure | `DashboardController:51-58` |
| inline closure | `BranchWiseReportController:62-68` |

The `IS NULL` arm is **mandatory**: `NULL <> x` evaluates to NULL, and NULL is the common state
(`syncComputedStatus()` `Passenger.php:471-479` nulls the column for non-manual statuses).

**D2 · PHP (1 site)** — `isPassengerCancelledForProfit()` `ProfitCalculationService:31-38`:

```php
$passenger->loadMissing('status');
// …existing cancelled_passengers row check… || $passenger->isOnCancel()
```

`isOnCancel()` = `Passenger.php:445`.

**D3 · Eager loads** — add `passengers.status` at `BOOKING_WITHS:25`,
`PRINT_BOOKING_WITHS:38`, new `PRINT_EFFECTIVE_WITHS`, `ProfitCalculationService:68`,
`:168`, `:361` → **+1 query per request** (≤11 rows, PK lookups), 0 after. The `loadMissing`
guard is a correctness net only: a missed site degrades to N+1, never to a wrong answer.

**SQL cost:** ~0 — `passenger_statuses` is an ~11-row table, materialised once per query.

**Safety:** `passenger_status_id` is non-null ⟺ manual status (`Passenger.php:430`
`MANUAL_STATUSES`); `getComputedStatusAttribute()` never returns `'Cancel'`. Written by
`CancellationService:209` and `PassengerCancellationService:306`; cleared by
`revertCancellation:138` (→ null) and booking revert `:85-91` (→ snapshot); `initiateCancellation`
writes `'Hold'`, so processing passengers stay counted. `PassengerStatusSeeder:27-47` guarantees
the row exists.

> **⚠ Accepted change:** a `Cancel`-status passenger with *no* `cancelled_passengers` row is
> counted today and will not be afterwards, and `recalculateBookingProfit:71` will start writing
> `passengers.profit = 0` for them, moving stored `bookings.profit`.
> `ProfitCancelledStatusOnlyTest:217-254` leaves `passenger_status_id` NULL → stays green.

---

### Part E — tests

**New**

- **A** · 422 on omitted `issued_date` ×3 (issue form, process-additional, edit); re-issue edit
  without a date → 200 with `re_issue_date` preserved; backfill assertions (only additional +
  NULL updated, nothing else); **`COUNT(*) WHERE issue_type='additional' AND issued_date IS NULL = 0`**;
  number-delta coverage for the `orWhereNull` sites
- **B** · `toSql()` asserting no `issued_ticket_logs` join; `cancelledBooking` present in both
  print constants
- **C** · pagination determinism across two `data()` calls
- **D** · `Cancel` status + no row → excluded; `Hold` + PROCESSING row → included; `NULL` status
  → included (guards the `IS NULL` arm); `recalculateBookingProfit` zeroes the new case;
  Dashboard/BranchWise consistency
- **E** · 800-row flat query-count regression (both types × both date modes, shared-branch seed
  helper)

**Update payloads (must add `issued_date`)**

- `tests/Feature/TicketRequestProcessAdditionalTest.php:196`, `:222`
- `tests/Feature/IssueFormIssuedTicketFareSourceTest.php:467`
- `tests/Feature/BookingInactiveFareSourcesTest.php:403`

**Update params** — `date_from` → `booking_date_from` in `ReportQueryOptimizationTest` at
`:341, :366, :379, :413, :450, :459, :481, :493, :520, :541`.

**Rebaseline** — `ReportQueryOptimizationTest:733` `assertLessThan(35, …)`.

**Must stay green** — `ProfitCancelledStatusOnlyTest:176/:212/:282/:341`,
`ProfitEffectiveDateComponentsTest` (incl. `:261`), `ProfitLossEffectiveDateFilterTest`,
`ReIssueCustomerPaymentDerivationTest:442/:474/:509`, `ReIssueEditRefundedNonCustomerTest:210`,
`ReIssueEditRefundPayableAdjustTest:248`, `TicketIssueReIssueFareSourceTest:214`,
`PassengerServiceRequiredGatingTest:210`.

---

## 4. Deferred — sargable rewrite + index *(not in this change set)*

Two halves ship **together, as one follow-up commit, only after `EXPLAIN`**:

1. **Rewrite** — `whereDate(…)` → half-open ranges at `applyDateFilters:82, :85` and
   `print():708, :711`:

   ```php
   $query->where('created_at', '>=', $from.' 00:00:00');
   $query->where('created_at', '<',  $to.' +1 day');   // never <= '{to} 23:59:59'
   ```

   (`bookingsQuery():69-74` contains the same code but is dead — leave it.)

2. **Index** — `bookings(created_at)`, or `bookings(booking_branch_id, created_at)` replacing
   `bookings_booking_branch_id_index` (`2026_09_10_000001:23`). If replacing, do
   `ADD …, DROP …` in a single `ALTER`.

Neither half works alone: without an index the rewrite still scans, and without the rewrite
`DATE(col)` is unservable by any index.

> **`EXPLAIN` is a required verification step (not optional) after Part B/C.** Ship the pair only
> if the bookings select reports `type: ALL` and the date range is selective.

**Risks if shipped earlier:** index write-amplification on `bookings`; DDL I/O during
`deploy-prod.sh`; lost before/after attribution. **No risk to report results** — an index never
changes output and the half-open rewrite is behaviour-equivalent.

---

## 5. Explicitly *not* recommended

- Caching the rendered print HTML — filter combinations make invalidation error-prone.
- Queue + PDF generation — overkill until Parts A–C are measured.
- `Response::stream()` for progressive rendering — possible later fallback.
- Removing `additionalTicketEffectiveDate()`'s log fallback — it is dead code, not a cost (A5).
- Dropping `orWhereNull('issued_date')` at `:388` / `DashboardController:224` /
  `BranchWiseReportController:360` — harmless after A1 and a useful safety net.

---

## 6. Sequencing

| Step | Content |
|---|---|
| 0 | This document → Status **Approved**; record Part D, 3-site A4, deferred index |
| 1 | **A1** migration |
| 2 | **A2 + A3** (+ their tests) |
| 3 | **A4** (3 sites) |
| 4 | **B1 + B2 + B3** (one commit) → **required `EXPLAIN`** → decide the deferred follow-up |
| 5 | **C** |
| 6 | **D** |
| 7 | **E** tests |

**Gate after every step** (AGENTS.md §7):

```bash
php artisan test
vendor/bin/pint
npm run build
docker compose config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

---

## 7. Verification Plan

### Before / after query count

```php
DB::enableQueryLog();
$response = $this->get(route('report.profit-loss.print', [
    'type' => 'customer',                 // and again with 'passenger'
    'booking_date_from' => now()->subDays(365)->toDateString(),
    'booking_date_to'   => now()->toDateString(),
    // passenger tab: 'effective_date_from' / 'effective_date_to'
]));
DB::disableQueryLog();
$count = count(DB::getQueryLog());
```

**Targets:** query count must be **independent of row count** — run with 10 and with 800 rows and
assert the numbers are (nearly) identical; rebaseline the existing budget after Part B.

### Existing test caveat

`tests/Feature/ReportQueryOptimizationTest.php:711`
(`test_profit_loss_print_stays_bounded_query_count`) passes `'date_from'` / `'date_to'`, but the
controller reads **`booking_date_from` / `booking_date_to`**. The filters therefore never apply,
only 10 bookings are seeded, and the N+1 stays under the 35-query budget. Fix the parameter names
when extending it.

### Manual profiling

1. `php artisan tinker` → time the route, or `dump(count(...))` behind `DB::enableQueryLog()`.
2. `EXPLAIN` the bookings select (required after Part B/C — see §4).
3. Confirm production has `php artisan optimize` / `view:cache` output (entrypoint already runs
   it) and OPcache enabled — Blade rendering of 800 rows is *not* the bottleneck (expected <100 ms).

### Definition of done

- [ ] 800-row customer print loads in well under 1 s
- [ ] 800-row passenger print (effective mode) loads in well under 1 s
- [ ] Query count does not scale with row count
- [ ] Effective mode does no per-passenger lazy loading
- [ ] `issued_date` 422s on the three forms; re-issue edit still works
- [ ] Profit/Loss, Dashboard and BranchWise agree on passenger exclusion
- [ ] `COUNT(*) WHERE issue_type='additional' AND issued_date IS NULL = 0` after A1
- [ ] `php artisan test` green (incl. new 800-row regression test)
- [ ] `vendor/bin/pint` clean
- [ ] `npm run build` passes
- [ ] Both `docker compose … config --quiet` pass
- [ ] Plan file matches reality
