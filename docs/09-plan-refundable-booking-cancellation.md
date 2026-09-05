# Plan: Integrate Total Passenger Refundable Amount into Booking Cancellation

## Problem

The booking-level cancellation refund is currently calculated as:

```
Refund = Total Paid - Total Cost - Service Charge
```

This formula **ignores** the `refund_payable` amounts on each passenger (money owed back to the customer from ticket refunds). As a result, when a whole booking is cancelled, the money already owed back to passengers is not included in the refund calculation.

We will:

1. Include the sum of all (active) passengers' `refund_payable` in the booking-level cancellation refund calculation.
2. Display the aggregate "total refundable amount" in both the initiate (modal) and confirm pages.
3. Keep the **confirm-page refund amount editable with a minimum floor** — the existing `updateRefundAmount` endpoint gets a server-side minimum check so a Branch Manager can still override the final refund payout, but never below the money already owed to passengers.
4. Snapshot `total_passenger_refundable` on `CancelledBooking` at initiate time and use the snapshot (not a live recompute) everywhere downstream.
5. Restore `refund_payable` if a PROCESSING cancellation is reverted.

## Scope

- **Display:** Aggregate total only (no per-passenger breakdown) in both the booking cancellation modal and the confirm page.
- **Calculation:** Include the passengers' total refundable amount in the booking refund formula, clamped to a minimum of that same amount.
- **Editable:** The final refund amount on the confirm page stays editable, with `:min = total_passenger_refundable` in the UI plus a 422 guard server-side.
- **Audit:** `cancelled_bookings.total_passenger_refundable` snapshot for reporting.
- **Revert:** `revertCancellation()` restores `refund_payable` from ticket refund data.

---

## Changes

### 1. `app/Models/Booking.php` — helper method

Add a method that returns the sum of `refund_payable` across all active (non-cancelled) passengers:

```php
public function getTotalPassengerRefundable(): float
{
    return (float) $this->passengers()
        ->where('is_cancelled', false)
        ->sum('refund_payable');
}
```

### 2. `app/Services/CancellationService.php`

**`initiateCancellation()`** — currently:

```php
$refundAmount = $totalPaid - $totalCost - ($serviceCharge ?? 0);
```

becomes:

```php
$totalPassengerRefundable = $booking->getTotalPassengerRefundable();
$refundAmount = max(
    $totalPassengerRefundable,
    $totalPaid - $totalCost - ($serviceCharge ?? 0) + $totalPassengerRefundable
);
```

Store the snapshot on the `CancelledBooking` record for audit/reporting and as the minimum floor (requires a new column — see item 6):

```php
'total_passenger_refundable' => $totalPassengerRefundable,
```

**`getCostBreakdown()`** — currently:

```php
'potential_refund' => $invoice->paid_amount - $costSummary['total_cost'],
```

becomes:

```php
'total_passenger_refundable' => $booking->getTotalPassengerRefundable(),
'potential_refund' => $invoice->paid_amount - $costSummary['total_cost'] + $booking->getTotalPassengerRefundable(),
```

**`confirmCancellation()`** — after creating the "Customer Refund" payment + voucher, zero `refund_payable` on all active passengers with a single query (avoids N+1 saves):

```php
$booking->passengers()
    ->where('is_cancelled', false)
    ->where('refund_payable', '>', 0)
    ->update(['refund_payable' => 0]);
```

The invoice balance is already set to 0 in this method and stays unchanged.

**`revertCancellation()`** — restore `refund_payable` on each active passenger from ticket refund data before un-cancelling (idempotent safety; revert only runs on status = PROCESSING, i.e. before confirm zeroes anything):

```php
DB::transaction(function () use ($cancelledBooking) {
    $booking = $cancelledBooking->booking;
    $invoice = $cancelledBooking->invoice;

    // Restore refund_payable on each active passenger from ticket refund data
    $booking->passengers()
        ->where('is_cancelled', false)
        ->each(fn ($passenger) => $passenger->update([
            'refund_payable' => $passenger->verifyRefundPayable(),
        ]));

    $booking->update(['is_cancelled' => false]);

    $invoiceService = app(InvoiceService::class);
    $invoice->refresh();
    $invoiceService->updatePaymentStatus($invoice);

    $cancelledBooking->delete();
});
```

