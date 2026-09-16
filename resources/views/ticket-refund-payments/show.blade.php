@extends('layouts.app')
@section('title', 'Ticket Refund Payment Details')
@section('content')
<div class="w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="{{ route('ticket-refund-payments.index') }}" class="text-sm text-blue-600 hover:text-blue-800 mb-2 inline-block">← Back to Cancelled Bookings</a>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                Ticket Refund Payment Details
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                    Paid
                </span>
            </h1>
            <p class="text-sm text-slate-600 mt-1">
                Passenger: {{ trim(($payment->passenger?->first_name ?? '') . ' ' . ($payment->passenger?->last_name ?? '')) ?: '—' }} | Invoice: {{ $payment->booking?->invoice_id ?? '—' }}
            </p>
        </div>
        <a href="{{ route('ticket-refund-payments.print', $payment->id) }}"
           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">Print Refund Voucher</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Passenger Information</h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Name:</span><span class="font-medium text-slate-700">{{ trim(($payment->passenger?->first_name ?? '') . ' ' . ($payment->passenger?->last_name ?? '')) ?: '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Passport:</span><span class="font-medium text-slate-700">{{ $payment->passenger?->passport_no ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">DOB:</span><span class="font-medium text-slate-700">{{ $payment->passenger?->date_of_birth?->format('d-M-Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Mobile:</span><span class="font-medium text-slate-700">{{ $payment->passenger?->mobile_no ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Booking Invoice:</span><span class="font-medium text-slate-700">{{ $payment->booking?->invoice_id ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Customer:</span><span class="font-medium text-slate-700">{{ $payment->booking?->customer?->name ?? '—' }}</span></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Refund Payment Summary</h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Refund Amount:</span><span class="font-semibold text-green-600 text-right">@currency($payment->amount, 2)</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Method:</span><span class="font-medium text-slate-700">{{ ucfirst($payment->payment_method?->value ?? $payment->payment_method ?? '—') }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Voucher No:</span><span class="font-medium text-slate-700">{{ $payment->voucher?->voucher_id ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Payment Branch:</span><span class="font-medium text-slate-700">{{ $payment->branch?->name ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Paid By:</span><span class="font-medium text-slate-700">{{ $payment->user?->name ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Date:</span><span class="font-medium text-slate-700">{{ $payment->created_at?->format('d-M-Y') ?? '—' }}</span></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Financial Transactions</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="border border-green-200 bg-green-50 rounded-lg p-3">
                    <span class="font-semibold text-green-700">Refund Payment</span>
                    <div class="mt-2 space-y-1 text-green-800/70">
                        <div>Amount: <span class="font-medium">@currency($payment->amount, 2)</span></div>
                        <div>Method: {{ ucfirst($payment->payment_method?->value ?? $payment->payment_method ?? '—') }}</div>
                        <div>Voucher: {{ $payment->voucher?->voucher_id ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
