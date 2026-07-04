@extends('layouts.app')
@section('title', 'Payments')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Payments</h1>
        @if(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Co Admin'))
        <a href="{{ route('payments.create') }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Payment
        </a>
        @endif
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-lg p-6 mb-4">
        <form method="GET" action="{{ route('payments.index') }}" id="payment-filter-form" class="flex items-end gap-4">
            <div class="min-w-[220px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">Transaction Type</label>
                <select name="transaction_type_id"
                        onchange="document.getElementById('payment-filter-form').submit()"
                        class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="">All Transaction Types</option>
                    @foreach($transactionTypes as $type)
                        <option value="{{ $type->id }}" {{ request('transaction_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <a href="{{ route('payments.index') }}"
                   class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition text-sm font-medium">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">ID</th>
                        <th class="px-3 py-2 text-left font-medium">Created By</th>
                        <th class="px-3 py-2 text-left font-medium">Payment Date</th>
                        <th class="px-3 py-2 text-left font-medium">Created At</th>
                        <th class="px-3 py-2 text-left font-medium">Transaction Type</th>
                        <th class="px-3 py-2 text-left font-medium">Agent Name</th>
                        <th class="px-3 py-2 text-left font-medium">Method</th>
                        <th class="px-3 py-2 text-right font-medium">Amount (SAR)</th>
                        <th class="px-3 py-2 text-right font-medium">Amount (BDT)</th>
                        <th class="px-3 py-2 text-left font-medium">Sender Bank</th>
                        <th class="px-3 py-2 text-left font-medium">Receiver Bank</th>
                        <th class="px-3 py-2 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-800 font-medium">#{{ $payment->id }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->user->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-600">
                                {{ $payment->payment_date->format('d/m/Y') }}
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                {{ $payment->created_at->format('d/m/Y') }}
                                <span class="local-time" data-utc="{{ $payment->created_at->toIso8601String() }}"></span>
                            </td>
                            <td class="px-3 py-2">
                                @if($payment->voucher?->transactionType)
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $payment->voucher->transactionType->type === 'debit' ? 'bg-amber-100 text-amber-700' : 'bg-teal-100 text-teal-700' }}">
                                        {{ $payment->voucher->transactionType->name }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                @if($payment->voucher?->transactionType?->name === 'Commission Agent Payment' && $payment->commissionAgent)
                                    {{ $payment->commissionAgent->name }}
                                @elseif($payment->voucher?->transactionType?->name === 'Ticket Agent Payment' && $payment->ticketAgent)
                                    {{ $payment->ticketAgent->name }}
                                @elseif($payment->voucher?->transactionType?->name === 'Visa Agent Payment' && $payment->visaAgent)
                                    {{ $payment->visaAgent->name }}
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($payment->payment_method->value === 'cash')
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700">Cash</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">Bank</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">{{ number_format($payment->bdt_amount, 2) }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->senderBank->name ?? $payment->other_sender_bank ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $payment->receiver_bank ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <a href="{{ route('payments.show', $payment->id) }}" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded">View</a>
                                    @if(auth()->user()->hasRole('Super Admin'))
                                    <a href="{{ route('payments.edit', $payment->id) }}" class="text-xs bg-amber-100 hover:bg-amber-200 text-amber-600 px-2 py-1 rounded">Edit</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-3 py-8 text-center text-slate-500">
                                No payments yet. Click "Add Payment" to create a new one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-center">
            {{ $payments->links() }}
        </div>
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