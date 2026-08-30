# Profit/Loss Report — Slow Load Fix

## Problem

The profit/loss report page loads slowly despite client-side pagination being added. The root cause is that **pagination is purely cosmetic** — the server loads ALL bookings with deep eager loads (10 relation paths, 3-4 levels deep), computes per-row profit breakdowns for every booking and passenger via `ProfitCalculationService`, serializes everything as JSON, and sends it all to the browser. The summary cards and grand total rows then `.reduce()` over this full dataset client-side.

### Key bottlenecks

| Severity | Issue | Location |
|----------|-------|----------|
| CRITICAL | `->get()` loads all bookings — no SQL-level pagination | `ProfitLossReportController.php:35` |
| CRITICAL | Per-booking + per-passenger profit breakdowns computed for every record on every request | `ProfitLossReportController.php:38-69`, `ProfitCalculationService.php` |
| CRITICAL | Massive eager-load tree (10 paths, 3-4 levels deep) | `ProfitLossReportController.php:13-24` |
| HIGH | `fingerprintCharge` missing from eager loads — N+1 lazy load per booking | `ProfitCalculationService.php:79,117` |
| HIGH | `visaSellingPrice` missing from eager loads — N+1 lazy load per passenger | `ProfitCalculationService.php:257,317` |
| HIGH | Entire dataset serialized as JSON (potentially megabytes) | `ProfitLossReportController.php:76-79` |
| MEDIUM | Grand total rows and summary cards computed client-side from full dataset | `profit-loss.blade.php:712-729` |

---

## Solution Overview

1. **New SQL-based summary endpoint** — lightweight `SUM()` aggregations for summary cards (no deep eager loads, no `ProfitCalculationService`)
2. **Server-side pagination** — `->paginate(25)` at SQL level, breakdowns computed only for the 25 records on the current page
3. **Fix missing eager loads** — add `fingerprintCharge` and `passengers.visaSubmission.visaSellingPrice`
4. **Remove grand total rows** from the main report tables (summary cards replace them)
5. **Update print view** — support search/profit-loss filters, use passed summary data

---

## Files to modify

| # | File | Changes |
|---|------|---------|
| 1 | `app/Http/Controllers/ProfitLossReportController.php` | Add `summary()`, rewrite `data()` with pagination, fix eager loads, update `print()` |
| 2 | `routes/web.php` | Add `/api/reports/profit-loss/summary` route |
| 3 | `resources/views/reports/profit-loss.blade.php` | Summary from API, server-side pagination, debounce search, remove grand total rows |
| 4 | `resources/views/reports/profit-loss-print.blade.php` | Use passed summary data, add search/profit-loss filter support |
| 5 | `tests/Feature/ProfitLossReportTest.php` | Tests for summary + paginated data endpoints |

---

## Detailed Changes

### 1. `ProfitLossReportController.php`

#### A. Fix eager loads in `bookingsQuery()` (lines 13-24)

Add two missing relations to the `with()` array:

```
'fingerprintCharge'
'passengers.visaSubmission.visaSellingPrice'
```

Full eager load list becomes:

```php
Booking::with([
    'customer',
    'invoice',
    'fingerprint',
    'fingerprintCharge',
    'package.ticketFare',
    'package.ticketFareInbound',
    'package.ticketFareOutbound',
    'passengers.visaSubmission.cancelledSubmissions',
    'passengers.visaSubmission.visaSellingPrice',
    'passengers.allIssuedTickets.ticketFare',
    'passengers.allIssuedTickets.reIssuedTickets',
    'passengers.allIssuedTickets.refundedTickets',
])
```

#### B. New `summary()` method

Lightweight SQL aggregation — no deep eager loads, no `ProfitCalculationService` calls.

**Customer summary query:**

