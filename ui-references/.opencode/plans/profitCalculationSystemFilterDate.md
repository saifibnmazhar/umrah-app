# Profit Calculation System — Component Storage & Date Filtering

## Overview

This plan adds per-component profit storage (`visa_profit`, `ticket_profit`, `service_charge`) with effective dates to the `passengers` table, and introduces dual date filter modes (Booking Date vs Effective Date) to the profit/loss report.

---

## Part 1: Database Changes

### 1.1 Migration: Add Component Profit Columns to `passengers`

**New file:** `database/migrations/2026_09_02_000001_add_component_profits_to_passengers_table.php`

```php
Schema::table('passengers', function (Blueprint $table) {
    $table->decimal('visa_profit', 14, 6)->default(0)->after('profit');
    $table->timestamp('visa_profit_effective_at')->nullable()->after('visa_profit');
    $table->decimal('ticket_profit', 14, 6)->default(0)->after('visa_profit_effective_at');
    $table->timestamp('ticket_profit_effective_at')->nullable()->after('ticket_profit');
    $table->decimal('service_charge', 14, 6)->default(0)->after('ticket_profit_effective_at');
    $table->timestamp('service_charge_effective_at')->nullable()->after('service_charge');
});
```

**Columns added:**

| Column | Type | Default | Purpose |
|---|---|---|---|
| `visa_profit` | decimal(14,6) | 0 | Stored visa profit component |
| `visa_profit_effective_at` | timestamp, nullable | null | When visa profit became effective |
| `ticket_profit` | decimal(14,6) | 0 | Stored ticket profit component |
| `ticket_profit_effective_at` | timestamp, nullable | null | When ticket profit became effective |
| `service_charge` | decimal(14,6) | 0 | Stored service charge component |
| `service_charge_effective_at` | timestamp, nullable | null | When service charge became effective |

### 1.2 Rollback

```php
Schema::table('passengers', function (Blueprint $table) {
    $table->dropColumn([
        'visa_profit', 'visa_profit_effective_at',
        'ticket_profit', 'ticket_profit_effective_at',
        'service_charge', 'service_charge_effective_at',
    ]);
});
```

---

## Part 2: Model Changes

### 2.1 `app/Models/Passenger.php`

Add to `$fillable`:
```php
'visa_profit', 'visa_profit_effective_at',
'ticket_profit', 'ticket_profit_effective_at',
'service_charge', 'service_charge_effective_at',
```

Add to `$casts`:
```php
'visa_profit' => 'decimal:6',
'ticket_profit' => 'decimal:6',
'service_charge' => 'decimal:6',
'visa_profit_effective_at' => 'datetime',
'ticket_profit_effective_at' => 'datetime',
'service_charge_effective_at' => 'datetime',
```

---

## Part 3: ProfitCalculationService Changes

### 3.1 Update `recalculatePassengerProfit()`

After computing the breakdown, determine effective dates and store component values:

```php
public function recalculatePassengerProfit(Passenger $passenger): float
{
    $passenger->unsetRelation('allIssuedTickets');
    $passenger->unsetRelation('visaSubmission');

    $breakdown = $this->getPassengerProfitBreakdown($passenger);

    $visaEffectiveAt = $this->determineVisaEffectiveDate($passenger);
    $ticketEffectiveAt = $this->determineTicketEffectiveDate($passenger);
    $serviceChargeEffectiveAt = ($visaEffectiveAt && $ticketEffectiveAt)
        ? max($visaEffectiveAt, $ticketEffectiveAt)
        : null;

    $passenger->updateQuietly(array_merge(
        $breakdown,
        [
            'visa_profit_effective_at' => $visaEffectiveAt,
            'ticket_profit_effective_at' => $ticketEffectiveAt,
            'service_charge_effective_at' => $serviceChargeEffectiveAt,
        ]
    ));

    return (float) $passenger->profit;
}
```

### 3.2 New Method: `determineVisaEffectiveDate()`

**Source:** `visa_update_logs` table — find the log where `new_values->status = 'issued'`.