Note: if a cancellation is already confirmed (status = CANCELLED), it cannot be reverted via this path — the refund payments/vouchers already exist and `refund_payable` is legitimately zeroed.

### 3. `app/Http/Controllers/BookingCancellationViewController.php`

In `initiate()`, add `total_passenger_refundable` to the JSON response and fix inline `potential_refund`:

```php
$totalPassengerRefundable = (float) $booking->getTotalPassengerRefundable();
// ...
'total_passenger_refundable' => $totalPassengerRefundable,
'potential_refund' => (float) (($invoice?->paid_amount ?? 0) - $costSummary['total_cost']) + $totalPassengerRefundable,
```

### 4. `resources/views/bookings/index.blade.php` — initiate modal

- Add Alpine.js state: `cancelTotalPassengerRefundable: 0`.
- In `openCancelModal()`, store the new field from the API response: `this.cancelTotalPassengerRefundable = data.total_passenger_refundable;`.
- Update the `computedRefundAmount` getter:

```js
get computedRefundAmount() {
    const paid = this.cancelTotalPaid;
    const cost = this.cancelCosts.total_cost;
    const charge = parseFloat(this.cancelServiceCharge) || 0;
    const refundable = parseFloat(this.cancelTotalPassengerRefundable) || 0;
    return (paid - cost - charge + refundable).toFixed(2);
},
```

- Add a "Total Passenger Refundable" row to the Financial Summary in the Cancel Booking modal, with a tooltip/hint explaining it comes from passenger ticket refunds.
- Update the hint text near line 3015 to: `Refund = Total Paid − Total Cost − Service Charge + Total Passenger Refundable`.

### 5. `resources/views/cancelled-bookings/confirm.blade.php` — confirm page

Use the **snapshot** (`$cancelledBooking->total_passenger_refundable`), never a live `booking->getTotalPassengerRefundable()` call — the live value drops to 0 once the refund is confirmed.

Add Alpine.js data:

```php
totalPassengerRefundable: '{{ $cancelledBooking->total_passenger_refundable ?? 0 }}',
```

Fix the `effectiveServiceCharge` getter (old version computed `paid - cost - refund`, the inverse of the old formula; new formula is `refund = paid - cost - charge + passengerRefundable`):

```js
get effectiveServiceCharge() {
    const paid = parseFloat(this.totalPaid) || 0;
    const cost = parseFloat(this.totalCost) || 0;
    const refund = parseFloat(this.refundAmount) || 0;
    const passengerRefundable = parseFloat(this.totalPassengerRefundable) || 0;
    const result = paid - cost + passengerRefundable - refund;
    return result > 0 ? result.toFixed(6) : '0.000000';
},
```

Add a "Total Passenger Refundable" row to the Financial Summary (between Service Charge Deduction and Refund Amount):

```html
<div class="flex justify-between text-sm">
    <span class="text-slate-500">Total Passenger Refundable</span>
    <span class="font-medium text-slate-800" x-text="$currency(totalPassengerRefundable, 2)"></span>
</div>
```

Refund input stays editable but clamped:

```html
<input type="number" x-model="refundAmount" step="0.000001"
    :min="totalPassengerRefundable"
    :max="originalRefundAmount" ...>
```

Update the BDT input similarly with a computed min in BDT.

Update the toast warning to fire if the user tries to type below the minimum:

```js
if (val < parseFloat(this.totalPassengerRefundable)) {
    refundAmount = this.totalPassengerRefundable;
    showToast('Refund cannot be less than total passenger refundable', 'warning');
    return;
}
```

Update the default-value hint text (line ~208) to:

```
Default: {{ $cancelledBooking->refund_amount }} SAR (Total Paid − Total Cost − Service Charge + Total Passenger Refundable)
```

No change to the auto-save watcher mechanics — only the min guard is added.

