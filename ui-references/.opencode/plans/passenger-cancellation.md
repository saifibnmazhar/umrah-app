# Plan — Individual Passenger Cancellation (Revised)

> Umrah App (Laravel 12). Adds per-passenger cancellation, driven by the existing
> **Current Status** dropdown in the Bookings index → Passengers tab, mirroring the
> existing whole-booking two-stage workflow but scoped to a single passenger.

---

## 1. Overview

**Goal:** Allow cancelling a single passenger from a multi-passenger booking via a two-stage
workflow (initiate → processing → confirm/revert), with its own financial model where the
base is passenger.package_value — fingerprint charge and booking discount are **never touched**.

### Key decisions locked

- **Initiation trigger:** Select Cancel in the Current Status inline dropdown (no 3-dot button)
- **Role restriction:** Cancel dropdown option + initiation: Super Admin / Co Admin only
- **Initiate effect:** Status → Hold, create cancelled_passengers row (cancellation processing)
- **Revert:** Soft-delete row, is_cancelled = false, status → none, syncComputedStatus()
- **Confirm:** Status → Cancel permanently (un-editable), totals recalc, balance credit, refund/deduction Payment+Voucher
- **Refundable formula:** package_value − (visa_cost + ticket_cost) − service_charge + refund_payable
- **Balance adjustment:** adjusted = min(refundable, balance) when balance > 0; user-reducible downward; customer_payout = refundable − adjusted
- **Fingerprint / discount:** Ignored; base = passenger.package_value
- **Confirm page content:** Package value + refundable amount only (no breakdown, no service charge edit)
- **Audit:** Soft-delete on revert for both passenger and booking cancellation; reverted_by_id FK

---

## 2. Workflow

### Stage 1 — Initiate (Super Admin / Co Admin only)

1. Status dropdown: Cancel option rendered only for Super/Co Admin.
2. Client-side intercept: selecting Cancel → fetch('GET /passengers/{id}/cancellation/preview') → open Cancel Passenger modal (PATCH is not sent).
3. Modal shows: package value, visa cost breakdown (4 lines), ticket cost breakdown (per-ticket), total cost, service charge input (SAR + BDT), refundable amount (live), cancellation branch select.
4. [Start Cancellation] → POST /passengers/{id}/cancellation/initiate:
   - Guards: passenger not already cancelled, no existing non-deleted cancelled_passengers row, booking not fully cancelled, booking has >= 2 active passengers.
   - Sets passenger.passenger_status_id = Hold, is_cancelled = true, cancelled_at = now().
   - Creates cancelled_passengers row: snapshot (package_value, visa_cost, ticket_cost, service_charge_deduction, refundable_amount, cancellation_branch_id, user_id), status PROCESSING.
   - No totals/payment changes.

### Stage 2 — Processing

Listed in Pending Refunds → Passenger Cancellations tab (status = 'cancellation processing'). Status shows Hold on the passenger. Booking/invoice untouched; fully reversible.

### Stage 3 — Revert OR Confirm (Branch Manager / Fingerprint Admin)

**Revert** (POST /cancelled-passengers/{id}/revert):
- Guard: status must be PROCESSING.
- Soft-delete the row (deleted_at), set reverted_by_id = auth()->id().
- passenger.is_cancelled = false, cancelled_at = null, passenger_status_id = null.
- Run syncComputedStatus().
- refund_payable NOT touched. Nothing financial to unwind.

**Confirm** (GET /cancelled-passengers/{id}/confirm page + POST):
- Guard: status must be PROCESSING.
- Page shows: Package Value (readonly), Refundable Amount (readonly), Adjust from Balance input (default min(refundable, balance), editable down), Customer Refund (auto = refundable − adjusted), Payment Method (cash/bank; bank requires remarks), remarks.
- Applies:
  - status = Cancel (permanent, un-editable)
  - booking.pax_qty −1; booking.total_value −= package_value
  - invoice.total_amount −= package_value
  - Credit invoice.balance −= adjusted (floor 0)
  - If service_charge > 0: deduction Payment+Voucher (cancelled_passenger_id, branch = cancellation branch, TransactionType "Service Charge Deduction")
  - If customer_payout > 0: refund Payment+Voucher (cancelled_passenger_id, branch = cancellation branch, TransactionType "Customer Refund")
  - passenger.refund_payable = max(0, refund_payable − refundable) (effectively 0)
  - confirmed_by_id = auth()->id()
  - Recompute invoice status via InvoiceService::updatePaymentStatus()
