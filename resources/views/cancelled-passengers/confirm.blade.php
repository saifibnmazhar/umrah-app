@extends('layouts.app')
@section('title', 'Confirm Passenger Cancellation')
@section('content')
@php
    $booking = $cancelledPassenger->booking;
    $invoice = $cancelledPassenger->invoice;
    $passenger = $cancelledPassenger->passenger;
@endphp
<div class="max-w-4xl mx-auto" x-data="{
    refundableAmount: {{ (float) $cancelledPassenger->refundable_amount }},
    adjusted: {{ min((float) $cancelledPassenger->refundable_amount, (float) ($invoice->balance ?? 0)) }},
    paymentMethod: 'cash',
    remarks: '',

    get customerRefund() {
        return Math.max(0, parseFloat(this.refundableAmount) - parseFloat(this.adjusted));
    },
    get maxAdjustment() {
        return Math.max(0, Math.min(
            parseFloat(this.refundableAmount),
            parseFloat('{{ $invoice->balance ?? 0 }}')
        ));
    },
    validate() {
        if (this.customerRefund > 0 && this.paymentMethod === 'bank' && !this.remarks.trim()) {
            alert('Remarks are required when payment method is Bank.');
            return false;
        }
        return confirm('Confirm this passenger cancellation? This action cannot be undone.');
    }
}">
    <div class="mb-6">
        <a href="{{ route('pending-refunds.index', ['tab' => 'passengers']) }}" class="text-slate-400 hover:text-slate-600 inline-flex items-center gap-1">
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
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-700 mb-4">Cancellation Summary</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between p-3 bg-green-50 rounded-lg">
                        <span class="text-slate-700 font-medium">Package Value</span>
                        <span class="font-semibold text-slate-800">@currency($cancelledPassenger->package_value, 2)</span>
                    </div>
                    <div class="flex justify-between p-3 bg-green-50 rounded-lg">
                        <span class="text-slate-700 font-medium">Additional Tickets</span>
                        <span class="font-semibold text-slate-800">@currency($cancelledPassenger->additional_ticket_value, 2)</span>
                    </div>
                    <div class="flex justify-between p-3 bg-green-50 rounded-lg">
                        <span class="text-slate-700 font-medium">Total Passenger Due</span>
                        <span class="font-semibold text-slate-800">@currency($cancelledPassenger->total_passenger_due, 2)</span>
                    </div>
                    <div class="flex justify-between p-3 bg-green-50 rounded-lg">
                        <span class="text-slate-700 font-medium">Refundable Amount</span>
                        <span class="font-semibold text-green-700">@currency($cancelledPassenger->refundable_amount, 2)</span>
                    </div>
                    <div class="flex justify-between p-3 bg-slate-50 rounded-lg">
                        <span class="text-slate-700 font-medium">Invoice Balance</span>
                        <span class="font-semibold text-slate-800">@currency($invoice->balance ?? 0, 2)</span>
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
                        {{-- DB stores GMT; the browser converts it to local time. --}}
                        <span class="font-medium text-slate-800"
                            x-text="new Date('{{ $cancelledPassenger->created_at->toIso8601String() }}').toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true })"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-700 mb-4">Adjust & Refund</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Adjust from Balance</label>
                        <input type="number" x-model.number="adjusted" min="0" :max="maxAdjustment" step="0.000001"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none font-medium"
                            placeholder="0">
                        <p class="text-xs text-slate-400 mt-1">Max: <span x-text="$currency(maxAdjustment, 2)"></span> (lesser of refundable and balance)</p>
                    </div>

                    <div class="p-3 bg-blue-50 rounded-lg">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-700 font-medium">Customer Refund</span>
                            <span class="font-bold text-blue-700" x-text="$currency(customerRefund, 2)"></span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Refundable Amount − Adjusted</p>
                    </div>

                    <div x-show="customerRefund > 0" x-cloak>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method *</label>
                        <select x-model="paymentMethod" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none bg-white">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Remarks
                            <span x-show="customerRefund > 0 && paymentMethod === 'bank'" class="text-red-500">*</span>
                        </label>
                        <textarea x-model="remarks" rows="3"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                            :required="customerRefund > 0 && paymentMethod === 'bank'" placeholder="Enter remarks"></textarea>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('cancelled-passengers.confirm.submit', $cancelledPassenger->id) }}" @submit.prevent="if(validate()) $el.submit()">
                @csrf
                <input type="hidden" name="balance_adjusted_amount" :value="adjusted">
                <input type="hidden" name="payment_method" :value="paymentMethod">
                <input type="hidden" name="remarks" :value="remarks">

                <button type="submit" class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-lg">
                    Confirm Cancellation
                </button>
            </form>

            <a href="{{ route('pending-refunds.index', ['tab' => 'passengers']) }}" class="block text-center px-6 py-3 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">
                Back
            </a>
        </div>
    </div>
</div>
@endsection