```php
private function determineVisaEffectiveDate(Passenger $passenger): ?string
{
    if (! $this->isVisaProfitEffective($passenger)) {
        return null;
    }

    $visa = $passenger->visaSubmission;
    if (! $visa) {
        return null;
    }

    // Find the log where new_values contains status: 'issued'
    $log = \App\Models\VisaUpdateLog::where('visa_submission_id', $visa->id)
        ->where('new_values->status', 'issued')
        ->latest('created_at')
        ->first();

    return $log?->created_at?->toDateTimeString();
}
```

**Logic:**
- If visa profit is not effective (status not issued, or ticket_only with no visa) → null
- Query `visa_update_logs` for entries where the `new_values` JSON has `status: 'issued'`
- Use the latest such log's `created_at` as the effective date
- If no log found → fall back to `visa_submission.created_at`

**Rules:**
- If visa status is reverted from issued → `isVisaProfitEffective()` returns false → effective_at = null
- If costs change while status remains issued → profit updated, effective_at **unchanged** (still the original log timestamp where `new_values->status = 'issued'`)

### 3.3 New Method: `determineTicketEffectiveDate()`

**Source:** `issued_tickets.issued_date` field — the latest `issued_date` among all regular/pending_outbound tickets.

```php
private function determineTicketEffectiveDate(Passenger $passenger): ?string
{
    if (! $this->isTicketProfitEffective($passenger)) {
        return null;
    }

    $tickets = $this->regularTickets($passenger);
    if ($tickets->isEmpty()) {
        return null;
    }

    // Latest issued_date among all regular/pending_outbound tickets
    return $tickets->max('issued_date')?->toDateTimeString();
}
```

**Logic:**
- If ticket profit is not effective → null
- `regularTickets()` returns tickets where `issue_type` is `null`, `'regular'`, or `'pending_outbound'`
- Use the latest `issued_date` among those tickets
- If net_fare changes → profit updated, effective_at **unchanged**

**When ticket profit becomes effective:**
- `isTicketProfitEffective()` returns true when ALL regular/pending_outbound tickets have status `issued`, `re-issued`, or `refunded`
- Once effective, status changes to `re-issued` or `refunded` preserve effectiveness (both are in the allowed set)
- The observer does NOT trigger recalculation for status changes (Bug A fix) — profit is captured at issuance and preserved

### 3.4 Update `getPassengerProfitBreakdown()`

Add effective dates to the breakdown array:

```php
public function getPassengerProfitBreakdown(Passenger $passenger): array
{
    $visaProfit = $this->calculateVisaProfit($passenger);
    $ticketProfit = $this->calculateTicketProfit($passenger);
    $additionalTicketProfit = $this->calculateAdditionalTicketProfit($passenger);
    $reIssueProfit = $this->calculateReIssueProfit($passenger);
    $refundProfit = $this->calculateRefundProfit($passenger);
    $reIssueCost = $this->calculateReIssueCost($passenger);
    $serviceCharge = $this->calculateServiceCharge($passenger);

    $visaEffectiveAt = $this->determineVisaEffectiveDate($passenger);
    $ticketEffectiveAt = $this->determineTicketEffectiveDate($passenger);
    $serviceChargeEffectiveAt = ($visaEffectiveAt && $ticketEffectiveAt)
        ? max($visaEffectiveAt, $ticketEffectiveAt)
        : null;

    return [
        'visa_profit' => round($visaProfit, 6),
        'ticket_profit' => round($ticketProfit, 6),
        'additional_ticket_profit' => round($additionalTicketProfit, 6),
        're_issue_profit' => round($reIssueProfit, 6),
        'refund_profit' => round($refundProfit, 6),
        're_issue_cost' => round($reIssueCost, 6),
        'service_charge' => round($serviceCharge, 6),
        'total' => round(
            $visaProfit + $ticketProfit + $additionalTicketProfit
            + $reIssueProfit + $refundProfit + $serviceCharge - $reIssueCost,
            6
        ),
        'visa_profit_effective_at' => $visaEffectiveAt,
        'ticket_profit_effective_at' => $ticketEffectiveAt,
        'service_charge_effective_at' => $serviceChargeEffectiveAt,
    ];
}
```

