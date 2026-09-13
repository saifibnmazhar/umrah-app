# Plan: Refund Payable Payment System (Passenger-Level)

## Problem

Three gaps exist:

1. **`paid_amount` calculation is fragile** — uses column-based exclusions (`whereNull`)
   instead of filtering by voucher transaction type. Every new payment type requires a
   new `whereNull` clause.
2. **No way to directly pay refund payable** — `passenger.refund_payable` can only be
   consumed via re-issue adjustment or passenger cancellation. There is no direct
   cash/bank payout path.
3. **No refund payment tracking on passengers** — no status, no branch assignment,
   no payment confirmation flow.

---

## Solution Overview

1. Fix `paid_amount` to only count vouchers with transaction type `'Initial Payment'`
   and `'Due Collection'` (whitelist approach).
2. Add `refund_payment_status` and `refund_payment_branch_id` to the `passengers` table.
3. Add "Pay Refund" button in bookings page 3-dot menu → modal with refund payable
   (read-only) + branch selector.
4. Add "Ticket Refunds" tab in Pending Refunds page → shows passengers with
   `refund_payment_status = 'processing'` → Confirm/Revert buttons.
5. Add `RefundPaymentStatus` enum: `pending`, `processing`, `paid`.

---

## Access Control

| Action | Roles |
|--------|-------|
| Assign branch (Pay Refund) | Super Admin, Co Admin, Ticket Admin |
| Confirm / Revert payment | Super Admin, Co Admin, Branch Manager, Fingerprint Admin |

---

## Security & Reliability Measures

| Concern | Mitigation |
|---------|------------|
| Race condition (concurrent payments) | `lockForUpdate()` on passenger row inside the transaction |
| Rate limiting | `throttle:5,1` middleware on the routes (5 requests per minute) |
| Branch-level access | Pending Refunds filtered by `refund_payment_branch_id` for non-admin users |
| Null safety | Validates invoice and currency_rate exist before proceeding |
| Paid amount integrity | Only `'Initial Payment'` and `'Due Collection'` vouchers count toward `paid_amount` |
| Amount integrity | Refund payable is read-only — branch users cannot edit the amount |

---

## File Changes (11 files)

| # | File | Action |
|---|------|--------|
| 1 | `app/Enums/RefundPaymentStatus.php` | **New** |
| 2 | `database/migrations/2026_09_03_000002_add_refund_payment_fields_to_passengers_table.php` | **New** |
| 3 | `app/Models/Passenger.php` | **Edit** |
| 4 | `app/Services/InvoiceService.php` | **Edit** |
| 5 | `app/Http/Controllers/RefundController.php` | **Edit** — add 3 methods |
| 6 | `app/Http/Controllers/BookingCancellationViewController.php` | **Edit** |
| 7 | `routes/web.php` | **Edit** |
| 8 | `resources/views/pending-refunds/index.blade.php` | **Edit** |
| 9 | `resources/views/bookings/index.blade.php` | **Edit** |
| 10 | `app/Console/Commands/VerifyRefundPayableCommand.php` | **Edit** |
| 11 | `tests/Feature/RefundPaymentTest.php` | **New** |

---

## Corrected Flow

```
1. Ticket Refunded (existing behavior, unchanged)
   → passenger.refund_payable INCREASED by customer_refund
   → refund_payment_status = null (no change at refund time)

2. "Pay Refund" Button (bookings index, 3-dot menu per passenger)
   → Modal shows passenger's refund_payable (READ-ONLY)
   → Admin selects a branch
   → POST /passengers/{id}/refund-pay-assign-branch
   → refund_payment_status → 'processing'
   → refund_payment_branch_id → selected branch

3. Pending Refunds Page → Ticket Refunds Tab
   → Shows passengers where refund_payment_status = 'processing'
   → Filtered by refund_payment_branch_id for non-admin users
   → Each row: passenger name, booking, refund payable, branch, Confirm/Revert

4. Confirm Button (Pending Refunds page)
   → Modal shows: passenger name, refund payable (READ-ONLY)
   → Branch user selects payment method + remarks (cannot edit amount)
   → POST /passengers/{id}/refund-pay-confirm
   → Creates Payment + Voucher (type: 'Ticket Refund - Payment')
   → passenger.refund_payable DECREASED by full refund_payable
   → refund_payment_status → 'paid'

5. Revert Button (Pending Refunds page)
   → POST /passengers/{id}/refund-pay-revert
   → refund_payment_status → 'pending'
   → refund_payment_branch_id → null
```

