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
            referralBranch: '{{ old('branch_id', $payment->branch_id) }}',

            bankModalOpen: false,
            bankSaving: false,
            activeBankField: '',
            bankData: { name: '', description: '', currency: '', location: '' },
            bankErrors: {},

            handleCurrencyChange() {
                if (this.currency === 'BDT' && this.amount_sar > 0 && this.exchangeRate > 0) {
                    this.amount_bdt = (this.amount_sar * this.exchangeRate).toFixed(6);
                } else if (this.currency === 'SAR' && this.amount_bdt > 0 && this.exchangeRate > 0) {
                    this.amount_sar = (this.amount_bdt / this.exchangeRate).toFixed(6);
                }
            },
            handleSarInput() {
                if (this.exchangeRate > 0) {
                    this.amount_bdt = (this.amount_sar * this.exchangeRate).toFixed(6);
                }
            },
            handleBdtInput() {
                if (this.exchangeRate > 0) {
                    this.amount_sar = (this.amount_bdt / this.exchangeRate).toFixed(6);
                }
            },

            openBankModal(field) {
                this.activeBankField = field;
                this.bankData = { name: '', description: '', currency: '', location: '' };
                this.bankErrors = {};
                this.bankModalOpen = true;
            },
            closeBankModal() {
                this.bankModalOpen = false;
                this.activeBankField = '';
                this.bankErrors = {};
            },
            async saveBank() {
                this.bankSaving = true;
                this.bankErrors = {};
                try {
                    const response = await fetch('/api/banks/quick-create', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify(this.bankData)
                    });
                    const data = await response.json();
                    if (data.success) {
                        const senderOpt = document.createElement('option');
                        senderOpt.value = data.bank.id;
                        senderOpt.text = data.bank.name;
                        senderOpt.selected = (this.activeBankField === 'sender');
                        document.getElementById('sender_bank_id').add(senderOpt);

                        this.closeBankModal();
                        window.showToast('Bank created successfully', 'success');
                    } else {
                        if (data.errors) {
                            this.bankErrors = data.errors;
                        } else {
                            window.showToast(data.message || 'Failed to create bank', 'error');
                        }
                    }
                } catch (e) {
                    window.showToast('An error occurred', 'error');
                } finally {
                    this.bankSaving = false;
                }
            }
          }">
        @csrf
        @method('PUT')

        {{-- 1. Payment Method --}}
        <div>
            <label for="payment_method" class="block text-sm font-semibold text-slate-700 mb-1">Payment Method *</label>
            <select name="payment_method" id="payment_method" x-model="method" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('payment_method') border-red-500 @enderror">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
            </select>
            @error('payment_method')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror

            <div x-show="method === 'bank'" x-cloak class="pl-4 border-l-2 border-slate-200 space-y-4 mt-4">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="sender_bank_id" class="block text-sm font-medium text-slate-700">Sender Bank</label>
                        <button type="button" @click="openBankModal('sender')" class="text-sky-600 hover:text-sky-700 text-xs font-medium">+ Add Bank</button>
                    </div>
                    <select name="sender_bank_id" id="sender_bank_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('sender_bank_id') border-red-500 @enderror">
                        <option value="">Select Sender Bank</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" {{ old('sender_bank_id', $payment->sender_bank_id) == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                        @endforeach
                    </select>
                    @error('sender_bank_id')
                        <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="receiver_bank" class="block text-sm font-medium text-slate-700 mb-1">Receiver Bank</label>
                    <input type="text" name="receiver_bank" id="receiver_bank" value="{{ old('receiver_bank', $payment->receiver_bank) }}" maxlength="255" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('receiver_bank') border-red-500 @enderror" placeholder="Enter receiver bank name">
                    @error('receiver_bank')
                        <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="transaction_id" class="block text-sm font-medium text-slate-700 mb-1">Transaction ID</label>
                    <input type="text" name="transaction_id" id="transaction_id" value="{{ old('transaction_id', $payment->transaction_id) }}" maxlength="255" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('transaction_id') border-red-500 @enderror" placeholder="Enter TRX ID">
                    @error('transaction_id')
                        <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        {{-- 2. Payment Date --}}
        <div>
            <label for="payment_date" class="block text-sm font-semibold text-slate-700 mb-1">Payment Date *</label>
            <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', $payment->payment_date) }}" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('payment_date') border-red-500 @enderror">
            @error('payment_date')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        {{-- 3. Currency --}}
        <div>
            <label for="currency" class="block text-sm font-semibold text-slate-700 mb-1">Currency</label>
            <select x-model="currency" @change="handleCurrencyChange()" id="currency" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                <option value="SAR">SAR</option>
                <option value="BDT">BDT</option>
            </select>

            <div class="pl-4 border-l-2 border-slate-200 space-y-4 mt-4">
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

                <div x-show="currency === 'BDT'" x-cloak>
                    <template x-if="exchangeRate > 0">
                        <p class="text-sm text-slate-500">1 SAR = <span x-text="exchangeRate"></span> BDT</p>
                    </template>
                    <template x-if="exchangeRate <= 0">
                        <p class="text-sm text-red-500">Exchange rate not available. Cannot process BDT payment.</p>
                    </template>
                </div>
            </div>
        </div>

        {{-- 4. Referral Branch --}}
        <div>
            <label for="branch_id" class="block text-sm font-semibold text-slate-700 mb-1">Referral Branch</label>
            <select name="branch_id" id="branch_id" x-model="referralBranch" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('branch_id') border-red-500 @enderror">
                <option value="">Other</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ old('branch_id', $payment->branch_id) == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('branch_id')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror

            <div x-show="referralBranch === ''" x-cloak class="mt-3">
                <label for="payment_referral" class="block text-sm font-medium text-slate-700 mb-1">Payment Referral</label>
                <input type="text" name="payment_referral" id="payment_referral" value="{{ old('payment_referral', $payment->payment_referral) }}" maxlength="255" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('payment_referral') border-red-500 @enderror" placeholder="Enter referral name">
                @error('payment_referral')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        {{-- 5. Transaction Type --}}
        <div>
            <label for="transaction_type_id" class="block text-sm font-semibold text-slate-700 mb-1">Transaction Type *</label>
            <select name="transaction_type_id" id="transaction_type_id" x-model="transactionType" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('transaction_type_id') border-red-500 @enderror">
                <option value="">Select Type</option>
                @foreach($transactionTypes as $tt)
                    <option value="{{ $tt->id }}" {{ old('transaction_type_id', $payment->voucher?->transaction_type_id) == $tt->id ? 'selected' : '' }}>{{ $tt->name }}</option>
                @endforeach
            </select>
            @error('transaction_type_id')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror

            <div class="pl-4 border-l-2 border-slate-200 space-y-4 mt-4">
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
        </div>

        {{-- 6. Remarks --}}
        <div>
            <label for="remarks" class="block text-sm font-semibold text-slate-700 mb-1">Remarks</label>
            <input type="text" name="remarks" id="remarks" value="{{ old('remarks', $payment->remarks) }}" maxlength="255" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('remarks') border-red-500 @enderror" placeholder="Enter remarks">
            @error('remarks')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        @include('partials.bank-form-modal')

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