### 3.5 `backfillAllBookings()`

No changes needed. The existing backfill calls `recalculateBookingProfit()` → `recalculatePassengerProfit()` for each passenger, which will now also store component values and effective dates automatically.

---

## Part 4: Re-Issue / Refund / Cost Effective Dates

These are sourced from the related row's `created_at` timestamp:

| Component | Effective Date Source |
|---|---|
| re_issue_profit | `re_issued_ticket.created_at` |
| refund_profit | `refunded_ticket.created_at` |
| re_issue_cost | `re_issued_ticket.created_at` |

These are not stored on the passenger — they are calculated dynamically when needed for effective date filtering.

---

## Part 5: Recalculation Chain (Reference)

Every change triggers a **complete recalculation** of the entire booking:

```
Observer fires
  → recalculateBookingProfit(booking)
    → for EACH passenger:
        recalculatePassengerProfit(passenger)
          → getPassengerProfitBreakdown(passenger)
            → calculateVisaProfit()      // reads fresh visa_submission values
            → calculateTicketProfit()    // reads fresh issued_ticket values
            → calculateServiceCharge()   // checks both effective
            → calculateAdditionalTicketProfit()
            → calculateReIssueProfit()
            → calculateRefundProfit()
            → calculateReIssueCost()
            → determineVisaEffectiveDate()
            → determineTicketEffectiveDate()
          → saves passengers.profit + component columns + effective dates
    → saves fingerprints.profit
    → sums all effective passenger profits + fingerprint - discount
    → saves bookings.profit
```

**Observer trigger rules:**

| Observer | Triggers Recalc When |
|---|---|
| VisaSubmissionObserver | `status`, `net_visa_cost`, `agent_commission`, `additional_cost`, `visa_selling_price_id` changed |
| IssuedTicketObserver | `net_fare`, `issue_type` changed (NOT `status`) |
| ReIssuedTicketObserver | `service_charge`, `payment_by`, `re_issue_charge`, `fare_difference`, `other_costs`, `net_fare`, `total_cost` changed |
| RefundedTicketObserver | `service_charge` changed |
| FingerprintObserver | `cost` changed |

---

## Part 6: Profit/Loss Report — Dual Date Filter Mode

### 6.1 Filter Mode Rules

| Rule | Behavior |
|---|---|
| Two filters: `booking_date_from/to` and `effective_date_from/to` | |
| Setting one filter clears the other | Mutual exclusivity |
| Booking Date default | **No default** (empty) |
| Effective Date default | **Last 30 days** |
| Per Customer tab | Uses **only** Booking Date filter, with a default set |
| Per Passenger tab | Uses **either** Booking Date or Effective Date filter |

### 6.2 `ProfitLossReportController.php` Changes

**Update `applyDateFilters()` to accept filter mode:**

```php
private function applyDateFilters($query, Request $request, string $mode = 'booking'): void
{
    if ($mode === 'booking') {
        if ($request->booking_date_from) {
            $query->whereDate('bookings.created_at', '>=', $request->booking_date_from);
        }
        if ($request->booking_date_to) {
            $query->whereDate('bookings.created_at', '<=', $request->booking_date_to);
        }
    }
}
```

**New method for effective date filtering on passenger components:**

```php
private function applyEffectiveDateFilter($query, Request $request): void
{
    if ($request->effective_date_from || $request->effective_date_to) {
        $query->where(function ($q) use ($request) {
            if ($request->effective_date_from) {
                $q->where('visa_profit_effective_at', '>=', $request->effective_date_from)
                  ->orWhere('ticket_profit_effective_at', '>=', $request->effective_date_from)
                  ->orWhere('service_charge_effective_at', '>=', $request->effective_date_from);
            }
            if ($request->effective_date_to) {
                $q->where('visa_profit_effective_at', '<=', $request->effective_date_to)
                  ->orWhere('ticket_profit_effective_at', '<=', $request->effective_date_to)
                  ->orWhere('service_charge_effective_at', '<=', $request->effective_date_to);
            }
        });
    }
}
```

