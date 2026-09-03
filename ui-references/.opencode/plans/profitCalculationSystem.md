# Profit/Loss Calculation System — Implementation Plan

## Overview

This plan restructures the profit/loss calculation from a simple `revenue - cost` model to a detailed sector-based system with stored (denormalized) profit values per passenger and per booking. Profits are computed reactively when related entities change status.

---

## Part 1: Database Changes

### 1.1 Add `profit` column to `bookings` table

**Migration:** `2026_08_24_000001_add_profit_to_bookings_table.php`

```php
$table->decimal('profit', 14, 6)->default(0)->after('total_value');
```

### 1.2 Add `profit` column to `passengers` table

**Migration:** `2026_08_24_000002_add_profit_to_passengers_table.php`

```php
$table->decimal('profit', 14, 6)->default(0)->after('package_value');
```

### 1.3 Add `profit` column to `fingerprints` table

**Migration:** `2026_08_24_000003_add_profit_to_fingerprints_table.php`

```php
$table->decimal('profit', 14, 6)->default(0)->after('cost');
```

---

## Part 2: Profit Calculation Service

**New file:** `app/Services/ProfitCalculationService.php`

This is the core service that computes and stores profit values. It replaces the on-the-fly calculation in `CostTrackingService`.

### 2.1 Per-Passenger Profit Formula

```
profit = visa_profit + ticket_profit + additional_ticket_profit
       + reissue_profit + refund_profit + service_charge - reissue_cost
```

Where each sector is only included when "effective" (see Part 2.2).

### 2.2 Sector Definitions & Effectiveness Rules

| Sector | Formula | Effective When |
|--------|---------|----------------|
| **Visa Profit** | `visaSellingPrice.selling_price - visaSubmission.net_visa_cost - visaSubmission.agent_commission - visaSubmission.additional_cost - cancellation_fee` | visaSubmission.status == 'issued' |
| **Ticket Profit** | `packageTicketSellingFare - SUM(issued_tickets.net_fare)` where issue_type IN ('regular','pending_outbound') | ALL issuedTickets with issue_type IN ('regular','pending_outbound') have status='issued' for this passenger |
| **Additional Ticket Profit** | SUM over additional issuedTickets: `(ticketFare.selling_fare or offer_price if ticket_type=offer) - issued_ticket.net_fare` | Each additional issuedTicket with status='issued' |
| **Re-Issue Profit** | SUM(re_issued_tickets.service_charge) WHERE payment_by != 'company' | Entry exists in re_issued_tickets for this passenger |
| **Refund Profit** | SUM(refunded_tickets.service_charge) | Entry exists in refunded_tickets for this passenger |
| **Re-Issue Cost** | SUM(re_issued_tickets.total_cost) WHERE payment_by == 'company' | Re-issued ticket with payment_by='company' exists |
| **Service Charge** | `package.service_charge` | Only after visa_profit AND ticket_profit are both effective |

### 2.3 Package Ticket Selling Fare Computation

For a passenger, get the package from `passenger.booking.package`:

- **Single ticket** (`package.is_double_ticket == false`):
  - Get `package.ticketFare`
  - Selling fare = `ticketFare.offer_price` if `ticketFare.ticket_type == 'offer'`, else `ticketFare.selling_fare`
  - Adjust by passenger type: adult uses base, child uses `child_fare_percentage`, infant uses `infant_fare_percentage`

- **Double ticket** (`package.is_double_ticket == true`):
  - Get `package.ticketFareInbound` and `package.ticketFareOutbound`
  - Each fare adjusted by passenger type (same logic as above)
  - Sum both adjusted fares

### 2.4 Visa Profit — Cancellation Fee

The `cancellation_fee` comes from `visaSubmission.cancelledSubmissions` (the latest `CancelledSubmission` record). Sum `cancellation_fee` across all cancelled submissions for that visa.

### 2.5 Fingerprint Profit

```
fingerprint_profit = booking.fingerprintCharge.fingerprint_charge - booking.fingerprint.cost
```

Stored in `fingerprints.profit`. This is per-booking (not per-passenger).

### 2.6 Per-Booking (Customer) Profit

```
booking.profit = SUM(passenger.profit for all passengers in booking)
               + fingerprint.profit
               - booking.discount_amount
```

### 2.7 Service Charge Rule

