@extends('layouts.app')
@section('title', 'Ticket Agent Report')
@section('content')

@livewire('report.ticket-agent-report-table')

<div x-data="ticketAgentModal()"
     x-on:open-agent-details.window="openModal($event.detail.agent)"
     x-cloak>
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.5);" @click="closeModal()"></div>
        <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
            <div class="modal-content relative bg-white rounded-lg shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col" @click.stop>
                <div class="bg-slate-700 px-6 py-4 flex justify-between items-center shrink-0">
                    <h2 class="text-xl font-bold text-white">Agent Details</h2>
                    <button @click="closeModal()" class="text-white hover:text-gray-300 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1 min-h-0 scrollbar-thin">
                    <template x-if="selectedAgent">
                        <div>
                            <div class="mb-6">
                                <h3 class="text-2xl font-bold text-gray-800 mb-4" x-text="selectedAgent.name"></h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Payable</p>
                                        <p class="text-xl font-bold text-gray-800 mt-1" x-text="formatCurrency(selectedAgent.payable)"></p>
                                    </div>
                                    <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Paid</p>
                                        <p class="text-xl font-bold text-green-700 mt-1" x-text="formatCurrency(selectedAgent.paid)"></p>
                                    </div>
                                    <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Balance</p>
                                        <p class="text-xl font-bold mt-1"
                                           :class="selectedAgent.due > 0 ? 'text-green-700' : (selectedAgent.due < 0 ? 'text-red-600' : 'text-gray-600')"
                                           x-text="(selectedAgent.due > 0 ? '+' : '') + formatCurrency(selectedAgent.due)"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="flex border-b border-gray-200">
                                    <button @@click="switchTab('payment')"
                                            :class="activeTab === 'payment' ? 'text-white bg-slate-700 border-slate-700' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-50 border-transparent'"
                                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">Payment</button>
                                </div>
                            </div>

                            <div x-show="activeTab === 'payment'" x-cloak>
                                <div class="border-2 border-gray-200 rounded-lg overflow-hidden">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="table-header">
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Date</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-if="(selectedAgent.transactions || []).length === 0">
                                                <tr>
                                                    <td colspan="4" class="px-4 py-8 text-sm text-center text-gray-500">No transactions found</td>
                                                </tr>
                                            </template>
                                            <template x-for="tx in (selectedAgent.transactions || [])" :key="tx.date">
                                                <tr class="modal-row border-b border-gray-100">
                                                    <td class="px-4 py-3 text-sm text-left" x-text="tx.date"></td>
                                                    <td class="px-4 py-3 text-sm text-right font-medium" x-text="formatCurrency(tx.payable)"></td>
                                                    <td class="px-4 py-3 text-sm text-right"
                                                       :class="tx.paid > 0 ? 'text-green-700' : 'text-gray-600'"
                                                       x-text="formatCurrency(tx.paid)"></td>
                                                    <td class="px-4 py-3 text-sm text-right font-semibold"
                                                       :class="(tx.paid - tx.payable) > 0 ? 'text-green-700' : ((tx.paid - tx.payable) < 0 ? 'text-red-600' : 'text-gray-600')"
                                                       x-text="(tx.paid - tx.payable) > 0 ? '+' : '' + formatCurrency(tx.paid - tx.payable)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end shrink-0">
                    <button @click="closeModal()" class="filter-btn px-6 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
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
function ticketAgentModal() {
    return {
        showModal: false,
        activeTab: 'payment',
        selectedAgent: null,

        openModal(agent) {
            this.selectedAgent = agent;
            this.activeTab = 'payment';
            this.showModal = true;
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            this.showModal = false;
            this.selectedAgent = null;
            document.body.style.overflow = 'auto';
        },

        switchTab(tab) {
            this.activeTab = tab;
        },

        formatCurrency(amount, decimals = 2) {
            if (!amount || amount === 0) return '0.00';
            return new Intl.NumberFormat('en-US', {minimumFractionDigits: decimals, maximumFractionDigits: decimals}).format(amount);
        },
    };
}
</script>
@endpush