---

## Change 1: New `app/Enums/RefundPaymentStatus.php`

Type-safe enum for refund payment status. Follows existing pattern
(see `CancelledBookingStatus`, `PaymentBy`).

```php
<?php

namespace App\Enums;

enum RefundPaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
}
```

---

## Change 2: New Migration — `refund_payment_fields` on `passengers`

**New file:** `database/migrations/2026_09_03_000002_add_refund_payment_fields_to_passengers_table.php`

Adds branch tracking and payment status to the passengers table. The refund is
passenger-specific (not ticket-specific).

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->foreignId('refund_payment_branch_id')->nullable()->after('refund_payable')
                ->constrained('branches')->nullOnDelete();
            $table->string('refund_payment_status')->nullable()->after('refund_payment_branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropForeign(['refund_payment_branch_id']);
            $table->dropColumn(['refund_payment_branch_id', 'refund_payment_status']);
        });
    }
};
```

---

## Change 3: Update `app/Models/Passenger.php`

Add new fields to `$fillable`, cast `refund_payment_status` to enum, add
`refundPaymentBranch()` relationship, add `refundPayablePayments()` relationship,
and update `verifyRefundPayable()`.

```php
// Add to $fillable (after 'refund_payable'):
'refund_payment_branch_id',
'refund_payment_status',

// Add to $casts:
'refund_payment_status' => \App\Enums\RefundPaymentStatus::class,

// Add relationship (after reIssueSettlements() at line 149):
public function refundPaymentBranch(): BelongsTo
{
    return $this->belongsTo(Branch::class, 'refund_payment_branch_id');
}

public function refundPayablePayments(): HasMany
{
    return $this->hasMany(Payment::class, 'refund_payable_id')
        ->whereHas('vouchers.transactionType', function ($q) {
            $q->where('name', 'Ticket Refund - Payment');
        });
}

// Update verifyRefundPayable() (lines 151-157):
public function verifyRefundPayable(): float
{
    $refunds = (float) $this->refundedTickets()->sum('refund_to_customer');
    $settlements = (float) $this->reIssueSettlements()->sum('amount');
    $refundPayablePayments = (float) $this->refundPayablePayments()->sum('amount');

    return max(0, $refunds - $settlements - $refundPayablePayments);
}
```

---

## Change 4: Update `app/Services/InvoiceService.php`

**File:** `app/Services/InvoiceService.php:36-41`

Replace column-based exclusions with voucher transaction type filtering. Only
payments with `'Initial Payment'` or `'Due Collection'` vouchers count as paid.
This automatically excludes refund payments, re-issue adjustments, cancellations,
and agent payments.

```php
// Current code (lines 36-41):
$invoice->paid_amount = $invoice->payments()
    ->whereNull('cancelled_booking_id')
    ->whereNull('cancelled_passenger_id')
    ->whereNull('refunded_ticket_id')
    ->whereNull('re_issued_ticket_id')
    ->sum('amount');

// Change to:
$invoice->paid_amount = $invoice->payments()
    ->whereHas('vouchers.transactionType', function ($q) {
        $q->whereIn('name', ['Initial Payment', 'Due Collection']);
    })
    ->sum('amount');
```

**Why:** Whitelist approach. Self-documenting. The dashboard already uses this
pattern at `DashboardController.php:211`.

---

## Change 5: Update `app/Http/Controllers/RefundController.php`

Add three new methods: `assignBranch`, `confirm`, `revert`. The existing `store()`
and `byBooking()` methods remain unchanged — no branch selection during refund
initialization.

```php
<?php

// Add imports:
use App\Enums\RefundPaymentStatus;
use App\Models\Payment;
use App\Models\TransactionType;
use App\Services\VoucherService;

class RefundController extends Controller
{
    // ... existing store() and byBooking() methods unchanged ...

