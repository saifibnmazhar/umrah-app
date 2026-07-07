@extends('layouts.app')
@section('title', 'Visa Agent Report')
@section('content')
<style>
select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 8px center;
    background-repeat: no-repeat;
    background-size: 16px 16px;
    padding-right: 32px;
}
</style>

<div class="max-w-[1600px] mx-auto p-4" x-data="visaAgentReport()">
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Report</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Visa Agent Report</span>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">SEARCH BOX</label>
                <input type="text" x-model="search" @keydown.enter="filterAgents()" placeholder="Search by Agent Name"
                       class="search-input w-80 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">Date</label>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="date_from" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="date_to" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2">
                <button @click="filterAgents()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filter
                </button>
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <button class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                    </svg>
                    PDF
                </button>
                <button class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4 text-green-700" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                    </svg>
                    Excel
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1400px] table-fixed">
                <thead>
                    <tr class="table-header">
                        <th class="w-56 px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Agent Name</th>
                        <th class="w-20 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Submitted</th>
                        <th class="w-20 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Issued</th>
                        <th class="w-20 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Pending</th>
                        <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Price<br>(Max/Min/Avg)</th>
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Receivable</th>
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Balance</th>
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Cancellation<br>Fee</th>
                        <th class="w-20 px-4 py-3 text-xs font-bold text-gray-700 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filteredData.length === 0">
                        <tr>
                            <td colspan="11" class="px-4 py-4 text-center text-gray-500">No data found</td>
                        </tr>
                    </template>
                    <template x-for="agent in filteredData" :key="agent.id">
                        <tr class="table-row-agent">
                            <td class="px-2 py-3 text-sm text-left border-r border-gray-200 font-medium text-gray-800" x-text="agent.name"></td>
                            <td class="px-2 py-3 text-sm text-center border-r border-gray-200 font-medium" x-text="agent.totalSubmitted"></td>
                            <td class="px-2 py-3 text-sm text-center border-r border-gray-200 font-medium" x-text="agent.totalIssued"></td>
                            <td class="px-2 py-3 text-sm text-center border-r border-gray-200 font-medium" x-text="agent.totalPending"></td>
                            <td class="px-2 py-3 text-xs text-center border-r border-gray-200 whitespace-nowrap">
                                <span class="text-red-600" x-text="agent.price.max.toLocaleString()"></span>
                                <span class="text-gray-400"> / </span>
                                <span class="text-green-600" x-text="agent.price.min.toLocaleString()"></span>
                                <span class="text-gray-400"> / </span>
                                <span class="text-blue-600" x-text="agent.price.avg.toLocaleString()"></span>
                            </td>
                            <td class="px-2 py-3 text-sm text-right border-r border-gray-200 font-medium" x-text="formatCurrency(agent.receivable)"></td>
                            <td class="px-2 py-3 text-sm text-right border-r border-gray-200 font-medium" x-text="formatCurrency(agent.payable)"></td>
                            <td class="px-2 py-3 text-sm text-right border-r border-gray-200"
                                :class="agent.paid > 0 ? 'text-green-700' : 'text-gray-600'"
                                x-text="formatCurrency(agent.paid)"></td>
                            <td class="px-2 py-3 text-sm text-right border-r border-gray-200 font-semibold"
                                :class="getBalanceColor(agent)"
                                x-text="formatCurrency(Math.abs(agent.payable - agent.receivable))"></td>
                            <td class="px-2 py-3 text-sm text-right border-r border-gray-200"
                                :class="agent.cancellationFee > 0 ? 'text-red-600' : 'text-gray-600'"
                                x-text="agent.cancellationFee > 0 ? formatCurrency(agent.cancellationFee) : '-'"></td>
                            <td class="px-2 py-3 text-center whitespace-nowrap">
                                <button @click="openModal(agent.id)" class="view-btn text-white px-3 py-1 rounded text-xs font-medium transition-all">
                                    View
                                </button>
                                <button @click="openPaymentModal(agent.id)" class="ml-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-medium transition-all">
                                    Pay
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mt-0">
        <div class="flex flex-wrap gap-6">
            <div class="footer-box rounded-lg overflow-hidden min-w-[320px]">
                <div class="footer-box-header px-4 py-2">
                    <span class="text-sm font-bold text-gray-700">Visa Agent Report Summary</span>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Agents:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="summary.totalAgents"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Agents with Due:</span>
                            <span class="text-xs font-bold text-red-700" x-text="summary.agentsWithDue"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Payable:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="formatCurrency(summary.totalPayable)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Paid:</span>
                            <span class="text-xs font-bold text-green-700" x-text="formatCurrency(summary.totalPaid)"></span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 mt-1">
                            <span class="text-xs font-bold text-gray-700">Total Balance:</span>
                            <span class="text-xs font-bold text-red-700" x-text="summary.totalBalanceLabel"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="footer-box rounded-lg overflow-hidden">
                    <div class="footer-box-header px-4 py-2">
                        <span class="text-sm font-bold text-gray-700">Export Options</span>
                    </div>
                    <div class="p-4 flex gap-3">
                        <button class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                            </svg>
                            PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center">
            <span class="text-xs text-gray-400">Last Updated: <span x-text="lastUpdated"></span></span>
            <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
        </div>
    </div>

    <div id="detailModal" x-show="detailModalOpen" x-cloak class="fixed inset-0 z-50">
        <div class="modal-overlay fixed inset-0 bg-black bg-opacity-50" @click="closeModal()"></div>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="modal-content relative bg-white rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                <div class="bg-slate-700 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-white">Agent Details</h2>
                    <button @click="closeModal()" class="text-white hover:text-gray-300 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] scrollbar-thin">
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4" x-text="modalAgent.name"></h3>

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
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Pending</p>
                                <p class="text-xl font-bold text-orange-700 mt-1" x-text="modalAgent.totalPending || 0"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Receivable</p>
                                <p class="text-xl font-bold text-purple-700 mt-1" x-text="formatCurrency(modalAgent.receivable || 0)"></p>
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
                                <p class="text-xl font-bold mt-1" x-text="formatCurrency(modalBalance)"
                                   :class="modalBalanceColor"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Cancellation Fee</p>
                                <p class="text-xl font-bold text-red-700 mt-1" x-text="modalAgent.cancellationFee > 0 ? formatCurrency(modalAgent.cancellationFee) : '-'"></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-2 border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead>
                                <tr class="table-header">
                                    <th class="px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Date</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Submitted</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Issued</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Pending</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Total<br>Receivable</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Balance</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right">Cancellation<br>Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(tx, idx) in modalAgent.transactions" :key="idx">
                                    <tr class="modal-row border-b border-gray-100">
                                        <td class="px-4 py-3 text-sm text-left" x-text="tx.date"></td>
                                        <td class="px-4 py-3 text-sm text-center font-medium" x-text="modalAgent.totalSubmitted"></td>
                                        <td class="px-4 py-3 text-sm text-center font-medium" x-text="modalAgent.totalIssued"></td>
                                        <td class="px-4 py-3 text-sm text-center font-medium" x-text="modalAgent.totalPending"></td>
                                        <td class="px-4 py-3 text-sm text-right font-medium" x-text="formatCurrency(modalAgent.receivable)"></td>
                                        <td class="px-4 py-3 text-sm text-right font-medium" x-text="formatCurrency(tx.payable)"></td>
                                        <td class="px-4 py-3 text-sm text-right" :class="tx.paid > 0 ? 'text-green-700' : 'text-gray-600'" x-text="formatCurrency(tx.paid)"></td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold" :class="getBalanceColor(modalAgent)" x-text="formatCurrency(Math.abs(modalBalance))"></td>
                                        <td class="px-4 py-3 text-sm text-right" :class="modalAgent.cancellationFee > 0 ? 'text-red-600' : 'text-gray-600'" x-text="modalAgent.cancellationFee > 0 ? formatCurrency(modalAgent.cancellationFee) : '-'"></td>
                                    </tr>
                                </template>
                                <template x-if="!modalAgent.transactions || modalAgent.transactions.length === 0">
                                    <tr>
                                        <td colspan="9" class="px-4 py-4 text-center text-gray-500">No transactions found</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-between">
                    <button @click="openPaymentModal(modalAgent.id)" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md text-sm font-medium flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Pay
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

    <div id="paymentModal" x-show="paymentModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="modal-overlay absolute inset-0 bg-black/50" @click="closePaymentModal()"></div>
        <div class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Payment Form</h3>
            <form @submit.prevent="handlePaymentSubmit">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Name</label>
                        <input type="text" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600" x-model="paymentAgentName">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Pay To *</label>
                        <select x-model="paymentPayTo" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="Visa Agent">Visa Agent</option>
                            <option value="Commission Agent">Commission Agent</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method *</label>
                        <select x-model="paymentMethod" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="Bank">Bank</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount (SAR) *</label>
                        <input type="number" x-model="paymentAmount" required min="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">Save</button>
                    <button type="button" @click="closePaymentModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function visaAgentReport() {
    return {
        search: '',
        date_from: '',
        date_to: '',
        agentData: [],
        filteredData: [],
        summary: {
            totalAgents: 0,
            agentsWithDue: 0,
            totalPayable: 0,
            totalPaid: 0,
            totalBalanceLabel: '0 SAR',
        },
        lastUpdated: '',
        detailModalOpen: false,
        paymentModalOpen: false,
        modalAgent: {},
        paymentAgentName: '',
        paymentPayTo: '',
        paymentMethod: '',
        paymentAmount: '',
        editingAgentId: null,

        get modalBalance() {
            return Math.abs((this.modalAgent.payable || 0) - (this.modalAgent.receivable || 0));
        },

        get modalBalanceColor() {
            const payable = this.modalAgent.payable || 0;
            const receivable = this.modalAgent.receivable || 0;
            if (payable > receivable) return 'text-red-600';
            if (receivable > payable) return 'text-green-700';
            return 'text-gray-600';
        },

        init() {
            this.agentData = [
                {
                    id: 1,
                    name: "Al Rajhi Travel",
                    totalSubmitted: 50,
                    totalIssued: 45,
                    totalPending: 5,
                    price: { max: 2500, min: 500, avg: 1200 },
                    receivable: 40000,
                    paid: 45000,
                    cancellationFee: 500,
                    payable: 45000,
                    transactions: [
                        { date: "01-Mar-2026", payable: 8500, paid: 8500 },
                        { date: "05-Mar-2026", payable: 12000, paid: 12000 },
                        { date: "12-Mar-2026", payable: 9500, paid: 9500 },
                        { date: "18-Mar-2026", payable: 8500, paid: 8500 },
                        { date: "25-Mar-2026", payable: 6500, paid: 6500 }
                    ]
                },
                {
                    id: 2,
                    name: "FlyBurj Agent",
                    totalSubmitted: 40,
                    totalIssued: 32,
                    totalPending: 8,
                    price: { max: 3000, min: 600, avg: 1500 },
                    receivable: 35000,
                    paid: 28000,
                    cancellationFee: 0,
                    payable: 30000,
                    transactions: [
                        { date: "02-Mar-2026", payable: 7500, paid: 7500 },
                        { date: "08-Mar-2026", payable: 9000, paid: 9000 },
                        { date: "15-Mar-2026", payable: 8000, paid: 6500 },
                        { date: "22-Mar-2026", payable: 8000, paid: 5000 }
                    ]
                },
                {
                    id: 3,
                    name: "Shahadat Visa Services",
                    totalSubmitted: 35,
                    totalIssued: 35,
                    totalPending: 0,
                    price: { max: 2000, min: 400, avg: 1000 },
                    receivable: 28000,
                    paid: 28000,
                    cancellationFee: 0,
                    payable: 28000,
                    transactions: [
                        { date: "03-Mar-2026", payable: 7000, paid: 7000 },
                        { date: "10-Mar-2026", payable: 8500, paid: 8500 },
                        { date: "17-Mar-2026", payable: 7500, paid: 7500 },
                        { date: "24-Mar-2026", payable: 5000, paid: 5000 }
                    ]
                },
                {
                    id: 4,
                    name: "Gulf Visa",
                    totalSubmitted: 60,
                    totalIssued: 48,
                    totalPending: 12,
                    price: { max: 3500, min: 700, avg: 1800 },
                    receivable: 60000,
                    paid: 55000,
                    cancellationFee: 2500,
                    payable: 67500,
                    transactions: [
                        { date: "01-Mar-2026", payable: 15000, paid: 15000 },
                        { date: "06-Mar-2026", payable: 12000, paid: 12000 },
                        { date: "12-Mar-2026", payable: 14500, paid: 10000 },
                        { date: "20-Mar-2026", payable: 13000, paid: 8000 },
                        { date: "28-Mar-2026", payable: 13000, paid: 10000 }
                    ]
                },
                {
                    id: 5,
                    name: "Dreamland Tours",
                    totalSubmitted: 25,
                    totalIssued: 25,
                    totalPending: 0,
                    price: { max: 1800, min: 350, avg: 900 },
                    receivable: 20000,
                    paid: 18000,
                    cancellationFee: 0,
                    payable: 18000,
                    transactions: [
                        { date: "04-Mar-2026", payable: 6000, paid: 6000 },
                        { date: "11-Mar-2026", payable: 5500, paid: 5500 },
                        { date: "21-Mar-2026", payable: 6500, paid: 6500 }
                    ]
                },
                {
                    id: 6,
                    name: "Saudi Umrah Express",
                    totalSubmitted: 45,
                    totalIssued: 38,
                    totalPending: 7,
                    price: { max: 2800, min: 550, avg: 1350 },
                    receivable: 38000,
                    paid: 35000,
                    cancellationFee: 1200,
                    payable: 41200,
                    transactions: [
                        { date: "02-Mar-2026", payable: 9800, paid: 9800 },
                        { date: "09-Mar-2026", payable: 11200, paid: 9000 },
                        { date: "16-Mar-2026", payable: 10200, paid: 8200 },
                        { date: "25-Mar-2026", payable: 10000, paid: 8000 }
                    ]
                }
            ];
            this.applyFilter();
            const now = new Date();
            this.lastUpdated = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        },

        formatCurrency(amount) {
            return Number(amount).toLocaleString('en-US') + ' SAR';
        },

        getBalanceColor(agent) {
            const balance = (agent.payable || 0) - (agent.receivable || 0);
            if (balance > 0) return 'text-red-600';
            if (balance < 0) return 'text-green-700';
            return 'text-gray-600';
        },

        filterAgents() {
            this.applyFilter();
        },

        applyFilter() {
            const searchTerm = this.search.toLowerCase().trim();
            const from = this.date_from ? new Date(this.date_from) : null;
            const to = this.date_to ? new Date(this.date_to) : null;

            function parseDate(dateStr) {
                const parts = dateStr.match(/(\d{2})-(\w{3})-(\d{4})/);
                if (!parts) return null;
                const months = { 'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5, 'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11 };
                return new Date(parts[3], months[parts[2]], parseInt(parts[1]));
            }

            this.filteredData = this.agentData.filter(agent => {
                const matchesSearch = agent.name.toLowerCase().includes(searchTerm);
                let matchesDate = true;
                if (from || to) {
                    matchesDate = agent.transactions.some(tx => {
                        const txDate = parseDate(tx.date);
                        if (!txDate) return false;
                        if (from && txDate < from) return false;
                        if (to && txDate > to) return false;
                        return true;
                    });
                }
                return matchesSearch && matchesDate;
            });
            this.updateSummary();
        },

        updateSummary() {
            const data = this.filteredData;
            const totalAgents = data.length;
            const agentsWithDue = data.filter(a => (a.payable || 0) > (a.receivable || 0)).length;
            const totalPayable = data.reduce((sum, a) => sum + (a.payable || 0), 0);
            const totalPaid = data.reduce((sum, a) => sum + (a.paid || 0), 0);
            const totalBalance = totalPayable - data.reduce((sum, a) => sum + (a.receivable || 0), 0);
            this.summary = {
                totalAgents: totalAgents,
                agentsWithDue: agentsWithDue,
                totalPayable: totalPayable,
                totalPaid: totalPaid,
                totalBalanceLabel: this.formatCurrency(Math.abs(totalBalance)),
            };
        },

        openModal(agentId) {
            const agent = this.agentData.find(a => a.id === agentId);
            if (!agent) return;
            this.modalAgent = agent;
            this.detailModalOpen = true;
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            this.detailModalOpen = false;
            document.body.style.overflow = 'auto';
        },

        openPaymentModal(agentId) {
            const agent = this.agentData.find(a => a.id === agentId);
            if (!agent) return;
            this.editingAgentId = agent.id;
            this.paymentAgentName = agent.name;
            this.paymentPayTo = '';
            this.paymentMethod = '';
            this.paymentAmount = '';
            this.paymentModalOpen = true;
        },

        closePaymentModal() {
            this.editingAgentId = null;
            this.paymentModalOpen = false;
        },

        handlePaymentSubmit() {
            const agent = this.agentData.find(a => a.id === this.editingAgentId);
            if (!agent) return;
            const amount = parseFloat(this.paymentAmount) || 0;
            agent.paid += amount;
            this.applyFilter();
            this.closePaymentModal();
            if (window.showToast) {
                window.showToast('Payment saved successfully', 'success');
            }
        },
    };
}
</script>
@endpush
