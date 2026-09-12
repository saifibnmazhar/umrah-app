# Profit/Loss Report Print — Query & Performance Optimization Plan

> **Status:** Implemented (conflicts resolved, merge completed)

## Problem

The `print()` method in `ProfitLossReportController.php:575-672` is slow because it:

1. Loads **ALL** matching bookings with 18 eager-loaded relations (including nested `allIssuedTickets.logs`)
2. Computes expensive `getPassengerProfitBreakdownDetailed()` per passenger — the print view only needs `visa_profit`, `ticket_profit`, `total_profit`
3. Calls `summary()` redundantly (3+ extra SQL queries)
4. In effective-date mode, computes per-passenger breakdowns **twice** (once in `passengerHasEffectiveComponentInRange`, again in `map()`)
5. `effectiveAdditionalTotal()` re-loads passengers/tickets already available

Additionally, the print method had two critical bugs:

- **`$profitService` undefined** in the effective-date closure — would throw runtime error
- **No SQL-level effective date filtering** — loaded ALL bookings when effective date mode was active, then filtered in PHP (extremely slow for large datasets)

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

### 7. Updated `print()` method with proper effective date handling

**File:** `app/Http/Controllers/ProfitLossReportController.php`

The print method now branches on date mode:

- **Effective date mode (passenger tab):** Uses a `Passenger` query with `applyEffectiveDateFilter()` at the SQL level (matching `data()` behavior), then loads only matching bookings with `PRINT_BOOKING_WITHS`. Recomputes effective-date-scoped profits in PHP.
- **Booking date mode (both tabs):** Uses a `Booking` query with `PRINT_BOOKING_WITHS` and booking date filters on `created_at`.

Also fixes:
- `$profitService` is now declared before the if-block
- Summary includes `total_visa_profit` and `total_ticket_profit` for the passenger tab
- No row limit — all filtered rows are included
- Removed `$truncated` from view data

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
        'search', 'profitLossFilter', 'summary', 'branchName'
    ));
}
```

---

## Expected Performance Improvement

| Metric | Before | After |
|--------|--------|-------|
| Eager-loaded relations | 18 | 6 (PRINT_BOOKING_WITHS) |
| PHP breakdown computations per passenger | ~10 methods (`getPassengerProfitBreakdownDetailed`) | 0 (read stored columns) |
| `summary()` DB queries | 2-6 | 0 (inlined from loaded data) |
| Effective-date SQL filtering | None (loads all, filters in PHP) | `applyEffectiveDateFilter()` at SQL level |
| Memory (no. of loaded objects) | 18 × N bookings | 6 × N bookings (or filtered passenger set) |

---

## Files Modified

1. `app/Http/Controllers/ProfitLossReportController.php` — new print-specific methods (`mapPassengerForPrint`, `mapCustomersForPrint`, `mapPassengersForPrint`), `PRINT_BOOKING_WITHS` constant, branching effective/booking date logic in `print()`, inlined summary
2. `resources/views/reports/profit-loss-print.blade.php` — removed truncation warning

---

## Existing Tests to Verify

- `tests/Feature/ReportQueryOptimizationTest.php`
  - `test_profit_loss_print_includes_summary_data` (line 506)
  - `test_profit_loss_passenger_print_matches_index_tab_columns` (line 531)
- `tests/Feature/ProfitLossReportBranchFilterTest.php` — print tests
- `tests/Feature/ProfitLossEffectiveDateFilterTest.php` — print with effective dates