- Confirmed is final (no revert after confirm).

---

## 3. Financial model

### 3.1 Cost breakdown (per passenger, at initiate + confirm)

**Visa Cost** — passenger.visaSubmission:
net_visa_cost + agent_commission + additional_cost + cancellation_fee

| Source | Field | Table |
|---|---|---|
| visaSubmission->net_visa_cost | net_visa_cost | visa_submissions |
| visaSubmission->agent_commission | agent_commission | visa_submissions |
| visaSubmission->additional_cost | additional_cost | visa_submissions |
| visaSubmission->cancelledSubmission->cancellation_fee | cancellation_fee | cancelled_submissions |

0 if no visa submission.

**Ticket Cost** — sum over passenger.allIssuedTickets:

| IssuedTicket.status | Contribution |
|---|---|
| issued | net_fare |
| re-issued | latestReIssuedTicket().net_fare |
| refunded | latestRefundedTicket().net_fare |
| pending, awaiting-group, others | 0 (ignored) |

### 3.2 Refundable formula

refundable = package_value − (visa_cost + ticket_cost) − service_charge + refund_payable

Clamped >= 0. refund_payable = passenger.refund_payable (from refunded-ticket settlements).

### 3.3 Confirm-time math

    pax_qty         = pax_qty − 1
    total_value     = total_value − package_value
    invoice.total   = total_amount − package_value
    new_balance     = max(0, new_total − paid_amount)
    adjusted        = user-chosen (default min(refundable, new_balance); 0 if balance <= 0)
    balance         = new_balance − adjusted  (floor 0)
    customer_payout = refundable − adjusted

If service_charge > 0 → deduction Payment+Voucher. If customer_payout > 0 → refund Payment+Voucher. Refund rows carry cancelled_passenger_id and are excluded from paid_amount sum (Section 5 fix).

---

## 4. Migrations

