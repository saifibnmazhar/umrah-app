# Profit Calculation Bug Fixes — Full Implementation Plan

## Context

The profit calculation system stores per-passenger and per-booking profit in the database. Three bugs were identified during review:

1. **Bug A:** When a ticket's status changes from `issued` → `re-issued` or `refunded`, the previously captured ticket profit is erased because the observer recalculates profit from scratch and the formula conditions no longer include that ticket.

2. **Bug B:** `calculateReIssueCost()` manually sums `re_issue_charge + fare_difference + other_costs + net_fare`, which double-counts `net_fare` (already subtracted in Ticket Profit at issuance). A `total_cost` field should store the adjusted cost after refund payable adjustment.

3. **Bug C:** Refund adjustment fields are restricted to `payment_by === 'customer'` in the UI. Refund payable should be usable regardless of who pays. When `payment_by` is not `customer`, `payment_option` is forced to `refund_adjustment`.

---

## Bug A: Status Change Erases Captured Ticket Profit

### Problem

1. Ticket is issued → `IssuedTicketObserver::created()` fires → profit is captured (ticket_profit = package_fare − net_fare)
2. Ticket is re-issued → status changes to `re-issued` → `IssuedTicketObserver::updated()` fires
3. `wasChanged(['status', 'net_fare', 'issue_type'])` is true → triggers full recalculation
4. `isTicketProfitEffective()` checks `$t->status === 'issued'` → fails (status is now `re-issued`)
5. `calculateTicketProfit()` returns 0 → passenger profit is overwritten → ticket profit erased

### Fix

**File:** `app/Observers/IssuedTicketObserver.php` (line 25)

```php
// BEFORE:
if ($issuedTicket->wasChanged(['status', 'net_fare', 'issue_type'])) {

// AFTER:
if ($issuedTicket->wasChanged(['net_fare', 'issue_type'])) {
```

---

## Bug B: Re-Issue Cost Should Use `total_cost`

### Problem

`re_issued_tickets` table has no `total_cost` field. `calculateReIssueCost()` sums 4 individual components including `net_fare`, which double-counts the fare already subtracted in Ticket Profit at issuance.

### Fix

1. Add `total_cost` field to `re_issued_tickets`
2. `total_cost` = `re_issue_charge + fare_difference + other_costs + net_fare - refund_adjustment_amount`
3. `calculateReIssueCost()` sums `total_cost` WHERE `payment_by == 'company'`
4. Backfill existing records: `total_cost = re_issue_charge + fare_difference + other_costs`

### Files

| # | File | Change |
|---|------|--------|
| 1 | `database/migrations/2026_08_24_000004_add_total_cost_to_re_issued_tickets_table.php` | New migration |
| 2 | `app/Models/ReIssuedTicket.php` | Add `total_cost` to `$fillable` + `$casts` |
| 3 | `app/Services/ProfitCalculationService.php` | `calculateReIssueCost()` → `->sum('total_cost')` |
| 4 | `app/Http/Controllers/ReIssueController.php` | Compute + store `total_cost` after creation |
| 5 | `app/Http/Controllers/TicketRequestController.php` | Same as ReIssueController |

### Migration

```php
Schema::table('re_issued_tickets', function (Blueprint $table) {
    $table->decimal('total_cost', 14, 6)->default(0)->after('service_charge');
});
```

### ReIssuedTicket Model

```php
// Add to $fillable:
'total_cost',

// Add to $casts:
'total_cost' => 'decimal:6',
```

### ReIssueController — After Creating ReIssuedTicket

```php
$rawCost = (float) $reIssue->re_issue_charge
    + (float) $reIssue->fare_difference
    + (float) $reIssue->other_costs
    + (float) $reIssue->net_fare;

$totalCost = $rawCost - $reIssue->refund_adjustment_amount;
$reIssue->update(['total_cost' => round($totalCost, 6)]);

if ($reIssue->refund_adjustment_amount > 0) {
    $passenger->decreaseRefundPayable($reIssue->refund_adjustment_amount);
}
```

