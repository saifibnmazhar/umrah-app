@extends('layouts.app')
@section('title', 'Cancelled Passenger Details')
@section('content')
@php $cp = $cancelledPassenger; @endphp
<div class="w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="{{ route('cancelled-bookings.index', ['tab' => 'passengers']) }}" class="text-sm text-blue-600 hover:text-blue-800 mb-2 inline-block">← Back to Cancelled Bookings</a>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                Cancelled Passenger Details
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cp->status === \App\Enums\CancelledBookingStatus::CANCELLED ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                    {{ $cp->status === \App\Enums\CancelledBookingStatus::CANCELLED ? 'Cancelled' : 'Cancellation Processing' }}
                </span>
            </h1>
            <p class="text-sm text-slate-600 mt-1">
                Passenger: {{ trim(($cp->passenger?->first_name ?? '') . ' ' . ($cp->passenger?->last_name ?? '')) ?: '—' }} | Invoice: {{ $cp->booking?->invoice_id ?? '—' }}
            </p>
        </div>
        <a href="{{ route('cancelled-passengers.print', $cp->id) }}"
           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">Print Refund Voucher</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Passenger Information</h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Name:</span><span class="font-medium text-slate-700">{{ trim(($cp->passenger?->first_name ?? '') . ' ' . ($cp->passenger?->last_name ?? '')) ?: '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Passport:</span><span class="font-medium text-slate-700">{{ $cp->passenger?->passport_no ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">DOB:</span><span class="font-medium text-slate-700">{{ $cp->passenger?->date_of_birth?->format('d-M-Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Gender:</span><span class="font-medium text-slate-700">{{ ucfirst($cp->passenger?->gender?->value ?? '—') }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Mobile:</span><span class="font-medium text-slate-700">{{ $cp->passenger?->mobile_no ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Booking Invoice:</span><span class="font-medium text-slate-700">{{ $cp->booking?->invoice_id ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Customer:</span><span class="font-medium text-slate-700">{{ $cp->booking?->customer?->name ?? '—' }}</span></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Cancellation Summary</h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Package Value:</span><span class="font-medium text-slate-700 text-right">@currency($cp->package_value, 2)</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Additional Tickets:</span><span class="font-medium text-slate-700 text-right">@currency($cp->additional_ticket_value, 2)</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Total Passenger Due:</span><span class="font-medium text-slate-700 text-right">@currency($cp->total_passenger_due, 2)</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Service Charge Deduction:</span><span class="font-medium text-slate-700 text-right">@currency($cp->service_charge_deduction, 2)</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Refundable Amount:</span><span class="font-medium text-slate-700 text-right">@currency($cp->refundable_amount, 2)</span></div>
                <div class="border-t border-slate-200 pt-2 flex justify-between bg-blue-50 px-3 py-2 rounded-lg -mx-3">
                    <span class="font-semibold text-blue-700">Adjusted from Due:</span>
                    <span class="font-semibold text-blue-700 text-right">@currency($cp->balance_adjusted_amount, 2)</span>
                </div>
                <div class="flex justify-between"><span class="text-slate-500">Invoice Due (before):</span><span class="font-medium text-slate-700 text-right">@currency($cp->booking?->invoice?->balance, 2)</span></div>
                <div class="border-t border-slate-200 pt-2 flex justify-between">
                    <span class="font-semibold text-slate-700">Refund Amount (Cash):</span>
                    <span class="font-semibold text-green-600 text-right">@currency($cp->refund_amount, 2)</span>
                </div>
                <div class="border-t border-slate-200 pt-2 mt-2 space-y-3">
                    <div class="flex justify-between"><span class="text-slate-500">Cancel Date:</span><span class="font-medium text-slate-700">{{ $cp->created_at?->format('d-M-Y') ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Cancellation Branch:</span><span class="font-medium text-slate-700">{{ $cp->cancellationBranch?->name ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Confirmed By:</span><span class="font-medium text-slate-700">{{ $cp->confirmedBy?->name ?? '—' }}</span></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Financial Transactions</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="border border-slate-200 rounded-lg p-3">
                    <span class="font-semibold text-slate-700">Deduction Payment (Service Charge)</span>
                    <div class="mt-2 space-y-1 text-slate-600">
                        <div>Amount: <span class="font-medium text-slate-700">@currency($cp->deductionPayment?->amount, 2)</span></div>
                        <div>Method: {{ ucfirst($cp->deductionPayment?->payment_method?->value ?? '—') }}</div>
                        <div>Voucher: {{ $cp->deductionVoucher?->voucher_id ?? '—' }}</div>
                    </div>
                </div>
                <div class="border border-blue-200 bg-blue-50 rounded-lg p-3">
                    <span class="font-semibold text-blue-700">Adjustment Payment (Due Adjustment)</span>
                    <div class="mt-2 space-y-1 text-blue-800/70">
                        <div>Amount: <span class="font-medium">@currency($cp->adjustmentPayment?->amount, 2)</span></div>
                        <div>Method: {{ ucfirst($cp->adjustmentPayment?->payment_method?->value ?? '—') }}</div>
                        <div>Voucher: {{ $cp->adjustmentVoucher?->voucher_id ?? '—' }}</div>
                    </div>
                </div>
                <div class="border border-slate-200 rounded-lg p-3">
                    <span class="font-semibold text-slate-700">Refund Payment</span>
                    <div class="mt-2 space-y-1 text-slate-600">
                        <div>Amount: <span class="font-medium text-slate-700">@currency($cp->refundPayment?->amount, 2)</span></div>
                        <div>Method: {{ ucfirst($cp->refundPayment?->payment_method?->value ?? '—') }}</div>
                        <div>Voucher: {{ $cp->refundVoucher?->voucher_id ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