### 4.1 create_cancelled_passengers_table (new)

    Schema::create('cancelled_passengers', function (Blueprint $table) {
        $table->id();

        $table->foreignId('booking_id')->constrained()->restrictOnDelete();
        $table->foreignId('passenger_id')->constrained()->restrictOnDelete();
        $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
        $table->foreignId('user_id')->constrained()->restrictOnDelete();

        $table->decimal('package_value', 14, 6);
        $table->decimal('visa_cost', 14, 6)->default(0);
        $table->decimal('ticket_cost', 14, 6)->default(0);
        $table->decimal('service_charge_deduction', 14, 6)->nullable();
        $table->decimal('refundable_amount', 14, 6)->default(0);
        $table->decimal('balance_adjusted_amount', 14, 6)->default(0);
        $table->decimal('refund_amount', 14, 6)->default(0);

        $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();

        $table->enum('status', ['cancellation processing', 'cancelled'])
            ->default('cancellation processing');

        $table->foreignId('deduction_payment_id')->nullable()->constrained('payments')->nullOnDelete();
        $table->foreignId('deduction_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
        $table->foreignId('refund_payment_id')->nullable()->constrained('payments')->nullOnDelete();
        $table->foreignId('refund_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();

        $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('reverted_by_id')->nullable()->constrained('users')->nullOnDelete();

        $table->timestamps();
        $table->softDeletes();

        // No unique constraint — enforce uniqueness in service logic
    });

### 4.2 add_is_cancelled_to_passengers_table (new)

    Schema::table('passengers', function (Blueprint $table) {
        $table->boolean('is_cancelled')->default(false)->after('refund_payable');
        $table->timestamp('cancelled_at')->nullable()->after('is_cancelled');
    });

### 4.3 add_cancelled_passenger_id_to_payments_and_vouchers_table (new)

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

### 4.4 add_soft_deletes_and_reverted_by_to_cancelled_bookings_table (retrofit)

    Schema::table('cancelled_bookings', function (Blueprint $table) {
        $table->foreignId('confirmed_by_id')
            ->nullable()->after('user_id')
            ->constrained('users')->nullOnDelete();
        $table->foreignId('reverted_by_id')
            ->nullable()->after('confirmed_by_id')
            ->constrained('users')->nullOnDelete();
        $table->softDeletes();
    });

---

## 5. Invoice accounting fix

InvoiceService::updatePaymentStatus() (InvoiceService.php:30) currently sums all payments:

    $invoice->paid_amount = $invoice->payments()->sum('amount');

**Fix:** Exclude refund/deduction-linked rows:

    $invoice->paid_amount = $invoice->payments()
        ->whereNull('cancelled_booking_id')
        ->whereNull('cancelled_passenger_id')
        ->whereNull('refunded_ticket_id')
        ->whereNull('re_issued_ticket_id')
        ->sum('amount');

Without this, refund rows (positive amounts) inflate paid_amount on open invoices, breaking the balance math.

---

## 6. Models

### 6.1 CancelledPassenger (new)

    class CancelledPassenger extends Model
    {
        use SoftDeletes;

        protected $fillable = [
            'booking_id', 'passenger_id', 'invoice_id', 'user_id',
            'package_value', 'visa_cost', 'ticket_cost',
            'service_charge_deduction', 'refundable_amount',
            'balance_adjusted_amount', 'refund_amount',
            'cancellation_branch_id', 'status',
            'deduction_payment_id', 'deduction_voucher_id',
            'refund_payment_id', 'refund_voucher_id',
            'confirmed_by_id', 'reverted_by_id',
        ];

        protected $casts = [
            'package_value' => 'decimal:6',
            'visa_cost' => 'decimal:6',
            'ticket_cost' => 'decimal:6',
            'service_charge_deduction' => 'decimal:6',
            'refundable_amount' => 'decimal:6',
            'balance_adjusted_amount' => 'decimal:6',
            'refund_amount' => 'decimal:6',
            'status' => CancelledBookingStatus::class,
        ];

        // Relations: booking, invoice, user, passenger, cancellationBranch,
        //            deductionPayment, deductionVoucher, refundPayment, refundVoucher,
        //            confirmedBy, revertedBy
    }

### 6.2 CancelledBooking (updated)

- Add use SoftDeletes;
- Add confirmed_by_id to $fillable
- Add reverted_by_id to $fillable
- Add confirmedBy() BelongsTo relation
- Add revertedBy() BelongsTo relation

### 6.3 Passenger (updated)

- Add is_cancelled, cancelled_at to $fillable and $casts

### 6.4 Payment / Voucher (updated)

- Add cancelled_passenger_id to $fillable
- Add cancelledPassenger() BelongsTo relation

---

## 7. Service — PassengerCancellationService

Mirrors App\Services\CancellationService (wrapped in DB::transaction).

### getCancellationPreview(Passenger): array

Returns the modal data:

    [
        'package_value'    => $passenger->package_value,
        'visa_cost'        => [
            'net_visa_cost'     => $visa->net_visa_cost ?? 0,
            'agent_commission'  => $visa->agent_commission ?? 0,
            'additional_cost'   => $visa->additional_cost ?? 0,
            'cancellation_fee'  => $visa->cancelledSubmission?->cancellation_fee ?? 0,
            'total'             => $visaCost,
        ],
        'ticket_cost'      => [
            'tickets' => $ticketLines,
            'total'   => $ticketCost,
        ],
        'total_cost'       => $visaCost + $ticketCost,
        'refund_payable'   => $passenger->refund_payable,
        'refundable_amount' => max(0, $packageValue - $visaCost - $ticketCost - $serviceCharge + $refundPayable),
        'branches'         => $branches,
    ]

### initiateCancellation(Passenger, array $data): CancelledPassenger

Guards (throw Exception on failure):
1. passenger.is_cancelled is false
2. No non-deleted CancelledPassenger where passenger_id + booking_id
3. booking.is_cancelled is false
4. Active passengers >= 2

Compute:

    $visaCost     = $this->computeVisaCost($passenger);
    $ticketCost   = $this->computeTicketCost($passenger);
    $refundPayable = (float) $passenger->refund_payable;
    $refundable   = max(0, $packageValue - $visaCost - $ticketCost - $serviceCharge + $refundPayable);

Create:

    CancelledPassenger::create([
        'booking_id'            => $booking->id,
        'passenger_id'          => $passenger->id,
        'invoice_id'            => $booking->invoice_id,
        'user_id'               => auth()->id(),
        'package_value'         => $packageValue,
        'visa_cost'             => $visaCost,
        'ticket_cost'           => $ticketCost,
        'service_charge_deduction' => $serviceCharge,
        'refundable_amount'     => $refundable,
        'cancellation_branch_id' => $data['cancellation_branch_id'],
        'status'                => CancelledBookingStatus::PROCESSING,
    ]);

Flag passenger:

    $passenger->update([
        'passenger_status_id' => $holdStatusId,
        'is_cancelled'        => true,
        'cancelled_at'        => now(),
    ]);

No totals/payment changes.

### revertCancellation(CancelledPassenger): void

Guard: status must be PROCESSING.

    DB::transaction(function () use ($cancelledPassenger) {
        $passenger = $cancelledPassenger->passenger;

        $cancelledPassenger->update([
            'reverted_by_id' => auth()->id(),
        ]);
        $cancelledPassenger->delete(); // SoftDeletes

        $passenger->update([
            'is_cancelled'        => false,
            'cancelled_at'        => null,
            'passenger_status_id' => null,
        ]);
        $passenger->syncComputedStatus();
    });

refund_payable untouched.

### confirmCancellation(CancelledPassenger, array $data): CancelledPassenger

Guard: status must be PROCESSING.

    DB::transaction(function () use ($cancelledPassenger, $data) {
        $booking  = $cancelledPassenger->booking;
        $invoice  = $booking->invoice;
        $passenger = $cancelledPassenger->passenger;

        $pkg         = (float) $cancelledPassenger->package_value;
        $refundable  = (float) $cancelledPassenger->refundable_amount;
        $adjusted    = (float) $data['balance_adjusted_amount'];
        $refund      = max(0, $refundable - $adjusted);
        $serviceCharge = (float) $cancelledPassenger->service_charge_deduction;

        // 1. Reduce totals
        $booking->update(['pax_qty' => $booking->pax_qty - 1]);
        $booking->update(['total_value' => (float)$booking->total_value - $pkg]);
        $invoice->update(['total_amount' => (float)$invoice->total_amount - $pkg]);

        // 2. Credit balance
        $newBalance = max(0, (float)$invoice->total_amount - (float)$invoice->paid_amount);
        $invoice->update(['balance' => max(0, $newBalance - $adjusted)]);

        // 3. Deduction payment (service charge)
        if ($serviceCharge > 0) {
            // Create deduction Payment + Voucher (TransactionType: "Service Charge Deduction")
            // with cancelled_passenger_id, branch = cancellation_branch_id
        }

        // 4. Refund payment (customer payout)
        if ($refund > 0) {
            // Create refund Payment + Voucher (TransactionType: "Customer Refund")
            // with cancelled_passenger_id, branch = cancellation_branch_id
        }

        // 5. Reduce refund_payable
        $passenger->update([
            'refund_payable' => max(0, (float)$passenger->refund_payable - $refundable),
        ]);

        // 6. Update cancelled_passengers record
        $cancelledPassenger->update([
            'status'                => CancelledBookingStatus::CANCELLED,
            'balance_adjusted_amount' => $adjusted,
            'refund_amount'         => $refund,
            'confirmed_by_id'       => auth()->id(),
        ]);

        // 7. Set permanent status
        $passenger->update([
            'passenger_status_id' => $cancelStatusId,
        ]);

        // 8. Recompute invoice status (with Section 5 exclusion fix)
        $invoiceService = app(InvoiceService::class);
        $invoiceService->updatePaymentStatus($invoice);
    });

### computeVisaCost(Passenger): float (private helper)

    $visa = $passenger->visaSubmission;
    if (!$visa) return 0;

    $netVisaCost     = (float) ($visa->net_visa_cost ?? 0);
    $agentCommission = (float) ($visa->agent_commission ?? 0);
    $additionalCost  = (float) ($visa->additional_cost ?? 0);
    $cancellationFee = (float) ($visa->cancelledSubmission?->cancellation_fee ?? 0);

    return $netVisaCost + $agentCommission + $additionalCost + $cancellationFee;

### computeTicketCost(Passenger): float (private helper)

    return $passenger->allIssuedTickets
        ->filter(fn($t) => in_array($t->status, ['issued', 're-issued', 'refunded']))
        ->sum(function ($ticket) {
            return match ($ticket->status) {
                'issued'    => (float) $ticket->net_fare,
                're-issued' => (float) $ticket->latestReIssuedTicket?->net_fare ?? 0,
                'refunded'  => (float) $ticket->latestRefundedTicket?->net_fare ?? 0,
            };
        });

---

## 8. Controllers

### 8.1 PassengerCancellationViewController

In routes/booking-cancellation.php (or new route file).

| Method | Route | Name | Roles |
|---|---|---|---|
| preview | GET /passengers/{passenger}/cancellation/preview | passengers.cancellation.preview | Super Admin, Co Admin |
| confirmPage | GET /cancelled-passengers/{cp}/confirm | cancelled-passengers.confirm | Branch Manager, Fingerprint Admin |
| passengerIndex | GET /pending-refunds/passengers | pending-refunds.passengers | Super, Co, Branch Manager, Fingerprint Admin |

preview returns JSON (same structure as getCancellationPreview). confirmPage loads the view. passengerIndex queries CancelledPassenger with status = PROCESSING + branch scoping.

### 8.2 PassengerCancellationActionController

| Method | Route | Name | Roles |
|---|---|---|---|
| initiate | POST /passengers/{passenger}/cancellation/initiate | passengers.cancellation.initiate | Super Admin, Co Admin |
| revert | POST /cancelled-passengers/{cp}/revert | cancelled-passengers.revert | Branch Manager, Fingerprint Admin |
| confirmSubmit | POST /cancelled-passengers/{cp}/confirm | cancelled-passengers.confirm.submit | Branch Manager, Fingerprint Admin |

Both revert and confirmSubmit call ensureBranchAccess() (mirrors BookingCancellationActionController::ensureBranchAccess, with ensureFingerprintAdminHasBranch).

### 8.3 PassengerController::updateStatus (updated)

    // Server-side rejection of Cancel status
    $statusName = PassengerStatus::find($validated['passenger_status_id'])?->name;

    if ($statusName === 'Cancel') {
        return response()->json([
            'success' => false,
            'message' => 'Use the cancellation workflow to cancel a passenger.',
        ], 422);
    }

    // Block edits while cancellation is active
    $hasActiveCancellation = CancelledPassenger::where('passenger_id', $passenger->id)
        ->whereNull('deleted_at')->exists();
    if ($hasActiveCancellation) {
        return response()->json([
            'success' => false,
            'message' => 'Passenger status is locked during cancellation.',
        ], 422);
    }

---

## 9. UI

### 9.1 Bookings index — Passengers tab

File: resources/views/bookings/index.blade.php

**Status dropdown (~line 1125)** — add Cancel visibility gate:

    <select
        class="..."
        x-bind:value="getComputedStatusId({{ $loop->index }})"
        x-on:change="updatePassengerStatus({{ $passenger->id }}, $event.target.value)">
        <option value="">None</option>
        @foreach($passengerStatuses as $status)
            @php
                $isCancelOption = $status->name === 'Cancel';
                $canSelectCancel = $isCancelOption && auth()->user()->hasAnyRole(['Super Admin', 'Co Admin']);
                $isLocked = $passenger->cancelledPassengers()->whereNull('deleted_at')->exists();
            @endphp
            @if($isCancelOption && !$canSelectCancel)
                {{-- Don't render Cancel option for non-Super/Co Admin --}}
            @else
                <option value="{{ $status->id }}"
                    {{ $status->name === 'Hold' || $status->name === 'Cancel' || $isLocked ? 'disabled' : '' }}>
                    {{ $status->name }}
                </option>
            @endif
        @endforeach
    </select>

**Client-side intercept — updatePassengerStatus function (~line 6596):**

    function updatePassengerStatus(passengerId, statusId) {
        const statusName = getComputedStatusName(statusId);

        if (statusName === 'Cancel') {
            // Intercept — don't PATCH, open modal instead
            fetch(`/passengers/${passengerId}/cancellation/preview`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf_token() }
            })
            .then(r => r.json())
            .then(data => {
                openCancelPassengerModal(passengerId, data);
            })
            .catch(() => alert('Failed to load cancellation preview.'));
            return;
        }

        // Normal status update (existing logic)
        fetch(`/passengers/${passengerId}/status`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf_token() },
            body: JSON.stringify({ passenger_status_id: statusId || null })
        })
        .then(/* ... */);
    }

