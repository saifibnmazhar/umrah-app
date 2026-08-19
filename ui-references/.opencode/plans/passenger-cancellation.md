# Plan — Individual Passenger Cancellation

> Umrah App (Laravel 12). Adds per-passenger cancellation, mirroring the existing
> whole-booking cancellation but scoped to a single passenger, following the
> business rules below.

## 1. Overview

Booking cancellation today is all-or-nothing (`cancelled_bookings` +
`bookings.is_cancelled`), with a two-stage workflow (initiate → pending-refunds →
confirm/revert). This feature adds the same two-stage workflow **per passenger**.

Key decisions locked:

- **Cancel Pax** action lives inside the passenger-row **action column 3-dot dropdown**
  in the Bookings index → Passengers tab.
- The `cancelled_passengers` record is created **at initiate** with status
  `cancellation processing` (like booking cancel). **No financial changes at initiate.**
- **Revert** (while processing) = delete the row + un-flag the passenger → back to before.
- **Confirm** applies all financial adjustments at that moment and creates the
  refund/deduction financial records.
- Customer refund = **airline ticket refund received − service charge** (only the
  ticket money goes back; visa/fingerprint/margin are forfeited).
- Ticket refund received is **editable**, auto-filled from:
  `RefundedTicket.iata_refunded_amount` → `ticketFare.offer_price` → `ticketFare.selling_fare`.

## 2. Financial model

Cancelling 1 passenger from a booking with **N** active passengers
(shares computed from live state at each operation):

| Item | Delta |
|---|---|
| `booking.pax_qty` | −1 (at confirm) |
| `booking.total_value` | −(`package_value` + fingerprint share) |
| `booking.discount_amount` | −(`discount_amount ÷ N`) — booking-level discount, reduced like fingerprint |
| `invoice.total_amount` | (new total_value) − (new discount_amount) |
| fingerprint share | = `FingerprintCharge` (by district+location) ÷ N, skipped if location = Office |

- Customer refund (`invoice.balance` / manual payout) = `ticket_refund_received − service_charge`. The refund is **independent** of discount; discount only affects the invoice totals.
- Remaining passengers implicitly re-split the fingerprint cost share.

## 3. Workflow

### Stage 1 — Initiate (Super Admin / Co Admin)
Action column 3-dot menu → **Cancel Pax**.
Modal shows financial preview, expected ticket refund, cancellation branch, service charge.
Submit creates `cancelled_passengers` row (`status = 'cancellation processing'`),
sets `passenger.is_cancelled = true`, `cancelled_at = now()`, status `Cancel`.
**No financial changes yet.**

### Stage 2 — Processing
Listed in `pending-refunds` (query `status = 'cancellation processing'`).
Booking untouched; fully reversible.

### Stage 3 — Revert OR Confirm (Branch Manager / Fingerprint Admin)
- **Revert**: delete the row, `is_cancelled = false`, `cancelled_at = null`, restore
  status via `syncComputedStatus()`. Nothing financial to unwind.
- **Confirm** (refund page): editable ticket refund received (auto-filled) + service
  charge + disposition (**Apply to due** / **Manual payout**) + payment method + remarks.
  Applies: `status='cancelled'`, `pax_qty −1`, totals recalc, discount/fingerprint share
  reduction, `invoice.total_amount` update, refund + deduction Payments/Vouchers, invoice
  payment status recompute. Confirmed is **final** (no revert after confirm).

## 4. Migrations

### 4.1 `create_cancelled_passengers_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancelled_passengers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('passenger_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->decimal('total_amount', 14, 6);
            $table->decimal('ticket_refund_received', 14, 6)->default(0);
            $table->decimal('service_charge_deduction', 14, 6)->nullable();
            $table->decimal('refund_amount', 14, 6)->default(0);

            $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();

            $table->enum('status', ['cancellation processing', 'cancelled'])
                ->default('cancellation processing');

            $table->foreignId('deduction_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('deduction_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('refund_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();

            $table->timestamps();

            $table->unique(['booking_id', 'passenger_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancelled_passengers');
    }
};
```

### 4.2 `add_is_cancelled_to_passengers_table`

```php
Schema::table('passengers', function (Blueprint $table) {
    $table->boolean('is_cancelled')->default(false)->after('refund_payable');
    $table->timestamp('cancelled_at')->nullable()->after('is_cancelled');
});
```

### 4.3 `add_cancelled_passenger_id_to_payments_and_vouchers_table`

```php
Schema::table('payments', function (Blueprint $table) {
    $table->foreignId('cancelled_passenger_id')
        ->nullable()->after('cancelled_booking_id')
        ->constrained('cancelled_passengers')->nullOnDelete();
});
Schema::table('vouchers', function (Blueprint $table) {
    $table->foreignId('cancelled_passenger_id')
        ->nullable()->after('cancelled_booking_id')
        ->constrained('cancelled_passengers')->nullOnDelete();
});
```

### Column reference

| Column | Type | Nullable | Source / set at |
|---|---|---|---|
| `booking_id` | FK bookings | no | `passenger->booking_id` · initiate |
| `passenger_id` | FK passengers | no | passenger |
| `invoice_id` | FK invoices | no | `booking->invoice_id` |
| `user_id` | FK users | no | `auth()->id()` at initiate |
| `total_amount` | decimal(14,6) | no | `package_value + fingerprint_share` snapshot at initiate |
| `ticket_refund_received` | decimal(14,6) | no | editable at confirm; auto-fill `iata_refunded_amount → offer_price → selling_fare` |
| `service_charge_deduction` | decimal(14,6) | yes | captured at initiate, editable at confirm |
| `refund_amount` | decimal(14,6) | no | computed at confirm `ticket_refund − service_charge` |
| `cancellation_branch_id` | FK branches | no | initiate modal select |
| `status` | enum | no | `cancellation processing` (initiate) → `cancelled` (confirm) |
| `deduction_payment_id` | FK payments | yes | confirm, IF `service_charge > 0` |
| `deduction_voucher_id` | FK vouchers | yes | confirm, IF `service_charge > 0` |
| `refund_payment_id` | FK payments | yes | confirm, ONLY IF Manual payout |
| `refund_voucher_id` | FK vouchers | yes | confirm, ONLY IF Manual payout |

### Payment / voucher FK meaning

- `deduction_*` — records the company-kept slice (`TransactionType` "Service Charge Deduction"), only if `service_charge > 0`.
- `refund_*` — records money actually paid to the customer (`TransactionType` "Customer Refund"), only when disposition = **Manual payout** (no cash moves on **Apply to due**, so no Payment/Voucher).

## 5. Service — `PassengerCancellationService`

Mirrors `App\Services\CancellationService`.

- `initiateCancellation(Passenger, array $data): CancelledPassenger`
  - Guards: passenger not already cancelled, booking not fully cancelled, booking has ≥ 2 active passengers.
  - Compute `total_amount` snapshot, create record (status PROCESSING),
    `passenger.is_cancelled = true`, `cancelled_at = now()`, status `Cancel`.
  - No financial/totals changes.
- `revertCancellation(CancelledPassenger): void`
  - Guard: status must be PROCESSING.
  - Delete record, `passenger.is_cancelled = false`, `cancelled_at = null`,
    `passenger->syncComputedStatus()`.
- `confirmCancellation(CancelledPassenger, array $data): CancelledPassenger`
  - Guard: status must be PROCESSING.
  - `refund_amount = ticket_refund_received − service_charge`.
  - `pax_qty −1`; dedicated totals recalc (remove charge + fp share, reduce discount by `D/N`,
    update `booking.total_value`, `booking.discount_amount`, `invoice.total_amount`).
  - If `service_charge > 0`: create deduction Payment + Voucher (`cancelled_passenger_id`).
  - If disposition = Manual payout: create refund Payment + Voucher (`cancelled_passenger_id`).
  - Apply refund to `invoice.balance` when disposition = Apply to due.
  - Link IDs, set status CANCELLED, recompute invoice status via `InvoiceService::updatePaymentStatus()`.
- `getCancellationPreview(Passenger): array` — JSON for the initiate modal:
  passenger charge, fingerprint share, discount share, expected ticket refund + its source label.
- Dedicated totals recalc helper (must NOT reuse `BookingService::recalculateBookingTotal()`,
  which resets scaled fingerprint/discount). Revert path is symmetric.

## 6. Invoice accounting fix

`InvoiceService::updatePaymentStatus()` must exclude refund/deduction-linked Payment rows from the
`paid_amount` sum: `cancelled_booking_id`, `cancelled_passenger_id`, `refunded_ticket_id`,
`re_issued_ticket_id`. Otherwise refund rows (positive amount) inflate `paid_amount` on a still-open invoice.

## 7. Controllers + routes

New `PassengerCancellationViewController` + `PassengerCancellationActionController`
(in `routes/booking-cancellation.php` or a new route file):

- `GET  /passengers/{passenger}/cancellation/initiate` → preview JSON (Super Admin, Co Admin)
- `POST /passengers/{passenger}/cancellation/initiate` → store (Super Admin, Co Admin)
- `GET  /cancelled-passengers` → pending-refunds passenger tab (Super Admin, Co Admin, Branch Manager, Fingerprint Admin)
- `POST /cancelled-passengers/{cancelledPassenger}/revert` → revert (Branch Manager, Fingerprint Admin)
- `GET  /cancelled-passengers/{cancelledPassenger}/confirm` → confirm page (Branch Manager, Fingerprint Admin)
- `POST /cancelled-passengers/{cancelledPassenger}/confirm` → confirm submit (Branch Manager, Fingerprint Admin)

Branch-scoping guards mirror `BookingCancellation*Controller::ensureBranchAccess()`.

## 8. UI

### 8.1 Bookings index — Passengers tab (resources/views/bookings/index.blade.php)
- Action column 3-dot dropdown (currently lines ~1387–1420: View Passenger / View Tickets / Download / Download All):
  add **Cancel Pax** (Super/Co Admin, shown when `!passenger.is_cancelled` and booking not fully cancelled).
- When cancelled: show **Revert** / **Confirm Refund** links (Branch Mgr / Fingerprint Admin) in the same dropdown.
- Passenger badge: yellow **Cancellation Processing** (row status = processing),
  red **Cancelled** (status = cancelled).

### 8.2 Initiate modal ("Cancel Passenger")
Styled like the existing Cancel Booking modal (line ~2821).
1. Financial preview (read-only): Passenger Charge · Fingerprint Share · Discount Share.
2. Expected ticket refund (info): `iata_refunded_amount` → `offer_price` → `selling_fare`.
3. Cancellation Branch * (select).
4. Service Charge (SAR + BDT auto-convert).
5. `[Start Cancellation]` (orange) · `[Cancel]`.

### 8.3 Pending refunds
Add a passenger-cancellation tab/section to `pending-refunds.index`
(listing `status = 'cancellation processing'`) with Revert + Confirm links.

### 8.4 Confirm refund page
Patterned on `cancelled-bookings.confirm`: cancellation branch (pre-filled),
editable **Ticket Refund Received** (auto-filled), **Service Charge** (pre-filled/editable),
readonly **Customer Refund** (`refund_amount`), **Disposition** (Apply to due / Manual payout),
**Payment Method** (cash/bank; bank requires remarks), remarks. Refund amount auto-saves via
`PUT /api/cancelled-passengers/{id}/refund-amount`.

## 9. Guards & edge cases

- Block ticket issue / visa submit / fingerprint / payment actions for `is_cancelled` passengers
  (mirror booking-level guards, per passenger).
- Cannot cancel the **last active passenger** — prompt to use whole-booking cancellation.
- Passenger whose ticket already refunded → auto-fill `ticket_refund_received`;
  visa already cancelled → visa cost already excluded.
- Multiple sequential confirmations: shares computed from live state at each confirm.
- Revert always recomputes from live data (never trusts stored derived totals).

## 10. Tests (TDD-first)

- `PassengerCancellationServiceTest`: initiate (snapshot, flag, no financial change),
  revert (restore/symmetry), confirm (refund formula, deduction + refund payments/vouchers,
  discount percentage + fixed, fingerprint share, apply-to-due vs manual payout), guards.
- `PassengerCancellationControllerTest`: routes, roles, branch scope, validation.
- `InvoiceServiceTest`: refund-link payments excluded from `paid_amount`.
- Blade render tests (like `BookingEditPackagePreloadTest`): new dropdown item + modal render.
- Verify: `vendor/bin/pint`, `php artisan test`, `npm run build`,
  `docker compose config --quiet`.

## 11. Implementation order

1. Migrations + enum + `CancelledPassenger` model + relations.
2. `PassengerCancellationService` + unit tests.
3. Invoice accounting fix + tests.
4. Controllers + routes.
5. UI: dropdown item + initiate modal, pending-refunds tab, confirm page.
6. Guards on ticket/visa/fingerprint/payment actions.
7. Full suite + Pint + build.