# Plan: Mandatory due-settlement for passenger cancellation refunds

## Problem

Customer refund for the **passenger cancellation** process is being mishandled. The
confirmation page lets a user choose how much of the refundable amount to adjust against
the invoice due, and the "Adjust from Due" field:

- defaults to 0 / does not auto-settle (front-end bug), and
- is user-editable, so a user can pick a *smaller* adjustment to force a larger cash
  (Customer Refund) payout.

## Desired behavior (confirmed)

- **Fully automatic & mandatory**: Always settle the full refundable amount against the
  invoice due first.

  ```
  adjustFromDue = min(refundable, max(0, invoice.balance))
  customerRefund = max(0, refundable - adjustFromDue)
  ```

- A **Customer Refund** (cash payout) is created **only** when the due cannot cover the
  whole refundable amount (i.e. `customerRefund > 0`).
- The customer refund is bounded by `total paid > total customer refund`
  (existing `RefundCapService`).
- Enforce this **server-side** (not just in the UI).
- Fix the front-end so the field reflects the full auto-settlement and is non-editable.

## Changes

### 1. Backend logic — `app/Services/PassengerCancellationService.php`

In `confirmCancellation(CancelledPassenger $cancelledPassenger, array $data)`:

- Resolve the invoice: `$invoice = $cancelledPassenger->invoice ?? $cancelledPassenger->booking?->invoice`.
- Compute the settlement **internally** (ignore any passed `balance_adjusted_amount`):

  ```php
  $refundable = (float) $cancelledPassenger->refundable_amount;
  $balance    = max(0, (float) ($invoice->balance ?? 0));
  $adjusted   = min($refundable, $balance);
  $refund     = max(0, $refundable - $adjusted);
  ```

- Keep the existing deduction / adjustment / refund payment+voucher creation:
  - adjustment only when `$adjusted > 0`,
  - refund only when `$refund > 0`.
- Keep `RefundCapService::assertRefundAllowed($capInvoice, $refund)` capping the cash refund.
- Remove reliance on `$data['balance_adjusted_amount']` / `$data['currency']` for the split.

### 2. Controller — `app/Http/Controllers/PassengerCancellationActionController.php`

In `confirmSubmit(Request $request, CancelledPassenger $cancelledPassenger)`:

- Drop the user-supplied `balance_adjusted_amount` from validation (no longer an accepted,
  ranged input). Keep validating only `payment_method`, `currency`, `remarks`.
- Remove the manual `$maxAdjustable` client-cap computation and the
  `normalizeToSar(...)`/`assertRefundAllowed(...)` duplication — defer settlement to the
  service.
- Keep `ensureBranchAccess($cancelledPassenger)` and the redirect.

### 3. View — `resources/views/cancelled-passengers/confirm.blade.php`

- Replace the editable "Adjust from Due" inputs (SAR/BDT) with a **read-only**
  auto-settled display:
  - `adjustFromDue = min(refundable, max(0, invoice.balance))`
  - "Amount Adjusted" and "Customer Refund" computed values
    (`customerRefund = refundable - adjustFromDue`).
- Remove the BDT/SAR editable input logic for the adjustment (keep currency display only)
  so a larger cash refund cannot be forced via UI.
- Submit `balance_adjusted_amount` as a hidden field set to the auto-computed value
  (or remove it and rely entirely on the service).
- Keep `remainingRefundable` display and the client-side "customer refund cannot exceed
  remaining refundable" guard, but the source of truth is now server-side.

### 4. Tests (TDD-first)

- `tests/Feature/PassengerCancellationServiceTest.php`:
  - Balance covers refundable → `adjusted = refundable`, `refund_amount = 0`,
    no refund payment.
  - Balance partially covers → `adjusted = balance`,
    `refund_amount = refundable - balance`, refund payment/voucher created.
  - Balance = 0 → no adjustment, refund = full refundable (subject to cap).
  - Passing a smaller `balance_adjusted_amount` does **not** reduce settlement
    (mandatory enforced).
- `tests/Feature/PassengerCancellationControllerTest.php`:
  - Confirm endpoint ignores/overrides a user-supplied small adjustment.
- `tests/Feature/CancelledPassengerConfirmLocalTimeTest.php` (or a new view test):
  - Confirm page renders auto-settled "Amount Adjusted" and "Customer Refund" values and
    offers no editable adjustment field.

### 5. Verification

- `vendor/bin/pint`
- `php artisan test`
- `npm run build`

## Notes / open points

- The implementation uses the stored `invoice.balance` column (pre-adjustment balance) as
  the "due," consistent with current code. `InvoiceService::calculateBalance()` would
  include this confirmation's own adjustment, so the stored column is the safer source.