Once service_charge becomes effective (both visa_profit and ticket_profit effective), it stays effective permanently. Cancellation of visa after issuance does NOT remove service_charge. Since visa status cannot actually change from 'issued' to anything else in the current system, this is a defensive safeguard — the logic simply never removes service_charge once added.

```php
private function calculateServiceCharge(Passenger $passenger): float
{
    if (!$this->isVisaProfitEffective($passenger) || !$this->isTicketProfitEffective($passenger)) {
        return 0;
    }
    return (float) ($passenger->booking->package->service_charge ?? 0);
}
```

No special "was previously effective" tracking needed — the conditions are checked fresh each time, and since issued status is irreversible, once both are true they stay true.

---

## Part 3: Reactive Profit Updates (Event-Driven)

Profit is recalculated and stored whenever a relevant entity changes. The approach: attach observers/listeners to the models that affect profit.

### 3.1 VisaSubmission Status Change → Recalculate Passenger Profit

**Where:** In the existing visa status update logic (likely in `VisaController` or similar), after status changes to/from 'issued'.

**Action:**
```php
$passenger = $visaSubmission->passenger;
$profitService = app(ProfitCalculationService::class);
$profitService->recalculatePassengerProfit($passenger);
$profitService->recalculateBookingProfit($passenger->booking);
```

### 3.2 IssuedTicket Status Change → Recalculate Passenger Profit

**Where:** In the existing ticket issuance logic, after status changes to 'issued'.

**Action:** Same pattern — recalculate passenger + booking profit.

### 3.3 ReIssuedTicket/RefundedTicket Created/Updated → Recalculate

**Where:** In existing controllers/services that create re-issue or refund records.

**Action:** Recalculate passenger + booking profit.

### 3.4 Fingerprint Cost Change → Recalculate Fingerprint + Booking Profit

**Where:** In existing fingerprint cost update logic.

**Action:**
```php
$fingerprint = $booking->fingerprint;
$fingerprint->profit = ($booking->fingerprintCharge->fingerprint_charge ?? 0) - $fingerprint->cost;
$fingerprint->saveQuietly();
$profitService->recalculateBookingProfit($booking);
```

### 3.5 Booking Discount Change → Recalculate Booking Profit

**Where:** In existing discount update logic.

**Action:** Recalculate booking profit.

### 3.6 Implementation Strategy

Create an `app/Observers/` directory with observers for:
- `VisaSubmissionObserver` — on `updated` event when status changes
- `IssuedTicketObserver` — on `created`, `updated` events
- `ReIssuedTicketObserver` — on `created`, `updated`, `deleted` events
- `RefundedTicketObserver` — on `created`, `updated`, `deleted` events

Register observers in `app/Providers/AppServiceProvider.php`.

---

## Part 4: ProfitCalculationService Methods

```php
class ProfitCalculationService
{
    // Core recalculation methods
    public function recalculatePassengerProfit(Passenger $passenger): float
    public function recalculateBookingProfit(Booking $booking): float

    // Individual sector calculators (called internally)
    private function calculateVisaProfit(Passenger $passenger): float
    private function calculateTicketProfit(Passenger $passenger): float
    private function calculateAdditionalTicketProfit(Passenger $passenger): float
    private function calculateReIssueProfit(Passenger $passenger): float
    private function calculateRefundProfit(Passenger $passenger): float
    private function calculateReIssueCost(Passenger $passenger): float
    private function calculateServiceCharge(Passenger $passenger): float

    // Fingerprint profit
    public function calculateFingerprintProfit(Fingerprint $fingerprint): float

    // Helper: get package ticket selling fare adjusted for passenger type
    private function getPackageTicketSellingFare(Passenger $passenger): float

    // Helper: check if ticket profit is effective
    private function isTicketProfitEffective(Passenger $passenger): bool

    // Helper: check if visa profit is effective
    private function isVisaProfitEffective(Passenger $passenger): bool

    // Get full breakdown for display
    public function getPassengerProfitBreakdown(Passenger $passenger): array

    // Bulk backfill
    public function backfillAllBookings(): void
}
```

---

## Part 5: Passenger Index UI Changes

### 5.1 File: `resources/views/bookings/index.blade.php`

**Current columns** (Passenger Index tab):
| ... | Package Value | Total Cost | Markup (Profit) | Due | ... |