    public function assignBranch(Request $request, Passenger $passenger)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        if ((float) $passenger->refund_payable <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger has no refund payable balance.',
            ], 422);
        }

        if ($passenger->refund_payment_status !== null
            && $passenger->refund_payment_status !== RefundPaymentStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Refund payment is already in progress or completed.',
            ], 422);
        }

        $passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $validated['branch_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refund payment branch assigned successfully.',
        ]);
    }

    public function confirm(Request $request, Passenger $passenger)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,bank',
            'remarks' => 'nullable|string|max:500',
        ]);

        if ($passenger->refund_payment_status !== RefundPaymentStatus::PROCESSING) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger is not in processing status.',
            ], 422);
        }

        $booking = $passenger->booking;
        $invoice = $booking->invoice;
        $amount = (float) $passenger->refund_payable;

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Refund payable balance is zero.',
            ], 422);
        }

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Booking has no invoice.',
            ], 422);
        }

        return DB::transaction(function () use ($passenger, $booking, $invoice, $amount, $validated) {
            // Lock the passenger row to prevent race conditions
            $passenger = Passenger::lockForUpdate()->find($passenger->id);

            $transactionType = TransactionType::where('name', 'Ticket Refund - Payment')->first();

            if (! $transactionType) {
                throw new \RuntimeException('Transaction type "Ticket Refund - Payment" not found.');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'branch_id' => $passenger->refund_payment_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $booking->currency_rate_id,
                'payment_date' => now(),
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'bdt_amount' => 0,
                'passenger_id' => $passenger->id,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $voucher = app(VoucherService::class)->createVoucher([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'branch_id' => $passenger->refund_payment_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $booking->currency_rate_id,
                'transaction_type_id' => $transactionType->id,
                'payment_date' => now(),
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'bdt_amount' => 0,
                'notes' => $validated['remarks'] ?? null,
            ]);

            $passenger->decreaseRefundPayable($amount);

            $passenger->update([
                'refund_payment_status' => RefundPaymentStatus::PAID,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund payment processed successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'voucher_id' => $voucher->id,
                    'amount' => $amount,
                ],
            ]);
        });
    }

    public function revert(Passenger $passenger)
    {
        if ($passenger->refund_payment_status !== RefundPaymentStatus::PROCESSING) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger is not in processing status.',
            ], 422);
        }

        $passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PENDING,
            'refund_payment_branch_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refund payment reverted to pending.',
        ]);
    }
}
```

**Key details:**
- `assignBranch`: passenger must have `refund_payable > 0` and status must be `null` or `pending`
- `confirm`: uses `lockForUpdate()` on passenger row to prevent race conditions; creates Payment + Voucher; amount is the full `refund_payable` (read-only)
- `revert`: only allowed from `processing`; clears branch, sets status to `pending`

---

## Change 6: Update `app/Http/Controllers/BookingCancellationViewController.php`

**File:** `app/Http/Controllers/BookingCancellationViewController.php:62-102`

Query passengers with `refund_payment_status = 'processing'` for the new
"Ticket Refunds" tab. Filtered by branch for non-admin users.

```php
// Add imports:
use App\Enums\RefundPaymentStatus;
use App\Models\Passenger as PassengerModel;

// Add inside pendingRefunds() method (after $cancelledPassengers query):
$ticketRefundQuery = PassengerModel::with([
    'booking.customer',
    'booking.bookingBranch',
    'refundPaymentBranch',
])->where('refund_payment_status', RefundPaymentStatus::PROCESSING)
    ->where('refund_payable', '>', 0);

if (auth()->user()->branch_id) {
    $ticketRefundQuery->where('refund_payment_branch_id', auth()->user()->branch_id);
}

if ($request->filled('branch_id')) {
    $ticketRefundQuery->where('refund_payment_branch_id', $request->branch_id);
}

$ticketRefunds = $ticketRefundQuery->latest()->paginate(20)->withQueryString();

// Pass $ticketRefunds to view (update line 102):
return view('pending-refunds.index', compact(
    'cancelledBookings', 'cancelledPassengers', 'ticketRefunds', 'branches', 'tab'
));
```

---

## Change 7: Add Routes to `routes/web.php`

```php
// Add import at top:
use App\Http\Controllers\RefundController;

