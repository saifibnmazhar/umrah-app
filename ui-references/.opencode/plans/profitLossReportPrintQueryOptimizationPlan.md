# Profit/Loss Report Print — Query & Performance Optimization Plan

## Problem

The `print()` method in `ProfitLossReportController.php:575-672` is slow because it:

1. Loads **ALL** matching bookings with 18 eager-loaded relations (including nested `allIssuedTickets.logs`)
2. Computes expensive `getPassengerProfitBreakdownDetailed()` per passenger — the print view only needs `visa_profit`, `ticket_profit`, `total_profit`
3. Calls `summary()` redundantly (3+ extra SQL queries)
4. In effective-date mode, computes per-passenger breakdowns **twice** (once in `passengerHasEffectiveComponentInRange`, again in `map()`)
5. `effectiveAdditionalTotal()` re-loads passengers/tickets already available
6. Has **no row limit** — renders everything into one HTML page

## Root Cause

The `print()` method was built by copying the interactive report's data-loading logic, but the print view only needs a flat summary table — not the full breakdown modals. The controller computes expensive breakdowns, loads deeply nested relations, and calls aggregate methods that are completely unused by the print Blade template.

---

## Changes

### 1. Create `mapPassengerForPrint()` — lightweight passenger mapper

**File:** `app/Http/Controllers/ProfitLossReportController.php`

Read profit values directly from the Passenger model's stored columns (`visa_profit`, `ticket_profit`, `profit`) instead of calling `getPassengerProfitBreakdownDetailed()`. This avoids 10+ PHP calculation methods per passenger.

```php
private function mapPassengerForPrint($passenger): array
{
    $booking = $passenger->booking;

    return [
        'id' => (int) $passenger->id,
        'invoice_id' => $booking->invoice_id,
        'customer_name' => $booking->customer->name ?? '',
        'customer_passport' => $booking->customer->passport_no ?? '',
        'customer_iqama' => $booking->customer->iqama_no ?? '',
        'mobile' => $passenger->mobile_no,
        'passenger_name' => trim($passenger->first_name.' '.$passenger->last_name),
        'passenger_passport' => $passenger->passport_no ?? '',
        'package_value' => (float) ($passenger->package_value ?? 0),
        'total_profit' => (float) ($passenger->profit ?? 0),
        'visa_profit' => (float) ($passenger->visa_profit ?? 0),
        'ticket_profit' => (float) ($passenger->ticket_profit ?? 0),
        'service_charge' => (float) ($passenger->service_charge ?? 0),
    ];
}
```

### 2. Create `mapPassengersForPrint()` — uses the lightweight mapper

**File:** `app/Http/Controllers/ProfitLossReportController.php`

```php
private function mapPassengersForPrint($bookings, ProfitCalculationService $profitService): array
{
    return $bookings->reject(fn (Booking $booking) => $profitService->isBookingCancelledForProfit($booking))
        ->flatMap(fn (Booking $booking) => $booking->passengers
            ->reject(fn ($p) => $profitService->isPassengerCancelledForProfit($p))
            ->map(fn ($passenger) => $this->mapPassengerForPrint($passenger))
        )->values()->toArray();
}
```

### 3. Create `mapCustomersForPrint()` — no breakdown computation

**File:** `app/Http/Controllers/ProfitLossReportController.php`

Same as `mapCustomers()` but without calling `getCustomerProfitBreakdown()`:

```php
private function mapCustomersForPrint($bookings, ProfitCalculationService $profitService): array
{
    return $bookings->reject(fn (Booking $booking) => $profitService->isBookingCancelledForProfit($booking))
        ->map(fn (Booking $booking) => [
            'invoice_id' => $booking->invoice_id,
            'customer_name' => $booking->customer->name ?? '',
            'customer_passport' => $booking->customer->passport_no ?? '',
            'customer_iqama' => $booking->customer->iqama_no ?? '',
            'mobile' => $booking->customer->mobile_no ?? '',
            'pax_qty' => $booking->pax_qty,
            'package_value' => (float) ($booking->invoice->total_amount ?? 0),
            'fingerprint_profit' => (float) ($booking->fingerprint?->profit ?? 0),
            'passenger_profit_total' => (float) $booking->passengers
                ->reject(fn ($p) => $profitService->isPassengerCancelledForProfit($p))
                ->sum('profit'),
            'discount' => (float) ($booking->discount_amount ?? 0),
            'total_profit' => (float) ($booking->profit ?? 0),
        ])->values()->toArray();
}
```

### 4. Reduce eager loads for print

**File:** `app/Http/Controllers/ProfitLossReportController.php`

**Current (18 relations):** `BOOKING_WITHS` + `passengers.allIssuedTickets.logs`