### 6. New migration — `cancelled_bookings.total_passenger_refundable`

Add a new migration adding a `total_passenger_refundable` decimal column (default 0) to the `cancelled_bookings` table. This stores the snapshot of the passengers' total refundable amount at cancellation time for audit, display, and minimum-floor enforcement.

```php
Schema::table('cancelled_bookings', function (Blueprint $table) {
    $table->decimal('total_passenger_refundable', 14, 6)->default(0)->after('refund_amount');
});
```

### 7. `app/Models/CancelledBooking.php`

Add `total_passenger_refundable` to `$fillable` and cast it as `decimal:6`.

```php
protected $fillable = [
    // ...
    'total_passenger_refundable',
    // ...
];

protected $casts = [
    // ...
    'total_passenger_refundable' => 'decimal:6',
];
```

### 8. `app/Http/Controllers/BookingCancellationActionController.php` — minimum enforcement

**`updateRefundAmount()`** — reject amounts below the snapshot:

```php
if ($validated['refund_amount'] < (float) $cancelledBooking->total_passenger_refundable) {
    return response()->json([
        'success' => false,
        'message' => 'Refund amount cannot be less than the total passenger refundable amount (' . $cancelledBooking->total_passenger_refundable . ').',
    ], 422);
}
```

**`confirmSubmit()`** — apply the same minimum check (defense in depth; the form posts `refund_amount` directly and could bypass the auto-save endpoint).

---

## Flow Summary

| Step | Behavior |
|------|----------|
| **Initiate** | `refundable = max(passengerRefundable, paid − costs − serviceCharge + Σ passenger.refund_payable)`; snapshot stored on `cancelled_bookings` |
| **Modal** | Displays the total passenger refundable amount alongside the computed refund |
| **Confirm page** | Displays the snapshot total passenger refundable amount; refund amount editable with `:min = snapshot` |
| **Update refund** | 422 if `refund_amount < total_passenger_refundable` |
| **Confirm** | Refund payment = editable amount (≥ floor); `refund_payable` bulk-zeroed on each active passenger; invoice balance set to 0 |
| **Revert (PROCESSING only)** | `refund_payable` restored from `verifyRefundPayable()`; booking un-cancelled; `cancelled_booking` row deleted |
| **Report/audit** | `total_passenger_refundable` snapshot stored on `cancelled_bookings` |

---

## Testing (TDD-First)

1. `Booking::getTotalPassengerRefundable()` returns the correct sum of active passengers' `refund_payable`.
2. `CancellationService::initiateCancellation()` includes the passengers' total refundable amount in the computed refund.
3. `CancellationService::confirmCancellation()` zeroes `refund_payable` on each active passenger (single query, no N+1).
4. The initiate endpoint (`GET /bookings/{booking}/cancellation/initiate`) response includes `total_passenger_refundable` and corrected `potential_refund`.
5. The confirmation view renders the "Total Passenger Refundable" row from the snapshot.
6. The confirm-page refund amount remains editable (existing `PUT /api/cancelled-bookings/{id}/refund-amount` behavior preserved).
7. `revertCancellation()` restores each active passenger's `refund_payable` to their computed value from `verifyRefundPayable()`.
8. `updateRefundAmount()` rejects amounts below `total_passenger_refundable` with a 422 response.
9. `initiateCancellation()` clamps the refund to a minimum of `total_passenger_refundable`.
10. The confirm page's `effectiveServiceCharge` getter accounts for `totalPassengerRefundable`.

---

## Open Questions / Decisions

- **Editable refund amount:** Kept editable on the confirm page (existing `updateRefundAmount` endpoint + `confirmSubmit` guard). The Branch Manager can override the final payout, but never below `total_passenger_refundable`.
- **Breakdown level:** Aggregate total only (no per-passenger table) in both the modal and confirm page.
- **Display locations:** Both the initiate (modal) and the confirm page.
- **Snapshot vs live:** Confirm page and minimum floor always use the `cancelled_bookings.total_passenger_refundable` snapshot, since live `refund_payable` is zeroed on confirm.
