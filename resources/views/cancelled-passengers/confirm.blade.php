@extends('layouts.app')
@section('title', 'Confirm Passenger Cancellation')
@section('content')
@php
    $booking = $cancelledPassenger->booking;
    $invoice = $cancelledPassenger->invoice;
    $passenger = $cancelledPassenger->passenger;
@endphp
<div class="max-w-4xl mx-auto" x-data="{
    refundAmount: '{{ $cancelledPassenger->refund_amount }}',
    refundAmountBdt: '',
    originalRefundAmount: '{{ $cancelledPassenger->refund_amount }}',
    serviceCharge: '{{ $cancelledPassenger->service_charge_deduction ?? 0 }}',
    serviceChargeBdt: '',
    disposition: 'apply_to_due',
    paymentMethod: 'cash',
    remarks: '',
    cancelledPassengerId: '{{ $cancelledPassenger->id }}',
    currency: $store.currency.mode,
    toastMessage: '',
    toastType: 'info',
    toastVisible: false,
    init() {
        if ($store.currency.mode === 'BDT' && $store.currency.rate > 0) {
            if (this.refundAmount) this.refundAmountBdt = Math.round(parseFloat(this.refundAmount) * $store.currency.rate * 100) / 100;
            if (this.serviceCharge) this.serviceChargeBdt = Math.round(parseFloat(this.serviceCharge) * $store.currency.rate * 100) / 100;
        }
        this.$watch('refundAmount', () => {
            if (this._refundTimer) clearTimeout(this._refundTimer);
            this._refundTimer = setTimeout(() => this.saveRefundAmount(), 800);
        });
        this.$watch('currency', (mode) => {
            $store.currency.mode = mode;
            localStorage.setItem('currency_mode', mode);
            $store.currency.convertAll();
            window.dispatchEvent(new CustomEvent('currency-toggled'));
        });
        window.addEventListener('currency-toggled', () => {
            this.currency = $store.currency.mode;
            const r = $store.currency.rate;
            if ($store.currency.mode === 'BDT' && r > 0) {
                if (this.refundAmount) this.refundAmountBdt = Math.round(parseFloat(this.refundAmount) * r * 100) / 100;
                if (this.serviceCharge) this.serviceChargeBdt = Math.round(parseFloat(this.serviceCharge) * r * 100) / 100;
            } else {
                this.refundAmountBdt = '';
                this.serviceChargeBdt = '';
            }
        });
    },
    get customerRefund() {
        const refund = parseFloat(this.refundAmount) || 0;
        const charge = parseFloat(this.serviceCharge) || 0;
        return (refund - charge) > 0 ? (refund - charge).toFixed(6) : '0.000000';
    },
    showToast(message, type = 'info') {
        this.toastMessage = message;
        this.toastType = type;
        this.toastVisible = true;
        if (this._toastTimer) clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => this.toastVisible = false, 3000);
    },
    async saveRefundAmount() {
        try {
            const response = await fetch(`/api/cancelled-passengers/${this.cancelledPassengerId}/refund-amount`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ refund_amount: this.refundAmount }),
            });
            if (response.ok) this.showToast('Refund amount saved', 'success');
        } catch (e) {
            console.error('Failed to save refund amount', e);
        }
    },
}">
    <div class="mb-6">
        <a href="{{ route('pending-refunds.passengers') }}" class="text-slate-400 hover:text-slate-600 inline-flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Pending Refunds
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-2">Confirm Passenger Cancellation</h1>
    <p class="text-slate-500 mb-6">Invoice #{{ $booking->invoice_id ?? '—' }} — {{ $booking->customer?->name ?? 'N/A' }} — Passenger: {{ trim(($passenger->first_name ?? '') . ' ' . ($passenger->last_name ?? '')) ?: '—' }}</p>

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ session('error') }}
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Left Column: Read-only Summary --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-700 mb-4">Passenger Cancellation Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Package Value</span>
                        <span class="font-medium text-slate-800">@currency($cancelledPassenger->package_value, 2)</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Visa Cost</span>
                        <span class="font-medium text-red-600">@currency($cancelledPassenger->visa_cost, 2)</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Ticket Cost</span>
                        <span class="font-medium text-red-600">@currency($cancelledPassenger->ticket_cost, 2)</span>
                    </div>
                    <div class="flex justify-between text-sm pt-2 border-t border-slate-200">
                        <span class="text-slate-700 font-medium">Total Deduction</span>
                        <span class="font-semibold text-red-600">@currency($cancelledPassenger->visa_cost + $cancelledPassenger->ticket_cost, 2)</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-700 font-medium">Refundable Amount</span>
                        <span class="font-semibold text-green-600">@currency($cancelledPassenger->refundable_amount, 2)</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-700 mb-4">Cancellation Info</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Initiated By</span>
                        <span class="font-medium text-slate-800">{{ $cancelledPassenger->user?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Cancellation Branch</span>
                        <span class="font-medium text-slate-800">{{ $cancelledPassenger->cancellationBranch?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Initiated Date</span>
                        <span class="font-medium text-slate-800">{{ $cancelledPassenger->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Editable Form --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-700 mb-4">Refund Details</h3>

                <div class="space-y-4">
                    {{-- Ticket Refund Received --}}
                    <div>
                        <div x-show="$store.currency.mode === 'BDT'" x-cloak class="mb-3">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Refund Received (BDT)</label>
                            <input type="number" x-model="refundAmountBdt" min="0" step="0.01"
                                @input="
                                    const bdt = parseFloat(refundAmountBdt) || 0;
                                    const origSar = parseFloat(originalRefundAmount);
                                    const origBdt = Math.round(origSar * ($store.currency.rate || 1) * 100) / 100;
                                    if (bdt > origBdt) { refundAmountBdt = origBdt; showToast('Refund amount cannot exceed the original amount', 'warning'); return; }
                                    refundAmount = parseFloat(((parseFloat(refundAmountBdt) || 0) / ($store.currency.rate || 1)).toFixed(6));
                                "
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none font-medium"
                                placeholder="Enter amount in BDT">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Refund Received (SAR)</label>
                            <input type="number" x-model="refundAmount" step="0.000001" min="0"
                                :max="originalRefundAmount"
                                :readonly="$store.currency.mode === 'BDT'"
                                :class="{'bg-slate-100 cursor-not-allowed': $store.currency.mode === 'BDT'}"
                                @input="
                                    const val = parseFloat($event.target.value) || 0;
                                    const orig = parseFloat(originalRefundAmount);
                                    if (val > orig) { refundAmount = orig; showToast('Refund amount cannot exceed the original amount', 'warning'); }
                                    if ($store.currency.mode === 'BDT' && $store.currency.rate > 0) {
                                        refundAmountBdt = Math.round((refundAmount || 0) * $store.currency.rate * 100) / 100;
                                    }
                                "
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none font-medium"
                                placeholder="Enter amount in SAR">
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Auto-filled from refundable amount. Auto-saved on change.</p>
                    </div>

                    {{-- Service Charge --}}
                    <div>
                        <div x-show="$store.currency.mode === 'BDT'" x-cloak class="mb-3">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge (BDT)</label>
                            <input type="number" x-model="serviceChargeBdt" min="0" step="0.01"
                                @input="serviceCharge = parseFloat(((parseFloat(serviceChargeBdt) || 0) / ($store.currency.rate || 1)).toFixed(6))"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none font-medium"
                                placeholder="Enter amount in BDT">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge (SAR)</label>
                            <input type="number" x-model="serviceCharge" step="0.000001" min="0"
                                :readonly="$store.currency.mode === 'BDT'"
                                :class="{'bg-slate-100 cursor-not-allowed': $store.currency.mode === 'BDT'}"
                                @input="if ($store.currency.mode === 'BDT' && $store.currency.rate > 0) { serviceChargeBdt = Math.round((serviceCharge || 0) * $store.currency.rate * 100) / 100; }"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none font-medium"
                                placeholder="Enter amount in SAR">
                        </div>
                    </div>

                    {{-- Customer Refund (computed) --}}
                    <div class="p-3 bg-slate-50 rounded-lg">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-700 font-medium">Customer Refund</span>
                            <span class="font-bold text-blue-700" x-text="$currency(customerRefund, 2)"></span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Ticket Refund Received − Service Charge</p>
                    </div>

                    {{-- Disposition --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Disposition *</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" x-model="disposition" value="apply_to_due" class="text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-slate-700">Apply to due (reduce invoice balance)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" x-model="disposition" value="manual_payout" class="text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-slate-700">Manual payout (pay customer directly)</span>
                            </label>
                        </div>
                    </div>

                    {{-- Payment Method (only for manual payout) --}}
                    <div x-show="disposition === 'manual_payout'" x-cloak>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method *</label>
                        <select x-model="paymentMethod" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>

                    {{-- Remarks --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Remarks
                            <span x-show="disposition === 'manual_payout' && paymentMethod === 'bank'" class="text-red-500">*</span>
                        </label>
                        <textarea x-model="remarks" rows="3"
                                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
                                  :required="disposition === 'manual_payout' && paymentMethod === 'bank'" placeholder="Enter remarks"></textarea>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <form method="POST" action="{{ route('cancelled-passengers.confirm.submit', $cancelledPassenger->id) }}" onsubmit="return validatePassengerRefundForm()">
                @csrf
                <input type="hidden" name="refund_amount" :value="refundAmount">
                <input type="hidden" name="service_charge_deduction" :value="serviceCharge">
                <input type="hidden" name="balance_adjusted_amount" :value="disposition === 'apply_to_due' ? customerRefund : 0">
                <input type="hidden" name="payment_method" :value="disposition === 'manual_payout' ? paymentMethod : 'cash'">
                <input type="hidden" name="remarks" :value="remarks">

                <button type="submit" class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-lg">
                    Confirm & Process Refund
                </button>
            </form>
        </div>
    </div>

    {{-- Toast Notification --}}
    <div x-show="toastVisible" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="translate-x-0 opacity-100"
         x-transition:leave-end="translate-x-full opacity-0"
         class="fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white font-medium"
         :class="{
             'bg-slate-700': toastType === 'info',
             'bg-emerald-600': toastType === 'success',
             'bg-red-500': toastType === 'error',
             'bg-amber-500': toastType === 'warning'
         }">
        <span x-text="toastMessage"></span>
    </div>
</div>

@push('scripts')
<script>
function validatePassengerRefundForm() {
    const disposition = document.querySelector('[name="balance_adjusted_amount"]').closest('[x-data]').__x.$data.disposition;
    const method = document.querySelector('[name="payment_method"]').value;
    const remarks = document.querySelector('[name="remarks"]').value;
    if (disposition === 'manual_payout' && method === 'bank' && !remarks.trim()) {
        alert('Remarks are required when payment method is Bank.');
        return false;
    }
    return confirm('Confirm this passenger cancellation? This action cannot be undone.');
}
</script>
@endpush
@endsection
