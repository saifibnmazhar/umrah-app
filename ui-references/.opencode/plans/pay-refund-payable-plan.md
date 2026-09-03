# Plan: Pay Refund Payable to Customer Without Cancelling Passenger

## Problem

When a ticket is refunded, `passenger.refund_payable` is increased. Currently, this
balance can only be consumed via:

1. **Re-issue refund adjustment** — used as credit when re-issuing a ticket for the
   same passenger (`Ticket Refund - Re-issue` payment).
2. **Passenger cancellation** — folded into the cancellation refund calculation and
   paid out as part of the `Customer Refund` payment.

There is **no way to directly pay** the refund payable to the customer as a
cash/bank transfer without doing either of the above. This plan adds that
capability.

---

## Solution Overview

Add a **"Pay Refund"** button in the 3-dot action menu on the passenger index
page. When clicked, it opens a modal where the admin can enter an amount
(partial or full), select a payment method, and submit. The system creates a
Payment + Voucher pair (transaction type `Ticket Refund - Payment`), decreases
the passenger's `refund_payable`, and ensures the invoice balance is not
affected.

---

## Access Control

**Roles allowed:** Super Admin, Co Admin, Ticket Admin

---

## File Changes (9 files)

| # | File | Action |
|---|------|--------|
| 1 | `database/migrations/2026_09_03_000001_add_refund_payable_id_to_payments_table.php` | **New** |
| 2 | `app/Services/InvoiceService.php` | **Edit** |
| 3 | `app/Models/Passenger.php` | **Edit** |
| 4 | `app/Services/RefundPayablePaymentService.php` | **New** |
| 5 | `app/Http/Controllers/RefundPayablePaymentController.php` | **New** |
| 6 | `routes/web.php` | **Edit** |
| 7 | `resources/views/bookings/index.blade.php` | **Edit** |
| 8 | `app/Console/Commands/VerifyRefundPayableCommand.php` | **Edit** |
| 9 | `tests/Feature/RefundPayablePaymentTest.php` | **New** |

---

## Change 1: Migration — `refund_payable_id` on payments

**New file:** `database/migrations/2026_09_03_000001_add_refund_payable_id_to_payments_table.php`

Adds a nullable `refund_payable_id` FK (unsigned big integer, FK to `passengers`,
nullOnDelete) to the `payments` table. This flag is used by
`InvoiceService::updatePaymentStatus` to **exclude** refund payable payments from
the invoice's `paid_amount` — they are money going *out*, not coming *in*.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('refund_payable_id')->nullable()->after('cancelled_passenger_id')
                ->constrained('passengers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['refund_payable_id']);
            $table->dropColumn('refund_payable_id');
        });
    }
};
```

---

## Change 2: Update `app/Services/InvoiceService.php`

Add `->whereNull('refund_payable_id')` to the `paid_amount` calculation in
`updatePaymentStatus()` (and implicitly in the query it runs). Without this, a
refund payable payment would incorrectly inflate the invoice balance.

```php
// In updatePaymentStatus() — current code (lines 36-41):
$invoice->paid_amount = $invoice->payments()
    ->whereNull('cancelled_booking_id')
    ->whereNull('cancelled_passenger_id')
    ->whereNull('refunded_ticket_id')
    ->whereNull('re_issued_ticket_id')
    ->sum('amount');

// Change to:
$invoice->paid_amount = $invoice->payments()
    ->whereNull('cancelled_booking_id')
    ->whereNull('cancelled_passenger_id')
    ->whereNull('refunded_ticket_id')
    ->whereNull('re_issued_ticket_id')
    ->whereNull('refund_payable_id')
    ->sum('amount');
```

---

## Change 3: Update `app/Models/Passenger.php`

Add a `refundPayablePayments()` relationship and update `verifyRefundPayable()`
to subtract direct refund payable payments from the computed balance.

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

## Change 4: New `app/Services/RefundPayablePaymentService.php`

Service that creates the Payment + Voucher pair and decreases the passenger's
`refund_payable`. Follows the same patterns as `PassengerCancellationService`
and `ReIssueController`.

```php
<?php

namespace App\Services;

use App\Models\Passenger;
use App\Models\Payment;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

class RefundPayablePaymentService
{
    public function payRefundPayable(Passenger $passenger, array $data): array
    {
        $amount = (float) $data['amount'];
        $refundPayable = (float) $passenger->refund_payable;

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if ($amount > $refundPayable) {
            throw new \InvalidArgumentException(
                "Payment amount ({$amount}) exceeds available refund payable ({$refundPayable})."
            );
        }

        $booking = $passenger->booking;
        $invoice = $booking->invoice;
        $currencyRateId = $booking->currency_rate_id;
        $paymentMethod = $data['payment_method'];
        $remarks = $data['remarks'] ?? null;

        return DB::transaction(function () use ($passenger, $booking, $invoice, $amount, $paymentMethod, $currencyRateId, $remarks) {
            $transactionType = TransactionType::where('name', 'Ticket Refund - Payment')->first();

            if (! $transactionType) {
                throw new \RuntimeException('Transaction type "Ticket Refund - Payment" not found.');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice?->id,
                'booking_id' => $booking->id,
                'branch_id' => $booking->booking_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $currencyRateId,
                'payment_date' => now(),
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'bdt_amount' => 0,
                'passenger_id' => $passenger->id,
                'refund_payable_id' => $passenger->id,
                'remarks' => $remarks,
            ]);

            $voucher = app(VoucherService::class)->createVoucher([
                'invoice_id' => $invoice?->id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'branch_id' => $booking->booking_branch_id,
                'user_id' => auth()->id(),
                'currency_rate_id' => $currencyRateId,
                'transaction_type_id' => $transactionType->id,
                'payment_date' => now(),
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'bdt_amount' => 0,
                'notes' => $remarks,
            ]);

            $passenger->decreaseRefundPayable($amount);

            return [$payment, $voucher];
        });
    }
}
```

**Key details:**
- Sets `refund_payable_id = passenger->id` so `InvoiceService` excludes it
- Uses existing `'Ticket Refund - Payment'` transaction type (already seeded)
- Creates both Payment + Voucher atomically
- Decreases `refund_payable` on the passenger

---

## Change 5: New `app/Http/Controllers/RefundPayablePaymentController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Passenger;
use App\Services\RefundPayablePaymentService;
use Illuminate\Http\Request;