### ProfitCalculationService

```php
// BEFORE (lines 161-168):
->sum(fn ($r) => (float) ($r->re_issue_charge ?? 0)
    + (float) ($r->fare_difference ?? 0)
    + (float) ($r->other_costs ?? 0)
    + (float) ($r->net_fare ?? 0));

// AFTER:
->sum('total_cost');
```

---

## Bug C: Refund Adjustment UI — Any `payment_by`

### Rule

| `payment_by` | `payment_option` behavior |
|---|---|
| `customer` | User chooses: `customer_payment` or `refund_adjustment` |
| `company` / `employee` / `airline` | Forced to `refund_adjustment` — dropdown disabled |

When `payment_by` is not `customer`, `refund_adjustment_amount` is always shown and required. `service_charge` is hidden and reset to 0.

### Visibility Summary

| Field | `payment_by = customer` | `payment_by = company/employee/airline` |
|-------|------------------------|----------------------------------------|
| Payment Option | User chooses (enabled) | Forced to `refund_adjustment` (disabled) |
| Refund Payable | Shown if `refund_adjustment` selected | Always shown |
| Refund Adjustment (SAR) | Shown if `refund_adjustment` selected | Always shown |
| Refund Adjustment (BDT) | Shown if `refund_adjustment` selected | Always shown |
| Service Charge | Shown | Hidden, reset to 0 |
| Total Customer Payment | Shown | Hidden |

### Files

| # | File | Type | Changes |
|---|------|------|---------|
| 1 | `resources/views/bookings/index.blade.php` | Alpine.js | Remove `payment_by === 'customer'` from visibility; force `payment_option` to `refund_adjustment` when not customer; disable dropdown |
| 2 | `resources/views/re-issues/confirmation.blade.php` | Vanilla JS | Same — force `payment_option`, disable dropdown, update handlers |
| 3 | `app/Http/Controllers/ReIssueController.php` | Backend | Remove `payment_by === 'customer'` from validation `requiredIf` |
| 4 | `app/Http/Controllers/TicketRequestController.php` | Backend | Same as ReIssueController |

### Form 1: `bookings/index.blade.php` (Alpine.js)

**Payment Option select (line 2670):**

```html
<select x-model="reIssueForm.payment_option"
    :disabled="reIssueForm.payment_by !== 'customer'"
    ...>
    <option value="customer_payment">Customer Payment</option>
    <option value="refund_adjustment">Refund Adjustment</option>
</select>
```

**Refund Payable + Adjustment visibility (line 2678):**

```html
<!-- BEFORE: -->
<div x-show="reIssueForm.payment_by === 'customer' && reIssueForm.payment_option === 'refund_adjustment'">

<!-- AFTER: -->
<div x-show="reIssueForm.payment_option === 'refund_adjustment'">
```

**`handleReIssuePaymentByChange()` (lines 5521-5529):**

```javascript
// BEFORE:
handleReIssuePaymentByChange() {
    if (this.reIssueForm.payment_by !== 'customer') {
        this.reIssueForm.service_charge = 0;
        this.reIssueForm.service_charge_bdt = '';
        this.reIssueForm.payment_option = 'customer_payment';
        this.reIssueForm.refund_adjustment_amount = 0;
        this.reIssueForm.refund_adjustment_amount_bdt = '';
    }
    this.recalcReIssueTotals();
}

// AFTER:
handleReIssuePaymentByChange() {
    if (this.reIssueForm.payment_by !== 'customer') {
        this.reIssueForm.service_charge = 0;
        this.reIssueForm.service_charge_bdt = '';
        this.reIssueForm.payment_option = 'refund_adjustment';
    }
    this.recalcReIssueTotals();
}
```

### Form 2: `re-issues/confirmation.blade.php` (Vanilla JS)

**`handlePaymentByChange()` (lines 858-874):**

