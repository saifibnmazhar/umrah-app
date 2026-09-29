# Profit/Loss Report — Print Performance Optimization Plan

> **Status:** Proposed (investigation complete, not yet implemented)
> **Date:** 2026-09-29
> **Page:** `http://127.0.0.1:8000/reports/profit-loss`
> **Symptom:** With 700–800 customers/passengers, the *Customer Print* and *Passenger Print*
> tabs take many seconds to load in production.

**Related plan:** `profitLossReportPrintQueryOptimizationPlan.md` (earlier optimization pass —
`mapPassengerForPrint()`, SQL-level effective-date filtering). This document covers what is
*still* slow after that work.

---

## 1. Code Path Investigated

| Piece | Location |
|---|---|
| Route | `routes/web.php:348` → `Route::get('/reports/profit-loss/print', ...)` |
| Controller | `app/Http/Controllers/ProfitLossReportController.php:637` → `print()` |
| Eager-load constant | `ProfitLossReportController.php:33` → `PRINT_BOOKING_WITHS` |
| Row mappers | `mapCustomersForPrint()` `:446`, `mapPassengersForPrint()` `:489`, `mapPassengerForPrint()` `:467` |
| Print view | `resources/views/reports/profit-loss-print.blade.php` (191 lines) |
| Print buttons | `resources/views/reports/profit-loss.blade.php:211-212`, URL builder `printUrl()` `:940` |
| Profit logic | `app/Services/ProfitCalculationService.php` |
| Report page data API | `ProfitLossReportController::data()` `:518`, `summary()` `:223` |
| Existing query-budget test | `tests/Feature/ReportQueryOptimizationTest.php:711` |

---

## 2. Root Causes Found

### A. N+1 on `cancelledBooking` (largest single win)

`PRINT_BOOKING_WITHS` (`:33`) eager-loads:

```php
'customer', 'invoice', 'fingerprint', 'fingerprintCharge', 'passengers.cancelledPassengers'
```

It does **not** load `cancelledBooking`. Both mappers call:

```php
// :448 and :491
$bookings->reject(fn (Booking $booking) => $profitService->isBookingCancelledForProfit($booking))
```

and the service falls back to a query when the relation is missing
(`ProfitCalculationService.php:22-28`):

```php
if ($booking->relationLoaded('cancelledBooking')) { ... }          // NOT taken
return $booking->cancelledBooking()->where('status', ...)->exists(); // 1 query per booking
```

**Cost:** 2 calls × 800 bookings = **~1,600 extra queries** per print request.

Compounding problem — line 716–717 always builds **both** collections:

```php
$customers = collect($this->mapCustomersForPrint($bookings, $profitService));
$passengers = collect($this->mapPassengersForPrint($bookings, $profitService));
```

even though the Blade view only renders `$type === 'customer' ? $customers : $passengers`
(`profit-loss-print.blade.php:93` / `:143`).

---

### B. Passenger print defaults to "effective date" mode → per-passenger N+1

`profit-loss.blade.php:727-734`:

```js
switchToPassengerTab() {
    this.activeTab = 'passenger';
    this.ensureEffectiveDates();
    this.activeDateFilter = 'effective';   // ← default for passenger tab
    ...
}
```

`printUrl()` (`:940`) then sends `effective_date_from/to`, so the controller takes the branch at
`ProfitLossReportController.php:649`. Inside it:

```php
$breakdown = $this->calculateEffectiveDateBreakdown($passenger, $dateFrom, $dateTo);
```

→ `ProfitCalculationService::calculateEffectiveDateProfitDetailed()` (`:522`) touches:

| Called | Relation used | Loaded by `PRINT_BOOKING_WITHS`? |
|---|---|---|
| `effectiveAdditionalTicketProfit()` `:478` | `$passenger->allIssuedTickets` | **No** → 1 query/passenger |
| `passengerReIssues()` `:743` | `$t->reIssuedTickets` | **No** → 1 query/ticket |
| `effectiveRefundProfit()` `:516` | `$t->refundedTickets` | **No** → 1 query/ticket |
| `additionalTicketEffectiveDate()` `:450` | `IssuedTicketLog::...->first()` when `issued_date` is NULL | **No** → 1 query/ticket |

**Cost:** ~2–4 queries × 800 passengers ≈ **1,600–3,200 extra queries**, plus
`mapCustomersForPrint()` on line 701 adds another ~800 (`cancelledBooking` N+1 again).

> Note: `BOOKING_WITHS` (`:16`) used by the *data* API **does** include
> `passengers.allIssuedTickets.*` — only `PRINT_BOOKING_WITHS` is missing it.

---

### C. Non-sargable date filter + missing index

`ProfitLossReportController.php:707-712`:

```php
$query->whereDate('created_at', '>=', $request->booking_date_from);
$query->whereDate('created_at', '<=', $request->booking_date_to);
```

`whereDate()` wraps the column in `DATE()` → index on `created_at` would be unusable anyway,
and there is **no index on `bookings.created_at`** at all:

- `database/migrations/2026_05_08_000002_create_bookings_table.php` — only `invoice_id` unique + FKs
- `database/migrations/2026_09_10_000001_add_indexes_for_server_side_pagination.php` — adds
  `booking_branch_id`, `fingerprint_branch_id` only

→ full table scan on every print.

**Effective-mode filter** (`:106`) is worse — a correlated subquery per passenger row:

