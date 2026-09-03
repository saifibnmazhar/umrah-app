# Plan: Pay Refund Payable + Ticket Refund Payment System

## Problem

Three gaps exist:

1. **`paid_amount` calculation is fragile** — uses column-based exclusions (`whereNull`)
   instead of filtering by voucher transaction type. Every new payment type requires a
   new `whereNull` clause.
2. **No way to directly pay refund payable** — `passenger.refund_payable` can only be
   consumed via re-issue adjustment or passenger cancellation. There is no direct
   cash/bank payout path.
3. **No ticket refund payment tracking** — refunded tickets have no branch assignment,
   payment status, or batch payment flow. The Pending Refunds page only shows
   booking/passenger cancellations.

---

## Solution Overview

1. Fix `paid_amount` to only count vouchers with transaction type `'Initial Payment'`
   and `'Due Collection'` (whitelist approach).
2. Add `refund_payment_branch_id` and `refund_payment_status` to `refunded_tickets`.
3. Add `'Ticket Refunds'` tab to the Pending Refunds page.
4. Add batch refund payment form (one Payment + Voucher per selected refunded ticket).
5. Add `RefundPaymentStatus` enum.
6. Add "Pay Refund" button in the 3-dot action menu on the bookings page for quick
   per-passenger access.

---

## Access Control

**Roles allowed:** Super Admin, Co Admin, Ticket Admin

---

## Security & Reliability Measures

| Concern | Mitigation |
|---------|------------|
| Race condition (concurrent payments) | `lockForUpdate()` on refunded ticket / passenger row inside the transaction |
| Rate limiting | `throttle:5,1` middleware on the routes (5 requests per minute) |
| Branch-level access | Controller checks `$user->branch_id` against branch assignment |
| Null safety | Service validates invoice, currency_rate, and booking exist before proceeding |
| Invoice balance integrity | `paid_amount` only counts `'Initial Payment'` and `'Due Collection'` vouchers |
| Verification command | `verifyRefundPayable()` formula accounts for refund payable payments |

---

## File Changes (13 files)

| # | File | Action |
|---|------|--------|
| 1 | `app/Enums/RefundPaymentStatus.php` | **New** |
| 2 | `database/migrations/2026_09_03_000002_add_refund_payment_fields_to_refunded_tickets_table.php` | **New** |
| 3 | `app/Models/RefundedTicket.php` | **Edit** |
| 4 | `app/Services/InvoiceService.php` | **Edit** |
| 5 | `app/Http/Controllers/RefundController.php` | **Edit** |
| 6 | `app/Http/Controllers/RefundPaymentController.php` | **New** |
| 7 | `app/Http/Controllers/BookingCancellationViewController.php` | **Edit** |
| 8 | `routes/web.php` | **Edit** |
| 9 | `resources/views/pending-refunds/index.blade.php` | **Edit** |
| 10 | `app/Models/Passenger.php` | **Edit** |
| 11 | `app/Console/Commands/VerifyRefundPayableCommand.php` | **Edit** |
| 12 | `tests/Feature/RefundPaymentTest.php` | **New** |
| 13 | `resources/views/bookings/index.blade.php` | **Edit** |

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
    case PAID = 'paid';
    case REVERTED = 'reverted';
}
```

---

## Change 2: New Migration — `refund_payment_fields` on `refunded_tickets`

**New file:** `database/migrations/2026_09_03_000002_add_refund_payment_fields_to_refunded_tickets_table.php`

Adds branch tracking and payment status to refunded tickets.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->foreignId('refund_payment_branch_id')->nullable()->after('reason_id')
                ->constrained('branches')->nullOnDelete();
            $table->string('refund_payment_status')->nullable()->default('pending')
                ->after('refund_payment_branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->dropForeign(['refund_payment_branch_id']);
            $table->dropColumn(['refund_payment_branch_id', 'refund_payment_status']);
        });
    }
};
```

---

## Change 3: Update `app/Models/RefundedTicket.php`

