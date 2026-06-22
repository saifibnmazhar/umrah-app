@extends('layouts.app')
@section('title', 'Add Voucher')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('vouchers.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Vouchers
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Add Voucher</h1>

    <form method="POST" action="{{ route('vouchers.store') }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="voucher_id" class="block text-sm font-medium text-slate-700 mb-1">Voucher ID</label>
                <input 
                    type="text" 
                    name="voucher_id" 
                    id="voucher_id" 
                    value="{{ old('voucher_id', 'VCH-' . date('Ymd') . '-' . str_pad(\App\Models\Voucher::count() + 1, 4, '0', STR_PAD_LEFT)) }}"
                    maxlength="255"
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('voucher_id') border-red-500 @enderror"
                >
                @error('voucher_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="transaction_type_id" class="block text-sm font-medium text-slate-700 mb-1">Transaction Type *</label>
                <select 
                    name="transaction_type_id" 
                    id="transaction_type_id" 
                    required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('transaction_type_id') border-red-500 @enderror"
                >
                    <option value="">Select Type</option>
                    @foreach($transactionTypes as $type)
                        <option value="{{ $type->id }}" {{ old('transaction_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }} ({{ ucfirst($type->type) }})
                        </option>
                    @endforeach
                </select>
                @error('transaction_type_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label for="booking_id" class="block text-sm font-medium text-slate-700 mb-1">Booking *</label>
                <select 
                    name="booking_id" 
                    id="booking_id" 
                    required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('booking_id') border-red-500 @enderror"
                >
                    <option value="">Select Booking</option>
                    @foreach($bookings as $booking)
                        <option value="{{ $booking->id }}" {{ old('booking_id') == $booking->id ? 'selected' : '' }}>
                            #{{ $booking->id }} - {{ $booking->customer->name ?? 'N/A' }}
                        </option>
                    @endforeach
                </select>
                @error('booking_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="payment_id" class="block text-sm font-medium text-slate-700 mb-1">Payment *</label>
                <select 
                    name="payment_id" 
                    id="payment_id" 
                    required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('payment_id') border-red-500 @enderror"
                >
                    <option value="">Select Payment</option>
                    @foreach($payments as $payment)
                        @php $paymentRate = $payment->booking?->currencyRate?->rate ?? app(\App\Services\CurrencyRateService::class)->getRateForDate($payment->booking?->created_at)?->rate ?? 0; @endphp
                        <option value="{{ $payment->id }}" {{ old('payment_id') == $payment->id ? 'selected' : '' }}>
                            #{{ $payment->id }} - @currency($payment->amount, 2, $paymentRate)
                        </option>
                    @endforeach
                </select>
                @error('payment_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="branch_id" class="block text-sm font-medium text-slate-700 mb-1">Branch *</label>
                <select 
                    name="branch_id" 
                    id="branch_id" 
                    required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('branch_id') border-red-500 @enderror"
                >
                    <option value="">Select Branch</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
                @error('branch_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label for="payment_date" class="block text-sm font-medium text-slate-700 mb-1">Payment Date *</label>
                <input 
                    type="date" 
                    name="payment_date" 
                    id="payment_date" 
                    value="{{ old('payment_date', date('Y-m-d')) }}"
                    required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('payment_date') border-red-500 @enderror"
                >
                @error('payment_date')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="payment_method" class="block text-sm font-medium text-slate-700 mb-1">Payment Method *</label>
                <select 
                    name="payment_method" 
                    id="payment_method" 
                    required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('payment_method') border-red-500 @enderror"
                >
                    <option value="">Select Method</option>
                    <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                    <option value="bank" {{ old('payment_method') == 'bank' ? 'selected' : '' }}>Bank</option>
                </select>
                @error('payment_method')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="bank_id" class="block text-sm font-medium text-slate-700 mb-1">Bank</label>
                <select 
                    name="bank_id" 
                    id="bank_id" 
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('bank_id') border-red-500 @enderror"
                >
                    <option value="">Select Bank</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                            {{ $bank->name }}
                        </option>
                    @endforeach
                </select>
                @error('bank_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">Amount (SAR) *</label>
                <input 
                    type="number" 
                    name="amount" 
                    id="amount" 
                    value="{{ old('amount') }}"
                    step="0.01"
                    min="0"
                    required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('amount') border-red-500 @enderror"
                >
                @error('amount')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="bdt_amount" class="block text-sm font-medium text-slate-700 mb-1">BDT Amount *</label>
                <input 
                    type="number" 
                    name="bdt_amount" 
                    id="bdt_amount" 
                    value="{{ old('bdt_amount') }}"
                    step="0.01"
                    min="0"
                    required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('bdt_amount') border-red-500 @enderror"
                >
                @error('bdt_amount')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="currency_rate_id" class="block text-sm font-medium text-slate-700 mb-1">Currency Rate</label>
                <select 
                    name="currency_rate_id" 
                    id="currency_rate_id" 
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('currency_rate_id') border-red-500 @enderror"
                >
                    <option value="">Select Rate</option>
                    @foreach($currencyRates as $rate)
                        <option value="{{ $rate->id }}" {{ old('currency_rate_id') == $rate->id ? 'selected' : '' }}>
                            {{ number_format($rate->rate, 4) }}
                        </option>
                    @endforeach
                </select>
                @error('currency_rate_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="transaction_id" class="block text-sm font-medium text-slate-700 mb-1">Transaction ID</label>
                <input 
                    type="text" 
                    name="transaction_id" 
                    id="transaction_id" 
                    value="{{ old('transaction_id') }}"
                    maxlength="255"
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('transaction_id') border-red-500 @enderror"
                >
                @error('transaction_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label for="ticket_agent_id" class="block text-sm font-medium text-slate-700 mb-1">Ticket Agent</label>
                <select 
                    name="ticket_agent_id" 
                    id="ticket_agent_id" 
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('ticket_agent_id') border-red-500 @enderror"
                >
                    <option value="">Select Agent</option>
                    @foreach($ticketAgents as $agent)
                        <option value="{{ $agent->id }}" {{ old('ticket_agent_id') == $agent->id ? 'selected' : '' }}>
                            {{ $agent->name }}
                        </option>
                    @endforeach
                </select>
                @error('ticket_agent_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="visa_agent_id" class="block text-sm font-medium text-slate-700 mb-1">Visa Agent</label>
                <select 
                    name="visa_agent_id" 
                    id="visa_agent_id" 
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('visa_agent_id') border-red-500 @enderror"
                >
                    <option value="">Select Agent</option>
                    @foreach($visaAgents as $agent)
                        <option value="{{ $agent->id }}" {{ old('visa_agent_id') == $agent->id ? 'selected' : '' }}>
                            {{ $agent->name }}
                        </option>
                    @endforeach
                </select>
                @error('visa_agent_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="commission_agent_id" class="block text-sm font-medium text-slate-700 mb-1">Commission Agent</label>
                <select 
                    name="commission_agent_id" 
                    id="commission_agent_id" 
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('commission_agent_id') border-red-500 @enderror"
                >
                    <option value="">Select Agent</option>
                    @foreach($commissionAgents as $agent)
                        <option value="{{ $agent->id }}" {{ old('commission_agent_id') == $agent->id ? 'selected' : '' }}>
                            {{ $agent->name }}
                        </option>
                    @endforeach
                </select>
                @error('commission_agent_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Create Voucher
            </button>
            <a href="{{ route('vouchers.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection