# Booking Cancellation — Final Implementation Plan

## 1. Overview

Soft-cancellation workflow with cost tracking, invoice status management, and process freezing.

### Core Formula

```
Refund Amount = total_paid - total_cost - service_charge_deduction
```

- **`total_paid`** — snapshot of `invoice.paid_amount` at initiation time (stored)
- **`total_cost`** — live-queried from `CostTrackingService` (not stored)
- **`service_charge_deduction`** — nullable; can be null, 0, positive, or negative
- **`refund_amount`** — computed result (stored)

### Workflow

```
Cancel Button (booking index)
  → Initiation Modal (cost breakdown, branch, service charge)
    → Booking.is_cancelled = true
    → Invoice.status = CANCELLED
    → Visa/Ticket/Fingerprint → FROZEN
    → Payment button → FROZEN
      → Pending Refunds Index
        → Revert: Booking active, invoice restored, processes unfrozen, deleted
        → Confirm: Refund Confirmation Page
          → Submit: 2 Payment+Voucher entries
          → Booking: CANCELLED (permanent)
          → Invoice: REFUNDED, balance = 0
          → Payment button → PERMANENTLY DISABLED
          → Processes → PERMANENTLY DISABLED
            → Cancellation Report
```

---

## 2. Permissions

| Action | Roles |
|---|---|
| **Initiate Cancellation** | **Super Admin, Co Admin** |
| Revert Cancellation | Super Admin, Co Admin, Branch Manager |
| Confirm / Process Refund | Super Admin, Co Admin, Branch Manager |
| View Pending Refunds | Super Admin, Co Admin, Branch Manager |
| View Cancellation Report | Super Admin, Co Admin, Auditor |

---

## 3. Migrations

### 3.1 Add `is_cancelled` to `bookings`

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->boolean('is_cancelled')->default(false)->after('remarks');
});
```

### 3.2 Create `cancelled_bookings` table

```php
Schema::create('cancelled_bookings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')->constrained()->restrictOnDelete();
    $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
    $table->foreignId('user_id')->constrained()->restrictOnDelete();
    $table->decimal('total_paid', 14, 6);
    $table->decimal('service_charge_deduction', 14, 6)->nullable();
    $table->decimal('refund_amount', 14, 6)->default(0);
    $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();
    $table->enum('status', ['cancellation processing', 'cancelled'])->default('cancellation processing');
    $table->foreignId('deduction_payment_id')->nullable()->constrained('payments')->nullOnDelete();
    $table->foreignId('deduction_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
    $table->foreignId('refund_payment_id')->nullable()->constrained('payments')->nullOnDelete();
    $table->foreignId('refund_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
    $table->timestamps();
});
```

### 3.3 Add `cancelled_booking_id` to `payments` and `vouchers`

```php
Schema::table('payments', function (Blueprint $table) {
    $table->foreignId('cancelled_booking_id')->nullable()->after('booking_id')->constrained('cancelled_bookings')->nullOnDelete();
});
Schema::table('vouchers', function (Blueprint $table) {
    $table->foreignId('cancelled_booking_id')->nullable()->after('booking_id')->constrained('cancelled_bookings')->nullOnDelete();
});
```

---

## 4. New Enum

**File:** `app/Enums/CancelledBookingStatus.php`

```php
<?php

namespace App\Enums;

enum CancelledBookingStatus: string
{
    case PROCESSING = 'cancellation processing';
    case CANCELLED = 'cancelled';
}
```

---

## 5. Seeder Update

**File:** `database/seeders/TransactionTypeSeeder.php`

Add:
```php
[
    'name' => 'Service Charge Deduction',
    'type' => 'credit',
],
```

`Customer Refund` (debit) already exists.

---

## 6. Model Updates

### 6.1 Booking Model

**File:** `app/Models/Booking.php`

Add to `$fillable`:
```php
'is_cancelled',
```

Add relationship:
```php
public function cancelledBooking(): HasOne
{
    return $this->hasOne(CancelledBooking::class)->latestOfMany();
}
```

### 6.2 CancelledBooking Model (new)

**File:** `app/Models/CancelledBooking.php`

```php
<?php

namespace App\Models;