// Add routes (in the passenger routes group):
Route::post('/passengers/{passenger}/refund-pay-assign-branch', [RefundController::class, 'assignBranch'])
    ->name('passengers.refund-pay-assign-branch')
    ->middleware(['role:Super Admin,Co Admin,Ticket Admin', 'throttle:5,1']);

Route::post('/passengers/{passenger}/refund-pay-confirm', [RefundController::class, 'confirm'])
    ->name('passengers.refund-pay-confirm')
    ->middleware(['role:Super Admin,Co Admin,Branch Manager,Fingerprint Admin', 'throttle:5,1']);

Route::post('/passengers/{passenger}/refund-pay-revert', [RefundController::class, 'revert'])
    ->name('passengers.refund-pay-revert')
    ->middleware(['role:Super Admin,Co Admin,Branch Manager,Fingerprint Admin', 'throttle:5,1']);
```

---

## Change 8: Update `resources/views/pending-refunds/index.blade.php`

### 8a) Add third tab link (after "Passenger Cancellations" tab, line 30)

```blade
<a href="?tab=tickets{{ request('branch_id') ? '&branch_id=' . request('branch_id') : '' }}"
   class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'tickets' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
    Ticket Refunds
</a>
```

### 8b) Add Ticket Refunds tab content (after the passengers tab `@endif`)

```blade
@if($tab === 'tickets')
<div class="overflow-auto flex-1 min-h-0" style="max-height: calc(95vh - 260px);">
    <table class="w-full min-w-[900px] text-sm">
        <thead class="bg-slate-50 text-slate-600 sticky top-0 z-10">
            <tr>
                <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                <th class="px-3 py-2 text-left font-medium">Customer</th>
                <th class="px-3 py-2 text-left font-medium">Passenger</th>
                <th class="px-3 py-2 text-left font-medium">Booking Branch</th>
                <th class="px-3 py-2 text-left font-medium">Refund Branch</th>
                <th class="px-3 py-2 text-right font-medium">Refund Payable</th>
                <th class="px-3 py-2 text-center font-medium">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            @forelse($ticketRefunds as $tp)
            <tr>
                <td class="px-3 py-2 text-slate-700">{{ $tp->booking?->invoice_id ?? '—' }}</td>
                <td class="px-3 py-2 text-slate-700">{{ $tp->booking?->customer?->name ?? 'N/A' }}</td>
                <td class="px-3 py-2 text-slate-700">{{ trim(($tp->first_name ?? '') . ' ' . ($tp->last_name ?? '')) ?: '—' }}</td>
                <td class="px-3 py-2 text-slate-700">{{ $tp->booking?->bookingBranch?->name ?? '—' }}</td>
                <td class="px-3 py-2 text-slate-700">{{ $tp->refundPaymentBranch?->name ?? '—' }}</td>
                <td class="px-3 py-2 text-slate-800 font-medium text-right">@currency($tp->refund_payable, 2)</td>
                <td class="px-3 py-2 text-center whitespace-nowrap">
                    <form method="POST" action="{{ route('passengers.refund-pay-revert', $tp->id) }}"
                          onsubmit="return confirm('Revert this refund payment? Status will return to pending.')" class="inline">
                        @csrf
                        <button type="submit" class="text-xs bg-amber-100 hover:bg-amber-200 text-amber-600 px-2 py-1 rounded font-medium">
                            Revert
                        </button>
                    </form>
                    <button onclick="openConfirmRefundModal({{ $tp->id }}, '{{ addslashes(trim(($tp->first_name ?? '') . ' ' . ($tp->last_name ?? ''))) }}', {{ $tp->refund_payable }})"
                        class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded font-medium ml-1">
                        Confirm
                    </button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-3 py-4 text-center text-slate-500">No pending ticket refunds found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $ticketRefunds->links() }}</div>
