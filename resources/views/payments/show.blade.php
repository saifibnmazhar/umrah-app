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
            @if($payment->payment_method === 'cash')
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
                    <p class="text-sm text-slate-500">Booking</p>
                    <p class="font-medium text-slate-800">{{ $payment->booking->id ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Branch</p>
                    <p class="font-medium text-slate-800">{{ $payment->branch->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Payment Date</p>
                    <p class="font-medium text-slate-800">{{ $payment->payment_date }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Created By</p>
                    <p class="font-medium text-slate-800">{{ $payment->user->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Created At</p>
                    <p class="font-medium text-slate-800">{{ $payment->created_at->format('Y-m-d H:i') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="p-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">Amount Details</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-sm text-slate-500">Amount (SAR)</p>
                    <p class="font-medium text-slate-800">@currency($payment->amount, 2)</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">BDT Amount</p>
                    <p class="font-medium text-slate-800">{{ number_format($payment->bdt_amount, 2) }}</p>
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
            <h2 class="text-lg font-semibold text-slate-700">Agent Information</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-3 gap-6">
                <div>
                    <p class="text-sm text-slate-500">Bank</p>
                    <p class="font-medium text-slate-800">{{ $payment->bank->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Ticket Agent</p>
                    <p class="font-medium text-slate-800">{{ $payment->ticketAgent->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Visa Agent</p>
                    <p class="font-medium text-slate-800">{{ $payment->visaAgent->name ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-between">
        <a href="{{ route('payments.index') }}" class="px-4 py-2 bg-slate-600 text-white rounded-md hover:bg-slate-700 transition">
            Back to List
        </a>
        <div class="flex gap-2">
            <a href="{{ route('payments.edit', $payment->id) }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                Edit
            </a>
            <form method="POST" action="{{ route('payments.destroy', $payment->id) }}" onsubmit="return confirm('Are you sure you want to delete this payment?')" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>
@endsection