The print view needs far fewer:

| Keep | Why |
|------|-----|
| `customer` | name, mobile |
| `invoice` | total_amount |
| `fingerprint` | fingerprint profit |
| `fingerprintCharge` | fingerprint profit |
| `cancelledBooking` | cancelled check |
| `passengers.cancelledPassengers` | cancelled check |

| Remove | Why not needed |
|--------|---------------|
| `package.ticketFareInbound` | Only for double-ticket fare calculation in breakdown |
| `package.ticketFareOutbound` | Only for double-ticket fare calculation in breakdown |
| `passengers.allIssuedTickets` | Only for detailed breakdown (additional, re-issue, refund) |
| `passengers.allIssuedTickets.ticketFare` | Sub-relation of above |
| `passengers.allIssuedTickets.reIssuedTickets` | Sub-relation of above |
| `passengers.allIssuedTickets.refundedTickets` | Sub-relation of above |
| `passengers.allIssuedTickets.logs` | Sub-relation of above |
| `passengers.visaSubmission` | Only for detailed breakdown |
| `passengers.visaSubmission.cancelledSubmissions` | Sub-relation of above |
| `passengers.visaSubmission.visaSellingPrice` | Sub-relation of above |

New constant:

```php
private const PRINT_BOOKING_WITHS = [
    'customer',
    'invoice',
    'fingerprint',
    'fingerprintCharge',
    'cancelledBooking',
    'passengers.cancelledPassengers',
];
```

This reduces the query from 18 eager loads to 6.

### 5. Fix effective-date mode double computation

**File:** `app/Http/Controllers/ProfitLossReportController.php`, lines 604-633

Currently `passengerHasEffectiveComponentInRange()` calls `calculateEffectiveDateBreakdown()` which runs `calculateEffectiveDateProfitDetailed()`. Then the `map()` callback calls `calculateEffectiveDateBreakdown()` again — identical work.

**Fix:** Merge into a single pass. Compute the breakdown once, check if it's non-zero, and filter:

```php
if ($type === 'passenger' && $isEffectiveMode) {
    $dateFrom = $request->effective_date_from ?? '1970-01-01';
    $dateTo = $this->effectiveDateTo($request);

    $passengerById = collect();
    foreach ($bookings as $bookingModel) {
        foreach ($bookingModel->passengers as $passengerModel) {
            $passengerModel->setRelation('booking', $bookingModel);
            $passengerById->put($passengerModel->id, $passengerModel);
        }
    }

    $passengers = collect($passengers)->map(function ($row) use ($passengerById, $profitService, $dateFrom, $dateTo) {
        $passenger = $passengerById->get($row['id'] ?? null);
        if (! $passenger) {
            return null;
        }

        $breakdown = $profitService->calculateEffectiveDateProfitDetailed($passenger, $dateFrom, $dateTo);

        $hasEffective = (float) $breakdown['total'] !== 0.0
            || (float) $breakdown['additional_ticket_profit'] !== 0.0
            || (float) $breakdown['re_issue_profit'] !== 0.0
            || (float) $breakdown['refund_profit'] !== 0.0
            || (float) $breakdown['re_issue_cost'] !== 0.0;

        if (! $hasEffective) {
            return null;
        }

        $row['total_profit'] = $breakdown['total'];
        $row['visa_profit'] = $breakdown['visa_profit'];
        $row['ticket_profit'] = $breakdown['ticket_profit'];
        $row['service_charge'] = $breakdown['service_charge'];

        return $row;
    })->filter(fn ($row) => $row !== null)->values();
}
```

This halves the PHP computation per passenger in effective-date mode.

### 6. Inline summary totals from loaded data

**File:** `app/Http/Controllers/ProfitLossReportController.php`, line 662

Currently `$this->summary($request)` runs 2 aggregate SQL queries plus `effectiveComponentTotals` (4 more queries in effective mode). These are already computable from the loaded `$customers`/`$passengers` arrays.

Replace with:

```php
$summary = [
    'customer' => [
        'count' => count($customers),
        'package_value' => collect($customers)->sum('package_value'),
        'fingerprint_profit' => collect($customers)->sum('fingerprint_profit'),
        'passenger_profit_total' => collect($customers)->sum('passenger_profit_total'),
        'discount' => collect($customers)->sum('discount'),
        'total_profit' => collect($customers)->sum('total_profit'),
    ],
    'passenger' => [
        'count' => count($passengers),
        'package_value' => collect($passengers)->sum('package_value'),
        'total_visa_profit' => collect($passengers)->sum('visa_profit'),
        'total_ticket_profit' => collect($passengers)->sum('ticket_profit'),
        'total_profit' => collect($passengers)->sum('total_profit'),
    ],
];
```

