@extends('layouts.app')
@section('title', 'Cancelled Booking Details')
@section('content')
@php $cb = $cancelledBooking; @endphp
<div class="w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="{{ route('cancelled-bookings.index') }}" class="text-sm text-blue-600 hover:text-blue-800 mb-2 inline-block">← Back to Cancelled Bookings</a>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                Cancelled Booking Details
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cb->status === \App\Enums\CancelledBookingStatus::CANCELLED ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                    {{ $cb->status === \App\Enums\CancelledBookingStatus::CANCELLED ? 'Cancelled' : 'Cancellation Processing' }}
                </span>
            </h1>
            <p class="text-sm text-slate-600 mt-1">Invoice: {{ $cb->booking?->invoice_id ?? '—' }}</p>
        </div>
        <a href="{{ route('cancelled-bookings.print', $cb->id) }}"
           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">Print Refund Voucher</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Booking Information</h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Invoice ID:</span><span class="font-medium text-slate-700">{{ $cb->booking?->invoice_id ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Booking Date:</span><span class="font-medium text-slate-700">{{ $cb->booking?->created_at?->format('d-M-Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Customer:</span><span class="font-medium text-slate-700">{{ $cb->booking?->customer?->name ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Mobile:</span><span class="font-medium text-slate-700">{{ $cb->booking?->customer?->mobile_no ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Booking Branch:</span><span class="font-medium text-slate-700">{{ $cb->booking?->bookingBranch?->name ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Fingerprint Branch:</span><span class="font-medium text-slate-700">{{ $cb->booking?->fingerprintBranch?->name ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Sale Representative:</span><span class="font-medium text-slate-700">{{ $cb->booking?->user?->name ?? '—' }}</span></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Passenger List</h2>
            @if($cb->booking?->passengers?->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">#</th>
                            <th class="px-3 py-2 text-left font-medium">Name</th>
                            <th class="px-3 py-2 text-left font-medium">Passport</th>
                            <th class="px-3 py-2 text-right font-medium">Package Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($cb->booking->passengers as $idx => $p)
                        <tr>
                            <td class="px-3 py-2 text-slate-600">{{ $idx + 1 }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ trim($p->first_name . ' ' . $p->last_name) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $p->passport_no ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700 text-right">@currency($p->package_value, 2)</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-sm text-slate-500">No passengers found</p>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Cancellation Summary</h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Total Amount:</span><span class="font-medium text-slate-700 text-right">@currency($cb->booking?->invoice?->total_amount, 2)</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Total Paid:</span><span class="font-medium text-slate-700 text-right">@currency($cb->total_paid, 2)</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Service Charge Deduction:</span><span class="font-medium text-slate-700 text-right">@currency($cb->service_charge_deduction, 2)</span></div>
                <div class="border-t border-slate-200 pt-2 flex justify-between">
                    <span class="font-semibold text-slate-700">Refund Amount:</span>
                    <span class="font-semibold text-green-600 text-right">@currency($cb->refund_amount, 2)</span>
                </div>
                <div class="border-t border-slate-200 pt-2 mt-2 space-y-3">
                    <div class="flex justify-between"><span class="text-slate-500">Cancelled By:</span><span class="font-medium text-slate-700">{{ $cb->user?->name ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Cancel Date:</span><span class="font-medium text-slate-700">{{ $cb->created_at?->format('d-M-Y') ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Cancellation Branch:</span><span class="font-medium text-slate-700">{{ $cb->cancellationBranch?->name ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Confirmed By:</span><span class="font-medium text-slate-700">{{ $cb->confirmedBy?->name ?? '—' }}</span></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Financial Transactions</h2>
            <div class="space-y-4 text-sm">
                <div class="border border-slate-200 rounded-lg p-3">
                    <span class="font-semibold text-slate-700">Deduction Payment (Service Charge)</span>
                    <div class="mt-2 space-y-1 text-slate-600">
                        <div>Amount: <span class="font-medium text-slate-700">@currency($cb->deductionPayment?->amount, 2)</span></div>
                        <div>Method: {{ ucfirst($cb->deductionPayment?->payment_method?->value ?? '—') }}</div>
                        <div>Date: {{ $cb->deductionPayment?->payment_date?->format('d-M-Y') ?? '—' }}</div>
                        <div>Voucher: {{ $cb->deductionVoucher?->voucher_id ?? '—' }}
                            @if($cb->deductionVoucher)
                            <a href="{{ route('payments.print-voucher', $cb->deduction_payment_id) }}" class="text-blue-600 hover:text-blue-800 ml-1">[Print]</a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="border border-slate-200 rounded-lg p-3">
                    <span class="font-semibold text-slate-700">Refund Payment</span>
                    <div class="mt-2 space-y-1 text-slate-600">
                        <div>Amount: <span class="font-medium text-slate-700">@currency($cb->refundPayment?->amount, 2)</span></div>
                        <div>Method: {{ ucfirst($cb->refundPayment?->payment_method?->value ?? '—') }}</div>
                        <div>Date: {{ $cb->refundPayment?->payment_date?->format('d-M-Y') ?? '—' }}</div>
                        <div>Voucher: {{ $cb->refundVoucher?->voucher_id ?? '—' }}
                            @if($cb->refundVoucher)
                            <a href="{{ route('payments.print-voucher', $cb->refund_payment_id) }}" class="text-blue-600 hover:text-blue-800 ml-1">[Print]</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