```javascript
// BEFORE:
function handlePaymentByChange() {
    var isCustomer = document.getElementById('inputPaymentBy').value === 'customer';
    var isAdjustment = document.getElementById('inputPaymentOption').value === 'refund_adjustment';
    document.getElementById('fieldServiceCharge').classList.toggle('hidden', !isCustomer);
    document.getElementById('fieldTotalPayment').classList.toggle('hidden', !isCustomer);
    document.getElementById('fieldPaymentOption').classList.toggle('hidden', !isCustomer);
    document.getElementById('fieldRefundAdjustment').classList.toggle('hidden', !(isCustomer && isAdjustment));
    if (!isCustomer) {
        document.getElementById('inputServiceCharge').value = '';
        document.getElementById('inputServiceChargeBdt').value = '';
        document.getElementById('inputPaymentOption').value = 'customer_payment';
        document.getElementById('inputRefundAdjustment').value = '';
        // ... clears BDT mirrors
        updateTotals();
    }
}

// AFTER:
function handlePaymentByChange() {
    var isCustomer = document.getElementById('inputPaymentBy').value === 'customer';
    var paymentOptionEl = document.getElementById('inputPaymentOption');

    if (!isCustomer) {
        paymentOptionEl.value = 'refund_adjustment';
        paymentOptionEl.disabled = true;
        document.getElementById('inputServiceCharge').value = '';
        document.getElementById('inputServiceChargeBdt').value = '';
    } else {
        paymentOptionEl.disabled = false;
    }

    document.getElementById('fieldServiceCharge').classList.toggle('hidden', !isCustomer);
    document.getElementById('fieldTotalPayment').classList.toggle('hidden', !isCustomer);
    document.getElementById('fieldRefundAdjustment').classList.toggle('hidden', false);
    updateTotals();
}
```

**`handlePaymentOptionChange()` (line 880):**

```javascript
// BEFORE:
document.getElementById('fieldRefundAdjustment').classList.toggle('hidden', !(isCustomer && isAdjustment));

// AFTER:
document.getElementById('fieldRefundAdjustment').classList.toggle('hidden', !isAdjustment);
```

### Backend Validation

**ReIssueController.php (lines 55-62):**

```php
// BEFORE:
'refund_adjustment_amount' => [
    'nullable', 'numeric', 'min:0',
    Rule::requiredIf(fn () => ($input['payment_by'] ?? '') === 'customer' && ($input['payment_option'] ?? '') === 'refund_adjustment'),
],

// AFTER:
'refund_adjustment_amount' => [
    'nullable', 'numeric', 'min:0',
    Rule::requiredIf(fn () => ($input['payment_option'] ?? '') === 'refund_adjustment'),
],
```

Same change in `TicketRequestController.php`.

---

## Backfill Updates

### Existing Re-Issued Tickets