This eliminates 2-6 DB queries from the print request.

### 7. Add a row limit with warning

**File:** `app/Http/Controllers/ProfitLossReportController.php`

Add a limit constant:

```php
private const PRINT_MAX_ROWS = 2000;
```

In the print method, after computing `$customers` or `$passengers`:

```php
$truncated = false;
if (count($customers) > self::PRINT_MAX_ROWS) {
    $customers = array_slice($customers, 0, self::PRINT_MAX_ROWS);
    $truncated = true;
}
if (count($passengers) > self::PRINT_MAX_ROWS) {
    $passengers = array_slice($passengers, 0, self::PRINT_MAX_ROWS);
    $truncated = true;
}
```

Pass `$truncated` to the Blade view.

**File:** `resources/views/reports/profit-loss-print.blade.php`

Add a warning banner after the filters summary:

```blade
@if($truncated ?? false)
<div style="padding: 8px; background: #fef3c7; border: 1px solid #f59e0b; margin-bottom: 10px; font-size: 12px;">
    <strong>Warning:</strong> Results truncated to {{ number_format(\App\Http\Controllers\ProfitLossReportController::PRINT_MAX_ROWS) }} rows.
    Please refine your filters to see all results.
</div>
@endif
```

### 8. Update `print()` to use new methods

**File:** `app/Http/Controllers/ProfitLossReportController.php`, method `print()`

Replace the full `print()` method with the optimized version:

```php
public function print(Request $request)
{
    $type = $request->get('type', 'customer');
    $currency = $request->get('currency', 'SAR');
    $dateFrom = $request->booking_date_from;
    $dateTo = $request->booking_date_to;
    $search = trim((string) $request->search);
    $profitLossFilter = $request->profit_loss_filter;

    $query = Booking::with(self::PRINT_BOOKING_WITHS)
        ->whereHas('invoice');
    $this->excludeCancelledBookings($query, 'bookings');

    if ($request->booking_date_from) {
        $query->whereDate('created_at', '>=', $request->booking_date_from);
    }
    if ($request->booking_date_to) {
        $query->whereDate('created_at', '<=', $request->booking_date_to);
    }
    $this->applyBranchFilter($query, $request);
    $bookings = $query->get();

    $profitService = app(ProfitCalculationService::class);
    $customers = $this->mapCustomersForPrint($bookings, $profitService);
    $passengers = $this->mapPassengersForPrint($bookings, $profitService);

    $isEffectiveMode = $request->filled('effective_date_from') || $request->filled('effective_date_to');
    if ($type === 'passenger' && $isEffectiveMode) {
        $dateFrom = $request->effective_date_from ?? '1970-01-01';
        $dateTo = $this->effectiveDateTo($request);

        $passengerById = collect();
        foreach ($bookings as $bookingModel) {
            foreach ($bookingModel->passengers as $passengerModel) {
                $passengerModel->setRelation('booking', $bookingModel);
                $passengerById->put($passengerModel->id, $passengerModel);
            }
        }

        $passengers = collect($passengers)->map(function ($row) use ($passengerById, $profitService, $dateFrom, $dateTo) {
            $passenger = $passengerById->get($row['id'] ?? null);
            if (! $passenger) {
                return null;
            }

            $breakdown = $profitService->calculateEffectiveDateProfitDetailed($passenger, $dateFrom, $dateTo);

            $hasEffective = (float) $breakdown['total'] !== 0.0
                || (float) $breakdown['additional_ticket_profit'] !== 0.0
                || (float) $breakdown['re_issue_profit'] !== 0.0
                || (float) $breakdown['refund_profit'] !== 0.0
                || (float) $breakdown['re_issue_cost'] !== 0.0;

            if (! $hasEffective) {
                return null;
            }

            $row['total_profit'] = $breakdown['total'];
            $row['visa_profit'] = $breakdown['visa_profit'];
            $row['ticket_profit'] = $breakdown['ticket_profit'];
            $row['service_charge'] = $breakdown['service_charge'];

            return $row;
        })->filter(fn ($row) => $row !== null)->values();

        $passengers = $passengers->toArray();
    }

    if ($search) {
        $q = strtolower($search);
        if ($type === 'passenger') {
            $passengers = collect($passengers)->filter(fn ($r) => str_contains(strtolower($r['invoice_id'] ?? ''), $q)
                || str_contains(strtolower($r['customer_name'] ?? ''), $q)
                || str_contains(strtolower($r['passenger_name'] ?? ''), $q)
                || str_contains(strtolower($r['passenger_passport'] ?? ''), $q)
            )->values()->toArray();
        } else {
            $customers = collect($customers)->filter(fn ($r) => str_contains(strtolower($r['invoice_id'] ?? ''), $q)
                || str_contains(strtolower($r['customer_name'] ?? ''), $q)
                || str_contains(strtolower($r['customer_passport'] ?? ''), $q)
                || str_contains(strtolower($r['customer_iqama'] ?? ''), $q)
            )->values()->toArray();
        }
    }

    if ($profitLossFilter === 'profit') {
        $passengers = array_values(array_filter($passengers, fn ($r) => (float) $r['total_profit'] >= 0));
        $customers = array_values(array_filter($customers, fn ($r) => (float) $r['total_profit'] >= 0));
    }
    if ($profitLossFilter === 'loss') {
        $passengers = array_values(array_filter($passengers, fn ($r) => (float) $r['total_profit'] < 0));
        $customers = array_values(array_filter($customers, fn ($r) => (float) $r['total_profit'] < 0));
    }

    $truncated = false;
    if (count($customers) > self::PRINT_MAX_ROWS) {
        $customers = array_slice($customers, 0, self::PRINT_MAX_ROWS);
        $truncated = true;
    }
    if (count($passengers) > self::PRINT_MAX_ROWS) {
        $passengers = array_slice($passengers, 0, self::PRINT_MAX_ROWS);
        $truncated = true;
    }

    $summary = [
        'customer' => [
            'count' => count($customers),
            'package_value' => collect($customers)->sum('package_value'),
            'fingerprint_profit' => collect($customers)->sum('fingerprint_profit'),
            'passenger_profit_total' => collect($customers)->sum('passenger_profit_total'),
            'discount' => collect($customers)->sum('discount'),
            'total_profit' => collect($customers)->sum('total_profit'),
        ],
        'passenger' => [
            'count' => count($passengers),
            'package_value' => collect($passengers)->sum('package_value'),
            'total_visa_profit' => collect($passengers)->sum('visa_profit'),
            'total_ticket_profit' => collect($passengers)->sum('ticket_profit'),
            'total_profit' => collect($passengers)->sum('total_profit'),
        ],
    ];

    $branchName = $request->filled('branch_id')
        ? Branch::find($request->branch_id)?->name
        : null;

    return view('reports.profit-loss-print', compact(
        'type', 'currency', 'customers', 'passengers', 'dateFrom', 'dateTo',
        'search', 'profitLossFilter', 'summary', 'branchName', 'truncated'
    ));
}
```