```php
 Booking::query()
    ->leftJoin('customers', 'customers.id', '=', 'bookings.customer_id')
    ->leftJoin('fingerprint', 'fingerprint.booking_id', '=', 'bookings.id')
    ->where('bookings.is_cancelled', false)
    ->whereNotNull('bookings.invoice_id')
    // date filter
    ->when($dateFrom, fn ($q) => $q->whereDate('bookings.created_at', '>=', $dateFrom))
    ->when($dateTo, fn ($q) => $q->whereDate('bookings.created_at', '<=', $dateTo))
    // search filter (customer fields)
    ->when($search, function ($q) use ($search) {
        $q->where(function ($q) use ($search) {
            $q->where('bookings.invoice_id', 'like', "%{$search}%")
              ->orWhere('customers.name', 'like', "%{$search}%")
              ->orWhere('customers.passport_no', 'like', "%{$search}%")
              ->orWhere('customers.iqama_no', 'like', "%{$search}%");
        });
    })
    // profit/loss filter
    ->when($profitLossFilter === 'profit', fn ($q) => $q->where('bookings.profit', '>=', 0))
    ->when($profitLossFilter === 'loss', fn ($q) => $q->where('bookings.profit', '<', 0))
    ->selectRaw('
        COALESCE(SUM(fingerprint.profit), 0) as fingerprint_profit,
        COALESCE((SELECT SUM(p.profit) FROM passengers p WHERE p.booking_id = bookings.id), 0) as passenger_profit_total,
        COALESCE(SUM(bookings.discount_amount), 0) as discount,
        COALESCE(SUM(bookings.profit), 0) as total_profit
    ')
    ->first();
```

**Passenger summary query:**

```php
Passenger::query()
    ->join('bookings', 'passengers.booking_id', '=', 'bookings.id')
    ->leftJoin('customers', 'customers.id', '=', 'bookings.customer_id')
    ->where('bookings.is_cancelled', false)
    ->whereNotNull('bookings.invoice_id')
    // date filter
    ->when($dateFrom, fn ($q) => $q->whereDate('bookings.created_at', '>=', $dateFrom))
    ->when($dateTo, fn ($q) => $q->whereDate('bookings.created_at', '<=', $dateTo))
    // search filter (customer + passenger fields)
    ->when($search, function ($q) use ($search) {
        $q->where(function ($q) use ($search) {
            $q->where('bookings.invoice_id', 'like', "%{$search}%")
              ->orWhere('customers.name', 'like', "%{$search}%")
              ->orWhere('passengers.first_name', 'like', "%{$search}%")
              ->orWhere('passengers.last_name', 'like', "%{$search}%")
              ->orWhere('passengers.passport_no', 'like', "%{$search}%")
              ->orWhere('customers.passport_no', 'like', "%{$search}%")
              ->orWhere('customers.iqama_no', 'like', "%{$search}%");
        });
    })
    // profit/loss filter
    ->when($profitLossFilter === 'profit', fn ($q) => $q->where('passengers.profit', '>=', 0))
    ->when($profitLossFilter === 'loss', fn ($q) => $q->where('passengers.profit', '<', 0))
    ->selectRaw('
        COALESCE(SUM(passengers.package_value), 0) as package_value,
        COALESCE(SUM(passengers.profit), 0) as total_profit
    ')
    ->first();
```

**Accepts params:** `date_from`, `date_to`, `search`, `profit_loss_filter`

**Returns:**

```json
{
    "customer": {
        "fingerprint_profit": 0,
        "passenger_profit_total": 0,
        "discount": 0,
        "total_profit": 0
    },
    "passenger": {
        "package_value": 0,
        "total_profit": 0
    }
}
```

#### C. Rewrite `data()` for server-side pagination

**New params:** `page` (default 1), `per_page` (default 25), `search`, `profit_loss_filter`, `tab` (customer/passenger)

**Customer tab flow:**

1. Base query: `Booking` with same joins as summary (customers, fingerprint)
2. Apply filters: date, search (customer fields), profit/loss (`bookings.profit`)
3. Get matching booking IDs: `->pluck('bookings.id')`
4. Eager-load deep relations on matching bookings: `Booking::with([...full eager loads...])->whereIn('id', $bookingIds)->get()`
5. Compute breakdowns via `mapCustomers()` only for these records
6. Paginate the mapped results in PHP (slice for current page)
7. Return paginated slice + metadata

> **Note:** SQL `paginate()` with JOINs on filtered sets is complex. Simpler approach: filter at SQL level to get IDs, then eager-load + compute only for matching IDs, slice for the page.

**Passenger tab flow:**