```sql
COALESCE(it.issued_date, (SELECT itl.created_at FROM issued_ticket_logs itl
    WHERE itl.issued_ticket_id = it.id AND itl.new_data LIKE '%"status":"issued"%'
    ORDER BY itl.created_at DESC LIMIT 1)) BETWEEN ? AND ?
```

`new_data LIKE '%…%'` on JSON text = full scan of `issued_ticket_logs` per candidate row.

---

### D. Unnecessary eager loads / work per print type

- Passenger print doesn't use `fingerprint`, `fingerprintCharge`, `invoice`
  (`mapPassengerForPrint()` `:467` reads `invoice_id` from the booking, `package_value` from the
  passenger) — but they're all loaded anyway.
- Line 717 computes `mapPassengersForPrint()` for a `type=customer` request (and vice versa) —
  wasted CPU + memory for 800 rows.
- The `$summary` array (`:746`) sums **both** collections though the view only reads
  `$summary['customer']` or `$summary['passenger']`.

---

### E. Report page itself (`data()`) — separate but related

`ProfitLossReportController.php:621-627` (customer tab):

```php
$bookingIds = $query->pluck('bookings.id');            // all matching ids
$bookings   = Booking::with(self::BOOKING_WITHS)       // 13 nested relations, ALL rows
                ->whereIn('id', $bookingIds)->get();
$rows       = $this->mapCustomers($bookings, ...);     // map ALL rows
$rows       = array_slice($rows, ($page-1)*$perPage, $perPage); // THEN paginate
```

For 800 bookings this hydrates and maps everything just to show 25 rows — noticeable memory and
time pressure before the user even clicks Print.

---

## 3. Recommendations (prioritized)

| # | Change | Files | Effort | Expected gain |
|---|---|---|---|---|
| **1** | Add `'cancelledBooking'` to `PRINT_BOOKING_WITHS` | `ProfitLossReportController.php:33` | 1 line | **−1,600 queries** |
| **2** | Build only the requested `$type` — skip the other mapper, filters and summary slice | `ProfitLossReportController.php:702-762` | small | ~50% less work |
| **3** | Effective branch: eager-load `passengers.allIssuedTickets.refundedTickets`, `passengers.allIssuedTickets.reIssuedTickets`, `passengers.allIssuedTickets.ticketFare`, `passengers.allIssuedTickets.logs` | `ProfitLossReportController.php` (effective branch `:664`) | small | **−1,600–3,200 queries** |
| **4** | Sargable dates: `where('created_at','>=',$from.' 00:00:00')` / `<= $to.' 23:59:59'`; migration adding `bookings(created_at)` and `bookings(booking_branch_id, created_at)` | controller + new migration | medium | removes full table scan |
| **5** | Add indexes for the hot subqueries: `issued_ticket_logs(issued_ticket_id, created_at)`, `visa_update_logs(visa_submission_id, created_at)`, `cancelled_bookings(booking_id, status)`, `cancelled_passengers(passenger_id, status)` | new migration | small | speeds effective filter |
| **6** | Trim eager loads per type (passenger print drops `fingerprint`, `fingerprintCharge`, `invoice`) | `ProfitLossReportController.php:33` / `:703` | small | memory + query payload |
| **7** | `data()` customer tab: paginate in SQL (`forPage()`/`limit`) **before** mapping | `ProfitLossReportController.php:621-627` | medium | report page load |
| **8** | Backfill `issued_tickets.issued_date` (one-time `artisan` command) so the JSON `LIKE` fallback in `applyEffectiveDateFilter()` is rarely needed | new command | medium | effective filter speed |
| **9** | Regression test: assert bounded query count at **800 rows** for both types and both date modes | `tests/Feature/ReportQueryOptimizationTest.php` | small | prevents relapse |

### Suggested implementation order

1. **Quick wins:** #1, #2, #3, #9 — pure code, no schema change, biggest measured impact.
2. **Indexes:** #4, #5 (+ sargable rewrite) — migration, needs production deploy.
3. **Page load:** #6, #7.
4. **Only if still slow:** #8, and only then consider caching/async.

### Explicitly *not* recommended (for now)

- Caching the rendered print HTML — filter combinations make invalidation error-prone.
- Queue + PDF generation — overkill until items 1–3 are measured.
- `Response::stream()` for progressive rendering — possible later fallback.

---

## 4. Verification Plan

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
assert the numbers are (nearly) identical; absolute budget `< 60`.

### Existing test caveat

`tests/Feature/ReportQueryOptimizationTest.php:711` (`test_profit_loss_print_stays_bounded_query_count`)
passes `'date_from'` / `'date_to'`, but the controller reads **`booking_date_from` / `booking_date_to`**.
The filters therefore never apply, only 10 bookings are seeded, and the N+1 stays under the
35-query budget. Fix the parameter names when extending it.

### Manual profiling

1. `php artisan tinker` → time the route, or enable query log in a controller `dump(count(...))`.
2. Compare `explain` on the bookings select with/without the new `created_at` index.
3. Confirm production has `php artisan optimize` / `view:cache` output (entrypoint already runs it)
   and OPcache enabled — Blade rendering of 800 rows is *not* the bottleneck (expected < 100 ms).

### Definition of done

- [ ] 800-row customer print loads in well under 1 s
- [ ] 800-row passenger print (effective mode) loads in well under 1 s
- [ ] Query count does not scale with row count
- [ ] `php artisan test` green (incl. new 800-row regression test)
- [ ] `vendor/bin/pint` clean
- [ ] New migration runs on production (`./deploy-prod.sh`)
