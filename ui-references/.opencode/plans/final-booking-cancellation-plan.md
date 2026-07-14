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

## 9. Controllers (Split for Parallelism)

Two controllers, no shared file, no merge conflict.

### 9.1 Action Controller — Track A

**File:** `app/Http/Controllers/BookingCancellationActionController.php`

| Method | Route | Purpose |
|---|---|---|
| `store()` | `POST /bookings/{booking}/cancellation/initiate` | Create CancelledBooking |
| `revert()` | `POST /cancelled-bookings/{cancelledBooking}/revert` | Revert cancellation |
| `confirmSubmit()` | `POST /cancelled-bookings/{cancelledBooking}/confirm` | Process refund |
| `reportData()` | `GET /api/reports/booking-cancellation` | Return report JSON |

### 9.2 View Controller — Track B

**File:** `app/Http/Controllers/BookingCancellationViewController.php`

| Method | Route | Purpose |
|---|---|---|
| `initiate()` | `GET /bookings/{booking}/cancellation/initiate` | Return JSON cost breakdown |
| `confirm()` | `GET /cancelled-bookings/{cancelledBooking}/confirm` | Show refund confirmation view |
| `pendingRefunds()` | `GET /pending-refunds` | Show pending refunds list |
| `report()` | `GET /reports/booking-cancellation` | Show report view |

### Stub Strategy (Track A Day 1 — Unblocks Track B)

Before implementing real service logic, Track A creates stub action methods returning placeholder responses so Track B has live routes to develop against from hour 1:

```php
// Stub — BookingCancellationActionController@store
public function store(Request $request, Booking $booking)
{
    return response()->json([
        'success' => true,
        'message' => 'Cancellation initiated',
        'data' => ['id' => 1, 'status' => 'cancellation processing', 'refund_amount' => 20500.00],
    ]);
}
```

---

## 10. Routes (Dedicated File)

**File:** `routes/booking-cancellation.php` (new)

No merge conflicts on `web.php` between tracks.

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingCancellationViewController;
use App\Http\Controllers\BookingCancellationActionController;

// ─── View Routes (Track B) ───
Route::get('/bookings/{booking}/cancellation/initiate', [BookingCancellationViewController::class, 'initiate'])
    ->name('bookings.cancellation.initiate')->middleware('role:Super Admin,Co Admin');