use App\Enums\CancelledBookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancelledBooking extends Model
{
    protected $fillable = [
        'booking_id', 'invoice_id', 'user_id',
        'total_paid', 'service_charge_deduction', 'refund_amount',
        'cancellation_branch_id', 'status',
        'deduction_payment_id', 'deduction_voucher_id',
        'refund_payment_id', 'refund_voucher_id',
    ];

    protected $casts = [
        'total_paid' => 'decimal:6',
        'service_charge_deduction' => 'decimal:6',
        'refund_amount' => 'decimal:6',
        'status' => CancelledBookingStatus::class,
    ];

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function cancellationBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'cancellation_branch_id'); }
    public function deductionPayment(): BelongsTo { return $this->belongsTo(Payment::class, 'deduction_payment_id'); }
    public function deductionVoucher(): BelongsTo { return $this->belongsTo(Voucher::class, 'deduction_voucher_id'); }
    public function refundPayment(): BelongsTo { return $this->belongsTo(Payment::class, 'refund_payment_id'); }
    public function refundVoucher(): BelongsTo { return $this->belongsTo(Voucher::class, 'refund_voucher_id'); }
}
```

### 6.3 Payment Model

**File:** `app/Models/Payment.php`

Add to `$fillable`:
```php
'cancelled_booking_id',
```

Add relationship:
```php
public function cancelledBooking(): BelongsTo
{
    return $this->belongsTo(CancelledBooking::class);
}
```

### 6.4 Voucher Model

**File:** `app/Models/Voucher.php`

Same as Payment — add `cancelled_booking_id` to fillable, add `BelongsTo cancelledBooking()`.

---

## 7. CostTrackingService (new)

**File:** `app/Services/CostTrackingService.php`

Live-query service — always reads current DB state. No storage, no observers.

### Methods

```php
<?php

namespace App\Services;

use App\Models\Booking;
use App\Enums\FingerprintStatus;
use Illuminate\Support\Collection;

class CostTrackingService
{
    public function getPassengerCosts(Booking $booking): Collection
    {
        $eligibleFingerprintCount = $booking->passengers->filter(fn($p) =>
            in_array($p->fingerprintDetail?->status?->value, [
                FingerprintStatus::PROCESSING->value,
                FingerprintStatus::DONE->value,
                FingerprintStatus::APPROVED->value,
            ])
        )->count();

        $fingerprintCost = $booking->fingerprint?->cost ?? 0;
        $perPassengerFpCost = $eligibleFingerprintCount > 0
            ? $fingerprintCost / $eligibleFingerprintCount
            : 0;

        return $booking->passengers->map(fn($p) => [
            'passenger_id'     => $p->id,
            'passenger_name'   => $p->first_name . ' ' . $p->last_name,
            'fingerprint_cost' => $this->isFingerprintEligible($p) ? $perPassengerFpCost : 0,
            'visa_cost'        => $this->getPassengerVisaCost($p),
            'ticket_cost'      => $this->getPassengerTicketCost($p),
            'total_cost'       => 0,
        ])->map(fn($item) => array_merge($item, [
            'total_cost' => $item['fingerprint_cost'] + $item['visa_cost'] + $item['ticket_cost'],
        ]));
    }

    public function getBookingCostSummary(Booking $booking): array
    {
        $passengerCosts = $this->getPassengerCosts($booking);
        return [
            'fingerprint_cost' => $passengerCosts->sum('fingerprint_cost'),
            'visa_cost'        => $passengerCosts->sum('visa_cost'),
            'ticket_cost'      => $passengerCosts->sum('ticket_cost'),
            'total_cost'       => $passengerCosts->sum('total_cost'),
            'passengers'       => $passengerCosts,
        ];
    }

    private function isFingerprintEligible($passenger): bool
    {
        return in_array($passenger->fingerprintDetail?->status?->value, [
            FingerprintStatus::PROCESSING->value,
            FingerprintStatus::DONE->value,
            FingerprintStatus::APPROVED->value,
        ]);
    }

    private function getPassengerVisaCost($passenger): float
    {
        $visa = $passenger->visaSubmission;
        if (!$visa || $visa->status?->value !== 'issued') return 0;
        return (float) ($visa->final_cost ?? 0);
    }

