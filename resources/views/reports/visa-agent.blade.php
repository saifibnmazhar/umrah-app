@extends('layouts.app')
@section('title', 'Visa Agent Report')
@section('content')

@livewire('report.visa-agent-report-table')

<div x-data="visaAgentDetailsModal()"
     x-on:open-agent-details.window="openAgentDetailModal($event.detail.agentId)"
     x-cloak>
    <div id="detailModal" x-show="detailModalOpen" x-cloak class="fixed inset-0 z-50">
        <div class="modal-overlay fixed inset-0 bg-transparent" @click="closeModal()"></div>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="modal-content relative bg-white rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="bg-slate-700 px-6 py-4 flex justify-between items-center shrink-0">
                    <h2 class="text-xl font-bold text-white">Agent Details</h2>
                    <button @click="closeModal()" class="text-white hover:text-gray-300 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1 min-h-0 scrollbar-thin">
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-2xl font-bold text-gray-800" x-text="modalAgent.name || 'Details'"></h3>
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-semibold text-gray-500">Date From</label>
                                <input type="date" x-model="modalDateFrom" @@change="loadModalCombined()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                <label class="text-xs font-semibold text-gray-500">To</label>
                                <input type="date" x-model="modalDateTo" @@change="loadModalCombined()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Submitted</p>
                                <p class="text-xl font-bold text-blue-700 mt-1" x-text="modalAgent.totalSubmitted || 0"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Issued</p>
                                <p class="text-xl font-bold text-green-700 mt-1" x-text="modalAgent.totalIssued || 0"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Payable</p>
                                <p class="text-xl font-bold text-gray-800 mt-1" x-text="formatCurrency(modalAgent.payable || 0)"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Paid</p>
                                <p class="text-xl font-bold text-green-700 mt-1" x-text="formatCurrency(modalAgent.paid || 0)"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Balance</p>
                                <p class="text-xl font-bold mt-1"
                                   :class="(modalAgent.balance || 0) > 0 ? 'text-green-700' : ((modalAgent.balance || 0) < 0 ? 'text-red-700' : 'text-gray-600')"
                                   x-text="(modalAgent.balance > 0 ? '+' : (modalAgent.balance < 0 ? '-' : '')) + formatCurrency(Math.abs(modalAgent.balance || 0))"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Cancellation Fee</p>
                                <p class="text-xl font-bold text-red-700 mt-1" x-text="modalAgent.cancellationFee > 0 ? formatCurrency(modalAgent.cancellationFee) : '-'"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Estimated Cost</p>
                                <p class="text-xl font-bold text-gray-800 mt-1" x-text="formatCurrency(modalAgent.estimatedCost || 0)"></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-b border-gray-200 mt-2">
                        <h3 class="px-4 py-2.5 font-bold text-sm text-gray-700 uppercase tracking-wide">Combined Report</h3>
                    </div>

                    <div class="mt-4">
                        <div class="border-2 border-gray-200 rounded-lg overflow-hidden">
                            <table class="w-full">
                                <thead>
                                    <tr class="table-header">
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Date</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Invoice ID</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Passenger Name</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Passport No</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Status</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Estimated Cost</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Balance</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right">Cancellation Fee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-if="modalAgent.loading">
                                        <tr>
                                            <td colspan="10" class="px-4 py-4 text-center text-gray-500">Loading...</td>
                                        </tr>
                                    </template>
                                    <template x-if="!modalAgent.loading && (!modalAgent.combined || modalAgent.combined.length === 0)">
                                        <tr>
                                            <td colspan="10" class="px-4 py-4 text-center text-gray-500">No data found</td>
                                        </tr>
                                    </template>
                                    <template x-for="row in (modalAgent.combined || []).filter(r => true)" :key="row.date + '-' + row.passenger_name">
                                        <tr class="border-b border-gray-100">
                                            <td class="px-3 py-3 text-sm text-left" x-text="row.date"></td>
                                            <td class="px-3 py-3 text-sm text-left font-medium" x-text="row.invoice_id || '-'"></td>
                                            <td class="px-3 py-3 text-sm text-left" x-text="row.passenger_name || '-'"></td>
                                            <td class="px-3 py-3 text-sm text-left" x-text="row.passport_no || '-'"></td>
                                            <td class="px-3 py-3 text-sm text-center">
                                                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold"
                                                    :class="{
                                                        'bg-blue-100 text-blue-800': row.status === 'Submitted',
                                                        'bg-green-100 text-green-800': row.status === 'Issued',
                                                        'bg-red-100 text-red-800': row.status === 'Cancelled',
                                                        'bg-gray-100 text-gray-600': row.status === 'Payment'
                                                    }"
                                                    x-text="row.status"></span>
                                            </td>
                                            <td class="px-3 py-3 text-sm text-right font-medium" x-text="row.estimated_cost > 0 ? formatCurrency(row.estimated_cost) : '-'"></td>
                                            <td class="px-3 py-3 text-sm text-right font-medium" x-text="row.payable > 0 ? formatCurrency(row.payable) : '-'"></td>
                                            <td class="px-3 py-3 text-sm text-right" :class="row.paid > 0 ? 'text-green-700' : 'text-gray-600'" x-text="row.paid > 0 ? formatCurrency(row.paid) : '-'"></td>
                                            <td class="px-3 py-3 text-sm text-right font-semibold"
                                                :class="row.balance > 0 ? 'text-green-700' : (row.balance < 0 ? 'text-red-600' : 'text-gray-600')"
                                                x-text="(row.balance > 0 ? '+' : (row.balance < 0 ? '-' : '')) + formatCurrency(Math.abs(row.balance))"></td>
                                            <td class="px-3 py-3 text-sm text-right" :class="row.cancellation_fee > 0 ? 'text-red-600' : 'text-gray-600'" x-text="row.cancellation_fee > 0 ? formatCurrency(row.cancellation_fee) : '-'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-between no-print shrink-0">
                    <button @click="window.open(`/reports/visa-agent/${encodeURIComponent(modalAgent.id)}/print`, '_blank')" class="filter-btn px-6 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 002 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </button>
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
function visaAgentDetailsModal() {
    return {
        detailModalOpen: false,
        modalAgent: {},
        modalDateFrom: '',
        modalDateTo: '',

        openAgentDetailModal(agentId) {
            this.modalDateFrom = '';
            this.modalDateTo = '';
            this.modalAgent = {
                id: agentId,
                name: '',
                combined: [],
                loading: true,
                totalSubmitted: 0,
                totalIssued: 0,
                payable: 0,
                paid: 0,
                balance: 0,
                cancellationFee: 0,
                estimatedCost: 0,
            };
            this.detailModalOpen = true;
            document.body.style.overflow = 'hidden';
            this.loadModalCombined(agentId);
        },

        closeModal() {
            this.detailModalOpen = false;
            document.body.style.overflow = '';
        },

        formatCurrency(amount, decimals = 2) {
            if (!amount || amount === 0) return '0.00';
            return new Intl.NumberFormat('en-US', {minimumFractionDigits: decimals, maximumFractionDigits: decimals}).format(amount);
        },

        async loadModalCombined(agentId) {
            const params = new URLSearchParams();
            if (this.modalDateFrom) params.set('date_from', this.modalDateFrom);
            if (this.modalDateTo) params.set('date_to', this.modalDateTo);
            const qs = params.toString() ? `?${params}` : '';

            try {
                const res = await fetch(`/api/reports/visa-agent/${agentId}/combined${qs}`);
                const result = await res.json();
                if (result.agent) {
                    const stats = result.stats || {};
                    this.modalAgent = {
                        ...this.modalAgent,
                        ...result.agent,
                        totalSubmitted: stats.totalSubmitted ?? 0,
                        totalIssued: stats.totalIssued ?? 0,
                        payable: stats.payable ?? 0,
                        paid: stats.paid ?? 0,
                        balance: stats.balance ?? 0,
                        cancellationFee: stats.cancellationFee ?? 0,
                        estimatedCost: stats.estimatedCost ?? 0,
                        loading: false,
                        combined: result.data || [],
                    };
                } else {
                    this.modalAgent.loading = false;
                }
            } catch (error) {
                console.error('Failed to load agent details:', error);
                this.modalAgent.loading = false;
            }
        },
    };
}
</script>
@endpush
