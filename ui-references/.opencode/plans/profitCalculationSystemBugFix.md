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
