# Booking Cancellation — Implementation Plan

## 1. Overview

Implement a booking cancellation workflow that soft-cancels bookings (marks them as cancelled without deleting), records refund and service charge deduction transactions, and provides admin reporting.

### Workflow

```
Cancel Button (booking index)
  → Cancellation Initiation Modal (branch selection, service charge, cost breakdown)
    → Booking marked as cancelled, status = "cancellation processing"
      → Appears in Pending Refund Index (Branch Admins)
        → Revert: undo cancellation, booking becomes active again
        → Confirm: redirect to Refund Confirmation Page
          → Confirmation page: shows totals, editable refund amount, method, currency
            → On submit: 2 Payment+Voucher entries created, status = "cancelled"
              → Booking Cancellation Report (Admin Only) shows all cancelled bookings
```

---

## 2. Migrations

### 2.1 Add `is_cancelled` to `bookings` table

**File:** `database/migrations/2026_07_14_000001_add_is_cancelled_to_bookings_table.php`

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->boolean('is_cancelled')->default(false)->after('remarks');
});
```

### 2.2 Create `cancelled_bookings` table

**File:** `database/migrations/2026_07_14_000002_create_cancelled_bookings_table.php`

```php
Schema::create('cancelled_bookings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')->constrained()->restrictOnDelete();
    $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
    $table->foreignId('user_id')->constrained()->restrictOnDelete(); // who initiated
    $table->decimal('total_paid', 14, 6); // snapshot of invoice->paid_amount at cancellation time
    $table->decimal('service_charge_deduction', 14, 6)->default(0);
    $table->decimal('refund_amount', 14, 6)->default(0);
    $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();
    $table->enum('status', ['cancellation processing', 'cancelled'])->default('cancellation processing');
    // Refund transaction links (populated on confirmation)
    $table->foreignId('deduction_payment_id')->nullable()->constrained('payments')->nullOnDelete();
    $table->foreignId('deduction_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
    $table->foreignId('refund_payment_id')->nullable()->constrained('payments')->nullOnDelete();
    $table->foreignId('refund_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
    $table->timestamps();
});
```

### 2.3 Add `cancelled_booking_id` to `payments` and `vouchers` tables

**File:** `database/migrations/2026_07_14_000003_add_cancelled_booking_id_to_payments_and_vouchers.php`

```php
Schema::table('payments', function (Blueprint $table) {
    $table->foreignId('cancelled_booking_id')->nullable()->after('booking_id')->constrained('cancelled_bookings')->nullOnDelete();
});
Schema::table('vouchers', function (Blueprint $table) {
    $table->foreignId('cancelled_booking_id')->nullable()->after('booking_id')->constrained('cancelled_bookings')->nullOnDelete();
});
```

---

## 3. New Enum

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

## 4. Seeder Update

**File:** `database/seeders/TransactionTypeSeeder.php`

Add one new transaction type (already seeded types remain unchanged):

```php
[
    'name' => 'Service Charge Deduction',
    'type' => 'credit', // company keeps this money
],
```

`Customer Refund` (debit) already exists. No change needed for it.

---

## 5. Model Updates

### 5.1 Booking Model

**File:** `app/Models/Booking.php`

Add to `$fillable`:
```php
'is_cancelled',
```

Add new relationship:
```php
public function cancelledBooking(): HasOne
{
    return $this->hasOne(CancelledBooking::class)->latestOfMany();
}
```

### 5.2 New CancelledBooking Model

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
        'booking_id',
        'invoice_id',
        'user_id',
        'total_paid',
        'service_charge_deduction',
        'refund_amount',
        'cancellation_branch_id',
        'status',
        'deduction_payment_id',
        'deduction_voucher_id',
        'refund_payment_id',
        'refund_voucher_id',
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

### 5.3 Payment Model Update

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

### 5.4 Voucher Model Update

**File:** `app/Models/Voucher.php`

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

---

## 6. Service Layer — CancellationService

**File:** `app/Services/CancellationService.php`

Handles all business logic for cancellation. Uses DB transactions.

### Methods

#### `initiateCancellation(Booking $booking, array $data): CancelledBooking`

Called from the Cancellation Modal. Steps:
1. Validate: booking must not already be cancelled (`is_cancelled === false`)
2. Get invoice via `$booking->invoice`
3. Create `CancelledBooking` record with:
   - `booking_id`, `invoice_id`, `user_id` (auth user)
   - `total_paid` = `$invoice->paid_amount` (snapshot)
   - `service_charge_deduction` = `$data['service_charge_deduction']`
   - `refund_amount` = `$invoice->paid_amount - $data['service_charge_deduction']`
   - `cancellation_branch_id` = `$data['cancellation_branch_id']`
   - `status` = `CancelledBookingStatus::PROCESSING`
4. Set `Booking.is_cancelled = true`
5. Return the `CancelledBooking`

#### `revertCancellation(CancelledBooking $cancelledBooking): void`

Called from Pending Refund Index. Steps:
1. Validate: status must be `PROCESSING`
2. Set `Booking.is_cancelled = false`
3. Delete the `CancelledBooking` record
4. (No payments/vouchers to undo since they haven't been created yet)

#### `confirmCancellation(CancelledBooking $cancelledBooking, array $data): CancelledBooking`

Called from Refund Confirmation Page. Steps (inside DB::transaction):
1. Validate: status must be `PROCESSING`
2. Get booking, invoice, currency rate info
3. Determine payment method and amounts from `$data`
4. **Create Service Charge Deduction Payment + Voucher:**
   - Payment: `amount` = `$data['service_charge_deduction']`, linked to booking/invoice
   - Voucher: `transaction_type_id` = "Service Charge Deduction" type ID, same amount
   - Both: `cancelled_booking_id` = this record's ID
5. **Create Customer Refund Payment + Voucher:**
   - Payment: `amount` = `$data['refund_amount']`, linked to booking/invoice
   - Voucher: `transaction_type_id` = "Customer Refund" type ID, same amount
   - Both: `cancelled_booking_id` = this record's ID
6. **Update CancelledBooking:**
   - `deduction_payment_id`, `deduction_voucher_id`
   - `refund_payment_id`, `refund_voucher_id`
   - `refund_amount` = `$data['refund_amount']` (may have been edited)
   - `service_charge_deduction` = recalculated if refund_amount was decreased
   - `status` = `CancelledBookingStatus::CANCELLED`
7. **Update Invoice:**
   - `status` = `InvoiceStatus::CANCELLED`
   - `balance` = `0`
   - `paid_amount` stays as-is (historical record)
8. Return updated `CancelledBooking`

#### `getCostBreakdown(Booking $booking): array`

Returns data for the initiation modal:
```php
[
    'total_amount' => $invoice->total_amount,
    'total_paid' => $invoice->paid_amount,
    'balance' => $invoice->balance,
    'currency_rate_id' => $booking->currency_rate_id,
    'booking_branch_id' => $booking->booking_branch_id,
    'booking_branch_name' => $booking->bookingBranch?->name,
    'booking_branch_location' => $booking->bookingBranch?->location, // KSA or BD
]
```

---

## 7. Controller — BookingCancellationController

**File:** `app/Http/Controllers/BookingCancellationController.php`

### Routes & Methods

| Method | URL | Name | Purpose |
|---|---|---|---|
| `GET` | `/bookings/{booking}/cancellation/initiate` | `bookings.cancellation.initiate` | Get cost breakdown (JSON for modal) |
| `POST` | `/bookings/{booking}/cancellation/initiate` | `bookings.cancellation.store` | Create cancelled_booking record |
| `POST` | `/cancelled-bookings/{cancelledBooking}/revert` | `cancelled-bookings.revert` | Revert cancellation |
| `GET` | `/cancelled-bookings/{cancelledBooking}/confirm` | `cancelled-bookings.confirm` | Show confirmation page |
| `POST` | `/cancelled-bookings/{cancelledBooking}/confirm` | `cancelled-bookings.confirm.submit` | Process refund |
| `GET` | `/reports/booking-cancellation` | `report.booking-cancellation` | Cancellation report page |
| `GET` | `/api/reports/booking-cancellation` | `report.booking-cancellation.data` | Cancellation report JSON data |
| `GET` | `/pending-refunds` | `pending-refunds.index` | Pending refunds list for branch admins |

### Middleware

| Route Group | Middleware |
|---|---|
| Initiate/Revert/Confirm | `auth`, `role:Super Admin,Co Admin,Branch Manager,Branch Staff` |
| Report | `auth`, `role:Super Admin,Co Admin,Auditor` |
| Pending Refunds | `auth`, `role:Super Admin,Co Admin,Branch Manager` |

---

## 8. Routes

**File:** `routes/web.php`

Add after existing booking routes (around line 120):

```php
// Booking Cancellation
Route::get('/bookings/{booking}/cancellation/initiate', [BookingCancellationController::class, 'initiate'])->name('bookings.cancellation.initiate')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff');
Route::post('/bookings/{booking}/cancellation/initiate', [BookingCancellationController::class, 'store'])->name('bookings.cancellation.store')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff');
Route::post('/cancelled-bookings/{cancelledBooking}/revert', [BookingCancellationController::class, 'revert'])->name('cancelled-bookings.revert')->middleware('role:Super Admin,Co Admin,Branch Manager');
Route::get('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationController::class, 'confirm'])->name('cancelled-bookings.confirm')->middleware('role:Super Admin,Co Admin,Branch Manager');
Route::post('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationController::class, 'confirmSubmit'])->name('cancelled-bookings.confirm.submit')->middleware('role:Super Admin,Co Admin,Branch Manager');

// Pending Refunds
Route::get('/pending-refunds', [BookingCancellationController::class, 'pendingRefunds'])->name('pending-refunds.index')->middleware('role:Super Admin,Co Admin,Branch Manager');

// Booking Cancellation Report
Route::get('/reports/booking-cancellation', [BookingCancellationController::class, 'report'])->name('report.booking-cancellation')->middleware('role:Super Admin,Co Admin,Auditor');
Route::get('/api/reports/booking-cancellation', [BookingCancellationController::class, 'reportData'])->name('report.booking-cancellation.data')->middleware('role:Super Admin,Co Admin,Auditor');
```

---

## 9. Frontend — Booking Index Changes

**File:** `resources/views/bookings/index.blade.php`

### 9.1 Add "Status" Column to Booking Table

**In `<thead>` (after Due column, before Actions):**

Add new `<th>Status</th>` between Due and Actions columns.

**In `<tbody>` (after Due `<td>`, before Actions `<td>`):**

```php
<td class="px-3 py-2">
    @if($booking->is_cancelled)
        @php $cancelledBooking = $booking->cancelledBooking; @endphp
        @if($cancelledBooking && $cancelledBooking->status->value === 'cancellation processing')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Cancellation Processing</span>
        @else
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Cancelled</span>
        @endif
    @else
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
    @endif
</td>
```

**Update colspan** in empty row to account for the new column.

### 9.2 Add "Cancel" Button to Action Column

**In action `<td>` (after View, before Delete):**

```php
@if(!$booking->is_cancelled)
    <button @click="openCancelModal({{ $booking->id }}, '{{ $booking->invoice_id }}')" class="text-orange-600 hover:text-orange-800 font-medium ml-3">Cancel</button>
@endif
```

The Cancel button is only shown for non-cancelled bookings.

### 9.3 Cancellation Initiation Modal

Add modal HTML (following existing modal patterns with `z-[60]`, `x-show`, dark overlay):

**Alpine state variables:**
```javascript
cancelModalVisible: false,
cancelBookingId: null,
cancelInvoiceId: '',
cancelBranches: @json($branches),
cancelBranchId: '{{ auth()->user()->branch_id ?? '' }}',
cancelServiceCharge: 0,
cancelTotalPaid: 0,
cancelBalance: 0,
cancelTotalAmount: 0,
cancelLoading: false,
```

**Alpine methods:**
```javascript
async openCancelModal(bookingId, invoiceId) {
    this.cancelBookingId = bookingId;
    this.cancelInvoiceId = invoiceId;
    this.cancelModalVisible = true;
    // Fetch cost breakdown from API
    const res = await fetch(`/bookings/${bookingId}/cancellation/initiate`);
    const data = await res.json();
    this.cancelTotalAmount = data.total_amount;
    this.cancelTotalPaid = data.total_paid;
    this.cancelBalance = data.balance;
    this.cancelServiceCharge = 0; // default, user enters
    if (data.booking_branch_id) this.cancelBranchId = data.booking_branch_id;
},
closeCancelModal() {
    this.cancelModalVisible = false;
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
                service_charge_deduction: this.cancelServiceCharge,
            }),
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (e) {
        alert('Failed to initiate cancellation');
    } finally {
        this.cancelLoading = false;
    }
},
```

**Modal HTML structure:**
- Title: "Cancel Booking"
- Shows: Invoice ID, Customer Name, Total Amount, Total Paid, Balance
- Input: Branch selection dropdown (pre-filled with booking branch)
- Input: Service Charge Deduction amount (number input)
- Computed display: Refund Amount = Total Paid - Service Charge Deduction
- Buttons: Submit Cancellation (red), Cancel (close modal)

---

## 10. Frontend — Pending Refunds Index

**File:** `resources/views/pending-refunds/index.blade.php`

New page showing bookings with `status = 'cancellation processing'`.

### Structure
- `@extends('layouts.app')`
- Alpine.js component for interactivity
- Table columns: Invoice ID, Customer, Mobile, Branch, Total Paid, Service Charge, Refund Amount, Cancel Date, Actions (Revert / Confirm)
- Filter: Branch filter for branch-scoped users

### Actions
- **Revert button**: POSTs to `/cancelled-bookings/{id}/revert` with confirm dialog
- **Confirm button**: Links to `/cancelled-bookings/{id}/confirm`

---

## 11. Frontend — Refund Confirmation Page

**File:** `resources/views/cancelled-bookings/confirm.blade.php`

### Structure
- `@extends('layouts.app')`
- Shows booking details at top (read-only): Invoice ID, Customer, Total Paid
- Form fields:
  - **Total Payment Till Date** — read-only display
  - **Service Charge** — read-only display (calculated from `service_charge_deduction`)
  - **Refund Amount** — editable number input (default = `total_paid - service_charge_deduction`). Can be DECREASED but NOT INCREASED beyond original value. If decreased, service_charge_deduction auto-adjusts: `service_charge_deduction = total_paid - refund_amount`
  - **Deduction** — read-only display (auto-adjusts based on refund amount)
  - **Method** — dropdown: Cash / Bank (PaymentMethod enum)
  - **Remarks** — textarea, required if Method = Bank
  - **Currency** — dropdown: SAR / BD. Auto-selected based on `cancellationBranch.location`:
    - KSA → SAR
    - BD → BDT
- Submit button: "Confirm Refund"
- On submit: POSTs to `/cancelled-bookings/{id}/confirm`, redirects to pending refunds index with success message

### JavaScript (Alpine)
```javascript
refundAmount: {{ $cancelledBooking->refund_amount }},
totalPaid: {{ $cancelledBooking->total_paid }},
maxRefund: {{ $cancelledBooking->refund_amount }},