Route::get('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationViewController::class, 'confirm'])
    ->name('cancelled-bookings.confirm')->middleware('role:Super Admin,Co Admin,Branch Manager');
Route::get('/pending-refunds', [BookingCancellationViewController::class, 'pendingRefunds'])
    ->name('pending-refunds.index')->middleware('role:Super Admin,Co Admin,Branch Manager');
Route::get('/reports/booking-cancellation', [BookingCancellationViewController::class, 'report'])
    ->name('report.booking-cancellation')->middleware('role:Super Admin,Co Admin,Auditor');

// ─── Action Routes (Track A) ───
Route::post('/bookings/{booking}/cancellation/initiate', [BookingCancellationActionController::class, 'store'])
    ->name('bookings.cancellation.store')->middleware('role:Super Admin,Co Admin');
Route::post('/cancelled-bookings/{cancelledBooking}/revert', [BookingCancellationActionController::class, 'revert'])
    ->name('cancelled-bookings.revert')->middleware('role:Super Admin,Co Admin,Branch Manager');
Route::post('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationActionController::class, 'confirmSubmit'])
    ->name('cancelled-bookings.confirm.submit')->middleware('role:Super Admin,Co Admin,Branch Manager');
Route::get('/api/reports/booking-cancellation', [BookingCancellationActionController::class, 'reportData'])
    ->name('report.booking-cancellation.data')->middleware('role:Super Admin,Co Admin,Auditor');
```

**Registration in `app/Providers/RouteServiceProvider.php`:**

```php
Route::middleware('web')
    ->group(base_path('routes/booking-cancellation.php'));
```

---

## 11. Booking Index View — Changes (Track B)

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

```html
@if(!$booking->is_cancelled && (auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Co Admin')))
    <button @click="openCancelModal({{ $booking->id }})" class="text-orange-600 hover:text-orange-800 font-medium ml-3">
        Cancel
    </button>
@endif
```

### 11.3 Cancellation Initiation Modal

**Alpine state:**
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
closeCancelModal() { this.cancelModalVisible = false; },
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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ cancellation_branch_id: this.cancelBranchId, service_charge_deduction: this.cancelServiceCharge === '' ? null : this.cancelServiceCharge }),
        });
        const data = await res.json();
        if (data.success) window.location.reload();
    } catch (e) { alert('Failed to initiate cancellation');
    } finally { this.cancelLoading = false; }
},
```

**Modal layout:**
```
Title: "Cancel Booking"
Invoice: #123   Customer: John Doe
Total Amount: 100,000.00 | Total Paid: 85,000.00 | Balance: 15,000.00
  ── Costs Incurred ──
  Fingerprint: 2,500.00 | Visa: 12,000.00 | Ticket: 45,000.00
  Total Cost:  59,500.00
Branch: [dropdown] | Service Charge: [input, nullable]
Refund Amount: 20,500.00 (auto)
[Submit Cancellation] [Cancel]
```

### 11.4 Freeze Per-Passenger Actions

Visa Submit/Issue/Edit/Cancel/Resubmit and Ticket Issue/Edit buttons check `booking.is_cancelled`. When true, replaced with muted indicator or hidden.

---

## 12. Booking Show View — Payment Freeze (Track B)

**File:** `resources/views/bookings/show.blade.php` (line ~237)

```html
@php
    $isPaymentFrozen = $booking->is_cancelled;
    $cancelledStatus = $booking->cancelledBooking?->status?->value;
    $isPermanentlyDisabled = $isPaymentFrozen && $cancelledStatus === 'cancelled';
@endphp
<button @click="!isPaymentFrozen && openPaymentModal()"
        :disabled="{{ $isPaymentFrozen ? 'true' : 'false' }}"
        class="px-6 py-3 rounded-lg font-medium transition {{ $isPaymentFrozen ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700' }}"
        title="{{ $isPermanentlyDisabled ? 'Booking Cancelled' : ($isPaymentFrozen ? 'Cancellation Processing' : '') }}">
    Payment
</button>
```

Also freeze Request Re-Issue, Request Add. Tkt, Request Ticket Refund buttons when `$booking->is_cancelled`.

---

## 13. Pending Refunds Index — New View (Track B)

**File:** `resources/views/pending-refunds/index.blade.php`

- Lists `cancelled_bookings` with `status = 'cancellation processing'`
- Columns: Invoice ID, Customer, Mobile, Branch, Total Paid, Service Charge, Refund Amount, Cancel Date
- Branch filter
- Actions: **Revert** (POST), **Confirm** (link)

---

## 14. Refund Confirmation Page — New View (Track B)

**File:** `resources/views/cancelled-bookings/confirm.blade.php`

- Booking details (read-only): Invoice ID, Customer, Total Paid
- Cost breakdown display: Fingerprint, Visa, Ticket costs
- Editable **Refund Amount** (default = `total_paid - total_cost - service_charge_deduction`)
- **Service Charge Deduction** (read-only, nullable display)
- **Deduction** (read-only, auto-adjusts)
- **Method**: Cash / Bank
- **Remarks**: textarea (required if Bank)
- **Currency**: auto-selected by branch location (KSA→SAR, BD→BDT)
- Submit POSTs to `/cancelled-bookings/{id}/confirm`

---

## 15. Cancellation Report — New View (Track B)

**File:** `resources/views/reports/booking-cancellation.blade.php`

- Alpine + API pattern
- Filters: Date range, Branch, Status
- Columns: Invoice ID, Customer, Mobile, Booking Branch, Cancellation Branch, Paid, Deduction, Refund, Method, Remarks, Cancel Date, Refund Date, Refunded By, Status
- API returns `{ data, summary: { total_paid, total_deduction, total_refund }, pagination }`

---

## 16. Navigation Updates (Track B)

**File:** `resources/views/partials/nav.php`

- **Pending Refunds** link → Super Admin, Co Admin, Branch Manager
- **Booking Cancellation** link (Reports) → Super Admin, Co Admin, Auditor

---

## 17. Setup Sprint — Person 1 (Prerequisites, ~4 hours)

Deliverables both tracks depend on.

### Step 1 — Migrations

Files: 1, 2, 3 (see file list). Run `php artisan migrate`.

### Step 2 — Enum

File: 4. Create `CancelledBookingStatus` enum.

### Step 3 — Seeder

File: 5. Add "Service Charge Deduction" to `TransactionTypeSeeder`. Run `php artisan db:seed --class=TransactionTypeSeeder`.

### Step 4 — Models

Files: 6, 7, 8, 9. Update Booking fillable/relationship, create CancelledBooking model, update Payment and Voucher.

### Step 5 — Route File + Registration

File: 13 (new `routes/booking-cancellation.php`) + 14 (edit `RouteServiceProvider.php`).

Created once. After this, both Track A and Track B have working route targets.

---

## 18. Track A — Person 1 Continues (Backend Services + Actions)

### Step A1 — Stub Action Controller (1 hour)

Create `BookingCancellationActionController` with stub methods returning realistic placeholder JSON. This unblocks Track B immediately.

### Step A2 — CostTrackingService

File: 10. Implement per-passenger and booking-level cost summary. Live queries, no storage.

### Step A3 — CancellationService

File: 11. Implement initiate/revert/confirm business logic with DB transactions.

### Step A4 — Real Action Controller

File: 12. Replace stubs in `BookingCancellationActionController` with real implementations calling `CancellationService` and `CostTrackingService`.

**Deliverable:** All 4 action endpoints working with real data.
**Files:** 3 new (service, service, controller), 1 stubbed then replaced.

---

## 19. Track B — Person 2 Starts Day 1 (Frontend)

Develops against stubs from Step A1. Wires to real endpoints as Track A delivers them.

### Step B1 — View Controller

File: 15. Create `BookingCancellationViewController` with 4 methods returning Blade views.

### Step B2 — Booking Index View

File: 16. Add Status column, Cancel button, initiation modal with cost breakdown, freeze per-passenger actions.

### Step B3 — Booking Show View

File: 17. Freeze payment button and visa/ticket/fingerprint action buttons.

### Step B4 — Pending Refunds Page

File: 18. New view listing PROCESSING cancellations with Revert/Confirm actions.

### Step B5 — Refund Confirmation Page

File: 19. New view with editable refund amount, method/currency selection.

### Step B6 — Cancellation Report Page

File: 20. New view with Alpine DataTable and filter bar.

### Step B7 — Navigation

File: 21. Add nav links for Pending Refunds and Booking Cancellation Report.

**Deliverable:** All 7 views rendering. Works end-to-end once Track A delivers real endpoints.
**Files:** 4 new, 3 edited.

---

## 20. Dependency Graph

```
Day 1 ─── Setup Sprint (Person 1, ~4 hrs)
              │
              ├── migrations + enum + seeder + models + route file
              │
              ├──▶ Track A continues (Person 1)
              │      A1. Stub action controller ──┐ (1 hr)
              │      A2. CostTrackingService       │
              │      A3. CancellationService       │
              │      A4. Real action controller    │
              │                                    │
              └──▶ Track B starts (Person 2)       │
                     B1. View controller           │
                     B2-B6. All 5 views           ◄┘ (uses stubs, later real endpoints)
                     B7. Navigation updates
```

Both tracks start day 1. Track B never waits — stubs bridge until Track A finishes.

---

## 21. Complete File List

| # | File | Action | Type | Step |
|---|---|---|---|---|
| 1 | `database/migrations/*_add_is_cancelled_to_bookings_table.php` | CREATE | Migration | **S1** |
| 2 | `database/migrations/*_create_cancelled_bookings_table.php` | CREATE | Migration | **S1** |
| 3 | `database/migrations/*_add_cancelled_booking_id_to_payments_and_vouchers.php` | CREATE | Migration | **S1** |
| 4 | `app/Enums/CancelledBookingStatus.php` | CREATE | Enum | **S2** |
| 5 | `database/seeders/TransactionTypeSeeder.php` | EDIT | Seeder | **S3** |
| 6 | `app/Models/Booking.php` | EDIT | Model | **S4** |
| 7 | `app/Models/CancelledBooking.php` | CREATE | Model | **S4** |
| 8 | `app/Models/Payment.php` | EDIT | Model | **S4** |
| 9 | `app/Models/Voucher.php` | EDIT | Model | **S4** |
| 10 | `app/Services/CostTrackingService.php` | CREATE | Service | **A2** |
| 11 | `app/Services/CancellationService.php` | CREATE | Service | **A3** |
| 12 | `app/Http/Controllers/BookingCancellationActionController.php` | CREATE | Controller | **A1/A4** |
| 13 | `routes/booking-cancellation.php` | CREATE | Routes | **S5** |
| 14 | `app/Providers/RouteServiceProvider.php` | EDIT | Registration | **S5** |
| 15 | `app/Http/Controllers/BookingCancellationViewController.php` | CREATE | Controller | **B1** |
| 16 | `resources/views/bookings/index.blade.php` | EDIT | View | **B2** |
| 17 | `resources/views/bookings/show.blade.php` | EDIT | View | **B3** |
| 18 | `resources/views/pending-refunds/index.blade.php` | CREATE | View | **B4** |
| 19 | `resources/views/cancelled-bookings/confirm.blade.php` | CREATE | View | **B5** |
| 20 | `resources/views/reports/booking-cancellation.blade.php` | CREATE | View | **B6** |
| 21 | `resources/views/partials/nav.php` | EDIT | View | **B7** |

**Total: 21 files** — 10 new (S1-S5: 6, A2-A3: 2, B4-B6: 2), 11 edited (S4: 3, S5: 1, A1/A4: 1, B2-B3: 3, B7: 1)

---

## 22. Key Deviations from Original Plan

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
| Controllers | 1 | **2** (split for parallelism) |
| Routes file | `web.php` (edit) | **`booking-cancellation.php`** (new, dedicated) |
| New/Edited Files | 17 | **21** (with route service provider) |