Add new fields to `$fillable`, cast `refund_payment_status` to enum, add
`refundPaymentBranch()` relationship.

```php
// Add to $fillable (after 'reason_id'):
'refund_payment_branch_id',
'refund_payment_status',

// Add to $casts:
'refund_payment_status' => \App\Enums\RefundPaymentStatus::class,

// Add relationship:
public function refundPaymentBranch(): BelongsTo
{
    return $this->belongsTo(Branch::class, 'refund_payment_branch_id');
}
```

---

## Change 4: Update `app/Services/InvoiceService.php`

**File:** `app/Services/InvoiceService.php:36-41`

Replace column-based exclusions with voucher transaction type filtering. Only
payments with `'Initial Payment'` or `'Due Collection'` vouchers count as paid.
This automatically excludes refund payments (`'Ticket Refund - Payment'`),
re-issue adjustments, cancellations, etc. — without needing `whereNull` clauses.

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

**File:** `app/Http/Controllers/RefundController.php:41-62`

Accept `refund_payment_branch_id` during refund initialization and set initial
status to `'pending'`.

```php
// Add to validation rules (after 'payment_by'):
'refund_payment_branch_id' => 'required|exists:branches,id',

// Add to $refundData array (after 'payment_by' => ...):
'refund_payment_branch_id' => $validated['refund_payment_branch_id'],
'refund_payment_status' => 'pending',
```

---

## Change 6: New `app/Http/Controllers/RefundPaymentController.php`

Handles batch refund payment — one Payment + Voucher per selected refunded ticket.
All inside a single DB transaction.

```php
<?php

namespace App\Http\Controllers;

use App\Enums\RefundPaymentStatus;
use App\Models\Payment;
use App\Models\RefundedTicket;
use App\Models\TransactionType;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundPaymentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'refunded_ticket_ids' => 'required|array|min:1',
            'refunded_ticket_ids.*' => 'exists:refunded_tickets,id',
            'payment_method' => 'required|in:cash,bank',
            'remarks' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($validated, $user) {
            $results = [];
            $transactionType = TransactionType::where('name', 'Ticket Refund - Payment')->first();

            if (! $transactionType) {
                throw new \RuntimeException('Transaction type "Ticket Refund - Payment" not found.');
            }

            foreach ($validated['refunded_ticket_ids'] as $ticketId) {
                $refundedTicket = RefundedTicket::lockForUpdate()->find($ticketId);

                if ($refundedTicket->refund_payment_status !== RefundPaymentStatus::PENDING) {
                    throw new \RuntimeException(
                        "Refunded ticket #{$ticketId} is not pending (status: {$refundedTicket->refund_payment_status->value})."
                    );
                }

                $passenger = $refundedTicket->issuedTicket->passenger;
                $booking = $refundedTicket->issuedTicket->booking;
                $invoice = $booking->invoice;
                $amount = (float) $refundedTicket->refund_to_customer;

                if ($amount <= 0) {
                    throw new \RuntimeException(
                        "Refunded ticket #{$ticketId} has no refund amount (refund_to_customer = 0)."
                    );
                }

                if (! $invoice) {
                    throw new \RuntimeException("Booking #{$booking->id} has no invoice.");
                }

                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'branch_id' => $refundedTicket->refund_payment_branch_id,
                    'user_id' => $user->id,
                    'currency_rate_id' => $booking->currency_rate_id,
                    'payment_date' => now(),
                    'payment_method' => $validated['payment_method'],
                    'amount' => $amount,
                    'bdt_amount' => 0,
                    'passenger_id' => $passenger->id,
                    'refunded_ticket_id' => $refundedTicket->id,
                    'remarks' => $validated['remarks'] ?? null,
                ]);

                $voucher = app(VoucherService::class)->createVoucher([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                    'branch_id' => $refundedTicket->refund_payment_branch_id,
                    'user_id' => $user->id,
                    'currency_rate_id' => $booking->currency_rate_id,
                    'transaction_type_id' => $transactionType->id,
                    'payment_date' => now(),
                    'payment_method' => $validated['payment_method'],
                    'amount' => $amount,
                    'bdt_amount' => 0,
                    'notes' => $validated['remarks'] ?? null,
                ]);

                $refundedTicket->update([
                    'refund_payment_status' => RefundPaymentStatus::PAID,
                ]);

                $passenger->decreaseRefundPayable($amount);

                $results[] = [
                    'refunded_ticket_id' => $refundedTicket->id,
                    'payment_id' => $payment->id,
                    'voucher_id' => $voucher->id,
                    'amount' => $amount,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => count($results) . ' refund payment(s) processed successfully.',
                'data' => $results,
            ]);
        });
    }
}
```