get computedDeduction() {
    return Math.max(0, this.totalPaid - this.refundAmount);
},
```

---

## 12. Frontend — Booking Cancellation Report

**File:** `resources/views/reports/booking-cancellation.blade.php`

### Structure
Following existing report pattern (Pattern B — controller + API):

- `@extends('layouts.app')`
- Alpine.js component with `loadData()` fetching from `/api/reports/booking-cancellation`
- Filter bar: Date range, Branch, Status
- Data table with columns:
  - Invoice ID
  - Customer Name
  - Customer Mobile
  - Booking Branch
  - Cancellation Branch
  - Paid (total_paid snapshot)
  - Deduction (service_charge_deduction)
  - Refund (refund_amount)
  - Method (refund payment method)
  - Remarks (refund payment remarks)
  - Cancel Date (cancelled_bookings.created_at)
  - Refund Date (refund voucher created_at)
  - Refunded By (refund voucher user name)
  - Status (cancellation processing / cancelled)

### Controller `reportData()` Method

Joins `cancelled_bookings` with related tables, applies filters, returns paginated JSON with structure:
```json
{
    "data": [...],
    "summary": { "total_paid": ..., "total_deduction": ..., "total_refund": ... },
    "pagination": { "current_page": ..., "last_page": ..., "per_page": ..., "total": ... }
}
```

---

## 13. Navigation Updates

**File:** `resources/views/partials/nav.php` (or wherever the nav is defined)

Add "Pending Refunds" link in the Reports section for roles: Super Admin, Co Admin, Branch Manager.

Add "Booking Cancellation" link in the Reports section for roles: Super Admin, Co Admin, Auditor.

---

## 14. File Summary

| # | File | Action | Description |
|---|---|---|---|
| 1 | `database/migrations/2026_07_14_000001_add_is_cancelled_to_bookings_table.php` | CREATE | Add `is_cancelled` boolean to bookings |
| 2 | `database/migrations/2026_07_14_000002_create_cancelled_bookings_table.php` | CREATE | New `cancelled_bookings` table |
| 3 | `database/migrations/2026_07_14_000003_add_cancelled_booking_id_to_payments_and_vouchers.php` | CREATE | Add FK to payments and vouchers |
| 4 | `app/Enums/CancelledBookingStatus.php` | CREATE | New enum for cancellation status |
| 5 | `database/seeders/TransactionTypeSeeder.php` | EDIT | Add "Service Charge Deduction" (credit) |
| 6 | `app/Models/Booking.php` | EDIT | Add `is_cancelled` to fillable, add `cancelledBooking()` relationship |
| 7 | `app/Models/CancelledBooking.php` | CREATE | New model |
| 8 | `app/Models/Payment.php` | EDIT | Add `cancelled_booking_id` to fillable, add relationship |
| 9 | `app/Models/Voucher.php` | EDIT | Add `cancelled_booking_id` to fillable, add relationship |
| 10 | `app/Services/CancellationService.php` | CREATE | Business logic service |
| 11 | `app/Http/Controllers/BookingCancellationController.php` | CREATE | Controller for all cancellation routes |
| 12 | `routes/web.php` | EDIT | Add cancellation, pending refund, and report routes |
| 13 | `resources/views/bookings/index.blade.php` | EDIT | Add Status column, Cancel button, Cancellation Modal |
| 14 | `resources/views/pending-refunds/index.blade.php` | CREATE | Pending refunds list page |
| 15 | `resources/views/cancelled-bookings/confirm.blade.php` | CREATE | Refund confirmation page |
| 16 | `resources/views/reports/booking-cancellation.blade.php` | CREATE | Cancellation report page |
| 17 | `resources/views/partials/nav.php` | EDIT | Add nav links for new pages |

---

## 15. Implementation Order

1. **Migrations** (files 1-3)
2. **Enum** (file 4)
3. **Seeder** (file 5)
4. **Models** (files 6-9)
5. **Service** (file 10)
6. **Controller** (file 11)
7. **Routes** (file 12)
8. **Booking Index view changes** (file 13) — Status column, Cancel button, Modal
9. **Pending Refunds page** (file 14)
10. **Refund Confirmation page** (file 15)
11. **Cancellation Report** (file 16)
12. **Navigation** (file 17)

---

## 16. Permissions Summary

| Action | Roles |
|---|---|
| Initiate Cancellation | Super Admin, Co Admin, Branch Manager, Branch Staff |
| Revert Cancellation | Super Admin, Co Admin, Branch Manager |
| Confirm / Process Refund | Super Admin, Co Admin, Branch Manager |
| View Pending Refunds | Super Admin, Co Admin, Branch Manager |
| View Cancellation Report | Super Admin, Co Admin, Auditor |
| Delete Booking (existing) | Super Admin |