    private function getPassengerTicketCost($passenger): float
    {
        $ticket = $passenger->latestIssuedTicket;
        if (!$ticket || !in_array($ticket->status, ['issued', 're-issued'])) return 0;
        return (float) ($ticket->net_fare ?? 0);
    }
}
```

### Cost Rules Summary

| Cost | Source | Condition | Per-Passenger Logic |
|---|---|---|---|
| **Fingerprint** | `fingerprints.cost` | `FingerprintDetail.status` ∈ `[processing, done, approved]` | `fingerprint.cost / eligible_count` (0 if not eligible) |
| **Visa** | `visa_submissions.final_cost` | `visa_submissions.status = 'issued'` | `final_cost` (0 otherwise) |
| **Ticket** | `issued_tickets.net_fare` | `issued_tickets.status` ∈ `[issued, re-issued]` | `net_fare` (0 otherwise) |

---

## 8. CancellationService (new)

**File:** `app/Services/CancellationService.php`

All business logic, uses DB transactions for writes.

### `initiateCancellation(Booking $booking, array $data): CancelledBooking`

1. Validate: `$booking->is_cancelled === false`
2. Get invoice via `$booking->invoice`
3. Get total cost from `CostTrackingService::getBookingCostSummary()`
4. Create `CancelledBooking` record (inside DB transaction):
   - `booking_id`, `invoice_id`, `user_id` (auth user)
   - `total_paid` = `$invoice->paid_amount` (snapshot)
   - `service_charge_deduction` = `$data['service_charge_deduction']` (nullable)
   - `refund_amount` = `$total_paid - $total_cost - $service_charge_deduction`
   - `cancellation_branch_id` = `$data['cancellation_branch_id']`
   - `status` = `CancelledBookingStatus::PROCESSING`
5. Set `Booking.is_cancelled = true`
6. Set `Invoice.status = InvoiceStatus::CANCELLED` (balance unchanged)
7. Return the `CancelledBooking`

### `revertCancellation(CancelledBooking $cancelledBooking): void`

1. Validate: status must be `PROCESSING`
2. Inside DB transaction:
   - Set `Booking.is_cancelled = false`
   - Restore invoice status via `InvoiceService::updatePaymentStatus()`
   - Delete the `CancelledBooking` record

### `confirmCancellation(CancelledBooking $cancelledBooking, array $data): CancelledBooking`

1. Validate: status must be `PROCESSING`
2. Get booking, invoice, currency rate info
3. Determine payment method and amounts from `$data`
4. Inside DB transaction:
   - **Create Service Charge Deduction Payment + Voucher** (credit, linked to `cancelled_booking_id`)
   - **Create Customer Refund Payment + Voucher** (debit, linked to `cancelled_booking_id`)
   - Update `CancelledBooking`: set FKs, status = `CANCELLED`
   - Set `Invoice.status = InvoiceStatus::REFUNDED`, `Invoice.balance = 0`
5. Return updated `CancelledBooking`

### `getCostBreakdown(Booking $booking): array`

```php
$costSummary = app(CostTrackingService::class)->getBookingCostSummary($booking);

