@extends('layouts.app')
@section('title', 'Confirm Refund')
@section('content')
@php
    $booking = $cancelledBooking->booking;
    $invoice = $booking->invoice;
@endphp
<div class="max-w-4xl mx-auto" x-data="{
    refundAmount: '{{ $cancelledBooking->refund_amount }}',
    refundAmountBdt: '',
    paymentMethod: 'cash',
    remarks: '',
    branchLocation: '{{ $booking->bookingBranch?->location ?? '' }}',
    currency: $store.currency.mode,
    init() {
        if ($store.currency.mode === 'BDT' && $store.currency.rate > 0 && this.refundAmount) {
            this.refundAmountBdt = Math.round(parseFloat(this.refundAmount) * $store.currency.rate * 100) / 100;
        }
        this.$watch('currency', (mode) => {
            $store.currency.mode = mode;
            localStorage.setItem('currency_mode', mode);
            $store.currency.convertAll();
            window.dispatchEvent(new CustomEvent('currency-toggled'));
        });
        window.addEventListener('currency-toggled', () => {
            this.currency = $store.currency.mode;
            const r = $store.currency.rate;
            if ($store.currency.mode === 'BDT' && r > 0 && this.refundAmount) {
                this.refundAmountBdt = Math.round(parseFloat(this.refundAmount) * r * 100) / 100;
            } else {
                this.refundAmountBdt = '';
            }
        });
    },
}">
    <div class="mb-6">
        <a href="{{ route('pending-refunds.index') }}" class="text-slate-400 hover:text-slate-600 inline-flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Pending Refunds
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-2">Confirm Refund</h1>
    <p class="text-slate-500 mb-6">Invoice #{{ $booking->invoice_id ?? '—' }} — {{ $booking->customer?->name ?? 'N/A' }}</p>

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ session('error') }}
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Left Column: Read-only Summary --}}
        <div class="space-y-6">
            {{-- Financial Summary --}}
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-700 mb-4">Financial Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Total Amount</span>
                        <span class="font-medium text-slate-800">@currency($invoice?->total_amount ?? 0)</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Total Paid</span>
                        <span class="font-medium text-green-600">@currency($invoice?->paid_amount ?? 0)</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Service Charge Deduction</span>
                        <span class="font-medium text-slate-800">
                            @if($cancelledBooking->service_charge_deduction !== null)
                                @currency($cancelledBooking->service_charge_deduction)
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between text-sm pt-2 border-t border-slate-200">
                        <span class="text-slate-700 font-medium">Refund Amount</span>
                        <span class="font-bold text-blue-700">@currency($cancelledBooking->refund_amount)</span>
                    </div>
                </div>
            </div>

            {{-- Cost Breakdown --}}
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-700 mb-4">Cost Breakdown</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Fingerprint Cost</span>
                        <span class="font-medium">@currency($costSummary['fingerprint_cost'])</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Visa Cost</span>
                        <span class="font-medium">@currency($costSummary['visa_cost'])</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Ticket Cost</span>
                        <span class="font-medium">@currency($costSummary['ticket_cost'])</span>
                    </div>
                    <div class="flex justify-between text-sm pt-2 border-t border-slate-200 font-semibold">
                        <span class="text-slate-700">Total Cost</span>
                        <span>@currency($costSummary['total_cost'])</span>
                    </div>
                </div>
            </div>

            {{-- Cancellation Info --}}
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-700 mb-4">Cancellation Info</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Cancelled By</span>
                        <span class="font-medium text-slate-800">{{ $cancelledBooking->user?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Cancellation Branch</span>
                        <span class="font-medium text-slate-800">{{ $cancelledBooking->cancellationBranch?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Cancel Date</span>
                        <span class="font-medium text-slate-800">{{ $cancelledBooking->created_at->format('Y-m-d') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Booking Branch</span>
                        <span class="font-medium text-slate-800">{{ $booking->bookingBranch?->name ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Editable Form --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-700 mb-4">Refund Details</h3>

                <div class="space-y-4">
                    {{-- Refund Amount --}}
                    <div>
                        <div x-show="$store.currency.mode === 'BDT'" class="mb-3">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Refund Amount (BDT)</label>
                            <input type="number" x-model="refundAmountBdt" min="0" step="0.01"
                                @input="refundAmount = parseFloat(((parseFloat(refundAmountBdt) || 0) / ($store.currency.rate || 1)).toFixed(6))"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none font-medium"
                                placeholder="Enter amount in BDT">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Refund Amount (SAR)</label>
                            <input type="number" x-model="refundAmount" step="0.000001" min="0"
                                :readonly="$store.currency.mode === 'BDT'"
                                :class="{'bg-slate-100 cursor-not-allowed': $store.currency.mode === 'BDT'}"
                                @input="if ($store.currency.mode === 'BDT' && $store.currency.rate > 0) { refundAmountBdt = Math.round((parseFloat($event.target.value) || 0) * $store.currency.rate * 100) / 100; }"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none font-medium"
                                placeholder="Enter amount in SAR">
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Default: {{ $cancelledBooking->refund_amount }} SAR (Total Paid &minus; Total Cost &minus; Service Charge)</p>
                    </div>

                    {{-- Currency --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                        <select x-model="currency"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="SAR">SAR</option>
                            <option value="BDT">BDT</option>
                        </select>
                    </div>

                    {{-- Payment Method --}}
                    <div>
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
                            <span x-show="paymentMethod === 'bank'" class="text-red-500">*</span>
                        </label>
                        <textarea x-model="remarks" rows="3"
                                  class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
                                  :required="paymentMethod === 'bank'" placeholder="Enter remarks"></textarea>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <form method="POST" action="{{ route('cancelled-bookings.confirm.submit', $cancelledBooking->id) }}" onsubmit="return validateRefundForm()">
                @csrf
                <input type="hidden" name="refund_amount" :value="refundAmount">
                <input type="hidden" name="payment_method" :value="paymentMethod">
                <input type="hidden" name="remarks" :value="remarks">
                <input type="hidden" name="service_charge_deduction" value="{{ $cancelledBooking->service_charge_deduction }}">

                <button type="submit" class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-lg">
                    Confirm & Process Refund
                </button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function validateRefundForm() {
    const method = document.querySelector('[name="payment_method"]').value;
    const remarks = document.querySelector('[name="remarks"]').value;
    if (method === 'bank' && !remarks.trim()) {
        alert('Remarks are required when payment method is Bank.');
        return false;
    }
    return confirm('Process this refund? This action cannot be undone.');
}
</script>
@endpush
@endsection
