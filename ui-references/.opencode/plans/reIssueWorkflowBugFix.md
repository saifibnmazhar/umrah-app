# Re-Issue Workflow Bug Fix Plan

## Bug Summary

| # | Bug | Scope | Root Cause |
|---|-----|-------|------------|
| 1 | Total Customer Payment not cleared when Payment By changes from Customer | Frontend + Backend | `handleReIssuePaymentByChange()` doesn't clear `total_payment`; `TicketIssueController::update()` doesn't zero `total_customer_payment` for non-customer |
| 2 | Editing re_issued_ticket doesn't update invoice.balance | Backend | `TicketIssueController::update()` never calls `InvoiceService::updateTotals()` |
| 3 | Re-Issue edit form doesn't load refunded ticket historical data | Frontend | `populateReIssueEditForm()` hardcodes `refunded_ticket=false`, `refunded_net_fare=0` |
| 4 | Refund adjustment — total_customer_payment not added to invoice.balance | Backend | Double subtraction of `refund_adjustment_amount` in `remainingPayment` calculation |

---

## Bug 1: Total Customer Payment not cleared when Payment By changes

### Root Cause

`handleReIssuePaymentByChange()` in `bookings/index.blade.php:5887` clears `service_charge` to 0 but never clears `total_payment`. The hidden field still carries a stale value that gets submitted. In `re-issues/confirmation.blade.php:885`, `handlePaymentByChange()` hides the Total Payment field but doesn't clear it either.

On the backend, `TicketIssueController::update():257` saves `total_customer_payment` unconditionally via `array_key_exists` — unlike `ReIssueController::store():128` which correctly zeroes it when `payment_by !== 'customer'`.

### Files to Modify

1. `resources/views/bookings/index.blade.php` — `handleReIssuePaymentByChange()`
2. `resources/views/re-issues/confirmation.blade.php` — `handlePaymentByChange()`
3. `app/Http/Controllers/TicketIssueController.php` — `update()`

### Changes

#### `resources/views/bookings/index.blade.php` — `handleReIssuePaymentByChange()` (~line 5887)

After `this.reIssueForm.service_charge = 0;` and `this.reIssueForm.service_charge_bdt = '';`, add:

```javascript
this.reIssueForm.total_payment = 0;
this.reIssueForm.total_payment_bdt = '';
```

#### `resources/views/re-issues/confirmation.blade.php` — `handlePaymentByChange()` (~line 885)

In the `if (!isCustomer && !isRefunded)` block, after clearing `inputServiceCharge`, also clear:

```javascript
document.getElementById('inputTotalPayment').value = 0;
```

In the `else if (isRefunded && !isCustomer)` block, also clear:

```javascript
document.getElementById('inputTotalPayment').value = 0;
```

#### `app/Http/Controllers/TicketIssueController.php` — `update()` (~line 257)

Change the `total_customer_payment` assignment to match `ReIssueController::store()`:

```php
'total_customer_payment' => ($validated['payment_by'] ?? $latestRe->payment_by) === 'customer'
    ? (array_key_exists('total_customer_payment', $validated) ? (float) $validated['total_customer_payment'] : $latestRe->total_customer_payment)
    : 0,
```

---

## Bug 2: Editing re_issued_ticket doesn't update invoice.balance

### Root Cause

`TicketIssueController::update()` (lines 214–291) updates the `ReIssuedTicket` record and recalculates `total_cost`, but **never calls `InvoiceService::updateTotals()`** and never adjusts the invoice. The invoice balance becomes stale after any edit to a re-issued ticket's cost/payment fields.

### Files to Modify

1. `app/Http/Controllers/TicketIssueController.php` — `update()`

### Changes

#### `app/Http/Controllers/TicketIssueController.php` — `update()` (after line 265, inside `if ($issuedTicket->status === 're-issued')` block, before `DB::commit()`)

Add full invoice reconciliation logic:

```php
// --- Capture old state (before update) ---
$oldPaymentBy = $latestRe->payment_by;
$oldPaymentOption = $latestRe->payment_option?->value;
$oldTotalCustomerPayment = (float) $latestRe->total_customer_payment;
$oldRefundAdjustmentAmount = (float) $latestRe->refund_adjustment_amount;

// --- Determine new state ---
$newPaymentBy = $validated['payment_by'] ?? $latestRe->payment_by;
$newPaymentOption = $validated['payment_option'] ?? $latestRe->payment_option?->value;
$newTotalCustomerPayment = (float) ($validated['total_customer_payment'] ?? $latestRe->total_customer_payment);
$newRefundAdjustmentAmount = (float) $refundAdjustment;

// Force total_customer_payment to 0 when not customer
if ($newPaymentBy !== 'customer') {
    $newTotalCustomerPayment = 0;
}
if ($oldPaymentBy !== 'customer') {
    $oldTotalCustomerPayment = 0;
}

// --- Calculate invoice impact ---
// Use full totalCustomerPayment for both customer_payment and refund_adjustment (Bug 4 fix)
$oldImpact = $oldTotalCustomerPayment;
$newImpact = $newTotalCustomerPayment;
$impactDelta = $newImpact - $oldImpact;

// --- Handle refund_adjustment payment changes ---
$passenger = $latestRe->issuedTicket->passenger;
$booking = $latestRe->issuedTicket->passenger->booking;

// Reverse old refund_adjustment if it existed
if ($oldPaymentBy === 'customer' && $oldPaymentOption === 'refund_adjustment' && $oldRefundAdjustmentAmount > 0) {
    $oldPayment = Payment::where('re_iced_ticket_id', $latestRe->id)->first();
    if ($oldPayment) {
        $passenger->increaseRefundPayable($oldRefundAdjustmentAmount);
        $oldVoucher = Voucher::where('payment_id', $oldPayment->id)->first();
        if ($oldVoucher) {
            $oldVoucher->delete();
        }
        $oldPayment->delete();
    }
}

// Create new refund_adjustment if applicable
if ($newPaymentBy === 'customer' && $newPaymentOption === 'refund_adjustment' && $newRefundAdjustmentAmount > 0) {
    if ($newRefundAdjustmentAmount > (float) $passenger->refund_payable) {
        DB::rollBack();
        return response()->json(['message' => 'Refund adjustment amount exceeds the available refund payable.'], 422);
    }

    $passenger->decreaseRefundPayable($newRefundAdjustmentAmount);

    $transactionType = TransactionType::where('name', 'Ticket Refund - Re-issue')->first();

    Payment::create([
        'invoice_id' => $booking->invoice?->id,
        'booking_id' => $booking->id,
        'branch_id' => $booking->booking_branch_id,
        'user_id' => auth()->id(),
        'currency_rate_id' => $booking->currency_rate_id,
        'payment_date' => now(),
        'payment_method' => PaymentMethod::CASH,
        'amount' => $newRefundAdjustmentAmount,
        'bdt_amount' => 0,
        'passenger_id' => $passenger->id,
        're_issued_ticket_id' => $latestRe->id,
        'remarks' => $validated['remarks'] ?? null,
    ]);

    $payment = Payment::where('re_iced_ticket_id', $latestRe->id)->latest()->first();

    app(VoucherService::class)->createVoucher([
        'invoice_id' => $booking->invoice?->id,
        'booking_id' => $booking->id,
        'payment_id' => $payment?->id,
        'branch_id' => $booking->booking_branch_id,
        'user_id' => auth()->id(),
        'currency_rate_id' => $booking->currency_rate_id,
        'transaction_type_id' => $transactionType?->id,
        'payment_date' => now(),
        'payment_method' => PaymentMethod::CASH,
        'amount' => $newRefundAdjustmentAmount,
        'bdt_amount' => 0,
        'notes' => $validated['remarks'] ?? null,
    ]);
}

// --- Update invoice ---
if ($impactDelta != 0) {
    $invoice = $booking->invoice;
    if ($invoice) {
        app(InvoiceService::class)->updateTotals(
            $invoice,
            (float) $invoice->total_amount + $impactDelta,
            're_issue_edited'
        );
    }
}
```

**New imports needed at top of file:**
```php
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\TransactionType;
use App\Enums\PaymentMethod;
use App\Services\InvoiceService;
use App\Services\VoucherService;
```

---

## Bug 3: Re-Issue edit form doesn't load refunded ticket historical data

### Root Cause