**New columns:**
| ... | Package Value | Markup | Invoice Info | ... |

Changes:
1. **Package Value column** — Display `passenger.package_value` (already does this, no change needed)
2. **Remove "Total Cost" column** — Cost is no longer shown as a separate column
3. **Rename "Markup (Profit)" to "Markup"** — Display `passenger.profit` from the stored value. Format: green if positive, red if negative. Use the per-passenger profit formula from Part 2.
4. **Rename "Due" to "Invoice Info"** — Multi-line cell showing Total, Due, Discount (NO Paid):

### 5.2 Invoice Info Multi-Line Cell

Each cell renders three lines:

```
Total: SAR 5,000
Due: SAR 2,000
Discount: SAR 500
```

Implementation in Blade:
```blade
<td class="px-3 py-2 text-slate-700 text-xs leading-relaxed">
    @php $inv = $passenger->booking->invoice ?? null; @endphp
    @if($inv)
        Total: @currency($inv->total_amount, 2, $rate)<br>
        Due: @currency($inv->balance, 2, $rate)<br>
        Discount: @currency($passenger->booking->discount_amount ?? 0, 2, $rate)
    @else
        —
    @endif
</td>
```

No "Paid" line — only Total, Due, Discount.

### 5.3 Markup Column Tooltip with Sector Breakdown

In the Markup column, add an Alpine.js tooltip that shows the breakdown when hovering:

```
Visa Profit: +500
Ticket Profit: +1,200
Additional Ticket: +0
Re-Issue Profit: +100
Refund Profit: +0
Re-Issue Cost: -200
Service Charge: +300
─────────────
Total: +1,900
```

### 5.4 Backend Data for Passenger Index

The current `BookingsController@index` (or the relevant controller that serves the passenger index) needs to:
1. eager-load `passengers.profit`, `passengers.booking.invoice`, `passengers.booking.fingerprint`
2. Pass the stored `profit` value to the view instead of computing on-the-fly
3. Include a `profit_breakdown` JSON array per passenger for the tooltip

---

## Part 6: Profit/Loss Report Reform

### 6.1 File: `app/Http/Controllers/ProfitLossReportController.php`

**Current logic:** Uses `CostTrackingService` to compute costs on-the-fly, then `profit = totalAmount - totalCost`.

**New logic:** Use stored profit values from database, include breakdown per passenger.

```php
// Per Customer
$customers = $bookings->map(fn ($booking) => [
    'invoice_id' => $booking->invoice_id,
    'customer_name' => $booking->customer->name,
    'pax_qty' => $booking->pax_qty,
    'total_amount' => $booking->invoice->total_amount,
    'discount' => $booking->discount_amount,
    'fingerprint_profit' => $booking->fingerprint?->profit ?? 0,
    'passenger_profit_total' => $booking->passengers->sum('profit'),
    'total_profit' => $booking->profit,
]);

// Per Passenger — with full breakdown
$passengers = $bookings->flatMap(fn ($booking) =>
    $booking->passengers->map(function ($p) use ($booking) {
        $breakdown = app(ProfitCalculationService::class)->getPassengerProfitBreakdown($p);
        return [
            'invoice_id' => $booking->invoice_id,
            'customer_name' => $booking->customer->name,
            'passenger_name' => $p->first_name . ' ' . $p->last_name,
            'package_value' => $p->package_value,
            'total_profit' => $p->profit,
            'breakdown' => $breakdown,
        ];
    })
);
```

### 6.2 Per-Passenger Breakdown Popover/Modal

In the per-passenger tab of the profit/loss report, add an **on-click popover** (Alpine.js) on the Total Profit cell that shows the same sector breakdown as the passenger index tooltip:

| Sector | Amount |
|--------|--------|
| Visa Profit | +500.00 |
| Ticket Profit | +1,200.00 |
| Additional Ticket | +0.00 |
| Re-Issue Profit | +100.00 |
| Refund Profit | +0.00 |
| Re-Issue Cost | -200.00 |
| Service Charge | +300.00 |
| **Total** | **+1,900.00** |

Implementation approach:
- Each passenger row's profit cell gets `@click="openProfitBreakdown({{ $loop->index }})"`
- A single Alpine.js modal component at the bottom of the table, driven by `x-show` and a reactive `selectedPassengerBreakdown` object
- The breakdown data is passed as a JSON array from the controller (the `breakdown` field per passenger in the API response)
- Green for positive, red for negative values
- The same popover component is reused in both the passenger index tooltip and the profit/loss report modal

### 6.3 File: `resources/views/reports/profit-loss.blade.php`

**Per Customer tab columns:** Invoice ID, Customer, Mobile, PAX, Package Value, Fingerprint Profit, Passenger Profit Total, Discount, Total Profit

**Per Passenger tab columns:** Invoice ID, Customer, Passenger, Package Value, Total Profit (clickable for breakdown popover)

### 6.4 File: `resources/views/reports/profit-loss-print.blade.php`

Same column changes as the interactive report. Print version shows the breakdown inline instead of a popover (since popover won't work in print).

---

## Part 7: Backfill Command

**New file:** `app/Console/Commands/BackfillProfitData.php`

```php
class BackfillProfitData extends Command
{
    protected $signature = 'profit:backfill';
    protected $description = 'Backfill profit values for all existing bookings and passengers';

    public function handle()
    {
        $profitService = app(ProfitCalculationService::class);
        $bookings = Booking::with([
            'passengers.visaSubmission.cancelledSubmissions',
            'passengers.allIssuedTickets.ticketFare',
            'passengers.booking.package.ticketFare',
            'passengers.booking.package.ticketFareInbound',
            'passengers.booking.package.ticketFareOutbound',
            'fingerprint',
            'fingerprintCharge',
            'invoice',
        ])->get();

        $bar = $this->output->createProgressBar($bookings->count());
        foreach ($bookings as $booking) {
            $profitService->recalculateBookingProfit($booking);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info('Backfill complete.');
    }
}
```

Register in `app/Console/Kernel.php` or auto-discovered.

---

## Part 8: Implementation Order

| Step | Task | Files |
|------|------|-------|
| 1 | Create migrations (3 files) | `database/migrations/2026_08_24_000001_add_profit_to_bookings_table.php`, `..._passengers_...`, `..._fingerprints_...` |
| 2 | Create `ProfitCalculationService` | `app/Services/ProfitCalculationService.php` |
| 3 | Create observers (4 files) | `app/Observers/VisaSubmissionObserver.php`, `IssuedTicketObserver.php`, `ReIssuedTicketObserver.php`, `RefundedTicketObserver.php` |
| 4 | Register observers | `app/Providers/AppServiceProvider.php` |
| 5 | Update Booking & Passenger models | Add `profit` to `$fillable` and `$casts` |
| 6 | Update Fingerprint model | Add `profit` to `$fillable` and `$casts` |
| 7 | Create backfill command | `app/Console/Commands/BackfillProfitData.php` |
| 8 | Run backfill | `php artisan profit:backfill` |
| 9 | Update Passenger Index UI | `resources/views/bookings/index.blade.php` |
| 10 | Update Profit/Loss report controller | `app/Http/Controllers/ProfitLossReportController.php` |
| 11 | Update Profit/Loss report views | `resources/views/reports/profit-loss.blade.php`, `profit-loss-print.blade.php` |
| 12 | Run tests + Pint | `php artisan test`, `vendor/bin/pint` |

---

## Part 9: Key Edge Cases

1. **Passenger with `service_required = 'ticket_only'`** — Visa profit sector is skipped (treated as effective). Service charge only requires ticket_profit to be effective.
2. **Passenger with `service_required = 'visa_only'`** — Ticket profit sector is skipped (treated as effective). Service charge only requires visa_profit to be effective.
3. **No visa submission exists** — Visa profit = 0, not effective.
4. **No issued tickets** — Ticket profit = 0, not effective.
5. **Mixed issue_types** — Only `regular` and `pending_outbound` count for ticket_profit. Only `additional` counts for additional_ticket_profit.
6. **Cancelled visa** — If visa status changes from 'issued' back (cancellation), visa profit becomes 0 but service charge is NOT removed (see Part 2.7).
7. **Negative profit** — All profit fields accept negative values (decimal 14,6).
8. **Re-issued tickets with `payment_by = 'company'`** — Counted as cost (reissue_cost), not profit.
9. **Service required filtering** — When `service_required` is 'visa_only', ticket_profit is treated as effective (skipped). When 'ticket_only', visa_profit is treated as effective (skipped). When 'all', both must be effective.