1. Base query: `Passenger` with joins to bookings/customers
2. Apply filters: date, search (customer + passenger fields), profit/loss (`passengers.profit`)
3. Paginate at SQL level: `->paginate(per_page)` to get 25 passengers
4. Eager-load deep relations on these 25 passengers via their booking
5. Compute breakdowns via `mapPassengers()` only for these records
6. Return paginated data + metadata

**Returns:**

```json
{
    "data": [...],
    "current_page": 1,
    "last_page": 10,
    "total": 250
}
```

#### D. Update `print()` method

- Accept `search` and `profit_loss_filter` params (currently only accepts date range)
- Use the same fixed eager loads from `bookingsQuery()`
- Compute summary data inline using the same SQL logic as `summary()` method
- Pass `$summary` variable to the Blade view

---

### 2. `routes/web.php`

Add before the existing `/api/reports/profit-loss` route (line 301):

```php
Route::get('/api/reports/profit-loss/summary', [ProfitLossReportController::class, 'summary'])
    ->name('api.reports.profit-loss.summary')
    ->middleware('role:Super Admin,Co Admin,Auditor');
```

---

### 3. `resources/views/reports/profit-loss.blade.php`

#### A. New Alpine.js data properties

```javascript
summary: {
    customer: { fingerprint_profit: 0, passenger_profit_total: 0, discount: 0, total_profit: 0 },
    passenger: { package_value: 0, total_profit: 0 }
},
summaryLoading: false,
searchTimeout: null,
customerMeta: { current_page: 1, last_page: 1, total: 0 },
passengerMeta: { current_page: 1, last_page: 1, total: 0 },
```

#### B. New `loadSummary()` async function

```javascript
async loadSummary() {
    this.summaryLoading = true;
    try {
        const params = new URLSearchParams();
        if (this.date_from) params.set('date_from', this.date_from);
        if (this.date_to) params.set('date_to', this.date_to);
        if (this.search) params.set('search', this.search);
        if (this.profitLossFilter !== 'all') params.set('profit_loss_filter', this.profitLossFilter);
        const res = await fetch(`/api/reports/profit-loss/summary?${params}`);
        const json = await res.json();
        this.summary = json;
    } catch (e) {
        console.error('Failed to load summary', e);
    } finally {
        this.summaryLoading = false;
    }
},
```

#### C. Update `init()`

```javascript
init() {
    this.setDefaultDates();
    this.loadSummary();
    this.loadData();
    // ... existing currency listener
},
```

#### D. Update `loadData()`

```javascript
async loadData() {
    this.loading = true;
    try {
        const params = new URLSearchParams();
        if (this.date_from) params.set('date_from', this.date_from);
        if (this.date_to) params.set('date_to', this.date_to);
        if (this.search) params.set('search', this.search);
        if (this.profitLossFilter !== 'all') params.set('profit_loss_filter', this.profitLossFilter);
        params.set('tab', this.activeTab);
        params.set('page', this.currentPage);
        params.set('per_page', this.perPage);
        const res = await fetch(`/api/reports/profit-loss?${params}`);
        const json = await res.json();
        if (this.activeTab === 'customer') {
            this.customers = json.data || [];
            this.customerMeta = { current_page: json.current_page, last_page: json.last_page, total: json.total };
        } else {
            this.passengers = json.data || [];
            this.passengerMeta = { current_page: json.current_page, last_page: json.last_page, total: json.total };
        }
    } catch (e) {
        console.error('Failed to load profit/loss data', e);
        this.customers = [];
        this.passengers = [];
    } finally {
        this.loading = false;
    }
},
```

#### E. Replace `grandTotalCustomer` / `grandTotalPassenger` getters

Delete the two `.reduce()` computed getters (lines 712-729). All template bindings change:

| Before | After |
|--------|-------|
| `grandTotalCustomer.fingerprint_profit` | `summary.customer.fingerprint_profit` |
| `grandTotalCustomer.passenger_profit_total` | `summary.customer.passenger_profit_total` |
| `grandTotalCustomer.discount` | `summary.customer.discount` |
| `grandTotalCustomer.total_profit` | `summary.customer.total_profit` |
| `grandTotalPassenger.package_value` | `summary.passenger.package_value` |
| `grandTotalPassenger.total_profit` | `summary.passenger.total_profit` |