**Key details:**
- Uses `lockForUpdate()` on each refunded ticket to prevent race conditions
- Creates one Payment + Voucher per refunded ticket
- Sets `refund_payment_status` to `'paid'`
- Decreases `passenger.refund_payable` by `refund_to_customer`
- Single DB transaction — all or nothing

---

## Change 7: Update `app/Http/Controllers/BookingCancellationViewController.php`

**File:** `app/Http/Controllers/BookingCancellationViewController.php:62-102`

Query refunded tickets for the new "Ticket Refunds" tab.

```php
// Add import at top:
use App\Models\RefundedTicket;

// Add inside pendingRefunds() method (after $cancelledPassengers query):
$ticketRefundQuery = RefundedTicket::with([
    'issuedTicket.passenger',
    'ticketAgent',
    'refundPaymentBranch',
    'reason',
])->whereHas('issuedTicket', function ($q) {
    $q->whereNotNull('booking_id');
});

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

## Change 8: Add Route to `routes/web.php`

```php
// Add import at top:
use App\Http\Controllers\RefundPaymentController;

// Add route (after refund-payable-pay route or in booking-cancellation.php):
Route::post('/refund-payments', [RefundPaymentController::class, 'store'])
    ->name('refund-payments.store')
    ->middleware(['role:Super Admin,Co Admin,Ticket Admin', 'throttle:5,1']);
```

---

## Change 9: Update `resources/views/pending-refunds/index.blade.php`

### 9a) Add third tab link (after "Passenger Cancellations" tab, line 30)

```blade
<a href="?tab=tickets{{ request('branch_id') ? '&branch_id=' . request('branch_id') : '' }}"
   class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'tickets' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
    Ticket Refunds
