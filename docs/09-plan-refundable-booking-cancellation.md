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
3. Keep the **confirm-page refund amount editable** — the existing `updateRefundAmount` endpoint remains unchanged so a Branch Manager can still override the final refund payout.

## Scope

- **Display:** Aggregate total only (no per-passenger breakdown) in both the booking cancellation modal and the confirm page.
- **Calculation:** Include the passengers' total refundable amount in the booking refund formula.
- **Editable:** The final refund amount on the confirm page stays editable exactly as it is today.

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

Update the refund calculations to include the passengers' total refundable amount.

**`initiateCancellation()`** — currently:

```php
$refundAmount = $totalPaid - $totalCost - ($serviceCharge ?? 0);
```

becomes:

```php
$totalPassengerRefundable = $booking->getTotalPassengerRefundable();
$refundAmount = $totalPaid - $totalCost - ($serviceCharge ?? 0) + $totalPassengerRefundable;
```

Store `total_passenger_refundable` on the `CancelledBooking` record for audit/reporting (requires a new column — see item 6).

**`getCostBreakdown()`** — currently:

```php
'potential_refund' => $invoice->paid_amount - $costSummary['total_cost'],
```

becomes:

```php
'potential_refund' => $invoice->paid_amount - $costSummary['total_cost'] + $booking->getTotalPassengerRefundable(),
```

**`confirmCancellation()`** — after creating the "Customer Refund" payment + voucher, decrement `refund_payable` to 0 on each active passenger (the refunded amount is settled):

```php
foreach ($booking->passengers()->where('is_cancelled', false)->get() as $passenger) {
    $passenger->decreaseRefundPayable((float) $passenger->refund_payable);
}
```

The invoice balance is already set to 0 in this method and stays unchanged.

### 3. `app/Http/Controllers/BookingCancellationViewController.php`

In `initiate()`, add `total_passenger_refundable` to the JSON response:

```php
'total_passenger_refundable' => (float) $booking->getTotalPassengerRefundable(),
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

### 5. `resources/views/cancelled-bookings/confirm.blade.php` — confirm page

- Add Alpine.js data: `totalPassengerRefundable: '{{ $cancelledBooking->booking->getTotalPassengerRefundable() }}'`.
- Add a "Total Passenger Refundable" row to the Financial Summary (between Service Charge Deduction and Refund Amount).
- The **Refund Amount input stays editable** — no change to the existing `updateRefundAmount` endpoint or its auto-save watcher.
- Update the default-value hint text to reflect the new formula: `Total Paid − Total Cost − Service Charge + Total Passenger Refundable`.

### 6. New migration — `cancelled_bookings.total_passenger_refundable`

Add a new migration adding a `total_passenger_refundable` decimal column (default 0) to the `cancelled_bookings` table. This stores the snapshot of the passengers' total refundable amount at cancellation time for audit and reporting purposes.

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

---

## Flow Summary

| Step | Behavior |
|------|----------|
| **Initiate** | `refundable = paid − costs − serviceCharge + Σ passenger.refund_payable` |
| **Modal** | Displays the total passenger refundable amount alongside the computed refund |
| **Confirm page** | Displays the total passenger refundable amount; refund amount remains editable |
| **Confirm** | Refund payment = editable amount; `refund_payable` zeroed on each active passenger; invoice balance set to 0 |
| **Report/audit** | `total_passenger_refundable` snapshot stored on `cancelled_bookings` |

---

## Testing (TDD-First)

1. `Booking::getTotalPassengerRefundable()` returns the correct sum of active passengers' `refund_payable`.
2. `CancellationService::initiateCancellation()` includes the passengers' total refundable amount in the computed refund.
3. `CancellationService::confirmCancellation()` decrements `refund_payable` to 0 on each active passenger.
4. The initiate endpoint (`GET /bookings/{booking}/cancellation/initiate`) response includes `total_passenger_refundable`.
5. The confirmation view renders the "Total Passenger Refundable" row.
6. The confirm-page refund amount remains editable (existing `PUT /api/cancelled-bookings/{id}/refund-amount` behavior preserved).

---

## Open Questions / Decisions

- **Editable refund amount:** Kept editable on the confirm page (existing `updateRefundAmount` endpoint unchanged). The Branch Manager can override the final payout.
- **Breakdown level:** Aggregate total only (no per-passenger table) in both the modal and confirm page.
- **Display locations:** Both the initiate (modal) and the confirm page.