- The initiator step (Super Admin/Co Admin preview modal in `bookings/index.blade.php`)
  needs no change, since settlement happens at confirmation time.

## Acceptance criteria

- `adjustFromDue` is always the full `min(refundable, max(0, invoice.balance))` — enforced
  by the service, controller, and view.
- A Customer Refund (cash payout) is produced only when the due cannot cover the refundable.
- Customer refund cannot exceed `total paid − total customer refund` (RefundCapService).
- The confirmation page auto-settles and does not allow a smaller adjustment.
- All relevant tests pass; Pint formatting and `npm run build` are green.

---

# Part 2: Booking-cancellation refund vs. prior passenger-level customer refunds

## Problem

If a Customer Refund was already given during a **passenger** cancellation, the booking
cancellation refund (which can only happen while at least one passenger remains) must not
payout the same money twice. Two issues found:

1. **Snapshot overstates the refund.** `CancellationService::initiateCancellation()` stores
   `refund_amount = totalPaid − totalCost − serviceCharge` *without* subtracting prior
   passenger-level refunds. The actual payout is still safe at confirm time because
   `RefundCapService::assertRefundAllowed()` caps it at `paid − refunded` (all `Customer
   Refund` vouchers on the invoice), but the pending record's stored amount is misleading
   until confirm clamps it.

2. **Double-counted costs.** `CostTrackingService::getBookingCostSummary()` sums costs
   across **all** passengers including already-cancelled ones. After a passenger
   cancellation, that passenger's visa/ticket costs still sit in `totalCost`, wrongly
   shrinking the remaining seats' booking-cancel refund.

Confirmed decisions: keep `paid_amount` as full collections (does **not** get netted down
by prior refunds), cap the booking snapshot against already-refunded amounts, **and**
exclude already-cancelled passengers from the booking total-cost.

## Changes

### 1. `app/Services/CostTrackingService.php`

In `getPassengerCosts()`:
- Exclude cancelled passengers: `$booking->passengers->reject(fn ($p) => $p->is_cancelled)`.
- Use the **active** passenger count as the fingerprint-cost denominator
  (`$fingerprintCost / activePassengers->count()` when > 0).

Flow-through (all booking-cancellation contexts — safe scope):
- `CancellationService::initiateCancellation()` refund calc
- `CancellationService::getCostBreakdown()` (initiate modal / detail)
- `BookingCancellationViewController::initiate()` `potential_refund`
- `BookingCancellationViewController::confirm()` `costSummary`
- `BookingController::store()` recompute of a processing cancellation's `refund_amount`

### 2. `app/Services/CancellationService.php`

In `initiateCancellation()` — clamp the stored snapshot against the existing cap:

```php
$rawRefund = max(0, $totalPaid - $totalCost - ($serviceCharge ?? 0));
$remaining = app(RefundCapService::class)->getCap($invoice)['remaining'];
$refundAmount = min($rawRefund, $remaining);
```

### 3. `app/Http/Controllers/BookingController.php` (~line 2068-2075)

Apply the same `remaining` clamp when recomputing a processing cancellation's
`refund_amount` after a late payment:

```php
$rawRefund = max(0, $invoice->paid_amount - $totalCost - $serviceCharge);
$remaining = app(RefundCapService::class)->getCap($invoice)['remaining'];
$refundAmount = min($rawRefund, $remaining);
```

### 4. Tests (TDD-first)

- `tests/Unit/CostTrackingServiceTest.php`:
  - Cost summary excludes a cancelled passenger's visa/ticket/fingerprint share and uses
    the active passenger count for the fingerprint split.
- `tests/Feature/CancellationServiceTest.php` (or new):
  - Initiating a booking cancellation after a passenger-level Customer Refund stores
    `refund_amount = min(raw, paid − refunded)`; no over-refund.
- Booking-cancel controller/confirm: booking refund stays within `paid − already refunded`.

### 5. Verification

- `vendor/bin/pint`
- `php artisan test`
- `npm run build`

## Notes / open points

- `paid_amount` is kept as full Initial + Due collections; prior customer refunds only
  affect the refund cap, never the `paid_amount` figure.
- `RefundCapService::getCap()` already counts every `Customer Refund` voucher on the
  invoice (passenger- and booking-level), so `remaining = paid − refunded` is the correct
  shared ceiling.

## Acceptance criteria (Part 2)

- `passenger refunds + booking refund ≤ total paid` — no double payout.
- Booking-cancel snapshot `refund_amount` is clamped to `paid − already refunded` at
  initiate and on late-payment recompute.
- Booking `total_cost` excludes already-cancelled passengers.
- All relevant tests pass; Pint formatting and `npm run build` are green.