**Update `data()` method:**

For **per passenger** tab:
- If `booking_date_from` or `booking_date_to` is set → filter by `bookings.created_at`, show stored profit values
- If `effective_date_from` or `effective_date_to` is set → don't filter by booking date, calculate profit dynamically based on effective dates

For **per customer** tab:
- Always use booking date filter (with default)

**Update `summary()` method:**

For per passenger summary:
- If booking date mode → sum stored `visa_profit`, `ticket_profit`, `service_charge` columns
- If effective date mode → sum stored values WHERE effective_at falls within the date range

### 6.3 Effective Date Mode — Dynamic Profit Calculation

When effective date filter is active, the profit breakdown must consider which components fall within the date range:

```php
private function calculateEffectiveDateProfit(Passenger $passenger, string $dateFrom, string $dateTo): float
{
    $total = 0.0;

    // Visa profit — included if visa_profit_effective_at is within range
    if ($passenger->visa_profit_effective_at
        && $passenger->visa_profit_effective_at->between($dateFrom, $dateTo)) {
        $total += (float) $passenger->visa_profit;
    }

    // Ticket profit — included if ticket_profit_effective_at is within range
    if ($passenger->ticket_profit_effective_at
        && $passenger->ticket_profit_effective_at->between($dateFrom, $dateTo)) {
        $total += (float) $passenger->ticket_profit;
    }

    // Service charge — included if service_charge_effective_at is within range
    if ($passenger->service_charge_effective_at
        && $passenger->service_charge_effective_at->between($dateFrom, $dateTo)) {
        $total += (float) $passenger->service_charge;
    }

    // Additional ticket profit — based on issued_ticket.created_at
    // Re-issue profit — based on re_issued_ticket.created_at
    // Refund profit — based on refunded_ticket.created_at
    // Re-issue cost — based on re_issued_ticket.created_at
    // These are calculated dynamically from related records

    return round($total, 6);
}
```

---

## Part 7: View Changes

### 7.1 `resources/views/reports/profit-loss.blade.php`

**Alpine.js data additions:**

```javascript
{
    // Replace single date_from/date_to with:
    booking_date_from: '',
    booking_date_to: '',
    effective_date_from: '',
    effective_date_to: '',
    activeDateFilter: 'effective', // 'booking' | 'effective'
}
```

**Filter UI — tabbed date filter:**

```html
<div class="flex gap-2 mb-3">
    <button @click="switchDateFilter('booking')"
        :class="activeDateFilter === 'booking'
            ? 'bg-slate-700 text-white' : 'bg-white text-slate-700 border'">
        Booking Date
    </button>
    <button @click="switchDateFilter('effective')"
        :class="activeDateFilter === 'effective'
            ? 'bg-slate-700 text-white' : 'bg-white text-slate-700 border'">
        Effective Date
    </button>
</div>

<!-- Booking Date inputs -->
<div x-show="activeDateFilter === 'booking'" class="flex gap-2">
    <input type="date" x-model="booking_date_from" @change="resetAndLoad()">
    <input type="date" x-model="booking_date_to" @change="resetAndLoad()">
</div>

<!-- Effective Date inputs -->
<div x-show="activeDateFilter === 'effective'" class="flex gap-2">
    <input type="date" x-model="effective_date_from" @change="resetAndLoad()">
    <input type="date" x-model="effective_date_to" @change="resetAndLoad()">
</div>
```

**`switchDateFilter()` method:**

```javascript
switchDateFilter(mode) {
    this.activeDateFilter = mode;
    if (mode === 'booking') {
        this.effective_date_from = '';
        this.effective_date_to = '';
    } else {
        this.booking_date_from = '';
        this.booking_date_to = '';
    }
    this.currentPage = 1;
    this.loadDataForTab();
    this.loadSummary();
}
```

**Default dates:**

```javascript
setDefaultDates() {
    // Effective date defaults to last 30 days
    this.effective_date_from = moment().subtract(30, 'days').format('YYYY-MM-DD');
    this.effective_date_to = moment().format('YYYY-MM-DD');
    this.activeDateFilter = 'effective';
    // Booking date has no default
}
```