---

## Expected Performance Improvement

| Metric | Before | After |
|--------|--------|-------|
| Eager-loaded relations | 18 | 6 |
| PHP breakdown computations per passenger | ~10 methods (`getPassengerProfitBreakdownDetailed`) | 0 (read stored columns) |
| `summary()` DB queries | 2-6 | 0 (inlined from loaded data) |
| Effective-date per-passenger PHP passes | 2 | 1 |
| Memory (no. of loaded objects) | 18 × N bookings | 6 × N bookings |
| Row limit | unlimited | 2000 |

---

## Files to Modify

1. `app/Http/Controllers/ProfitLossReportController.php` — new print-specific methods, trimmed eager loads, inlined summary, effective-date dedup
2. `resources/views/reports/profit-loss-print.blade.php` — truncation warning banner

---

## Existing Tests to Verify

- `tests/Feature/ReportQueryOptimizationTest.php`
  - `test_profit_loss_print_includes_summary_data` (line 506)
  - `test_profit_loss_passenger_print_matches_index_tab_columns` (line 531)
- `tests/Feature/ProfitLossReportBranchFilterTest.php` — print tests
- `tests/Feature/ProfitLossEffectiveDateFilterTest.php` — print with effective dates

---

## New Test to Add

A query-count test for the print endpoint:

```php
/** @test */
public function test_profit_loss_print_stays_bounded_query_count(): void
{
    $user = $this->setupUser();
    $deps = $this->seedAllPrerequisites($user);

    for ($i = 0; $i < 10; $i++) {
        $this->createBookingWithPassengers($user, $deps, $i, 2);
    }

    Auth::login($user);

    DB::enableQueryLog();
    $response = $this->get(route('report.profit-loss.print', [
        'date_from' => now()->subDays(60)->toDateString(),
        'date_to' => now()->addDays(1)->toDateString(),
        'type' => 'customer',
    ]));
    DB::disableQueryLog();

    $queryCount = count(DB::getQueryLog());

    $response->assertOk();
    $this->assertLessThan(20, $queryCount,
        'Profit/Loss print should execute fewer than 20 queries for 10 bookings. Actual: '.$queryCount);
}
```
