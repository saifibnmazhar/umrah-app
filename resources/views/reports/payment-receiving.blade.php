@extends('layouts.app')
@section('title', 'Payment Receiving Report')
@section('content')
<style>
    [x-cloak] { display: none !important; }
</style>
<div class="max-w-[1600px] mx-auto" x-data="paymentReceivingReport({ vouchersByDate: {{ $vouchersByDateJson }} })">
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Reports</span>
        <span class="text-sm text-gray-400 mx-1">›</span>
        <span class="text-sm text-gray-700 font-semibold">Payment Receiving Report</span>
    </div>

    <form method="GET" action="{{ route('report.payment-receiving') }}">
    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">From:</label>
                    <input type="date" name="date_from" value="{{ request('date_from', now()->subDays(30)->format('Y-m-d')) }}" onchange="if(this.value) this.form.submit()" class="date-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">To:</label>
                    <input type="date" name="date_to" value="{{ request('date_to', now()->format('Y-m-d')) }}" onchange="if(this.value) this.form.submit()" class="date-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF
                </button>
                <button type="button" class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Excel
                </button>
            </div>
        </div>
    </div>
    </form>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] table-fixed">
                <thead>
                    <tr class="table-header">
                        <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Date</th>
                        <th class="w-40 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Cash Received</th>
                        <th class="w-40 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Bank Received</th>
                        <th class="w-44 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">BD Office Collection</th>
                        <th class="w-44 px-4 py-3 text-sm font-bold text-gray-700 text-right">KSA Office Collection</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dailyPayments as $day)
                    <tr class="even:bg-[#fafafa] hover:bg-[#e8f4fc] cursor-pointer" @click="openModal('{{ $day['date'] }}')">
                        <td class="px-4 py-3 text-sm text-center border-r border-gray-200 font-medium text-gray-700">{{ date('d-M-Y', strtotime($day['date'])) }}</td>
                        <td class="px-4 py-3 text-sm text-right border-r border-gray-200 {{ $day['cash'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['cash'], 2)</td>
                        <td class="px-4 py-3 text-sm text-right border-r border-gray-200 {{ $day['bank'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['bank'], 2)</td>
                        <td class="px-4 py-3 text-sm text-right border-r border-gray-200 {{ $day['bd_office'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['bd_office'], 2)</td>
                        <td class="px-4 py-3 text-sm text-right {{ $day['ksa_office'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['ksa_office'], 2)</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No records found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="rounded-lg overflow-hidden border border-green-500 bg-gradient-to-b from-green-50 to-green-100">
            <div class="bg-gradient-to-b from-green-500 to-green-600 px-4 py-2">
                <h3 class="text-white text-sm font-bold">Total Cash Received</h3>
            </div>
            <div class="px-4 py-4">
                <p class="text-2xl font-bold text-green-700">@currency($totalCashPayment, 2)</p>
            </div>
        </div>
        <div class="rounded-lg overflow-hidden border border-green-500 bg-gradient-to-b from-green-50 to-green-100">
            <div class="bg-gradient-to-b from-green-500 to-green-600 px-4 py-2">
                <h3 class="text-white text-sm font-bold">Total Bank Received</h3>
            </div>
            <div class="px-4 py-4">
                <p class="text-2xl font-bold text-green-700">@currency($totalBankPayment, 2)</p>
            </div>
        </div>
        <div class="rounded-lg overflow-hidden border border-green-500 bg-gradient-to-b from-green-50 to-green-100">
            <div class="bg-gradient-to-b from-green-500 to-green-600 px-4 py-2">
                <h3 class="text-white text-sm font-bold">Total BD Office Collection</h3>
            </div>
            <div class="px-4 py-4">
                <p class="text-2xl font-bold text-green-700">@currency($totalBdOfficeCollection, 2)</p>
            </div>
        </div>
        <div class="rounded-lg overflow-hidden border border-green-500 bg-gradient-to-b from-green-50 to-green-100">
            <div class="bg-gradient-to-b from-green-500 to-green-600 px-4 py-2">
                <h3 class="text-white text-sm font-bold">Total KSA Office Collection</h3>
            </div>
            <div class="px-4 py-4">
                <p class="text-2xl font-bold text-green-700">@currency($totalKsaOfficeCollection, 2)</p>
            </div>
        </div>
    </div>

    <div class="text-center text-sm text-gray-500">
        Last Updated: <span id="lastUpdated"></span>
    </div>

    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-transparent" @click="closeModal()"></div>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="relative bg-white rounded-lg shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden">
                <div class="bg-slate-700 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-white">
                        Payment Details — <span x-text="selectedDateLabel"></span>
                    </h2>
                    <button @click="closeModal()" class="text-white hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[calc(90vh-130px)]">
                    <template x-if="selectedVouchers.length === 0">
                        <p class="text-center text-gray-500 py-8">No records found for this date.</p>
                    </template>
                    <template x-if="selectedVouchers.length > 0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[900px]">
                                <thead>
                                    <tr class="table-header">
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Invoice ID</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Voucher No</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Method</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Transaction Type</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Trx ID</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Receive By</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Receive At</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(v, idx) in selectedVouchers" :key="idx">
                                        <tr class="border-b border-gray-100 even:bg-[#fafafa]">
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.invoice_id"></td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.voucher_no"></td>
                                            <td class="px-3 py-2 text-sm text-center border-r border-gray-200">
                                                <span class="px-2 py-0.5 rounded text-xs font-medium"
                                                      :class="v.method === 'Bank' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'"
                                                      x-text="v.method"></span>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.transaction_type"></td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.trx_id"></td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.receive_by"></td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.receive_at"></td>
                                            <td class="px-3 py-2 text-sm text-right font-semibold" x-text="formatAmount(v.amount)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
                <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex justify-between items-center">
                    <div class="flex gap-6 text-sm">
                        <span class="font-medium text-green-700">
                            Cash: <span x-text="formatAmount(totalCash)"></span>
                        </span>
                        <span class="font-medium text-blue-700">
                            Bank: <span x-text="formatAmount(totalBank)"></span>
                        </span>
                        <span class="font-medium text-gray-800">
                            Total: <span x-text="formatAmount(totalAmount)"></span>
                        </span>
                    </div>
                    <button @click="closeModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function paymentReceivingReport(options = {}) {
        return {
            vouchersByDate: options.vouchersByDate || {},
            modalOpen: false,
            selectedDate: '',
            selectedVouchers: [],

            get selectedDateLabel() {
                if (!this.selectedDate) return '';
                const d = new Date(this.selectedDate + 'T00:00:00');
                return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            },

            openModal(date) {
                this.selectedDate = date;
                this.selectedVouchers = this.vouchersByDate[date] || [];
                this.modalOpen = true;
            },

            closeModal() {
                this.modalOpen = false;
            },

            get totalCash() {
                return this.selectedVouchers.filter(v => v.method === 'Cash').reduce((s, v) => s + v.amount, 0);
            },
            get totalBank() {
                return this.selectedVouchers.filter(v => v.method === 'Bank').reduce((s, v) => s + v.amount, 0);
            },
            get totalAmount() {
                return this.selectedVouchers.reduce((s, v) => s + v.amount, 0);
            },

            formatAmount(amount) {
                if (window.Alpine) {
                    return Alpine.store('currency').format(amount, 2);
                }
                return Number(amount).toFixed(2);
            },
        };
    }

    document.addEventListener('DOMContentLoaded', function() {
        var now = new Date();
        var options = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        var el = document.getElementById('lastUpdated');
        if (el) {
            el.textContent = now.toLocaleDateString('en-GB', options);
        }
    });
</script>
@endpush