return [
    'total_amount'       => $invoice->total_amount,
    'total_paid'         => $invoice->paid_amount,
    'balance'            => $invoice->balance,
    'costs'              => [
        'fingerprint_cost' => $costSummary['fingerprint_cost'],
        'visa_cost'        => $costSummary['visa_cost'],
        'ticket_cost'      => $costSummary['ticket_cost'],
        'total_cost'       => $costSummary['total_cost'],
    ],
    'passenger_costs'    => $costSummary['passengers'],
    'service_charge'     => 0,
    'potential_refund'   => $invoice->paid_amount - $costSummary['total_cost'],
    'currency_rate_id'   => $booking->currency_rate_id,
    'booking_branch_id'  => $booking->booking_branch_id,
    'booking_branch_name'=> $booking->bookingBranch?->name,
    'booking_location'   => $booking->bookingBranch?->location,
];
```

---

## 9. Controller

**File:** `app/Http/Controllers/BookingCancellationController.php`

| Method | Route | Purpose |
|---|---|---|
| `initiate()` | `GET /bookings/{booking}/cancellation/initiate` | Return JSON cost breakdown |
| `store()` | `POST /bookings/{booking}/cancellation/initiate` | Create CancelledBooking |
| `revert()` | `POST /cancelled-bookings/{cancelledBooking}/revert` | Revert cancellation |
| `confirm()` | `GET /cancelled-bookings/{cancelledBooking}/confirm` | Show refund confirmation view |
| `confirmSubmit()` | `POST /cancelled-bookings/{cancelledBooking}/confirm` | Process refund |
| `pendingRefunds()` | `GET /pending-refunds` | Show pending refunds list |
| `report()` | `GET /reports/booking-cancellation` | Show report view |
| `reportData()` | `GET /api/reports/booking-cancellation` | Return report JSON |

---

## 10. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\BookingCancellationController;

// Initiate — Super Admin, Co Admin only
Route::get('/bookings/{booking}/cancellation/initiate', [BookingCancellationController::class, 'initiate'])
    ->name('bookings.cancellation.initiate')
    ->middleware('role:Super Admin,Co Admin');
Route::post('/bookings/{booking}/cancellation/initiate', [BookingCancellationController::class, 'store'])
    ->name('bookings.cancellation.store')
    ->middleware('role:Super Admin,Co Admin');

// Revert — Super Admin, Co Admin, Branch Manager
Route::post('/cancelled-bookings/{cancelledBooking}/revert', [BookingCancellationController::class, 'revert'])
    ->name('cancelled-bookings.revert')
    ->middleware('role:Super Admin,Co Admin,Branch Manager');

// Confirm — Super Admin, Co Admin, Branch Manager
Route::get('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationController::class, 'confirm'])
    ->name('cancelled-bookings.confirm')
    ->middleware('role:Super Admin,Co Admin,Branch Manager');
Route::post('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationController::class, 'confirmSubmit'])
    ->name('cancelled-bookings.confirm.submit')
    ->middleware('role:Super Admin,Co Admin,Branch Manager');

// Pending Refunds — Super Admin, Co Admin, Branch Manager
Route::get('/pending-refunds', [BookingCancellationController::class, 'pendingRefunds'])
    ->name('pending-refunds.index')
    ->middleware('role:Super Admin,Co Admin,Branch Manager');

// Report — Super Admin, Co Admin, Auditor
Route::get('/reports/booking-cancellation', [BookingCancellationController::class, 'report'])
    ->name('report.booking-cancellation')
    ->middleware('role:Super Admin,Co Admin,Auditor');
Route::get('/api/reports/booking-cancellation', [BookingCancellationController::class, 'reportData'])
    ->name('report.booking-cancellation.data')
    ->middleware('role:Super Admin,Co Admin,Auditor');
```

---

## 11. Booking Index View — Changes

**File:** `resources/views/bookings/index.blade.php`

### 11.1 Status Column (between Due and Actions)

```html
<td class="px-3 py-2">
    @if($booking->is_cancelled)
        @php $cb = $booking->cancelledBooking; @endphp
        @if($cb && $cb->status->value === 'cancellation processing')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                Cancellation Processing
            </span>
        @else
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                Cancelled
            </span>
        @endif
    @else
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
            Active
        </span>
    @endif
</td>
```

Update `colspan` in empty row to account for new column.

### 11.2 Cancel Button (in Actions column)

Only for non-cancelled bookings and user has Super Admin/Co Admin role:

```html
@if(!$booking->is_cancelled && (auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Co Admin')))
    <button @click="openCancelModal({{ $booking->id }})" class="text-orange-600 hover:text-orange-800 font-medium ml-3">
        Cancel
    </button>
@endif
```

### 11.3 Cancellation Initiation Modal

**Alpine state variables:**
```js
cancelModalVisible: false,
cancelBookingId: null,
cancelBranches: @json($branches),
cancelBranchId: '',
cancelServiceCharge: null,
cancelTotalPaid: 0,
cancelCosts: { fingerprint_cost: 0, visa_cost: 0, ticket_cost: 0, total_cost: 0 },
cancelLoading: false,
```

**Alpine methods:**
```js
async openCancelModal(bookingId) {
    this.cancelBookingId = bookingId;
    this.cancelModalVisible = true;
    this.cancelServiceCharge = null;
    const res = await fetch(`/bookings/${bookingId}/cancellation/initiate`);
    const data = await res.json();
    this.cancelTotalPaid = data.total_paid;
    this.cancelCosts = data.costs;
    if (data.booking_branch_id) this.cancelBranchId = data.booking_branch_id;
},
closeCancelModal() {
    this.cancelModalVisible = false;
},
get computedRefundAmount() {
    const paid = this.cancelTotalPaid;
    const cost = this.cancelCosts.total_cost;
    const charge = parseFloat(this.cancelServiceCharge) || 0;
    return (paid - cost - charge).toFixed(2);
},
async handleCancelSubmit() {
    this.cancelLoading = true;
    try {
        const res = await fetch(`/bookings/${this.cancelBookingId}/cancellation/initiate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                cancellation_branch_id: this.cancelBranchId,
                service_charge_deduction: this.cancelServiceCharge === '' ? null : this.cancelServiceCharge,
            }),
        });
        const data = await res.json();
        if (data.success) window.location.reload();
    } catch (e) {
        alert('Failed to initiate cancellation');
    } finally {
        this.cancelLoading = false;
    }
},
```

**Modal HTML layout:**
```
Title: "Cancel Booking"
Shows: Invoice ID, Customer Name
Shows: Total Amount, Total Paid, Balance
  ── Costs Incurred ──
  Fingerprint Cost:    {costs.fingerprint_cost}
  Visa Cost:           {costs.visa_cost}
  Ticket Cost:         {costs.ticket_cost}
  ─────────────────────
  Total Cost Incurred: {costs.total_cost}
