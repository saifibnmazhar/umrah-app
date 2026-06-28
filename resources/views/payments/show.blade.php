@extends('layouts.app')
@section('title', 'Payment Details')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <a href="{{ route('payments.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                ← Back to Payments
            </a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Payment #{{ $payment->id }}</h1>
        </div>
        <div class="flex gap-2">
            @if($payment->payment_method->value === 'cash')
                <span class="px-3 py-1 text-sm font-medium rounded-full bg-amber-100 text-amber-700">Cash</span>
            @else
                <span class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-700">Bank</span>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="p-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">Payment Information</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm text-slate-500">Payment ID</p>
                    <p class="font-medium text-slate-800">#{{ $payment->id }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Payment Date</p>
                    <p class="font-medium text-slate-800">{{ $payment->payment_date->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Created By</p>
                    <p class="font-medium text-slate-800">{{ $payment->user->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Created At</p>
                    <p class="font-medium text-slate-800">{{ $payment->created_at->format('d/m/Y') }} <span class="local-time" data-utc="{{ $payment->created_at->toIso8601String() }}"></span></p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="p-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">Amount Details</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm text-slate-500">Amount</p>
                    <p class="font-medium text-slate-800">@currency($payment->amount, 6, $rate, $payment->bdt_amount)</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Currency Rate</p>
                    <p class="font-medium text-slate-800">{{ $payment->currencyRate ? number_format($payment->currencyRate->rate, 4) : 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Transaction ID</p>
                    <p class="font-medium text-slate-800">{{ $payment->transaction_id ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="p-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">Payment Details</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm text-slate-500">Payment Method</p>
                    <p class="font-medium">
                        @if($payment->payment_method->value === 'cash')
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700">Cash</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">Bank</span>
                        @endif
                    </p>
                </div>
                @if($payment->payment_method->value === 'bank')
                    <div>
                        <p class="text-sm text-slate-500">Sender Bank</p>
                        <p class="font-medium text-slate-800">{{ $payment->senderBank->name ?? $payment->other_sender_bank ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Receiver Bank</p>
                        <p class="font-medium text-slate-800">{{ $payment->receiver_bank ?? '-' }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-sm text-slate-500">Referral Branch</p>
                    <p class="font-medium text-slate-800">{{ $payment->branch->name ?? $payment->payment_referral ?? '-' }}</p>
                </div>
                @if($payment->ticketAgent)
                    <div>
                        <p class="text-sm text-slate-500">Ticket Agent</p>
                        <p class="font-medium text-slate-800">{{ $payment->ticketAgent->name }}</p>
                    </div>
                @endif
                @if($payment->visaAgent)
                    <div>
                        <p class="text-sm text-slate-500">Visa Agent</p>
                        <p class="font-medium text-slate-800">{{ $payment->visaAgent->name }}</p>
                    </div>
                @endif
                @if($payment->commissionAgent)
                    <div>
                        <p class="text-sm text-slate-500">Commission Agent</p>
                        <p class="font-medium text-slate-800">{{ $payment->commissionAgent->name }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="flex justify-start">
        <a href="{{ route('payments.index') }}" class="px-4 py-2 bg-slate-600 text-white rounded-md hover:bg-slate-700 transition">
            Back to List
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.local-time').forEach(function(el) {
        var d = new Date(el.getAttribute('data-utc'));
        if (!isNaN(d)) {
            el.textContent = d.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
        }
    });
});
</script>
@endpush