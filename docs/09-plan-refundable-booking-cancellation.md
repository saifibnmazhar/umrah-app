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
3. Keep the **confirm-page refund amount editable** — server validation unchanged (`min:0`); the user can only **lower** it (toward a client-side `:min="totalPassengerRefundable"`), never raise it. It is read-only when the computed value is at/below the refundable sum, and read-only showing `0` (raw negative kept for audit) when the computed value is negative.
4. Snapshot `total_passenger_refundable` on `CancelledBooking` at initiate time for audit/reporting.
5. Restore `refund_payable` if a PROCESSING cancellation is reverted.

## Scope

- **Display:** Aggregate total only (no per-passenger breakdown) in both the booking cancellation modal and the confirm page.
- **Calculation:** Refund = `Total Paid − Total Cost − Service Charge + Total Passenger Refundable`. The formula result is authoritative — it is **not** clamped up to `total_passenger_refundable`.
- **Negative refund:** `refund_amount` on `cancelled_bookings` may be stored **negative** (the raw formula result) for audit. The Payment/Voucher amount is floored at `0`. The shortfall is absorbed against the passengers' ticket refundable — on confirm, `refund_payable` is zeroed regardless, and the passengers simply do not receive the refundable. The `total_passenger_refundable` snapshot is stored **unchanged** (raw sum) for audit.
- **Editable:** The final refund amount on the confirm page is editable only when it exceeds `totalPassengerRefundable` — the Branch Manager can **lower** it (down to the refundable sum) but never raise it. When the computed amount is positive but at-or-below the refundable sum, the field is **read-only** and shows the actual amount. When the computed amount is negative, the field is **read-only and shows `0`**, while the raw negative value is preserved in the backend. Server validation stays `min:0`; the client-side `:min="totalPassengerRefundable"` enforces the floor. The computed value at initiate is stored as-is (it may be below the refundable sum or negative).
- **Revert:** `revertCancellation()` restores `refund_payable` from ticket refund data.

---

## Implementation Steps (in order)

### Step 1 — New migration: `cancelled_bookings.total_passenger_refundable`

**File:** `database/migrations/2026_09_06_000001_add_total_passenger_refundable_to_cancelled_bookings_table.php` (new file)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancelled_bookings', function (Blueprint $table) {
            $table->decimal('total_passenger_refundable', 14, 6)->default(0)->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('cancelled_bookings', function (Blueprint $table) {
            $table->dropColumn('total_passenger_refundable');
        });
    }
};
```

Stores the snapshot of `sum(refund_payable)` at cancellation initiation time for audit and reporting. `decimal(14,6)` matches the existing financial-column convention on this table.

### Step 2 — `app/Models/CancelledBooking.php`: add `$fillable` and cast

**Current `$fillable`:**
```php
protected $fillable = [
    'booking_id', 'invoice_id', 'user_id', 'total_paid',
    'service_charge_deduction', 'refund_amount',
    'cancellation_branch_id', 'status',
    'deduction_payment_id', 'deduction_voucher_id',
    'refund_payment_id', 'refund_voucher_id',
    'confirmed_by_id', 'reverted_by_id',
];
```

Add `'total_passenger_refundable'` after `'refund_amount'`:
```php
protected $fillable = [
    'booking_id', 'invoice_id', 'user_id', 'total_paid',
    'service_charge_deduction', 'refund_amount',
    'total_passenger_refundable',
    'cancellation_branch_id', 'status',
    'deduction_payment_id', 'deduction_voucher_id',
    'refund_payment_id', 'refund_voucher_id',
    'confirmed_by_id', 'reverted_by_id',
];
```

**Current `$casts`:**
```php
protected $casts = [
    'total_paid' => 'decimal:6',
    'service_charge_deduction' => 'decimal:6',
    'refund_amount' => 'decimal:6',
    'status' => CancelledBookingStatus::class,
];
```

Add the cast:
```php
protected $casts = [
    'total_paid' => 'decimal:6',
    'service_charge_deduction' => 'decimal:6',
    'refund_amount' => 'decimal:6',
    'total_passenger_refundable' => 'decimal:6',
    'status' => CancelledBookingStatus::class,
];
```

### Step 3 — `app/Models/Booking.php`: add `getTotalPassengerRefundable()`

Add after the `cancelledBooking()` method (end of file):

```php
public function getTotalPassengerRefundable(): float
{
    return (float) $this->passengers()
        ->where('is_cancelled', false)
        ->sum('refund_payable');
}
```

Centralizes the active-passengers `refund_payable` sum. Used by `CancellationService` (initiate + cost breakdown), the view controller (JSON response), and tests.

### Step 4 — `app/Services/CancellationService.php`: core logic

#### 4a. `initiateCancellation()` — new formula + snapshot

**Current:**
```php
$totalPaid = (float) $invoice->paid_amount;
$totalCost = $costSummary['total_cost'];
$serviceCharge = isset($data['service_charge_deduction']) ? (float) $data['service_charge_deduction'] : null;
$refundAmount = $totalPaid - $totalCost - ($serviceCharge ?? 0);

