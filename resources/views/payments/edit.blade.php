@extends('layouts.app')
@section('title', 'Edit Payment')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('payments.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Payments
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit Payment</h1>

    <form method="POST" action="{{ route('payments.update', $payment->id) }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5"
          x-data="{
            method: '{{ old('payment_method', $payment->payment_method) }}',
            currency: 'SAR',
            amount_sar: {{ old('amount', $payment->amount) }},
            amount_bdt: {{ old('bdt_amount', $payment->bdt_amount) }},
            exchangeRate: {{ $currentCurrencyRate?->rate ?? 0 }},
            transactionType: '{{ old('transaction_type_id', $payment->voucher?->transaction_type_id) }}',

            handleCurrencyChange() {
                if (this.currency === 'BDT' && this.amount_sar > 0 && this.exchangeRate > 0) {
                    this.amount_bdt = (this.amount_sar * this.exchangeRate).toFixed(2);
                } else if (this.currency === 'SAR' && this.amount_bdt > 0 && this.exchangeRate > 0) {
                    this.amount_sar = (this.amount_bdt / this.exchangeRate).toFixed(2);
                }
            },
            handleSarInput() {
                if (this.exchangeRate > 0) {
                    this.amount_bdt = (this.amount_sar * this.exchangeRate).toFixed(2);
                }
            },
            handleBdtInput() {
                if (this.exchangeRate > 0) {
                    this.amount_sar = (this.amount_bdt / this.exchangeRate).toFixed(2);
                }
            }
          }">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="currency" class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                <select x-model="currency" @change="handleCurrencyChange()" id="currency" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="SAR">SAR</option>
                    <option value="BDT">BDT</option>
                </select>
            </div>
            <div>
                <label for="payment_method" class="block text-sm font-medium text-slate-700 mb-1">Payment Method *</label>
                <select name="payment_method" id="payment_method" x-model="method" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('payment_method') border-red-500 @enderror">
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                </select>
                @error('payment_method')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div x-show="method === 'bank'" x-cloak>
                <label for="bank_id" class="block text-sm font-medium text-slate-700 mb-1">Bank</label>
                <select name="bank_id" id="bank_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('bank_id') border-red-500 @enderror">
                    <option value="">Select Bank</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}" {{ old('bank_id', $payment->bank_id) == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                    @endforeach
                </select>
                @error('bank_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div x-show="method === 'bank'" x-cloak>
                <label for="transaction_id" class="block text-sm font-medium text-slate-700 mb-1">Transaction ID</label>
                <input type="text" name="transaction_id" id="transaction_id" value="{{ old('transaction_id', $payment->transaction_id) }}" maxlength="255" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('transaction_id') border-red-500 @enderror" placeholder="Enter TRX ID">
                @error('transaction_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">Amount (SAR) *</label>
                <input type="number" name="amount" id="amount" x-model="amount_sar" @input="handleSarInput()" :readonly="currency === 'BDT'" step="0.01" min="0" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" :class="{'bg-slate-100 cursor-not-allowed': currency === 'BDT'}" placeholder="Enter SAR amount">
                @error('amount')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div x-show="currency === 'BDT'" x-cloak>
                <label for="bdt_amount" class="block text-sm font-medium text-slate-700 mb-1">Amount (BDT) *</label>
                <input type="number" name="bdt_amount" id="bdt_amount" x-model="amount_bdt" @input="handleBdtInput()" step="0.01" min="0" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter BDT amount">
                @error('bdt_amount')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div x-show="currency === 'BDT'" class="col-span-2" x-cloak>
                <template x-if="exchangeRate > 0">
                    <p class="text-sm text-slate-500">1 SAR = <span x-text="exchangeRate"></span> BDT</p>
                </template>
                <template x-if="exchangeRate <= 0">
                    <p class="text-sm text-red-500">Exchange rate not available. Cannot process BDT payment.</p>
                </template>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="transaction_type_id" class="block text-sm font-medium text-slate-700 mb-1">Transaction Type *</label>
                <select name="transaction_type_id" id="transaction_type_id" x-model="transactionType" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('transaction_type_id') border-red-500 @enderror">
                    <option value="">Select Type</option>
                    @foreach($transactionTypes as $tt)
                        <option value="{{ $tt->id }}" {{ old('transaction_type_id', $payment->voucher?->transaction_type_id) == $tt->id ? 'selected' : '' }}>{{ $tt->name }}</option>
                    @endforeach
                </select>
                @error('transaction_type_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="payment_date" class="block text-sm font-medium text-slate-700 mb-1">Payment Date *</label>
                <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', $payment->payment_date) }}" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('payment_date') border-red-500 @enderror">
                @error('payment_date')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5">
            <div x-show="transactionType === '{{ $ticketPaymentTypeId }}'" x-cloak>
                <label for="ticket_agent_id" class="block text-sm font-medium text-slate-700 mb-1">Ticket Agent</label>
                <select name="ticket_agent_id" id="ticket_agent_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('ticket_agent_id') border-red-500 @enderror">
                    <option value="">Select Agent</option>
                    @foreach($ticketAgents as $agent)
                        <option value="{{ $agent->id }}" {{ old('ticket_agent_id', $payment->ticket_agent_id) == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                    @endforeach
                </select>
                @error('ticket_agent_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div x-show="transactionType === '{{ $visaPaymentTypeId }}'" x-cloak>
                <label for="visa_agent_id" class="block text-sm font-medium text-slate-700 mb-1">Visa Agent</label>
                <select name="visa_agent_id" id="visa_agent_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('visa_agent_id') border-red-500 @enderror">
                    <option value="">Select Agent</option>
                    @foreach($visaAgents as $agent)
                        <option value="{{ $agent->id }}" {{ old('visa_agent_id', $payment->visa_agent_id) == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                    @endforeach
                </select>
                @error('visa_agent_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div x-show="transactionType === '{{ $commissionPaymentTypeId }}'" x-cloak>
                <label for="commission_agent_id" class="block text-sm font-medium text-slate-700 mb-1">Commission Agent</label>
                <select name="commission_agent_id" id="commission_agent_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('commission_agent_id') border-red-500 @enderror">
                    <option value="">Select Agent</option>
                    @foreach($commissionAgents as $agent)
                        <option value="{{ $agent->id }}" {{ old('commission_agent_id', $payment->commission_agent_id) == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                    @endforeach
                </select>
                @error('commission_agent_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Update Payment
            </button>
            <a href="{{ route('payments.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
               Cancel
           </a>
        </div>
    </form>
</div>
@endsection