For records with no `total_cost`:
```
total_cost = re_issue_charge + fare_difference + other_costs
```
(No refund adjustment for historical data, no net_fare since it's already in ticket profit)

**Update backfill command** (`app/Console/Commands/BackfillProfitData.php`) to include:

```php
// After recalculating booking profit, also backfill re-issue total_cost
ReIssuedTicket::where('total_cost', 0)->each(function ($reIssue) {
    $rawCost = (float) $reIssue->re_issue_charge
        + (float) $reIssue->fare_difference
        + (float) $reIssue->other_costs;
    $reIssue->update(['total_cost' => round($rawCost, 6)]);
});
```

---

## Implementation Order

| Step | Task | Files |
|------|------|-------|
| 1 | Bug A: Remove `status` from `wasChanged` | `app/Observers/IssuedTicketObserver.php` |
| 2 | Bug B: Add `total_cost` migration | `database/migrations/2026_08_24_000004_...php` |
| 3 | Bug B: Update ReIssuedTicket model | `app/Models/ReIssuedTicket.php` |
| 4 | Bug B + C: Update ReIssueController | `app/Http/Controllers/ReIssueController.php` |
| 5 | Bug B + C: Update TicketRequestController | `app/Http/Controllers/TicketRequestController.php` |
| 6 | Bug B: Update ProfitCalculationService | `app/Services/ProfitCalculationService.php` |
| 7 | Bug C: Update bookings/index.blade.php | `resources/views/bookings/index.blade.php` |
| 8 | Bug C: Update re-issues/confirmation.blade.php | `resources/views/re-issues/confirmation.blade.php` |
| 9 | Backfill: Update BackfillProfitData command | `app/Console/Commands/BackfillProfitData.php` |
| 10 | Run: `php artisan migrate` | — |
| 11 | Run: `php artisan profit:backfill` | — |
| 12 | Run: `php artisan test` | — |
| 13 | Run: `vendor/bin/pint` | — |

---

## Verification Checklist

- [ ] Issue a ticket → profit captured correctly (ticket_profit present)
- [ ] Re-issue the ticket → passenger profit unchanged (ticket_profit still counted)
- [ ] Re-issue cost shows `total_cost` in breakdown (no net_fare double-count)
- [ ] Refund adjustment available when payment_by = company/employee/airline
- [ ] When payment_by = company/employee/airline → payment_option forced to refund_adjustment (disabled)
- [ ] Refund adjustment reduces passenger's `refund_payable`
- [ ] Service charge only shown when payment_by = customer
- [ ] If payment_by ≠ customer AND passenger has no refund_payable → validation error
- [ ] Multiple re-issues with payment_by = company → all `total_cost` values summed
- [ ] Backfill correctly computes `total_cost` for existing re-issued tickets
- [ ] All tests pass
- [ ] Pint formatting clean

---

## Appendix: Fingerprint Profit Effectiveness & Booking Discount Guard

### Context

Two additional rules were identified for the profit calculation system:

1. **Fingerprint profit** should only be effective when the booking's fingerprint location is `HOME` **and** the fingerprint cost has been updated from its default zero value.

2. **Booking-level discount** should only be deducted when **all** passengers in the booking have effective profits (i.e., their visa, ticket, and service charge profits are all effective). A passenger's individual profit calculation logic remains unchanged.

---

## Fix D: Fingerprint Profit — Location & Cost Guard

### Problem

`calculateFingerprintProfit()` always computes `charge - cost` regardless of fingerprint location or whether cost was explicitly set. The system requires fingerprint profit to be captured only when:
- The booking's `fingerprint_location` is `HOME`
- The fingerprint `cost` has been updated from zero (the default)

When `fingerprint_location` is `OFFICE` or `cost` is `0`/`null`, fingerprint profit should be `0.0`.

### Fix

**File:** `app/Services/ProfitCalculationService.php`

1. Add `use App\Enums\FingerprintLocation;` import at top of file.

2. Update `calculateFingerprintProfit()` (lines 58–63):

```php
// BEFORE:
public function calculateFingerprintProfit(Fingerprint $fingerprint): float
{
    $charge = (float) ($fingerprint->booking->fingerprintCharge?->fingerprint_charge ?? 0);

    return $charge - (float) ($fingerprint->cost ?? 0);
}

// AFTER:
public function calculateFingerprintProfit(Fingerprint $fingerprint): float
{
    $location = $fingerprint->booking->fingerprint_location;
    $cost = (float) ($fingerprint->cost ?? 0);

    if ($location !== FingerprintLocation::HOME || $cost <= 0) {
        return 0.0;
    }

    $charge = (float) ($fingerprint->booking->fingerprintCharge?->fingerprint_charge ?? 0);

    return $charge - $cost;
}
```

### Behavioral Change

| Scenario | Before | After |
|---|---|---|
| Location = `OFFICE`, any cost | `charge - cost` | `0.0` |
| Location = `HOME`, cost = `0`/`null` | `charge - 0` = `charge` | `0.0` |
| Location = `HOME`, cost > 0 | `charge - cost` | `charge - cost` (unchanged) |

---

## Fix E: Booking Profit — Effective Passenger Filter & Discount Guard

### Problem

`recalculateBookingProfit()` currently:
1. Sums **all** passenger profits unconditionally
2. Deducts `discount_amount` unconditionally

Per the business rules:
1. A passenger's profit should only count toward the booking total when that passenger's visa, ticket, **and** service charge profits are all effective
2. The `discount_amount` should only be deducted when **every** passenger in the booking has effective profits

### Fix

**File:** `app/Services/ProfitCalculationService.php`

1. Add new private method after `isTicketProfitEffective()` (~line 207):

```php
private function isPassengerProfitEffective(Passenger $passenger): bool
{
    return $this->isVisaProfitEffective($passenger)
        && $this->isTicketProfitEffective($passenger);
}
```

> **Note:** `calculateServiceCharge()` already returns `0.0` when either `isVisaProfitEffective()` or `isTicketProfitEffective()` is false (line 181). So checking both is sufficient — service charge is implicitly effective when both are true.

2. Update `recalculateBookingProfit()` (lines 30–56):

```php
// BEFORE:
public function recalculateBookingProfit(Booking $booking): float
{
    $booking->loadMissing('passengers', 'fingerprint', 'fingerprintCharge');

    foreach ($booking->passengers as $passenger) {
        $this->recalculatePassengerProfit($passenger);
    }

    $fingerprintProfit = 0;

    if ($booking->fingerprint) {
        $fingerprintProfit = $this->calculateFingerprintProfit($booking->fingerprint);

        $booking->fingerprint->profit = round($fingerprintProfit, 6);
        $booking->fingerprint->saveQuietly();
    }

    $booking->profit = round(
        (float) $booking->passengers()->sum('profit')
            + $fingerprintProfit
            - (float) ($booking->discount_amount ?? 0),
        6
    );
    $booking->saveQuietly();

    return (float) $booking->profit;
}

// AFTER:
public function recalculateBookingProfit(Booking $booking): float
{
    $booking->loadMissing('passengers', 'fingerprint', 'fingerprintCharge');

    foreach ($booking->passengers as $passenger) {
        $this->recalculatePassengerProfit($passenger);
    }

    $fingerprintProfit = 0;

    if ($booking->fingerprint) {
        $fingerprintProfit = $this->calculateFingerprintProfit($booking->fingerprint);

        $booking->fingerprint->profit = round($fingerprintProfit, 6);
        $booking->fingerprint->saveQuietly();
    }

    $effectivePassengerProfit = 0;
    $allPassengersEffective = $booking->passengers->isNotEmpty();

    foreach ($booking->passengers as $passenger) {
        if ($this->isPassengerProfitEffective($passenger)) {
            $effectivePassengerProfit += (float) $passenger->profit;
        } else {
            $allPassengersEffective = false;
        }
    }

    $discount = $allPassengersEffective ? (float) ($booking->discount_amount ?? 0) : 0;

    $booking->profit = round(
        $effectivePassengerProfit + $fingerprintProfit - $discount,
        6
    );
    $booking->saveQuietly();

    return (float) $booking->profit;
}
```

### Behavioral Change

| Scenario | Before | After |
|---|---|---|
| Passenger visa not issued, ticket issued | Profit summed | Passenger excluded, `allPassengersEffective = false` |
| Passenger visa issued, no tickets | Profit summed | Passenger excluded, `allPassengersEffective = false` |
| `TICKET_ONLY` passenger, no tickets issued | Profit summed | Passenger excluded |
| `VISA_ONLY` passenger, visa not issued | Profit summed | Passenger excluded |
| Some passengers effective, some not | Discount deducted | Discount = `0` |
| All passengers effective | Discount deducted | Discount deducted (unchanged) |
| No passengers in booking | Discount deducted | Discount = `0` (changed — was `discount_amount`) |

---

## Backfill: Reset Mistakenly Stored Profits

### Problem

Existing bookings in the database have fingerprint profits calculated without the location/cost guard (e.g., `OFFICE` fingerprints with non-zero profit) and booking profits summed without the effective passenger filter (discount deducted even when some passengers are not effective). These need to be recalculated.

### Fix

**No new command needed.** The existing `profit:backfill` command (`app/Console/Commands/BackfillProfitData.php`) already:
1. Loads all bookings with eager-loaded relations (lines 30–40)
2. Calls `$profitService->recalculateBookingProfit($booking)` for each (line 43)
3. Also backfills `total_cost` for re-issued tickets (lines 52–68)

Since `recalculateBookingProfit()` and `calculateFingerprintProfit()` will contain the new logic after Fix D and Fix E are applied, re-running the command will automatically:

- Reset fingerprint profit to `0.0` for all `OFFICE` fingerprints and `HOME` fingerprints with `cost = 0/null`
- Recalculate fingerprint profit correctly for `HOME` fingerprints with `cost > 0`
- Exclude non-effective passengers from the booking profit sum
- Only deduct discount when all passengers are effective
- Backfill any remaining `total_cost = 0` re-issued tickets

### Command

```bash
php artisan profit:backfill
```

### Verification After Backfill

```sql
-- Fingerprint profits that should now be 0 (OFFICE location or zero cost)
SELECT f.id, f.profit, b.fingerprint_location, f.cost
FROM fingerprints f
JOIN bookings b ON b.id = f.booking_id
WHERE f.profit != 0
  AND (b.fingerprint_location != 'home' OR f.cost = 0 OR f.cost IS NULL);

-- Should return 0 rows

-- Bookings where discount was deducted but not all passengers are effective
SELECT b.id, b.profit, b.discount_amount
FROM bookings b
WHERE b.discount_amount > 0 AND b.discount_amount IS NOT NULL
  AND b.id IN (
    SELECT booking_id FROM passengers
    WHERE id NOT IN (
      SELECT p.id FROM passengers p
      JOIN visa_submissions vs ON vs.id = p.visa_submission_id
      WHERE vs.status = 'issued'
    )
);

-- Should return 0 rows (or only bookings where all passengers have issued visas)
```

---

## Files Changed

| # | File | Change |
|---|------|--------|
| 1 | `app/Services/ProfitCalculationService.php` | Add `FingerprintLocation` import; update `calculateFingerprintProfit()`; add `isPassengerProfitEffective()`; update `recalculateBookingProfit()` |

No changes to `BackfillProfitData.php` — it already calls `recalculateBookingProfit()` which will contain the updated logic.

---

## Implementation Order

| Step | Task | Files |
|------|------|-------|
| 1 | Fix D: Update `calculateFingerprintProfit()` with location/cost guard | `app/Services/ProfitCalculationService.php` |
| 2 | Fix E: Add `isPassengerProfitEffective()` method | `app/Services/ProfitCalculationService.php` |
| 3 | Fix E: Update `recalculateBookingProfit()` with effective passenger filter & discount guard | `app/Services/ProfitCalculationService.php` |
| 4 | Backfill: Run `php artisan profit:backfill` to reset all booking & fingerprint profits | — |
| 5 | Verify: Run SQL checks to confirm no mistakenly stored profits remain | — |
| 6 | Run: `php artisan test` | — |
| 7 | Run: `vendor/bin/pint` | — |

---

## Verification Checklist

- [ ] Fingerprint location = `HOME`, cost = `0` → fingerprint profit = `0`
- [ ] Fingerprint location = `HOME`, cost > 0 → fingerprint profit = `charge - cost`
- [ ] Fingerprint location = `OFFICE`, cost > 0 → fingerprint profit = `0`
- [ ] Fingerprint location = `HOME`, cost = `null` → fingerprint profit = `0`
- [ ] Passenger visa not issued → passenger excluded from booking profit sum
- [ ] Passenger ticket not issued → passenger excluded from booking profit sum
- [ ] `TICKET_ONLY` passenger, tickets issued → passenger included
- [ ] `VISA_ONLY` passenger, visa issued → passenger included
- [ ] All passengers effective → discount deducted
- [ ] Any passenger not effective → discount = `0`
- [ ] No passengers → discount = `0`
- [ ] Backfill: no `OFFICE` fingerprints with non-zero profit
- [ ] Backfill: no bookings with discount deducted when passengers not all effective
- [ ] Existing 14 PHPUnit tests still pass
- [ ] Pint formatting clean