### 9.2 Initiate modal ("Cancel Passenger")

Styled like the existing Cancel Booking modal (~line 2821). Alpine.js component.

**Layout:**
1. Header: "Cancel Passenger"
2. Passenger info: name, passport, invoice ID
3. Financial Summary (read-only): Passenger Charge (package_value)
4. Visa Cost Breakdown: net_visa_cost, agent_commission, additional_cost, cancellation_fee → total
5. Ticket Cost Breakdown: per-ticket row (ticket number, status, net_fare) → total
6. Total Cost (visa + ticket)
7. Refundable Amount (live: pkg − costs − service_charge + refund_payable)
8. Cancellation Branch * (select)
9. Service Charge (SAR + BDT auto-convert)
10. [Start Cancellation] (orange) · [Cancel]

**Alpine.js state + submit logic:**

    cancelData: {},
    cancelPassengerModalVisible: false,
    cancelPassengerId: null,
    cancelBranchId: '',
    serviceCharge: 0,
    serviceChargeBdt: '',
    branches: [],
    refundPayable: 0,
    isSubmitting: false,

    get refundableAmount() {
        const pkg = this.cancelData.package_value || 0;
        const costs = (this.cancelData.visa_cost.total || 0)
                    + (this.cancelData.ticket_cost.total || 0);
        return Math.max(0, pkg - costs - (this.serviceCharge || 0) + this.refundPayable);
    },

    async openCancelPassengerModal(passengerId, data) {
        this.cancelPassengerId = passengerId;
        this.cancelData = data;
        this.refundPayable = data.refund_payable || 0;
        this.branches = data.branches || [];
        this.cancelBranchId = '';
        this.serviceCharge = 0;
        this.serviceChargeBdt = '';
        this.cancelPassengerModalVisible = true;
    },

    async submitCancelPassenger() {
        if (!this.cancelBranchId || this.isSubmitting) return;
        this.isSubmitting = true;
        try {
            const response = await fetch(`/passengers/${this.cancelPassengerId}/cancellation/initiate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf_token(),
                },
                body: JSON.stringify({
                    cancellation_branch_id: this.cancelBranchId,
                    service_charge_deduction: this.serviceCharge,
                }),
            });
            const result = await response.json();
            if (result.success) {
                this.cancelPassengerModalVisible = false;
                window.location.reload();
            } else {
                alert(result.message || 'Failed to initiate cancellation');
            }
        } catch (e) {
            alert('Failed to initiate cancellation');
        } finally {
            this.isSubmitting = false;
        }
    },

    closeCancelPassengerModal() {
        this.cancelPassengerModalVisible = false;
        this.cancelPassengerId = null;
    },

### 9.3 Pending Refunds — tabbed

File: resources/views/pending-refunds/index.blade.php

Add tabs at the top: Booking Cancellations (existing) | Passenger Cancellations (new).

**Controller change in BookingCancellationViewController::pendingRefunds:**

    public function pendingRefunds(Request $request)
    {
        $tab = $request->get('tab', 'bookings');

        // Booking cancellations query (existing)
        $cancelledBookings = CancelledBooking::with([...])
            ->where('status', CancelledBookingStatus::PROCESSING)
            // ... branch scoping, pagination
            ->paginate(20);

        // Passenger cancellations query (new)
        $cancelledPassengers = CancelledPassenger::with([
                'booking.customer', 'booking.bookingBranch', 'passenger',
                'user', 'cancellationBranch',
            ])
            ->where('status', CancelledBookingStatus::PROCESSING)
            ->when(auth()->user()->branch_id, fn($q) =>
                $q->where('cancellation_branch_id', auth()->user()->branch_id))
            ->when($request->filled('branch_id'), fn($q) =>
                $q->where('cancellation_branch_id', $request->branch_id))
            ->latest()
            ->paginate(20);

        $branches = Branch::select('id', 'name')->orderBy('name')->get();

        return view('pending-refunds.index', compact(
            'cancelledBookings', 'cancelledPassengers', 'branches', 'tab'
        ));
    }

**Blade tab navigation:**

    <div class="mb-4 flex border-b border-slate-200">
        <a href="?tab=bookings"
           class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'bookings' ?
               'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Booking Cancellations
        </a>
        <a href="?tab=passengers"
           class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'passengers' ?
               'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Passenger Cancellations
        </a>
    </div>

### 9.4 Confirm page (cancelled-passengers.confirm)

File: resources/views/cancelled-passengers/confirm.blade.php (new)

Patterned on cancelled-bookings/confirm.blade.php. Shows package value + refundable amount only (no breakdown, no service charge edit). Alpine.js.

**Layout:**
1. Back link to pending-refunds.passengers
2. Header: "Confirm Passenger Cancellation"
3. Package Value (readonly, green-50 bg)
4. Refundable Amount (readonly, green-50 bg)
5. Invoice Balance (readonly, slate-50 bg)
6. Adjust from Balance input (default min(refundable, balance), editable down)
7. Customer Refund (auto = refundable − adjusted, blue-50 bg)
8. Payment Method (cash/bank) — shown only when customerRefund > 0
9. Remarks textarea
10. [Confirm Cancellation] (blue) · [Back]

**Alpine.js logic:**

    refundableAmount: '{{ $cancelledPassenger->refundable_amount }}',
    adjusted: '{{ min($cancelledPassenger->refundable_amount, $invoice->balance) }}',
    paymentMethod: 'cash',
    remarks: '',

    get customerRefund() {
        return Math.max(0, parseFloat(this.refundableAmount) - parseFloat(this.adjusted));
    },
    get maxAdjustment() {
        return Math.max(0, Math.min(
            parseFloat(this.refundableAmount),
            parseFloat('{{ $invoice->balance }}')
        ));
    },

Form POSTs to route('cancelled-passengers.confirm.submit') with hidden fields for balance_adjusted_amount, payment_method, remarks.

---

## 10. Guards & edge cases

| Guard | Enforcement |
|---|---|
| Cancel dropdown option | Frontend: only render for Super/Co Admin. Server: PassengerController::updateStatus rejects Cancel status with message. |
| Status edit lock | Any passenger with a non-deleted cancelled_passengers row → block updateStatus + disable dropdown. |
| Cannot cancel last active passenger | Service guard: activePassengers >= 2 check in initiateCancellation. |
| Duplicate cancellation | Service guard: check no non-deleted cancelled_passengers row exists for this passenger+booking. |
| Ticket issue/visa/fingerprint/payment blocked | Guard: is_cancelled = true → block actions. Mirror booking-level guards per passenger. |
| Refund amount locked | After confirm: status = CANCELLED, cancelled_passengers.status = CANCELLED, row not soft-deleted. No further edits. |
| Revert only in PROCESSING state | Service guard: status must be PROCESSING for revert and confirm. |
| Branch access | ensureBranchAccess() mirrors BookingCancellationActionController pattern. Fingerprint Admin must have a branch. |
| Balance <= 0 | No adjustment possible; full payout. Adjust input = 0, customer refund = refundable. |
| Refundable <= 0 | Fully adjusted or negative → no customer payout. Adjust = 0, refund = 0. |
| Multiple confirmations | After confirm, status = CANCELLED → no further confirm/revert possible. |
| Revert → re-initiate | Soft-delete preserves audit trail. Re-initiation creates a fresh row (unique enforced in service, not DB). |

---

## 11. Tests (TDD-first)

### 11.1 PassengerCancellationServiceTest

- test_preview_computes_visa_cost_correctly — net_visa_cost + agent_commission + additional_cost + cancellation_fee
- test_preview_computes_ticket_cost_issued — issued → own net_fare
- test_preview_computes_ticket_cost_re_issued — re-issued → latestReIssuedTicket.net_fare
- test_preview_computes_ticket_cost_refunded — refunded → latestRefundedTicket.net_fare
- test_preview_ignores_pending_awaiting_tickets — pending/awaiting-group → 0
- test_refundable_formula — pkg − costs − svc + refund_payable clamped >= 0
- test_initiate_sets_hold_status — passenger status = Hold, is_cancelled = true, row = PROCESSING
- test_initiate_blocks_on_existing_cancellation — duplicate initiation throws
- test_initiate_blocks_last_active_passenger — last passenger → exception
- test_revert_deletes_row_restores_status — soft-deleted, is_cancelled = false, syncComputedStatus()
- test_revert_does_not_touch_refund_payable — refund_payable unchanged
- test_confirm_full_adjustment — adjusted = refundable, balance reduced, refund = 0, no Payment/Voucher
- test_confirm_partial_adjustment — adjusted < refundable, refund = remainder, refund Payment+Voucher created
- test_confirm_reduces_totals — pax_qty −1, total_value −pkg, invoice.total_amount −pkg
- test_confirm_creates_deduction_when_service_charge — deduction Payment+Voucher with cancelled_passenger_id
- test_confirm_reduces_refund_payable — refund_payable = max(0, rp − refundable)
- test_confirm_sets_permanent_status — passenger_status_id = Cancel, un-editable
- test_confirm_sets_confirmed_by_id — confirmed_by_id = auth()->id()
- test_revert_sets_reverted_by_id — reverted_by_id = auth()->id()

### 11.2 PassengerCancellationControllerTest

- test_routes_require_auth
- test_preview_requires_super_or_co_admin
- test_initiate_requires_super_or_co_admin
- test_confirm_requires_branch_manager_or_fingerprint_admin
- test_revert_requires_branch_manager_or_fingerprint_admin
- test_branch_scoping_enforced
- test_initiate_validation (missing branch, missing service charge)

### 11.3 InvoiceServiceTest

- test_refund_linked_payments_excluded_from_paid_amount

### 11.4 Blade render tests

- test_cancel_option_visible_for_super_admin
- test_cancel_option_hidden_for_delivery_staff
- test_initiate_modal_renders
- test_confirm_page_renders_with_adjustment

### 11.5 Verify

    vendor/bin/pint
    php artisan test
    npm run build
    docker compose config --quiet

---

## 12. Implementation order

| Step | Description | TDD |
|---|---|---|
| 1 | Migrations (all 4 tables) + CancelledPassenger model + relations + CancelledPassengerStatus enum (reuse CancelledBookingStatus) | Write tests first |
| 2 | PassengerCancellationService + PassengerCancellationServiceTest | TDD |
| 3 | Invoice accounting fix (InvoiceService::updatePaymentStatus exclusion) + InvoiceServiceTest | TDD |
| 4 | Controllers + routes (PassengerCancellationViewController, PassengerCancellationActionController) | TDD |
| 5 | UI: dropdown Cancel gating + client-side intercept in bookings/index.blade.php | Blade test |
| 6 | UI: Initiate modal (cancelPassengerModal) in bookings/index.blade.php | Blade test |
| 7 | UI: Pending Refunds tabbed view (pending-refunds/index.blade.php) | Blade test |
| 8 | UI: Confirm page (cancelled-passengers/confirm.blade.php) | Blade test |
| 9 | PassengerController::updateStatus — reject Cancel + lock during cancellation | TDD |
| 10 | Guards on ticket/visa/fingerprint/payment actions for is_cancelled passengers | TDD |
| 11 | Retrofit CancelledBooking with SoftDeletes + reverted_by_id + migration | TDD |
| 12 | Full suite + vendor/bin/pint + npm run build + docker compose config --quiet | — |

---

## 13. Summary of files touched

**New files:**
- database/migrations/..._create_cancelled_passengers_table.php
- database/migrations/..._add_is_cancelled_to_passengers_table.php
- database/migrations/..._add_cancelled_passenger_id_to_payments_and_vouchers_table.php
- database/migrations/..._add_soft_deletes_and_reverted_by_to_cancelled_bookings_table.php
- app/Models/CancelledPassenger.php
- app/Http/Controllers/PassengerCancellationViewController.php
- app/Http/Controllers/PassengerCancellationActionController.php
- resources/views/cancelled-passengers/confirm.blade.php
- tests/Feature/PassengerCancellationServiceTest.php
- tests/Feature/PassengerCancellationControllerTest.php

**Modified files:**
- app/Services/InvoiceService.php — exclusion fix
- app/Models/CancelledBooking — SoftDeletes + reverted_by_id
- app/Models/Passenger — is_cancelled, cancelled_at, cancelledPassengers() relation
- app/Models/Payment — cancelled_passenger_id, relation
- app/Models/Voucher — cancelled_passenger_id, relation
- app/Http/Controllers/PassengerController — reject Cancel, lock during cancellation
- app/Services/CancellationService — set reverted_by_id before soft-delete
- resources/views/bookings/index.blade.php — Cancel dropdown gating + intercept + modal
- resources/views/pending-refunds/index.blade.php — tabs + passenger tab
- routes/booking-cancellation.php — new routes
- app/Http/Controllers/BookingCancellationViewController — passenger tab query