Branch: [dropdown pre-filled]
Service Charge Deduction: [number input, nullable]
Refund Amount: {computedRefundAmount} (auto-calculated)

[Submit Cancellation] [Cancel]
```

### 11.4 Freeze Per-Passenger Actions for Cancelled Bookings

All per-passenger action buttons (Visa Submit/Issue/Edit/Cancel/Resubmit, Ticket Issue/Edit, Fingerprint actions) need to check `booking.is_cancelled`. Pass `is_cancelled` into the passenger data rows. When true, action buttons are replaced with a muted indicator or hidden.

---

## 12. Booking Show View — Payment Button Freeze

**File:** `resources/views/bookings/show.blade.php` (around line 237)

```html
@php
    $isPaymentFrozen = $booking->is_cancelled;
    $cancelledStatus = $booking->cancelledBooking?->status?->value;
    $isPermanentlyDisabled = $isPaymentFrozen && $cancelledStatus === 'cancelled';
@endphp

<button @click="!isPaymentFrozen && openPaymentModal()"
        :disabled="{{ $isPaymentFrozen ? 'true' : 'false' }}"
        class="px-6 py-3 rounded-lg font-medium transition
            {{ $isPaymentFrozen ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700' }}"
        title="{{ $isPermanentlyDisabled ? 'Booking Cancelled' : ($isPaymentFrozen ? 'Cancellation Processing' : '') }}">
    Payment
</button>
```

### Visa/Ticket/Fingerprint Buttons on Booking Show

Any action buttons on the booking show page (Request Re-Issue, Request Add. Tkt, Request Ticket Refund, etc.) should also check `$booking->is_cancelled` and disable/hide them accordingly.

---

## 13. Pending Refunds Index (new view)

**File:** `resources/views/pending-refunds/index.blade.php`

- `@extends('layouts.app')`
- Alpine.js component for interactivity
- Lists `cancelled_bookings` with `status = 'cancellation processing'`
- Table columns: Invoice ID, Customer, Mobile, Branch, Total Paid, Service Charge, Refund Amount, Cancel Date
- Filter: Branch dropdown for branch-scoped users
- Actions:
  - **Revert button**: POSTs to `/cancelled-bookings/{id}/revert` with confirm dialog
  - **Confirm button**: Links to `/cancelled-bookings/{id}/confirm`

---

## 14. Refund Confirmation Page (new view)

**File:** `resources/views/cancelled-bookings/confirm.blade.php`

- `@extends('layouts.app')`
- Top section: Booking details (read-only): Invoice ID, Customer, Total Paid
- Cost breakdown display (read-only): Fingerprint, Visa, Ticket costs
- Form fields:
  - **Total Payment Till Date** — read-only
  - **Service Charge Deduction** — read-only (nullable display)
  - **Refund Amount** — editable number input (default = `total_paid - total_cost - service_charge_deduction`)
  - **Deduction** — read-only display (auto-adjusts based on refund amount)
  - **Method** — dropdown: Cash / Bank (PaymentMethod enum)
  - **Remarks** — textarea, required if Method = Bank
  - **Currency** — dropdown: SAR / BDT. Auto-selected based on cancellation branch location (KSA→SAR, BD→BDT)
- Submit button: "Confirm Refund"
- On submit: POSTs to `/cancelled-bookings/{id}/confirm`, redirects to pending refunds index with success message

---

## 15. Cancellation Report (new view)

**File:** `resources/views/reports/booking-cancellation.blade.php`

- `@extends('layouts.app')`
- Alpine.js component with `loadData()` fetching from `/api/reports/booking-cancellation`
- Filter bar: Date range, Branch, Status
- Data table columns: Invoice ID, Customer, Mobile, Booking Branch, Cancellation Branch, Paid, Deduction, Refund, Method, Remarks, Cancel Date, Refund Date, Refunded By, Status
- Controller `reportData()` joins `cancelled_bookings` with related tables, applies filters, returns:

```json
{
    "data": [...],
    "summary": { "total_paid": ..., "total_deduction": ..., "total_refund": ... },
    "pagination": { "current_page": ..., "last_page": ..., "per_page": ..., "total": ... }
}
```

---

## 16. Navigation Updates

**File:** `resources/views/partials/nav.php`

- **Pending Refunds** link → visible for Super Admin, Co Admin, Branch Manager
- **Booking Cancellation** link (in Reports section) → visible for Super Admin, Co Admin, Auditor

---

## 17. Complete File List

| # | File | Action | Type |
|---|---|---|---|
| 1 | `database/migrations/*_add_is_cancelled_to_bookings_table.php` | CREATE | Migration |
| 2 | `database/migrations/*_create_cancelled_bookings_table.php` | CREATE | Migration |
| 3 | `database/migrations/*_add_cancelled_booking_id_to_payments_and_vouchers.php` | CREATE | Migration |
| 4 | `app/Enums/CancelledBookingStatus.php` | CREATE | Enum |
| 5 | `database/seeders/TransactionTypeSeeder.php` | EDIT | Seeder |
| 6 | `app/Models/Booking.php` | EDIT | Model |
| 7 | `app/Models/CancelledBooking.php` | CREATE | Model |
| 8 | `app/Models/Payment.php` | EDIT | Model |
| 9 | `app/Models/Voucher.php` | EDIT | Model |
| 10 | `app/Services/CostTrackingService.php` | CREATE | Service |
| 11 | `app/Services/CancellationService.php` | CREATE | Service |
| 12 | `app/Http/Controllers/BookingCancellationController.php` | CREATE | Controller |
| 13 | `routes/web.php` | EDIT | Routes |
| 14 | `resources/views/bookings/index.blade.php` | EDIT | View |
| 15 | `resources/views/bookings/show.blade.php` | EDIT | View |
| 16 | `resources/views/pending-refunds/index.blade.php` | CREATE | View |
| 17 | `resources/views/cancelled-bookings/confirm.blade.php` | CREATE | View |
| 18 | `resources/views/reports/booking-cancellation.blade.php` | CREATE | View |
| 19 | `resources/views/partials/nav.php` | EDIT | View |

**Total: 19 files** (9 new, 10 edited)

---

## 18. Implementation Order

| Step | Files | Description |
|---|---|---|
| 1 | 1-3 | Run migrations |
| 2 | 4 | Create `CancelledBookingStatus` enum |
| 3 | 5 | Update `TransactionTypeSeeder` |
| 4 | 6-9 | Update `Booking`; create `CancelledBooking`; update `Payment` and `Voucher` |
| 5 | 10 | Create `CostTrackingService` |
| 6 | 11 | Create `CancellationService` |
| 7 | 12 | Create `BookingCancellationController` |
| 8 | 13 | Register routes |
| 9 | 14 | Update booking index — Status column, Cancel button, cost modal, freeze passenger actions |
| 10 | 15 | Update booking show — freeze payment button + other actions |
| 11 | 16 | Create pending refunds view |
| 12 | 17 | Create refund confirmation view |
| 13 | 18 | Create cancellation report view |
| 14 | 19 | Update navigation |

---

## 19. Key Deviations from Original Plan

| Aspect | Original Plan | Final Plan |
|---|---|---|
| Initiate Permission | Super Admin, Co Admin, Branch Manager, Branch Staff | **Super Admin, Co Admin only** |
| Invoice on Initiate | Not mentioned | **`Invoice.status = CANCELLED`** |
| Invoice on Confirm | `status = CANCELLED`, `balance = 0` | **`status = REFUNDED`, `balance = 0`** |
| `service_charge_deduction` | Non-nullable, default 0 | **Nullable** (null, 0, +, -) |
| Refund Formula | `total_paid - deduction` | **`total_paid - total_cost - deduction`** |
| `total_cost` | Not tracked | **Live-queried** via `CostTrackingService` (not stored) |
| Cost Breakdown | Total Paid only | **Fingerprint + Visa + Ticket costs** per passenger, plus booking summary |
| Payment Button | Not mentioned | **Frozen during PROCESSING, permanently disabled on CANCELLED** |
| Visa/Ticket/Fingerprint | Not mentioned | **Frozen for all cancelled bookings** (both states) |
| New/Edited Files | 17 | **19** |