@endif
```

### 8c) Add Confirm Refund Modal (before `@endsection`)

```blade
{{-- Confirm Refund Modal --}}
<div id="confirmRefundModal" class="hidden fixed inset-0 z-[9999] bg-black/50 flex items-center justify-center p-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-1">Confirm Refund Payment</h3>
        <p class="text-sm text-slate-500 mb-4">Process refund payment for this passenger.</p>

        <div class="space-y-2 text-sm mb-4 p-3 bg-slate-50 rounded-lg">
            <div class="flex justify-between">
                <span class="text-slate-500">Passenger</span>
                <span class="font-medium text-slate-700" id="confirmRefundPassengerName"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Refund Payable</span>
                <span class="font-semibold text-blue-600" id="confirmRefundAmount"></span>
            </div>
        </div>

        <form id="confirmRefundForm" onsubmit="submitConfirmRefund(event)" class="space-y-4">
            <input type="hidden" name="passenger_id" id="confirmRefundPassengerId">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method *</label>
                <select name="payment_method" required class="w-full px-4 py-2 border border-slate-300 rounded-lg">
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                <textarea name="remarks" rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg" placeholder="Enter remarks"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">Confirm Payment</button>
                <button type="button" onclick="document.getElementById('confirmRefundModal').classList.add('hidden')" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>
```

### 8d) Add JavaScript functions (before `@endsection`)

```blade
<script>
function openConfirmRefundModal(passengerId, name, amount) {
    document.getElementById('confirmRefundPassengerId').value = passengerId;
    document.getElementById('confirmRefundPassengerName').textContent = name;
    document.getElementById('confirmRefundAmount').textContent = new Intl.NumberFormat('en-SA', { minimumFractionDigits: 2 }).format(amount);
    document.getElementById('confirmRefundModal').classList.remove('hidden');
}

async function submitConfirmRefund(e) {
    e.preventDefault();
    const form = e.target;
    const passengerId = form.passenger_id.value;
    if (!confirm('Confirm this refund payment?')) return;

    const res = await fetch(`/passengers/${passengerId}/refund-pay-confirm`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            payment_method: form.payment_method.value,
            remarks: form.remarks.value || null,
        }),
    });
    const result = await res.json();
    if (result.success) {
        window.location.reload();
    } else {
        alert(result.message || 'Failed to confirm refund payment.');
    }
}
</script>
```

---

## Change 9: Update `resources/views/bookings/index.blade.php`

### 9a) Add role check variable (~line 688)

After the `$canConfirmCancellation` line:

```php
$canPayRefundPayable = auth()->user()->hasAnyRole(['Super Admin', 'Co Admin', 'Ticket Admin']);
```

### 9b) Add "Pay Refund" button in 3-dot dropdown (~line 1486)

After the "View Tickets" button, inside the dropdown. The button only shows when
the passenger has a refund payable balance AND the refund payment has not been
initiated (status is null or pending).

```blade
@if($canPayRefundPayable)
    <template x-if="passengersTicketData[{{ $loop->index }}]?.refund_payable > 0 && (!passengersTicketData[{{ $loop->index }}]?.refund_payment_status || passengersTicketData[{{ $loop->index }}]?.refund_payment_status === 'pending')">
        <button @click="open = false; openPayRefundModal({{ $loop->index }})"
            class="px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-slate-50 transition text-left">
            Pay Refund
        </button>
    </template>
@endif
```

### 9c) Add Pay Refund Modal HTML (~after Cancel Passenger Modal at line 3192)

The modal shows the passenger's refund payable (READ-ONLY — branch users cannot
edit the amount) and a branch selector.

```blade
{{-- Pay Refund Modal --}}
<div x-show="payRefundModalVisible" x-cloak
     class="fixed inset-0 z-[9999] bg-black/50 flex items-center justify-center p-4"
     @click.self="closePayRefundModal()"
     @keydown.escape.window="closePayRefundModal()">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-semibold text-slate-800 mb-1">Pay Refund</h3>
        <p class="text-sm text-slate-500 mb-4">Assign a branch for refund payment processing.</p>

        <div class="space-y-2 text-sm mb-4 p-3 bg-slate-50 rounded-lg">
            <div class="flex justify-between">
                <span class="text-slate-500">Passenger</span>
                <span class="font-medium text-slate-700" x-text="payRefundPassengerName"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Refund Payable</span>
                <span class="font-semibold text-blue-600" x-text="$currency(payRefundMaxAmount, 2)"></span>
            </div>
        </div>

        <form @submit.prevent="submitPayRefund()" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Refund Branch *</label>
                <select x-model="payRefundBranchId" required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                    <option value="">Select Branch</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" :disabled="payRefundLoading || !payRefundBranchId"
                    class="flex-1 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50">
                    <span x-show="!payRefundLoading">Pay Refund</span>
                    <span x-show="payRefundLoading" x-cloak>Processing...</span>
                </button>
                <button type="button" @click="closePayRefundModal()"
                    class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