</a>
```

### 9b) Add Ticket Refunds tab content (after the passengers tab `@endif`)

```blade
@if($tab === 'tickets')
<div class="overflow-auto flex-1 min-h-0" style="max-height: calc(95vh - 260px);">
    <div class="mb-3 flex justify-end">
        <button onclick="openRefundBatchModal()"
            :disabled="selectedRefundTickets.length === 0"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium disabled:opacity-50">
            Pay Selected (<span x-text="selectedRefundTickets.length">0</span>)
        </button>
    </div>
    <table class="w-full min-w-[1100px] text-sm">
        <thead class="bg-slate-50 text-slate-600 sticky top-0 z-10">
            <tr>
                <th class="px-3 py-2 text-left font-medium w-10">
                    <input type="checkbox" onchange="toggleAllRefundTickets(this)">
                </th>
                <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                <th class="px-3 py-2 text-left font-medium">Passenger</th>
                <th class="px-3 py-2 text-left font-medium">Ticket Number</th>
                <th class="px-3 py-2 text-left font-medium">Refund Branch</th>
                <th class="px-3 py-2 text-left font-medium">Reason</th>
                <th class="px-3 py-2 text-right font-medium">Refund Amount</th>
                <th class="px-3 py-2 text-left font-medium">Status</th>
                <th class="px-3 py-2 text-left font-medium">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            @forelse($ticketRefunds as $rt)
            <tr>
                <td class="px-3 py-2">
                    @if($rt->refund_payment_status?->value === 'pending')
                    <input type="checkbox" value="{{ $rt->id }}" class="refund-ticket-checkbox"
                        onchange="updateSelectedRefundTickets()">
                    @endif
                </td>
                <td class="px-3 py-2 text-slate-700">{{ $rt->issuedTicket?->booking?->invoice_id ?? '—' }}</td>
                <td class="px-3 py-2 text-slate-700">{{ trim(($rt->issuedTicket?->passenger?->first_name ?? '') . ' ' . ($rt->issuedTicket?->passenger?->last_name ?? '')) ?: '—' }}</td>
                <td class="px-3 py-2 text-slate-700">{{ $rt->ticket_number ?? '—' }}</td>
                <td class="px-3 py-2 text-slate-700">{{ $rt->refundPaymentBranch?->name ?? '—' }}</td>
                <td class="px-3 py-2 text-slate-600 text-xs">{{ $rt->reason?->name ?? '—' }}</td>
                <td class="px-3 py-2 text-slate-800 font-medium text-right">@currency($rt->refund_to_customer, 2)</td>
                <td class="px-3 py-2">
                    @if($rt->refund_payment_status?->value === 'pending')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                    @elseif($rt->refund_payment_status?->value === 'paid')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Paid</span>
                    @elseif($rt->refund_payment_status?->value === 'reverted')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Reverted</span>
                    @else
                        <span class="text-slate-500">—</span>
                    @endif
                </td>
                <td class="px-3 py-2 text-slate-600">{{ $rt->refund_date?->format('Y-m-d') ?? '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="px-3 py-4 text-center text-slate-500">No ticket refunds found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $ticketRefunds->links() }}</div>
@endif
```

### 9c) Add batch refund payment modal (before `@endsection`)

```blade
{{-- Pay Refund Batch Modal --}}
<div id="payRefundBatchModal" class="hidden fixed inset-0 z-[9999] bg-black/50 flex items-center justify-center p-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-semibold text-slate-800 mb-1">Pay Refund</h3>
        <p class="text-sm text-slate-500 mb-4">Process refund payment for selected tickets.</p>

        <div class="space-y-2 text-sm mb-4 p-3 bg-slate-50 rounded-lg" id="refundTicketSummary">
            {{-- Populated by JS --}}
        </div>

        <form id="refundBatchForm" onsubmit="submitRefundBatch(event)" class="space-y-4">
            <input type="hidden" name="refunded_ticket_ids" id="refundTicketIds">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method *</label>
                <select name="payment_method" required class="w-full px-4 py-2 border border-slate-300 rounded-lg">
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Remarks <span class="text-red-500">*</span>
                </label>
                <textarea name="remarks" rows="2" required class="w-full px-4 py-2 border border-slate-300 rounded-lg" placeholder="Enter remarks"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">Pay Refund</button>
                <button type="button" onclick="document.getElementById('payRefundBatchModal').classList.add('hidden')" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>
```

### 9d) Add JavaScript functions (before `@endsection`)

```blade
<script>
let selectedRefundTickets = [];

function toggleAllRefundTickets(source) {
    document.querySelectorAll('.refund-ticket-checkbox').forEach(cb => cb.checked = source.checked);
    updateSelectedRefundTickets();
}

function updateSelectedRefundTickets() {
    selectedRefundTickets = Array.from(document.querySelectorAll('.refund-ticket-checkbox:checked')).map(cb => parseInt(cb.value));
}

function openRefundBatchModal() {
    if (selectedRefundTickets.length === 0) return;
    document.getElementById('refundTicketIds').value = JSON.stringify(selectedRefundTickets);
    document.getElementById('payRefundBatchModal').classList.remove('hidden');
}

async function submitRefundBatch(e) {
    e.preventDefault();
    if (selectedRefundTickets.length === 0) return;
    if (!confirm('Process refund payment for ' + selectedRefundTickets.length + ' ticket(s)?')) return;

    const form = e.target;
    const res = await fetch('{{ route("refund-payments.store") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            refunded_ticket_ids: selectedRefundTickets,
            payment_method: form.payment_method.value,
            remarks: form.remarks.value || null,
        }),
    });
    const result = await res.json();
    if (result.success) {
        window.location.reload();
    } else {
        alert(result.message || 'Failed to process refund payment.');
    }
}
</script>
```

---

## Change 10: Update `app/Models/Passenger.php`

Add `refundPayablePayments()` relationship and update `verifyRefundPayable()` to
subtract direct refund payable payments from the computed balance.

```php
// Add after reIssueSettlements() (line 149):
public function refundPayablePayments(): HasMany
{
    return $this->hasMany(Payment::class, 'refund_payable_id')
        ->whereHas('vouchers.transactionType', function ($q) {
            $q->where('name', 'Ticket Refund - Payment');
        });
}