class RefundPayablePaymentController extends Controller
{
    public function store(Request $request, Passenger $passenger)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:' . implode(',', array_column(PaymentMethod::cases(), 'value')),
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            $service = app(RefundPayablePaymentService::class);
            [$payment, $voucher] = $service->payRefundPayable($passenger, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Refund payable payment processed successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'voucher_id' => $voucher->id,
                    'amount' => $payment->amount,
                    'remaining_refund_payable' => $passenger->fresh()->refund_payable,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the refund payable payment.',
            ], 500);
        }
    }
}
```

---

## Change 6: Add Route to `routes/web.php`

Add after line 571 (after the `refunded-tickets.by-booking` route):

```php
Route::post('/passengers/{passenger}/refund-payable-pay', [RefundPayablePaymentController::class, 'store'])
    ->name('passengers.refund-payable-pay')->middleware('role:Super Admin,Co Admin,Ticket Admin');
```

Add the import at the top of the file:

```php
use App\Http\Controllers\RefundPayablePaymentController;
```

---

## Change 7: Update `resources/views/bookings/index.blade.php`

### 7a) Add role check variable (~line 688)

After the `$canConfirmCancellation` line:

```php
$canPayRefundPayable = auth()->user()->hasAnyRole(['Super Admin', 'Co Admin', 'Ticket Admin']);
```

### 7b) Add "Pay Refund" button in 3-dot dropdown (~line 1486)

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

### 7c) Add Pay Refund Modal HTML (~after Cancel Passenger Modal at line 3192)

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

### 7d) Add Alpine.js state and functions inside `bookingIndexApp()`

Add before the `showToast` function (~line 6956):

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
            this.payRefundModalVisible = false;
            window.location.reload();
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

## Change 8: Update `app/Console/Commands/VerifyRefundPayableCommand.php`

Update the chunk query to also include passengers with refund payable payments:

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

## Change 9: New Test `tests/Feature/RefundPayablePaymentTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundPayablePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Booking $booking;
    private Passenger $passenger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->artisan('db:seed', '--class=TransactionTypeSeeder');

        $this->booking = Booking::factory()->create([
            'user_id' => $this->admin->id,
        ]);

        $this->passenger = Passenger::factory()->create([
            'booking_id' => $this->booking->id,
            'refund_payable' => 500,
        ]);
    }

    public function test_can_pay_refund_payable(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/passengers/{$this->passenger->id}/refund-payable-pay", [
                'amount' => 200,
                'payment_method' => 'cash',
                'remarks' => null,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'passenger_id' => $this->passenger->id,
            'refund_payable_id' => $this->passenger->id,
            'amount' => 200,
        ]);

        $this->assertDatabaseHas('vouchers', [
            'payment_id' => Payment::where('refund_payable_id', $this->passenger->id)->first()->id,
        ]);

        $this->passenger->refresh();
        $this->assertEquals(300, (float) $this->passenger->refund_payable);
    }

    public function test_cannot_pay_more_than_refund_payable(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/passengers/{$this->passenger->id}/refund-payable-pay", [
                'amount' => 600,
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_cannot_pay_zero_amount(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/passengers/{$this->passenger->id}/refund-payable-pay", [
                'amount' => 0,
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(422);
    }

    public function test_refund_payable_payment_does_not_affect_invoice_paid_amount(): void
    {
        Invoice::create([
            'booking_id' => $this->booking->id,
            'branch_id' => $this->booking->booking_branch_id,
            'user_id' => $this->admin->id,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'balance' => 1000,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/passengers/{$this->passenger->id}/refund-payable-pay", [
                'amount' => 200,
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
Before:
  Ticket Refunded → refund_payable INCREASED by customer_refund
  (existing behavior, unchanged)

After (new feature):
  Admin clicks "Pay Refund" in 3-dot menu
    → Modal opens showing available refund_payable
    → Admin enters amount, selects payment method, submits
    → POST /passengers/{id}/refund-payable-pay
    → RefundPayablePaymentService::payRefundPayable()
      → Payment created (refund_payable_id set, type = "Ticket Refund - Payment")
      → Voucher created
      → passenger.refund_payable DECREASED by paid amount
    → Invoice paid_amount UNAFFECTED (excluded by refund_payable_id filter)
    → Page reloads, updated refund_payable shown
```

---

## Verification

After implementation, run:

```bash
# Run the new tests
php artisan test tests/Feature/RefundPayablePaymentTest.php

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