**Per Customer tab — default booking date:**

When switching to customer tab:
```javascript
if (this.activeTab === 'customer' && !this.booking_date_from && !this.booking_date_to) {
    this.booking_date_from = moment().subtract(30, 'days').format('YYYY-MM-DD');
    this.booking_date_to = moment().format('YYYY-MM-DD');
    this.activeDateFilter = 'booking';
}
```

### 7.2 Per Passenger Tab — New Columns

| Invoice ID | Customer | Mobile | Passenger | Package Value | Visa Profit | Ticket Profit | Total Profit |
|---|---|---|---|---|---|---|---|

**Summary cards for per passenger tab:**
- Total Passengers
- Package Value
- **Visa Profit** (sum of visa_profit column)
- **Ticket Profit** (sum of ticket_profit column)
- Total Profit

---

## Part 8: Summary Cards Logic

### Per Passenger Tab Summary

**Booking Date mode:**
```sql
SELECT
    COUNT(*) as total_passengers,
    SUM(package_value) as total_package_value,
    SUM(visa_profit) as total_visa_profit,
    SUM(ticket_profit) as total_ticket_profit,
    SUM(profit) as total_profit
FROM passengers
JOIN bookings ON bookings.id = passengers.booking_id
WHERE bookings.created_at BETWEEN :booking_date_from AND :booking_date_to
```

**Effective Date mode:**
```sql
SELECT
    COUNT(*) as total_passengers,
    SUM(package_value) as total_package_value,
    SUM(CASE WHEN visa_profit_effective_at BETWEEN :from AND :to THEN visa_profit ELSE 0 END) as total_visa_profit,
    SUM(CASE WHEN ticket_profit_effective_at BETWEEN :from AND :to THEN ticket_profit ELSE 0 END) as total_ticket_profit,
    SUM(CASE WHEN (
        (visa_profit_effective_at BETWEEN :from AND :to)
        OR (ticket_profit_effective_at BETWEEN :from AND :to)
        OR (service_charge_effective_at BETWEEN :from AND :to)
    ) THEN profit ELSE 0 END) as total_profit
FROM passengers
```

### Per Customer Tab Summary

Always uses booking date filter:
```sql
SELECT
    COUNT(DISTINCT bookings.id) as total_customers,
    SUM(fingerprints.profit) as total_fingerprint_profit,
    SUM(passengers.profit) as total_passenger_profit,
    SUM(bookings.discount_amount) as total_discount,
    SUM(bookings.profit) as total_profit
FROM bookings
JOIN passengers ON passengers.booking_id = bookings.id
LEFT JOIN fingerprints ON fingerprints.booking_id = bookings.id
WHERE bookings.created_at BETWEEN :booking_date_from AND :booking_date_to
```

---

## Part 9: Implementation Order

| Step | Task | Files |
|---|---|---|
| 1 | Create migration for component profit columns | `database/migrations/2026_09_02_000001_...php` |
| 2 | Update Passenger model ($fillable, $casts) | `app/Models/Passenger.php` |
| 3 | Add `determineVisaEffectiveDate()` method | `app/Services/ProfitCalculationService.php` |
| 4 | Add `determineTicketEffectiveDate()` method | `app/Services/ProfitCalculationService.php` |
| 5 | Update `recalculatePassengerProfit()` to store components + effective dates | `app/Services/ProfitCalculationService.php` |
| 6 | Update `getPassengerProfitBreakdown()` to include effective dates | `app/Services/ProfitCalculationService.php` |
| 7 | Run migration + backfill | `php artisan migrate`, `php artisan profit:backfill` |
| 8 | Update ProfitLossReportController (dual date filter, effective date calculation) | `app/Http/Controllers/ProfitLossReportController.php` |
| 9 | Update profit-loss.blade.php (filter UI, new columns, summary cards) | `resources/views/reports/profit-loss.blade.php` |
| 10 | Update profit-loss-print.blade.php | `resources/views/reports/profit-loss-print.blade.php` |
| 11 | Add/update tests | `tests/Feature/ProfitCalculationServiceTest.php` |
| 12 | Run Pint | `vendor/bin/pint` |