#### F. Simplify `filteredCustomers` / `filteredPassengers`

These become simple passthrough getters since the API already handles filtering:

```javascript
get filteredCustomers() {
    return this.customers;
},
get filteredPassengers() {
    return this.passengers;
},
```

#### G. Simplify pagination getters

Use API metadata instead of computed values:

```javascript
get customerTotalPages() {
    return this.customerMeta.last_page || 1;
},
get passengerTotalPages() {
    return this.passengerMeta.last_page || 1;
},
get paginatedCustomers() {
    return this.customers;  // already paginated by API
},
get paginatedPassengers() {
    return this.passengers;  // already paginated by API
},
```

#### H. Debounce search input

The `@input` on the search field (line 149) changes to:

```html
@input="clearSearchTimeout(); searchTimeout = setTimeout(() => { currentPage = 1; loadData(); loadSummary(); }, 300)"
```

Add helper:

```javascript
clearSearchTimeout() {
    if (this.searchTimeout) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = null;
    }
},
```

#### I. Profit/loss filter triggers both loads

The `<select>` (line 154) changes to:

```html
@change="currentPage = 1; loadData(); loadSummary()"
```

#### J. Tab switch loads data for that tab

The tab buttons (lines 169-174) change to:

```html
@click="activeTab = 'customer'; currentPage = 1; loadData()"
@click="activeTab = 'passenger'; currentPage = 1; loadData()"
```

#### K. Remove grand total rows

- **Customer grand total row:** Delete lines 264-272 (the `<template x-if="!loading && filteredCustomers.length > 0">` block with Grand Total)
- **Passenger grand total row:** Delete lines 330-336 (the `<template x-if="!loading && filteredPassengers.length > 0">` block with Grand Total)

---

### 4. `profit-loss-print.blade.php`

#### A. Update `print()` controller method

- Accept `search` and `profit_loss_filter` query params
- Apply same filters as summary endpoint when querying bookings
- Compute summary data inline (same SQL aggregation logic as `summary()`)
- Pass `$summary` variable to the view

#### B. Update Blade view

- Replace Blade `reduce()` grand total computation (lines 107-114, 170-174) with `$summary` data passed from controller
- Add search and profit/loss to the filters summary display (lines 57-60):

```php
$appliedFilters = array_filter([
    'Date From' => $dateFrom,
    'Date To' => $dateTo,
    'Search' => $search ?? null,
    'Profit/Loss' => $profitLossFilter !== 'all' ? ucfirst($profitLossFilter) : null,
]);
```

- Grand total row in print stays (useful for printed reports) but uses `$summary` data instead of Blade reduce:

```php
<tr class="grand-total">
    <td class="text-left" colspan="4">Grand Total</td>
    <td class="text-right">{{ $fmtCurrency($summary['customer']['package_value'] ?? 0) }}</td>
    <td class="text-right ...">{{ $fmtCurrency($summary['customer']['fingerprint_profit'] ?? 0) }}</td>
    ...
</tr>
```

---

### 5. `tests/Feature/ProfitLossReportTest.php`

```php
test_summary_returns_correct_aggregates_with_date_filter()
test_summary_respects_search_filter()
test_summary_respects_profit_loss_filter()
test_data_returns_paginated_results()
test_data_respects_all_filters()
test_print_includes_summary_data()
```

---

## Execution Order

1. Fix eager loads in `bookingsQuery()` (quick win, eliminates N+1s)
2. Add `summary()` method to controller
3. Add `/api/reports/profit-loss/summary` route
4. Rewrite `data()` for server-side pagination
5. Update `print()` method
6. Update main Blade view (summary cards, pagination, debounce, remove grand total rows)
7. Update print Blade view
8. Write tests
9. Run `php artisan test`
10. Run `vendor/bin/pint`

---

## Expected Performance

| Operation | Before | After |
|-----------|--------|-------|
| Summary cards | Load all bookings + compute breakdowns for every one, then reduce in JS | Single lightweight SQL query (~ms) |
| Table data | Full load + compute breakdowns for all + serialize megabytes | Paginated SQL, compute breakdowns for 25 records only |
| Search/filter | Client-side array iteration on full dataset | SQL WHERE clause on indexed columns |