```

> **Note:** This modal references `$branches`. Verify `$branches` is passed to the
> view from `BookingController::index()` (or add it if missing).

### 9d) Add Alpine.js state and functions inside `bookingIndexApp()`

Add before the `showToast` function (~line 6956).

```javascript
// ── Pay Refund Payable State ──
payRefundModalVisible: false,
payRefundPassengerIndex: null,
payRefundPassengerId: null,
payRefundPassengerName: '',
payRefundMaxAmount: 0,
payRefundBranchId: '',
payRefundLoading: false,

openPayRefundModal(index) {
    const row = this.passengersTicketData[index];
    if (!row) return;
    this.payRefundPassengerIndex = index;
    this.payRefundPassengerId = row.id;
    this.payRefundPassengerName = row.passenger_name || '';
    this.payRefundMaxAmount = parseFloat(row.refund_payable || 0);
    this.payRefundBranchId = '';
    this.payRefundLoading = false;
    this.payRefundModalVisible = true;
},

closePayRefundModal() {
    this.payRefundModalVisible = false;
    this.payRefundPassengerIndex = null;
    this.payRefundPassengerId = null;
},

async submitPayRefund() {
    if (this.payRefundLoading) return;
    if (!this.payRefundBranchId) {
        alert('Please select a branch.');
        return;
    }
    if (!confirm('Assign this branch for refund payment processing?')) return;

    this.payRefundLoading = true;
    try {
        const res = await fetch(`/passengers/${this.payRefundPassengerId}/refund-pay-assign-branch`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                branch_id: this.payRefundBranchId,
            }),
        });
        const result = await res.json();
        if (result.success) {
            const idx = this.payRefundPassengerIndex;
            if (idx !== null && this.passengersTicketData[idx]) {
                this.passengersTicketData[idx].refund_payment_status = 'processing';
            }
            this.payRefundModalVisible = false;
            this.showToast('Refund payment branch assigned successfully.');
        } else {
            alert(result.message || 'Failed to assign branch.');
        }
    } catch (e) {
        alert('Failed to assign branch.');
    } finally {
        this.payRefundLoading = false;
    }
},
```

---

## Change 10: Update `app/Console/Commands/VerifyRefundPayableCommand.php`

**File:** `app/Console/Commands/VerifyRefundPayableCommand.php:18-19`

Include passengers with refund payable payments in verification scope. The actual
verification logic already calls `verifyRefundPayable()` which now accounts for
`refundPayablePayments` (from Change 3).

```php
// Line 18-19, change:
Passenger::has('refundedTickets')
    ->orHas('reIssueSettlements')
    ->chunkById(200, function ($passengers) use (&$mismatches) {

// To:
Passenger::has('refundedTickets')
    ->orHas('reIssueSettlements')
    ->orHas('refundPayablePayments')
    ->chunkById(200, function ($passengers) use (&$mismatches) {
```

---

## Change 11: New Test `tests/Feature/RefundPaymentTest.php`

7 test methods covering: branch assignment, zero-balance rejection, payment
confirm, revert, transaction type, and invoice balance unaffected.

```php
<?php

namespace Tests\Feature;

use App\Enums\RefundPaymentStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Booking $booking;
    private Passenger $passenger;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->artisan('db:seed', '--class=TransactionTypeSeeder');

        $this->branch = Branch::factory()->create();
        $this->booking = Booking::factory()->create(['user_id' => $this->admin->id]);
        $this->passenger = Passenger::factory()->create([
            'booking_id' => $this->booking->id,
            'refund_payable' => 500,
        ]);

        Invoice::create([
            'booking_id' => $this->booking->id,
            'branch_id' => $this->booking->booking_branch_id,
            'user_id' => $this->admin->id,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'balance' => 1000,
        ]);
    }

    public function test_can_assign_branch(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-assign-branch', $this->passenger->id), [
                'branch_id' => $this->branch->id,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->passenger->refresh();
        $this->assertEquals(RefundPaymentStatus::PROCESSING, $this->passenger->refund_payment_status);
        $this->assertEquals($this->branch->id, $this->passenger->refund_payment_branch_id);
    }

    public function test_cannot_assign_branch_with_zero_refund_payable(): void
    {
        $this->passenger->update(['refund_payable' => 0]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-assign-branch', $this->passenger->id), [
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_can_confirm_payment(): void
    {
        $this->passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-confirm', $this->passenger->id), [
                'payment_method' => 'cash',
                'remarks' => 'Test',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->passenger->refresh();
        $this->assertEquals(RefundPaymentStatus::PAID, $this->passenger->refund_payment_status);
        $this->assertEquals(0, (float) $this->passenger->refund_payable);
    }

    public function test_can_revert(): void
    {
        $this->passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-revert', $this->passenger->id));

        $response->assertOk()->assertJson(['success' => true]);

        $this->passenger->refresh();
        $this->assertEquals(RefundPaymentStatus::PENDING, $this->passenger->refund_payment_status);
        $this->assertNull($this->passenger->refund_payment_branch_id);
    }

    public function test_unauthorized_user_gets_403_on_assign(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)
            ->postJson(route('passengers.refund-pay-assign-branch', $this->passenger->id), [
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_voucher_uses_correct_transaction_type(): void
    {
        $this->passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-confirm', $this->passenger->id), [
                'payment_method' => 'cash',
            ]);

        $payment = \App\Models\Payment::where('passenger_id', $this->passenger->id)->first();
        $this->assertNotNull($payment);

        $voucher = $payment->voucher;
        $this->assertNotNull($voucher);
        $this->assertEquals('Ticket Refund - Payment', $voucher->transactionType->name);
    }

    public function test_paid_amount_not_affected_by_refund_payment(): void
    {
        $this->passenger->update([
            'refund_payment_status' => RefundPaymentStatus::PROCESSING,
            'refund_payment_branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('passengers.refund-pay-confirm', $this->passenger->id), [
                'payment_method' => 'cash',
            ]);

        $this->booking->invoice->refresh();
        $this->assertEquals(0, (float) $this->booking->invoice->paid_amount);
    }
}
```

---

## Transaction Flow Diagram

```
1. Ticket Refunded (existing behavior, unchanged)
   → passenger.refund_payable INCREASED by customer_refund
   → refund_payment_status = null

2. "Pay Refund" Button (bookings index, 3-dot menu)
   → Modal shows passenger's refund_payable (READ-ONLY)
   → Admin selects a branch
   → POST /passengers/{id}/refund-pay-assign-branch (throttled: 5/min)
   → refund_payment_status → 'processing'
   → refund_payment_branch_id → selected branch

3. Pending Refunds → Ticket Refunds Tab
   → Shows passengers where refund_payment_status = 'processing'
   → Filtered by refund_payment_branch_id for non-admin users
   → Rows have Confirm and Revert buttons

4. Confirm (Pending Refunds page)
   → Modal shows passenger + refund payable (READ-ONLY; branch user cannot edit amount)
   → Branch user selects payment method + remarks
   → POST /passengers/{id}/refund-pay-confirm (throttled: 5/min)
   → lockForUpdate() on passenger row (prevents race condition)
   → Payment created (type: "Ticket Refund - Payment")
   → Voucher created
   → passenger.refund_payable DECREASED by full refund_payable
   → refund_payment_status → 'paid'

5. Revert (Pending Refunds page)
   → POST /passengers/{id}/refund-pay-revert
   → refund_payment_status → 'pending'
   → refund_payment_branch_id → null

Invoice paid_amount calculation (fixed):
  paid_amount = payments WHERE voucher.transaction_type IN ('Initial Payment', 'Due Collection')
  → Automatically excludes: refunds, re-issues, cancellations, agent payments, etc.
```

---

## Verification

After implementation, run:

```bash
# Run the new tests
php artisan test tests/Feature/RefundPaymentTest.php

# Run the full test suite
php artisan test

# Run Pint for code style
vendor/bin/pint

# Verify refund payable balances
php artisan refund:verify

# Run frontend build
npm run build

# Validate Docker
docker compose -f docker-compose.yml config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```