---

## Part 10: Verification Checklist

- [ ] Migration runs without errors
- [ ] Passenger model has new columns in $fillable and $casts
- [ ] `recalculatePassengerProfit()` stores visa_profit, ticket_profit, service_charge
- [ ] `visa_profit_effective_at` = visa_update_log `created_at` where `new_values->status = 'issued'`
- [ ] `ticket_profit_effective_at` = latest `issued_date` among regular/pending_outbound tickets
- [ ] `service_charge_effective_at` = MAX(visa_effective, ticket_effective)
- [ ] If visa status reverted → visa_profit = 0, effective_at = null
- [ ] If costs change → profit updated, effective_at unchanged
- [ ] If ticket net_fare changes → profit updated, effective_at unchanged
- [ ] If ticket status changes to re-issued/refunded → profit unchanged (observer doesn't trigger)
- [ ] Re-issue profit date = `re_issued_ticket.created_at`
- [ ] Refund profit date = `refunded_ticket.created_at`
- [ ] Re-issue cost date = `re_issued_ticket.created_at`
- [ ] Booking date filter: no default, shows stored profit values
- [ ] Effective date filter: default 30 days, calculates dynamically
- [ ] Setting one filter clears the other
- [ ] Per passenger tab shows visa_profit and ticket_profit columns
- [ ] Per passenger tab has Visa Profit and Ticket Profit summary cards
- [ ] Per customer tab uses only booking date filter with default
- [ ] Total profit calculated based on active filter
- [ ] Backfill populates all new columns correctly
- [ ] All existing tests pass
- [ ] New tests for effective dates and date filtering
- [ ] Pint clean

---

## Appendix A: Post-Implementation Bugs

### Bug 1: `applyEffectiveDateFilter()` — Incorrect OR Logic (HIGH)

**File:** `app/Http/Controllers/ProfitLossReportController.php:55-71`

**Current (broken):**
```php
$query->where(function ($q) use ($request) {
    if ($request->effective_date_from) {
        $q->where('passengers.visa_profit_effective_at', '>=', $request->effective_date_from)
            ->orWhere('passengers.ticket_profit_effective_at', '>=', $request->effective_date_from)
            ->orWhere('passengers.service_charge_effective_at', '>=', $request->effective_date_from);
    }
    if ($request->effective_date_to) {
        $q->where('passengers.visa_profit_effective_at', '<=', $request->effective_date_to)
            ->orWhere('passengers.ticket_profit_effective_at', '<=', $request->effective_date_to)
            ->orWhere('passengers.service_charge_effective_at', '<=', $request->effective_date_to);
    }
});
```

**Problem:** The from-block and to-block are wrapped in the same `where()` closure with implicit AND between them. But the OR inside each block means a passenger can match `visa_profit_effective_at >= from` (true) via the first block, then match `ticket_profit_effective_at <= to` (true) via the second block — even if the ticket's effective date is far outside the range. Cross-column false positives.

**Example:** `from=2024-03-01, to=2024-06-30`. Passenger has `visa_profit_effective_at = 2024-01-15` (before from) and `ticket_profit_effective_at = 2024-08-15` (after to). Block 1: `ticket >= 2024-03-01` (true, 8月 ≥ 3月). Block 2: `visa <= 2024-06-30` (true, 1月 ≤ 6月). WHERE evaluates true. **False positive** — no component is actually in range.

**Fix:** Use `whereBetween` per column with OR between columns:
```php
private function applyEffectiveDateFilter($query, Request $request): void
{
    if ($request->effective_date_from || $request->effective_date_to) {
        $from = $request->effective_date_from ?? '1970-01-01';
        $to = $request->effective_date_to ?? now()->toDateTimeString();
        $query->where(function ($q) use ($from, $to) {
            $q->whereBetween('passengers.visa_profit_effective_at', [$from, $to])
              ->orWhereBetween('passengers.ticket_profit_effective_at', [$from, $to])
              ->orWhereBetween('passengers.service_charge_effective_at', [$from, $to]);
        });
    }
}
```

---

### Bug 2: `summary()` — Redundant CASE WHEN with WHERE (LOW)

**File:** `app/Http/Controllers/ProfitLossReportController.php:180-194`

The effective date branch calls `applyEffectiveDateFilter($passenger, $request)` to add a WHERE clause, then uses CASE WHEN with the same conditions in SELECT. Since WHERE already filters rows, the CASE WHEN is always true for surviving rows.

**Impact:** No incorrect results — just dead code. The CASE WHEN could be simplified to always return the profit value since rows are already filtered.

**Fix:** Remove the CASE WHEN and just sum the columns directly:
```php
$passenger->selectRaw('
    COUNT(*) as count,
    COALESCE(SUM(passengers.package_value), 0) as package_value,
    COALESCE(SUM(passengers.visa_profit), 0) as total_visa_profit,
    COALESCE(SUM(passengers.ticket_profit), 0) as total_ticket_profit,
    COALESCE(SUM(passengers.profit), 0) as total_profit
');
```

**Or** remove `applyEffectiveDateFilter()` from summary and keep the CASE WHEN (the CASE WHEN becomes the filter). Either approach works — just be consistent.

---

### Bug 3: `print()` — Ignores Effective Date Filter (MEDIUM)

**File:** `app/Http/Controllers/ProfitLossReportController.php:373-435` + `resources/views/reports/profit-loss.blade.php:208-209`

The `print()` method only handles `booking_date_from`/`booking_date_to`. The print links don't pass effective date parameters. When a user is in effective date mode, print shows all data or booking-date-filtered data, not what they see on screen.

**Fix in controller:**
```php
public function print(Request $request)
{
    // ... existing code ...
    if ($request->booking_date_from) {
        $query->whereDate('created_at', '>=', $request->booking_date_from);
    }
    if ($request->booking_date_to) {
        $query->whereDate('created_at', '<=', $request->booking_date_to);
    }
    // ADD: effective date handling for passenger type
    // (customer type always uses booking date per plan)
}
```

**Fix in Blade (lines 208-209):** Pass effective date params in print links:
```html
<a :href="'/reports/profit-loss/print?...&effective_date_from=' + effective_date_from + '&effective_date_to=' + effective_date_to + '...'">
```

---

### Bug 4: Customer Tab — No Default Dates on Page Load (MEDIUM)

**File:** `resources/views/reports/profit-loss.blade.php:680-687`

`setDefaultDates()` only sets effective date defaults. On page load, `activeTab` is `'customer'` (line 634) but `booking_date_from`/`booking_date_to` are empty. The customer tab shows all data instead of the last 30 days.

**Fix:** Set booking date defaults in `setDefaultDates()`:
```javascript
setDefaultDates() {
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);

    // Effective date defaults (for passenger tab)
    this.effective_date_from = thirtyDaysAgo.toISOString().split('T')[0];
    this.effective_date_to = today.toISOString().split('T')[0];
    this.activeDateFilter = 'effective';

    // Booking date defaults (for customer tab)
    this.booking_date_from = thirtyDaysAgo.toISOString().split('T')[0];
    this.booking_date_to = today.toISOString().split('T')[0];
},
```

---

## Appendix B: Bug Fix Implementation Order

| Step | Task | File | Bug # |
|---|---|---|---|
| 1 | Fix `applyEffectiveDateFilter()` — use `whereBetween` per column | `ProfitLossReportController.php` | #1 |
| 2 | Fix `summary()` — remove redundant CASE WHEN | `ProfitLossReportController.php` | #2 |
| 3 | Fix `print()` — add effective date support | `ProfitLossReportController.php` | #3 |
| 4 | Fix print links — pass effective date params | `profit-loss.blade.php` | #3 |
| 5 | Fix `setDefaultDates()` — set booking date defaults | `profit-loss.blade.php` | #4 |
| 6 | Run Pint | `vendor/bin/pint` | — |