`populateReIssueEditForm()` (line 5430) hardcodes `refunded_ticket = false` and `refunded_net_fare = 0`. The original ticket's refunded status and net fare are available at the call sites (`poit.status`, `poit.refunded_net_fare`, `lit.status`, `lit.refunded_net_fare`) but never passed to this function. This causes:
- `totalCost` calculation is wrong (missing `refunded_net_fare` from `rawCost`)
- Payment Option dropdown is hidden for non-customer payment_by when the source was refunded
- Refund Adjustment field is unavailable

### Files to Modify

1. `resources/views/bookings/index.blade.php` — `populateReIssueEditForm()` and its callers, Payment Option HTML visibility

### Changes

#### `resources/views/bookings/index.blade.php` — `populateReIssueEditForm()` (~line 5430)

Add two parameters: `issuedTicketStatus` and `refundedNetFare`:

```javascript
populateReIssueEditForm(re, issuedTicketId, row, issuedTicketStatus, refundedNetFare) {
    this.reIssueForm.issued_ticket_id = issuedTicketId || null;
    this.reIssueForm.passenger_id = row.id;
    this.reIssueForm.booking_id = row.booking_id;
    this.reIssueForm.reason_id = re.reason_id || '';
    this.reIssueForm.payment_by = re.payment_by || '';
    this.reIssueForm.payment_option = re.payment_option || 'customer_payment';
    this.reIssueForm.refund_adjustment_amount = re.refund_adjustment_amount || 0;
    this.reIssueForm.refund_adjustment_amount_bdt = '';
    this.reIssueForm.re_issue_charge = re.re_issue_charge || 0;
    this.reIssueForm.fare_difference = re.fare_difference || 0;
    this.reIssueForm.other_costs = re.other_costs || 0;
    this.reIssueForm.service_charge = re.service_charge || 0;
    this.reIssueForm.remarks = re.remarks || '';

    // Fixed: detect refunded ticket status instead of hardcoding false
    this.reIssueForm.refunded_ticket = (issuedTicketStatus === 'refunded');
    this.reIssueForm.refunded_net_fare = (issuedTicketStatus === 'refunded') ? (refundedNetFare || 0) : 0;

    const rate = window.__currencyRate || 0;
    this.reIssueForm.refunded_net_fare_bdt = this.reIssueForm.refunded_net_fare > 0 && rate > 0
        ? Math.round(this.reIssueForm.refunded_net_fare * rate)
        : '';

    this.reIssueForm.refund_payable = parseFloat(row.refund_payable || 0);
    this.recalcReIssueTotals();
},
```

#### Callers of `populateReIssueEditForm()`

Line 5103 — change from:
```javascript
this.populateReIssueEditForm(poit.re_issue_details, poit.id, row);
```
to:
```javascript
this.populateReIssueEditForm(poit.re_issue_details, poit.id, row, poit.status, poit.refunded_net_fare || 0);
```

Line 5123 — change from:
```javascript
this.populateReIssueEditForm(lit.latest_re_issued_ticket || {}, lit.id || null, row);
```
to:
```javascript
this.populateReIssueEditForm(lit.latest_re_issued_ticket || {}, lit.id || null, row, lit.status, lit.refunded_net_fare || 0);
```

#### Payment Option visibility in the edit form HTML (~line 2518)

Change:
```html
<div x-show="reIssueForm.payment_by === 'customer'">
```
to:
```html
<div x-show="reIssueForm.payment_by === 'customer' || reIssueForm.refunded_ticket">
```

#### Refund Adjustment field visibility in the edit form HTML (~line 2526)

Change:
```html
<div x-show="reIssueForm.payment_option === 'refund_adjustment' && reIssueForm.payment_by === 'customer'">
```
to:
```html
<div x-show="reIssueForm.payment_option === 'refund_adjustment' && (reIssueForm.payment_by === 'customer' || reIssueForm.refunded_ticket)">
```

#### `handleReIssuePaymentByChange()` (~line 5887)

When payment_by changes to `'customer'` on a refunded ticket, set payment_option to `'customer_payment'` and clear refund_adjustment_amount:

```javascript
handleReIssuePaymentByChange() {
    if (this.reIssueForm.payment_by !== 'customer') {
        this.reIssueForm.service_charge = 0;
        this.reIssueForm.service_charge_bdt = '';
        this.reIssueForm.payment_option = this.reIssueForm.refunded_ticket ? 'refund_adjustment' : 'customer_payment';
    } else {
        // When switching TO customer, default to customer_payment and clear refund adjustment
        this.reIssueForm.payment_option = 'customer_payment';
        this.reIssueForm.refund_adjustment_amount = 0;
        this.reIssueForm.refund_adjustment_amount_bdt = '';
    }
    this.reIssueForm.total_payment = 0;
    this.reIssueForm.total_payment_bdt = '';
    this.recalcReIssueTotals();
},
```

---

## Bug 4: Refund adjustment — total_customer_payment not added to invoice.balance

### Root Cause — Double subtraction

In both `ReIssueController::store()` and `TicketRequestController::processReIssue()`:

```
Line 144: totalCost = rawCost - refund_adjustment_amount     (subtract once)
Line 157: totalCustomerPayment = totalCost + service_charge
Line 205: remainingPayment = totalCustomerPayment - amount    (subtract TWICE)
```

Example: rawCost=2000, refund_adj=800, service=100
- totalCost = 2000 - 800 = 1200
- totalCustomerPayment = 1200 + 100 = 1300
- remainingPayment = 1300 - 800 = 500 (wrong — should be 1300)

The customer owes `totalCustomerPayment = 1300`, but only `500` is added to `invoice.total_amount`.

### Files to Modify

1. `app/Http/Controllers/ReIssueController.php` — `store()`
2. `app/Http/Controllers/TicketRequestController.php` — `processReIssue()`

### Changes

#### `app/Http/Controllers/ReIssueController.php` — `store()` (~lines 156–225)

Replace the entire invoice update block (lines 156–225) with simplified logic:

```php
if (($validated['payment_by'] ?? null) === 'customer' || $wasRefunded) {
    $totalCustomerPayment = $totalCost + (float) $validated['service_charge'];

    if ($validated['payment_option'] === 'refund_adjustment') {
        $amount = (float) $validated['refund_adjustment_amount'];

        if ($amount > $rawCost) {
            throw new \InvalidArgumentException('Refund adjustment amount exceeds the total customer payment.');
        }
        if ($amount > (float) $passenger->refund_payable) {
            throw new \InvalidArgumentException('Refund adjustment amount exceeds the available refund payable.');
        }

        if ($amount > 0) {
            $passenger->decreaseRefundPayable($amount);

            $transactionType = TransactionType::where('name', 'Ticket Refund - Re-issue')->first();

            Payment::create([
                'invoice_id' => $booking->invoice?->id,
                'booking_id' => $booking->id,
                'branch_id' => $booking->booking_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $booking->currency_rate_id,
                'payment_date' => now(),
                'payment_method' => PaymentMethod::CASH,
                'amount' => $amount,
                'bdt_amount' => 0,
                'passenger_id' => $passenger->id,
                're_issued_ticket_id' => $reIssuedTicket->id,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            app(VoucherService::class)->createVoucher([
                'invoice_id' => $booking->invoice?->id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'branch_id' => $booking->booking_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $booking->currency_rate_id,
                'transaction_type_id' => $transactionType?->id,
                'payment_date' => now(),
                'payment_method' => PaymentMethod::CASH,
                'amount' => $amount,
                'bdt_amount' => 0,
                'notes' => $validated['remarks'] ?? null,
            ]);
        }
    }

    // Add the FULL totalCustomerPayment to invoice (fixed: was remainingPayment = totalCustomerPayment - amount)
    if ($totalCustomerPayment > 0) {
        $invoice = $booking->invoice;
        if ($invoice) {
            app(InvoiceService::class)->updateTotals(
                $invoice,
                (float) $invoice->total_amount + $totalCustomerPayment,
                're_issue_cost_added'
            );
        }
    }
}
```

Key change: the `elseif ($totalCustomerPayment > 0)` and the `$remainingPayment = $totalCustomerPayment - $amount` lines are removed. A single invoice update block adds the full `totalCustomerPayment` regardless of payment_option.

#### `app/Http/Controllers/TicketRequestController.php` — `processReIssue()` (~lines 297–369)

Identical change: replace the split `if/elseif` invoice update block with the same simplified logic:

```php
if (($validated['payment_by'] ?? null) === 'customer' || $wasRefunded) {
    $totalCustomerPayment = $totalCost + (float) $validated['service_charge'];

    $passenger = $ticketRequest->passenger;
    $booking = $ticketRequest->booking;

    if ($validated['payment_option'] === 'refund_adjustment') {
        $amount = (float) $validated['refund_adjustment_amount'];

        if ($amount > $rawCost) {
            throw new \InvalidArgumentException('Refund adjustment amount exceeds the total customer payment.');
        }
        if ($amount > (float) $passenger->refund_payable) {
            throw new \InvalidArgumentException('Refund adjustment amount exceeds the available refund payable.');
        }

        if ($amount > 0) {
            $passenger->decreaseRefundPayable($amount);

            $transactionType = TransactionType::where('name', 'Ticket Refund - Re-issue')->first();

            Payment::create([
                'invoice_id' => $booking->invoice?->id,
                'booking_id' => $booking->id,
                'branch_id' => $ticketRequest->request_branch_id ?? $booking->booking_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $booking->currency_rate_id,
                'payment_date' => now(),
                'payment_method' => PaymentMethod::CASH,
                'amount' => $amount,
                'bdt_amount' => 0,
                'passenger_id' => $passenger->id,
                're_issued_ticket_id' => $reIssuedTicket->id,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            app(VoucherService::class)->createVoucher([
                'invoice_id' => $booking->invoice?->id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'branch_id' => $ticketRequest->request_branch_id ?? $booking->booking_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $booking->currency_rate_id,
                'transaction_type_id' => $transactionType?->id,
                'payment_date' => now(),
                'payment_method' => PaymentMethod::CASH,
                'amount' => $amount,
                'bdt_amount' => 0,
                'notes' => $validated['remarks'] ?? null,
            ]);
        }
    }

    // Add the FULL totalCustomerPayment to invoice
    if ($totalCustomerPayment > 0) {
        $invoice = $booking->invoice;
        if ($invoice) {
            app(InvoiceService::class)->updateTotals(
                $invoice,
                (float) $invoice->total_amount + $totalCustomerPayment,
                're_issue_cost_added'
            );
        }
    }
}
```

---

## Implementation Order

| Step | Bug | Files | Rationale |
|------|-----|-------|-----------|
| 1 | Bug 4 | `ReIssueController.php`, `TicketRequestController.php` | Simplest fix (remove double subtraction), unblocks Bug 2's old-impact calculation |
| 2 | Bug 1 | `bookings/index.blade.php`, `confirmation.blade.php`, `TicketIssueController.php` | Small scope, standalone |
| 3 | Bug 3 | `bookings/index.blade.php` | Frontend only, needed before Bug 2 so edit form data is correct |
| 4 | Bug 2 | `TicketIssueController.php` | Most complex, depends on Bug 4 fix for correct old-impact values |

---

## Testing Strategy

### Bug 1
- Open re-issue edit form, set payment_by to Customer, fill in service_charge and total_payment
- Change payment_by to Airline/Employee/Company
- Verify: total_payment field is hidden AND value is 0 in the submitted payload
- Verify: database `total_customer_payment = 0` for non-customer payment_by

### Bug 2
- Create a re-issue with customer_payment, verify invoice.total_amount increased
- Edit the re-issue, change re_issue_charge (increase/decrease)
- Verify: invoice.total_amount and invoice.balance are updated accordingly
- Test payment_by transitions: customer -> airline (invoice should decrease by old total_customer_payment)
- Test payment_option transitions: customer_payment -> refund_adjustment (verify Payment/Voucher created, refund_payable adjusted)

### Bug 3
- Re-issue a refunded ticket with refund_adjustment payment option
- Save and reopen the edit form
- Verify: Total Cost includes refunded_net_fare in the calculation
- Verify: Total Customer Payment reflects the correct value
- Verify: Payment Option dropdown is visible even when payment_by is not customer
- Change payment_by to customer, verify Refund Adjustment is cleared

### Bug 4
- Re-issue a refunded ticket with refund_adjustment (e.g., rawCost=2000, refund_adj=800, service=100)
- Verify: invoice.total_amount increases by 1300 (full totalCustomerPayment), not 500
- Verify: Payment record of 800 is created with re_issued_ticket_id
- Verify: Voucher record is created
- Verify: passenger.refund_payable decreased by 800
- Verify: customer_payment path still works identically

### General
- Run `php artisan test` after all changes
- Run `vendor/bin/pint` before committing
- Run `npm run build` to verify frontend assets compile