// Update verifyRefundPayable() (line 151-157) to:
public function verifyRefundPayable(): float
{
    $refunds = (float) $this->refundedTickets()->sum('refund_to_customer');
    $settlements = (float) $this->reIssueSettlements()->sum('amount');
    $refundPayablePayments = (float) $this->refundPayablePayments()->sum('amount');

    return max(0, $refunds - $settlements - $refundPayablePayments);
}
```

---

## Change 11: Update `app/Console/Commands/VerifyRefundPayableCommand.php`

**File:** `app/Console/Commands/VerifyRefundPayableCommand.php:18-19`

Include passengers with refund payable payments in verification scope. The actual
verification logic already calls `verifyRefundPayable()` which now accounts for
`refundPayablePayments` (from Change 10).

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

## Change 12: New Test `tests/Feature/RefundPaymentTest.php`

5 test methods covering: successful batch payment, non-pending rejection,
unauthorized access, transaction type correctness, and invoice balance unaffected.

```php
<?php

namespace Tests\Feature;

use App\Enums\RefundPaymentStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\RefundedTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Booking $booking;
    private Passenger $passenger;
    private IssuedTicket $issuedTicket;
    private RefundedTicket $refundedTicket;
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
        $this->issuedTicket = IssuedTicket::factory()->create([
            'passenger_id' => $this->passenger->id,
            'booking_id' => $this->booking->id,
            'status' => 'refunded',
        ]);
        $this->refundedTicket = RefundedTicket::factory()->create([
            'issued_ticket_id' => $this->issuedTicket->id,
            'refund_to_customer' => 300,
            'refund_payment_branch_id' => $this->branch->id,
            'refund_payment_status' => RefundPaymentStatus::PENDING,
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

    public function test_can_process_refund_payment(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('refund-payments.store'), [
                'refunded_ticket_ids' => [$this->refundedTicket->id],
                'payment_method' => 'cash',
                'remarks' => 'Test refund',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'passenger_id' => $this->passenger->id,
            'refunded_ticket_id' => $this->refundedTicket->id,
            'amount' => 300,
        ]);

        $this->refundedTicket->refresh();
        $this->assertEquals(RefundPaymentStatus::PAID, $this->refundedTicket->refund_payment_status);

        $this->passenger->refresh();
        $this->assertEquals(200, (float) $this->passenger->refund_payable);
    }

    public function test_cannot_pay_non_pending_ticket(): void
    {
        $this->refundedTicket->update(['refund_payment_status' => RefundPaymentStatus::PAID]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('refund-payments.store'), [
                'refunded_ticket_ids' => [$this->refundedTicket->id],
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(500);
    }

    public function test_unauthorized_user_gets_403(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)
            ->postJson(route('refund-payments.store'), [
                'refunded_ticket_ids' => [$this->refundedTicket->id],
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(403);
    }

    public function test_voucher_uses_correct_transaction_type(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('refund-payments.store'), [
                'refunded_ticket_ids' => [$this->refundedTicket->id],
                'payment_method' => 'cash',
                'remarks' => null,
            ]);

        $payment = Payment::where('refunded_ticket_id', $this->refundedTicket->id)->first();
        $this->assertNotNull($payment);

        $voucher = $payment->voucher;
        $this->assertNotNull($voucher);
        $this->assertEquals('Ticket Refund - Payment', $voucher->transactionType->name);
    }

    public function test_paid_amount_not_affected_by_refund_payment(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('refund-payments.store'), [
                'refunded_ticket_ids' => [$this->refundedTicket->id],
                'payment_method' => 'cash',
            ]);

        $this->booking->invoice->refresh();
        $this->assertEquals(0, (float) $this->booking->invoice->paid_amount);
    }
}
```

---

## Change 13: Update `resources/views/bookings/index.blade.php`

### 13a) Add role check variable (~line 688)

After the `$canConfirmCancellation` line:

```php
$canPayRefundPayable = auth()->user()->hasAnyRole(['Super Admin', 'Co Admin', 'Ticket Admin']);
```

### 13b) Add "Pay Refund" button in 3-dot dropdown (~line 1486)

After the "View Tickets" button, inside the dropdown:

```blade
@if($canPayRefundPayable)
    <template x-if="passengersTicketData[{{ $loop->index }}]?.refund_payable > 0">
        <button @click="open = false; openPayRefundModal({{ $loop->index }})"
            class="px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-slate-50 transition text-left">
            Pay Refund
        </button>
    </template>
@endif
```

### 13c) Add Pay Refund Modal HTML (~after Cancel Passenger Modal at line 3192)

```blade
{{-- Pay Refund Modal --}}
<div x-show="payRefundModalVisible" x-cloak
     class="fixed inset-0 z-[9999] bg-black/50 flex items-center justify-center p-4"
     @click.self="closePayRefundModal()"
     @keydown.escape.window="closePayRefundModal()">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-semibold text-slate-800 mb-1">Pay Refund</h3>
        <p class="text-sm text-slate-500 mb-4">Pay refund payable amount to the customer.</p>

        <div class="space-y-2 text-sm mb-4 p-3 bg-slate-50 rounded-lg">
            <div class="flex justify-between">
                <span class="text-slate-500">Passenger</span>
                <span class="font-medium text-slate-700" x-text="payRefundPassengerName"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Available Refund Payable</span>
                <span class="font-semibold text-blue-600" x-text="$currency(payRefundMaxAmount, 2)"></span>
            </div>
        </div>

        <form @submit.prevent="submitPayRefund()" class="space-y-4">
            <div x-show="$store.currency.mode === 'BDT'" x-cloak>
                <label class="block text-sm font-medium text-slate-700 mb-1">Amount (BDT)</label>
                <input type="number" x-model.number="payRefundAmountBdt"
                    @input="payRefundAmount = parseFloat(((parseFloat(payRefundAmountBdt) || 0) / ($store.currency.rate || 1)).toFixed(6))"
                    min="0.01" :max="payRefundMaxAmount * ($store.currency.rate || 1)" step="0.01"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-400 outline-none"
                    placeholder="0.00">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Amount (SAR) *</label>
                <input type="number" x-model.number="payRefundAmount"
                    @change="if ($store.currency.mode === 'BDT' && $store.currency.rate > 0) { payRefundAmountBdt = Math.round((parseFloat($event.target.value) || 0) * $store.currency.rate * 100) / 100; }"
                    min="0.01" :max="payRefundMaxAmount" step="0.000001" required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-400 outline-none"
                    placeholder="0.00">
                <p class="text-xs text-slate-400 mt-1">Max: <span x-text="$currency(payRefundMaxAmount, 2)"></span></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method *</label>
                <select x-model="payRefundPaymentMethod" required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Remarks
                    <span x-show="payRefundPaymentMethod === 'bank'" class="text-red-500">*</span>
                </label>
                <textarea x-model="payRefundRemarks" rows="2"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-400 outline-none"
                    :required="payRefundPaymentMethod === 'bank'"
                    placeholder="Enter remarks"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" :disabled="payRefundLoading || !payRefundAmount || payRefundAmount <= 0"
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

### 13d) Add Alpine.js state and functions inside `bookingIndexApp()`

Add before the `showToast` function (~line 6956). Uses in-place state update
instead of page reload.

```javascript
// ── Pay Refund Payable State ──
payRefundModalVisible: false,
payRefundPassengerIndex: null,
payRefundPassengerId: null,
payRefundPassengerName: '',
payRefundMaxAmount: 0,
payRefundAmount: 0,
payRefundAmountBdt: '',
payRefundPaymentMethod: 'cash',
payRefundRemarks: '',
payRefundLoading: false,

openPayRefundModal(index) {
    const row = this.passengersTicketData[index];
    if (!row) return;
    this.payRefundPassengerIndex = index;
    this.payRefundPassengerId = row.id;
    this.payRefundPassengerName = row.passenger_name || '';
    this.payRefundMaxAmount = parseFloat(row.refund_payable || 0);
    this.payRefundAmount = 0;
    this.payRefundAmountBdt = '';
    this.payRefundPaymentMethod = 'cash';
    this.payRefundRemarks = '';
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

    if (!this.payRefundAmount || this.payRefundAmount <= 0) {
        alert('Please enter a valid amount.');
        return;
    }

    if (this.payRefundAmount > this.payRefundMaxAmount) {
        alert('Amount exceeds available refund payable.');
        return;
    }

    if (this.payRefundPaymentMethod === 'bank' && !this.payRefundRemarks.trim()) {
        alert('Remarks are required when payment method is Bank.');
        return;
    }

    if (!confirm('Confirm this refund payable payment?')) return;

    this.payRefundLoading = true;
    try {
        const res = await fetch(`/passengers/${this.payRefundPassengerId}/refund-payable-pay`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                amount: this.payRefundAmount,
                payment_method: this.payRefundPaymentMethod,
                remarks: this.payRefundRemarks || null,
            }),
        });
        const result = await res.json();
        if (result.success) {
            const idx = this.payRefundPassengerIndex;
            if (idx !== null && this.passengersTicketData[idx]) {
                this.passengersTicketData[idx].refund_payable = result.data.remaining_refund_payable;
            }
            this.payRefundModalVisible = false;
            this.showToast('Refund payable payment processed. Paid: ' + this.$currency(result.data.amount, 2));
        } else {
            alert(result.message || 'Failed to process refund payable payment.');
        }
    } catch (e) {
        alert('Failed to process refund payable payment.');
    } finally {
        this.payRefundLoading = false;
    }
},
```

---

## Transaction Flow Diagram

```
Flow 1: Ticket Refund Initialization (existing + enhanced)
  Admin clicks "Refund" on a ticket
    → RefundController::store() creates RefundedTicket
    → Sets refund_payment_branch_id (admin-selected)
    → Sets refund_payment_status = 'pending'
    → passenger.refund_payable INCREASED by customer_refund
    → Ticket status set to 'refunded'

Flow 2: Batch Refund Payment (new — from Pending Refunds page)
  Admin goes to Pending Refunds → Ticket Refunds tab
    → Sees list of refunded tickets with refund_payment_status = 'pending'
    → Selects tickets via checkboxes
    → Clicks "Pay Selected" → modal opens
    → Selects payment method, enters remarks, submits
    → POST /refund-payments (throttled: 5/min)
    → RefundPaymentController::store()
      → For each selected ticket:
        → lockForUpdate() on refunded ticket (prevents race condition)
        → Validates refund_payment_status === 'pending'
        → Payment created (type = "Ticket Refund - Payment")
        → Voucher created
        → refund_payment_status → 'paid'
        → passenger.refund_payable DECREASED by refund_to_customer
    → Invoice paid_amount UNAFFECTED (filtered by voucher transaction type)

Flow 3: Quick Pay Refund (new — from Bookings page 3-dot menu)
  Admin clicks "Pay Refund" in 3-dot menu
    → Modal opens showing available refund_payable
    → Admin enters amount, selects payment method, submits
    → POST /passengers/{id}/refund-payable-pay (throttled: 5/min)
    → Creates Payment + Voucher (type = "Ticket Refund - Payment")
    → passenger.refund_payable DECREASED
    → Alpine.js updates row in-place, shows toast

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