return DB::transaction(function () use ($booking, $invoice, $data, $totalPaid, $serviceCharge, $refundAmount) {
    $cancelledBooking = CancelledBooking::create([
        'booking_id' => $booking->id,
        'invoice_id' => $invoice->id,
        'user_id' => auth()->id(),
        'total_paid' => $totalPaid,
        'service_charge_deduction' => $serviceCharge,
        'refund_amount' => $refundAmount,
        'cancellation_branch_id' => $data['cancellation_branch_id'],
        'status' => CancelledBookingStatus::PROCESSING,
    ]);
```

**New:**
```php
$totalPaid = (float) $invoice->paid_amount;
$totalCost = $costSummary['total_cost'];
$serviceCharge = isset($data['service_charge_deduction']) ? (float) $data['service_charge_deduction'] : null;
$totalPassengerRefundable = $booking->getTotalPassengerRefundable();
$refundAmount = $totalPaid - $totalCost - ($serviceCharge ?? 0) + $totalPassengerRefundable;

return DB::transaction(function () use ($booking, $invoice, $data, $totalPaid, $serviceCharge, $refundAmount, $totalPassengerRefundable) {
    $cancelledBooking = CancelledBooking::create([
        'booking_id' => $booking->id,
        'invoice_id' => $invoice->id,
        'user_id' => auth()->id(),
        'total_paid' => $totalPaid,
        'service_charge_deduction' => $serviceCharge,
        'refund_amount' => $refundAmount,
        'total_passenger_refundable' => $totalPassengerRefundable,
        'cancellation_branch_id' => $data['cancellation_branch_id'],
        'status' => CancelledBookingStatus::PROCESSING,
    ]);
```

**Formula rules:**
- `Refund = paid − cost − serviceCharge + totalPassengerRefundable`.
- The raw formula result is authoritative — it is **not** clamped up to `total_passenger_refundable`. If the formula produces a value *lower* than the refundable sum, the lower value is taken (the passengers' ticket refundable absorbs the cost overrun).
- **No flooring at initiate:** `refund_amount` is stored **exactly as the formula produces it** — including negative values — for audit purposes.
- If the formula runs negative, `refund_amount` is stored negative (audit), `total_passenger_refundable` is stored **unchanged** (raw sum), and on confirm the passengers' `refund_payable` is still zeroed — they simply do not receive the refundable.
- `total_passenger_refundable` snapshot is stored on the `CancelledBooking` record for audit/reporting.

#### 4b. `getCostBreakdown()` — fix `potential_refund`

**Current:**
```php
'potential_refund' => $invoice->paid_amount - $costSummary['total_cost'],
```

**New:**
```php
'total_passenger_refundable' => $booking->getTotalPassengerRefundable(),
'potential_refund' => $invoice->paid_amount - $costSummary['total_cost'] + $booking->getTotalPassengerRefundable(),
```

`potential_refund` (display-only) is aligned with the new formula; `total_passenger_refundable` is exposed for consumers.

#### 4c. `confirmCancellation()` — floor payment at 0 + bulk-zero `refund_payable`

**Payment/voucher amount is floored at 0** — `refund_amount` on `CancelledBooking` may be negative (audit), but a `Payment`/`Voucher` cannot have a negative amount:

```php
$refundPaymentAmount = max(0, (float) $data['refund_amount']);
```

Use `$refundPaymentAmount` for the "Customer Refund" `Payment::create()` amount and the matching `createVoucher()` amount.

After the "Customer Refund" payment + voucher creation block, add:

```php
$booking->passengers()
    ->where('is_cancelled', false)
    ->where('refund_payable', '>', 0)
    ->update(['refund_payable' => 0]);
```

**`refund_amount` is NOT overwritten during confirm** — the `$cancelledBooking->update([...])` block keeps the raw value (potentially negative) for audit. It continues to set `deduction_payment_id`, `deduction_voucher_id`, `refund_payment_id`, `refund_voucher_id`, and `status`, but drops the `'refund_amount' => $refundAmount` assignment.

The invoice balance is already set to 0 in this method and stays unchanged. A single `UPDATE` avoids N individual `save()` calls.

#### 4d. `revertCancellation()` — restore `refund_payable`

**Current:**
```php
DB::transaction(function () use ($cancelledBooking) {
    $booking = $cancelledBooking->booking;
    $invoice = $cancelledBooking->invoice;

    $booking->update(['is_cancelled' => false]);

    $invoiceService = app(InvoiceService::class);
    $invoice->refresh();
    $invoiceService->updatePaymentStatus($invoice);

    $cancelledBooking->delete();
});
```

**New:**
```php
DB::transaction(function () use ($cancelledBooking) {
    $booking = $cancelledBooking->booking;
    $invoice = $cancelledBooking->invoice;

    // Restore refund_payable from ticket refund data (idempotent)
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

`verifyRefundPayable()` recomputes `refund_payable` from ticket-refund / re-issue-settlement data. Only runs on PROCESSING cancellations (before confirm zeroes anything), so the restore is always valid.

### Step 5 — `app/Http/Controllers/BookingCancellationViewController.php`: `initiate()` JSON

**Current:**
```php
public function initiate(Booking $booking)
{
    $costSummary = app(CostTrackingService::class)->getBookingCostSummary($booking);
    $invoice = $booking->invoice;

    return response()->json([
        'total_amount' => (float) ($invoice?->total_amount ?? 0),
        'total_paid' => (float) ($invoice?->paid_amount ?? 0),
        'balance' => (float) ($invoice?->balance ?? 0),
        'costs' => [
            'fingerprint_cost' => $costSummary['fingerprint_cost'],
            'visa_cost' => $costSummary['visa_cost'],
            'ticket_cost' => $costSummary['ticket_cost'],
            'total_cost' => $costSummary['total_cost'],
        ],
        'passenger_costs' => $costSummary['passengers'],
        'service_charge' => 0,
        'potential_refund' => (float) (($invoice?->paid_amount ?? 0) - $costSummary['total_cost']),
        'currency_rate_id' => $booking->currency_rate_id,
        'booking_branch_id' => $booking->booking_branch_id,
        'booking_branch_name' => $booking->bookingBranch?->name,
        'booking_location' => $booking->bookingBranch?->location,
    ]);
}
```

**New:**
```php
public function initiate(Booking $booking)
{
    $costSummary = app(CostTrackingService::class)->getBookingCostSummary($booking);
    $invoice = $booking->invoice;
    $totalPassengerRefundable = (float) $booking->getTotalPassengerRefundable();

    return response()->json([
        'total_amount' => (float) ($invoice?->total_amount ?? 0),
        'total_paid' => (float) ($invoice?->paid_amount ?? 0),
        'balance' => (float) ($invoice?->balance ?? 0),
        'costs' => [
            'fingerprint_cost' => $costSummary['fingerprint_cost'],
            'visa_cost' => $costSummary['visa_cost'],
            'ticket_cost' => $costSummary['ticket_cost'],
            'total_cost' => $costSummary['total_cost'],
        ],
        'passenger_costs' => $costSummary['passengers'],
        'service_charge' => 0,
        'total_passenger_refundable' => $totalPassengerRefundable,
        'potential_refund' => (float) (($invoice?->paid_amount ?? 0) - $costSummary['total_cost']) + $totalPassengerRefundable,
        'currency_rate_id' => $booking->currency_rate_id,
        'booking_branch_id' => $booking->booking_branch_id,
        'booking_branch_name' => $booking->bookingBranch?->name,
        'booking_location' => $booking->bookingBranch?->location,
    ]);
}
```

Adds `total_passenger_refundable` for the modal UI and fixes `potential_refund` for consistency.

### Step 6 — `resources/views/bookings/index.blade.php`: initiate modal

1. **Alpine state** (after `cancelTotalPaid: 0,`):
   ```javascript
   cancelTotalPassengerRefundable: 0,
   ```

2. **`openCancelModal()`** — store from API response (after `this.cancelCosts = data.costs;`):
   ```javascript
   this.cancelTotalPassengerRefundable = data.total_passenger_refundable;
   ```

3. **`computedRefundAmount` getter** — replace:
   ```javascript
   get computedRefundAmount() {
       const paid = this.cancelTotalPaid;
       const cost = this.cancelCosts.total_cost;
       const charge = parseFloat(this.cancelServiceCharge) || 0;
       const refundable = parseFloat(this.cancelTotalPassengerRefundable) || 0;
       return (paid - cost - charge + refundable).toFixed(2);
   },
   ```

4. **Financial Summary** — add a "Total Passenger Refundable" row below the 3-column grid:
   ```html
   <div class="mb-4 p-3 bg-amber-50 rounded-lg text-sm">
       <div class="flex justify-between items-center">
           <span class="text-slate-600">Total Passenger Refundable</span>
           <span class="font-bold text-amber-700" x-text="$currency(cancelTotalPassengerRefundable, 2)"></span>
       </div>
       <p class="text-xs text-slate-400 mt-1">Sum of passenger ticket refund amounts owed back to customers</p>
   </div>
   ```

5. **Hint text** — replace:
   ```html
   <p class="text-xs text-slate-500 mt-1">Refund = Total Paid &minus; Total Cost &minus; Service Charge</p>
   ```
   with:
   ```html
   <p class="text-xs text-slate-500 mt-1">Refund = Total Paid &minus; Total Cost &minus; Service Charge + Total Passenger Refundable</p>
   ```

### Step 7 — `resources/views/cancelled-bookings/confirm.blade.php`: confirm page

Use the **snapshot** (`$cancelledBooking->total_passenger_refundable`), never a live `booking->getTotalPassengerRefundable()` call — the live `refund_payable` drops to 0 once the refund is confirmed.

1. **Alpine data** (after `totalCost: '...'`):
   ```php
   totalPassengerRefundable: '{{ $cancelledBooking->total_passenger_refundable ?? 0 }}',
   ```

2. **`effectiveServiceCharge` getter** — replace (old computed `paid − cost − refund`, the inverse of the old formula; new formula is `refund = paid − cost − charge + passengerRefundable`):
   ```javascript
   get effectiveServiceCharge() {
       const paid = parseFloat(this.totalPaid) || 0;
       const cost = parseFloat(this.totalCost) || 0;
       const refund = parseFloat(this.refundAmount) || 0;
       const passengerRefundable = parseFloat(this.totalPassengerRefundable) || 0;
       const result = paid - cost + passengerRefundable - refund;
       return result > 0 ? result.toFixed(6) : '0.000000';
   },
   ```
   Without this fix, the "Service Charge Deduction" row in the Financial Summary would show a deﬂated or clamped-to-zero value.

3. **Financial Summary** — add a "Total Passenger Refundable" row between Service Charge Deduction and Refund Amount:
   ```html
   <div class="flex justify-between text-sm">
       <span class="text-slate-500">Total Passenger Refundable</span>
       <span class="font-medium text-slate-800" x-text="$currency(totalPassengerRefundable, 2)"></span>
   </div>
   ```

4. **Editability + display getters** — drive read-only/editable states from the raw value vs the refundable floor:
   ```javascript
   get isRefundEditable() {
       const raw = parseFloat(this.refundAmount) || 0;
       const min = parseFloat(this.totalPassengerRefundable) || 0;
       return raw > min;
   },
   get refundDisplay() {
       const raw = parseFloat(this.refundAmount) || 0;
       return raw < 0 ? 0 : raw;
   },
   ```
   Three cases:
   - **Case A** — raw `> totalPassengerRefundable`: `isRefundEditable = true`; input shows the raw amount, user can lower it down to the refundable sum.
   - **Case B** — `0 ≤ raw ≤ totalPassengerRefundable`: read-only; input shows the actual raw amount.
   - **Case C** — `raw < 0`: read-only; input shows `0` (`refundDisplay`), while `refundAmount` keeps the raw negative value internally (needed by `effectiveServiceCharge` in item 2) and in the backend.

5. **SAR input** — bind the display value, apply floor/ceiling, and guard all edits:
   ```html
   <input type="number" step="0.000001"
       :value="refundDisplay"
       :readonly="!isRefundEditable || $store.currency.mode === 'BDT'"
       :class="{'bg-slate-100 cursor-not-allowed': !isRefundEditable || $store.currency.mode === 'BDT'}"
       :min="totalPassengerRefundable"
       :max="isRefundEditable ? parseFloat(originalRefundAmount) : totalPassengerRefundable"
       @input="
           if (!isRefundEditable) return;
           const val = parseFloat($event.target.value) || 0;
           const orig = parseFloat(originalRefundAmount);
           const min = parseFloat(totalPassengerRefundable) || 0;
           if (val > orig) { refundAmount = orig; showToast('Refund cannot exceed the original refund amount', 'warning'); return; }
           if (val < min) { refundAmount = min; showToast('Refund cannot be less than total passenger refundable', 'warning'); return; }
           if ($store.currency.mode === 'BDT' && $store.currency.rate > 0) {
               refundAmountBdt = Math.round((refundDisplay || 0) * $store.currency.rate * 100) / 100;
           }
       "
       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none font-medium"
       placeholder="Enter amount in SAR">
   ```
   In Cases B/C the range collapses to a single point (`:max === :min === totalPassengerRefundable`) **and** the field is read-only — the Branch Manager cannot raise above the calculated amount, and cannot lower below the refundable sum.

6. **BDT input** — same editability and floors; bounds composed from the SAR ones:
   ```html
   <input type="number" step="0.01"
       :value="refundAmountBdt"
       :readonly="!isRefundEditable"
       :class="{'bg-slate-100 cursor-not-allowed': !isRefundEditable}"
       :min="minBdt"
       :max="maxBdt"
       @input="
           if (!isRefundEditable) return;
           const bdt = parseFloat($event.target.value) || 0;
           const maxB = parseFloat(maxBdt) || 0;
           const minB = parseFloat(minBdt) || 0;
           if (bdt > maxB) { refundAmountBdt = maxB; showToast('Refund cannot exceed the original refund amount', 'warning'); return; }
           if (bdt < minB) { refundAmountBdt = minB; showToast('Refund cannot be less than total passenger refundable', 'warning'); return; }
           refundAmount = parseFloat(((parseFloat(refundAmountBdt) || 0) / ($store.currency.rate || 1)).toFixed(6));
       "
       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none font-medium"
       placeholder="Enter amount in BDT">
   ```
   ```javascript
   get minBdt() {
       const rate = $store.currency.rate || 1;
       return Math.round((parseFloat(this.totalPassengerRefundable) || 0) * rate * 100) / 100;
   },
   get maxBdt() {
       const rate = $store.currency.rate || 1;
       const ceiling = this.isRefundEditable
           ? (parseFloat(this.originalRefundAmount) || 0)
           : (parseFloat(this.totalPassengerRefundable) || 0);
       return Math.round(ceiling * rate * 100) / 100;
   },
   ```
   In `init()` and the `currency-toggled` handler, derive the BDT display from `refundDisplay` (so Case C shows `0` in BDT, not a negative):
   ```javascript
   this.refundAmountBdt = Math.round(parseFloat(this.refundDisplay) * r * 100) / 100;
   ```
   **Important:** the SAR/BDT inputs must drop the static `min="0"` attribute (it would fight the new `:min="totalPassengerRefundable"`).

7. **Hint text** — replace:
   ```
   Default: {{ $cancelledBooking->refund_amount }} SAR (Total Paid &minus; Total Cost &minus; Service Charge)
   ```
   with:
   ```
   Default: {{ $cancelledBooking->refund_amount }} SAR (Total Paid &minus; Total Cost &minus; Service Charge + Total Passenger Refundable)
   ```

8. **Form submission floors at 0** — the editable `refundAmount` may load as a negative (raw audit) value; the hidden input must floor it for the Payment:
   ```html
   <input type="hidden" name="refund_amount" :value="Math.max(0, parseFloat(refundAmount))">
   ```

**Behavior:** The auto-save watcher only fires on user edits, so the initial raw (possibly negative) value is not re-sent on page load. The `:min="totalPassengerRefundable"` guard prevents lowering below the refundable sum; the `:max` guard and `isRefundEditable` prevent raising above the initiate-computed value. In Cases B/C the field is read-only, so the raw value (≥ 0 actual, or negative audit) is never changed client-side and stays untouched in the backend. The computed value at initiate is stored as-is (Step 4a).

### Step 8 — `app/Http/Controllers/BookingCancellationActionController.php`: report refund from vouchers

`updateRefundAmount()` and `confirmSubmit()` are **unchanged** — they keep `refund_amount` validation (`required|numeric|min:0`). There is **no** server-side floor against `total_passenger_refundable`; the floor/ceiling are enforced **client-side** (Step 7).

`reportData()` is updated so the **refund figures come from the actual 'Customer Refund' vouchers**, not the raw `cancelled_bookings.refund_amount` (which is the audit value and may be negative).

Per-row mapping — use the linked refund voucher's amount (the amount actually disbursed, already floored at 0 by `confirmCancellation`):
```php
'refund_amount' => (float) ($cb->refundVoucher?->amount ?? 0),
```
PROCESSING rows have no voucher yet, so they report `0` (nothing disbursed), matching the `refunded_at = '-'` row.

Summary total — sum the 'Customer Refund' vouchers across the **full filtered set** (not just the current page), keyed by `cancelled_booking_id`:
```php
use App\Models\Voucher;

$cbIds = $query->clone()->pluck('cancelled_bookings.id');
$totalRefund = (float) Voucher::whereIn('cancelled_booking_id', $cbIds)
    ->whereHas('transactionType', fn ($q) => $q->where('name', 'Customer Refund'))
    ->sum('amount');
```

Replace `'total_refund' => (float) $query->clone()->sum('refund_amount')` with `'total_refund' => $totalRefund`. `total_paid` and `total_deduction` (sums of `total_paid` / `service_charge_deduction`) stay unchanged.

---

## Flow Summary

| Step | Behavior |
|------|----------|
| **Initiate** | `refundAmount = paid − costs − serviceCharge + Σ passenger.refund_payable` (raw — may be negative); snapshot `total_passenger_refundable` (raw sum) stored on `cancelled_bookings` |
| **Modal** | Displays the total passenger refundable amount alongside the computed refund |
| **Confirm page** | Displays the snapshot total passenger refundable amount; refund amount editable (lower → refundable) **only** when computed > refundable; read-only showing the actual amount when computed in `[0, refundable]`; read-only showing `0` (raw negative stored) when computed < 0; auto-save unchanged; form submission floors at 0 |
| **Confirm** | Refund payment/voucher = editable amount floored at 0; `refund_amount` (raw audit value) preserved; `refund_payable` bulk-zeroed on each active passenger; invoice balance set to 0 |
| **Revert (PROCESSING only)** | `refund_payable` restored from `verifyRefundPayable()`; booking un-cancelled; `cancelled_booking` row deleted |
| **Report/audit** | `total_passenger_refundable` snapshot stored on `cancelled_bookings`; report refund rows/totals read from 'Customer Refund' vouchers (`refundVoucher->amount`), never the raw `refund_amount` |

---

## Testing (TDD-First)

1. `Booking::getTotalPassengerRefundable()` returns the correct sum of active passengers' `refund_payable`.
2. `CancellationService::initiateCancellation()` includes the passengers' total refundable amount in the computed refund.
3. `CancellationService::initiateCancellation()` stores the **raw** formula result (including negative values) and the raw `total_passenger_refundable` snapshot unchanged.
4. `CancellationService::confirmCancellation()` zeroes `refund_payable` on each active passenger (single query, no N+1).
5. `CancellationService::confirmCancellation()` floors the refund Payment/Voucher amount at **0** when `refund_amount` is negative, and preserves the raw `refund_amount` on the record.
6. `CancellationService::revertCancellation()` restores each active passenger's `refund_payable` to their computed value from `verifyRefundPayable()`.
7. The initiate endpoint (`GET /bookings/{booking}/cancellation/initiate`) response includes `total_passenger_refundable` and corrected `potential_refund`.
8. The confirmation view renders the "Total Passenger Refundable" row from the snapshot.
9. The confirm-page `effectiveServiceCharge` getter accounts for `totalPassengerRefundable`.
10. The confirm-page refund amount remains editable (existing `PUT /api/cancelled-bookings/{id}/refund-amount` + `POST /cancelled-bookings/{id}/confirm` behavior preserved), and the form submission floors negative `refundAmount` at 0.
11. `reportData()` returns `refund_amount` from the customer-refund voucher amount and `total_refund` sums the 'Customer Refund' vouchers (full filtered set), so a negative raw `cancelled_bookings.refund_amount` never leaks into the report.
12. The confirm page renders the read-only states: `isRefundEditable === false` and the input shows the actual amount when `0 ≤ refund_amount ≤ total_passenger_refundable`, and shows `0` (rather than the negative raw) when `refund_amount < 0`.

**Fixture strategy (booking-level `CancellationService` tests):** mirror the existing `PassengerCancellationServiceTest` pattern — recreate the needed schemas in `setUp()` (`branches`, `customers`, `users`, `bookings`, `invoices`, `passengers`, `cancelled_bookings` incl. the new `total_passenger_refundable` column, `payments`, `vouchers`, `transaction_types`), use a **no-op `beginDatabaseTransaction()`** (DDL on MySQL implicitly commits), and `migrate:fresh` in `tearDownAfterClass()`. `CostTrackingService::getBookingCostSummary()` returns `0` costs for bare passenger fixtures (no visa/tickets), so `initiateCancellation()` runs without extra setup; set `refund_payable` directly on passengers to exercise the new formula (paid < charge ⇒ negative).

---

## Open Questions / Decisions

- **Editable refund amount:** Branch Manager can only **lower** the refund toward `totalPassengerRefundable`; it can never be raised above the initiate-computed value. Editable only when the computed amount exceeds the refundable sum. Otherwise read-only: shows the actual amount when in `[0, refundable]`, and shows `0` (raw negative kept for audit) when below `0`. Server validation stays `min:0`; the client-side `:min="totalPassengerRefundable"` enforces the floor. Users cannot reduce `total_passenger_refundable` manually — it only drops (to 0 or below, stored as negative) when the formula's `paid − cost − serviceCharge` portion is negative.
- **Formula result is authoritative:** The refund is *not* clamped up to `total_passenger_refundable`. If the formula produces a lower (or negative) value, that value applies; the passengers' ticket refundable absorbs the shortfall and is zeroed on confirm regardless.
- **Negative refund (audit):** `refund_amount` on `cancelled_bookings` is stored **exactly as the formula produces it** — negative values are kept for audit. `confirmCancellation()` does **not** overwrite it; the Payment/Voucher amount is floored at `0`. If the Branch Manager manually edits via `updateRefundAmount`, the raw value is replaced with the edited (≥ 0) value — an explicit override. The `total_passenger_refundable` snapshot is stored unchanged (raw sum) for audit.
- **Breakdown level:** Aggregate total only (no per-passenger table) in both the modal and confirm page.
- **Display locations:** Both the initiate (modal) and the confirm page.
- **Snapshot vs live:** Confirm page always uses the `cancelled_bookings.total_passenger_refundable` snapshot, since live `refund_payable` is zeroed on confirm.