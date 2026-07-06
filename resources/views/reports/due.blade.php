@extends('layouts.app')
@section('title', 'Due Report')
@section('content')
<style>
.search-input {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
    border: 1px solid #d4d4d4;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);
}

.filter-btn {
    background: linear-gradient(to bottom, #fff 0%, #e9ecef 100%);
    border: 1px solid #d4d4d4;
    box-shadow: 0 1px 0 rgba(255,255,255,0.5);
}

.filter-btn:hover {
    background: linear-gradient(to bottom, #f0f0f0 0%, #e2e6ea 100%);
}

.date-input {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
    border: 1px solid #d4d4d4;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);
}

.table-header {
    background: linear-gradient(to bottom, #f3f3f3 0%, #e8e8e8 100%);
    border: 1px solid #d4d4d4;
}

.table-row-due {
    background-color: #ffffff;
    border: 1px solid #d4d4d4;
}

.table-row-due:nth-child(even) {
    background-color: #fafafa;
}

.table-row-due:hover {
    background-color: #e8f4fc !important;
}

.footer-box {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
    border: 2px solid #d4d4d4;
}

.footer-box-header {
    background: linear-gradient(to bottom, #f3f3f3 0%, #e8e8e8 100%);
    border-bottom: 1px solid #d4d4d4;
}

.export-btn {
    background: linear-gradient(to bottom, #fff 0%, #e9ecef 100%);
    border: 1px solid #d4d4d4;
    box-shadow: 0 1px 0 rgba(255,255,255,0.5);
}

.export-btn:hover {
    background: linear-gradient(to bottom, #f0f0f0 0%, #dee2e6 100%);
}

input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0.5);
    cursor: pointer;
}

.scrollbar-thin::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 8px center;
    background-repeat: no-repeat;
    background-size: 16px 16px;
    padding-right: 32px;
}

.tab-btn {
    background: linear-gradient(to bottom, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #d4d4d4;
    transition: all 0.2s ease;
}

.tab-btn:hover {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
}

.tab-btn.active {
    background: linear-gradient(to bottom, #fff 0%, #fff 100%);
    border-bottom-color: white;
    color: #1e293b;
    font-weight: 600;
}

.badge-cash {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    background-color: #dbeafe;
    color: #1e40af;
    border: 1px solid #3b82f6;
}

.badge-bank {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    background-color: #f3e8ff;
    color: #7c3aed;
    border: 1px solid #9333ea;
}

.amount-positive {
    color: #166534;
    font-weight: 600;
}

.amount-due {
    color: #dc2626;
    font-weight: 600;
}

.amount-warning {
    color: #ea580c;
    font-weight: 600;
}

.btn-view {
    background: linear-gradient(to bottom, #3b82f6 0%, #2563eb 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid #1d4ed8;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-view:hover {
    background: linear-gradient(to bottom, #2563eb 0%, #1d4ed8 100%);
}

.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
}

.animate-fade {
    animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

[x-cloak] { display: none !important; }
</style>

<div x-data="dueReportApp()">
<div class="max-w-[1600px] mx-auto p-4">
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Reports</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Due Report</span>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Date From</label>
                <input type="date" x-model="date_from" class="date-input w-full px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Date To</label>
                <input type="date" x-model="date_to" class="date-input w-full px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Agent</label>
                <select x-model="agent" class="search-input w-full px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Agents</option>
                    <option value="agent1">Agent 1</option>
                    <option value="agent2">Agent 2</option>
                </select>
            </div>
            <div class="flex items-end">
                <button @click="loadData()" class="filter-btn w-full px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center justify-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Search
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF
                </button>
                <button class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Excel
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] table-fixed">
                <thead>
                    <tr class="table-header">
                        <th class="w-1/2 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Branch Name</th>
                        <th class="w-1/4 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Total Due</th>
                        <th class="w-1/4 px-4 py-3 text-sm font-bold text-gray-700 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && branches.length === 0">
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">No due data found</td>
                        </tr>
                    </template>
                    <template x-for="(branch, index) in branches" :key="branch.id">
                        <tr class="table-row-due">
                            <td class="px-4 py-3 text-sm border-r border-gray-200 font-medium text-gray-800" x-text="branch.name"></td>
                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-semibold amount-due" x-text="$currency(branch.totalDue, 2)"></td>
                            <td class="px-4 py-3 text-sm text-center">
                                <button @click="openBranchModal(branch.id)" class="btn-view inline-block">View</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div x-show="branchModalOpen" x-cloak class="fixed inset-0 z-50">
    <div class="modal-backdrop absolute inset-0" @click="closeBranchModal()"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 pt-16">
        <div class="modal-content bg-white rounded-lg shadow-2xl w-full max-w-6xl max-h-[85vh] overflow-hidden animate-fade">
            <div class="bg-slate-700 text-white px-6 py-4 flex justify-between items-center">
                <h2 class="text-lg font-bold" x-text="'Due Details - ' + (selectedBranch?.name || '')"></h2>
                <button @click="closeBranchModal()" class="text-white hover:text-gray-200 p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="border-b border-gray-300 bg-gray-50 px-4 pt-3">
                <div class="flex gap-2">
                    <button @click="activeTab = 'customerDue'" :class="activeTab === 'customerDue' ? 'tab-btn active' : 'tab-btn'" class="px-6 py-2.5 rounded-t-md text-sm font-medium text-gray-600">
                        Customer Due Report
                    </button>
                    <button @click="activeTab = 'dateWise'" :class="activeTab === 'dateWise' ? 'tab-btn active' : 'tab-btn'" class="px-6 py-2.5 rounded-t-md text-sm font-medium text-gray-600">
                        Date Wise Due
                    </button>
                </div>
            </div>

            <div class="p-4 max-h-[65vh] overflow-y-auto scrollbar-thin">
                <div x-show="activeTab === 'customerDue'">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1200px] table-fixed">
                            <thead>
                                <tr class="table-header">
                                    <th class="w-40 px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Customer Name</th>
                                    <th class="w-28 px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Mobile</th>
                                    <th class="w-32 px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Invoice ID</th>
                                    <th class="w-28 px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Ticket Date</th>
                                    <th class="w-36 px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Total Package Amount</th>
                                    <th class="w-32 px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid Amount</th>
                                    <th class="w-28 px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Due</th>
                                    <th class="w-24 px-3 py-3 text-xs font-bold text-gray-700 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="customer in (selectedBranch?.customers || [])" :key="customer.id">
                                    <tr class="table-row-due">
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-left" x-text="customer.name"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-center" x-text="customer.mobile"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-center font-medium" x-text="customer.invoiceId"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-center" x-text="customer.ticketDate"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium" x-text="$currency(customer.totalPackage)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium amount-positive" x-text="$currency(customer.paid)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-semibold amount-due" x-text="$currency(customer.due)"></td>
                                        <td class="px-3 py-2.5 text-xs text-center">
                                            <button @click="openDetailModal(customer.id)" class="btn-view">View</button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="(selectedBranch?.customers || []).length === 0">
                                    <tr>
                                        <td colspan="8" class="px-3 py-8 text-center text-xs text-gray-500">No customers found</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div x-show="activeTab === 'dateWise'">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1000px] table-fixed">
                            <thead>
                                <tr class="table-header">
                                    <th class="w-32 px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Date</th>
                                    <th class="w-28 px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Due</th>
                                    <th class="w-36 px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Collected Amount Cash</th>
                                    <th class="w-36 px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Collected Amount Bank</th>
                                    <th class="w-36 px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Total Collected Amount</th>
                                    <th class="w-28 px-3 py-3 text-xs font-bold text-gray-700 text-right">New Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, index) in (selectedBranch?.dateWiseData || [])" :key="index">
                                    <tr class="table-row-due">
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-center" x-text="row.date"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-semibold amount-due" x-text="$currency(row.due)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium amount-positive" x-text="$currency(row.cash)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium amount-positive" x-text="$currency(row.bank)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-semibold amount-positive" x-text="$currency(row.totalCollected)"></td>
                                        <td class="px-3 py-2.5 text-xs text-right font-semibold amount-warning" x-text="$currency(row.newDue)"></td>
                                    </tr>
                                </template>
                                <template x-if="(selectedBranch?.dateWiseData || []).length === 0">
                                    <tr>
                                        <td colspan="6" class="px-3 py-8 text-center text-xs text-gray-500">No date wise data found</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div x-show="detailModalOpen" x-cloak class="fixed inset-0 z-[60]">
    <div class="modal-backdrop absolute inset-0" @click="closeDetailModal()"></div>
    <div class="relative z-10 min-h-screen flex items-start justify-center p-4 pt-12">
        <div class="modal-content bg-white rounded-lg shadow-2xl w-full max-w-5xl max-h-[85vh] overflow-hidden animate-fade">
            <div class="bg-slate-700 text-white px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <button @click="backToCustomerList()" class="text-white hover:text-gray-200 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <h2 class="text-lg font-bold" x-text="'Transaction Details - ' + (selectedCustomer?.name || '')"></h2>
                </div>
                <button @click="closeDetailModal()" class="text-white hover:text-gray-200 p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="bg-gray-50 border-b border-gray-300 p-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">Date From:</label>
                        <input type="date" x-model="filterDateFrom" class="date-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">Date To:</label>
                        <input type="date" x-model="filterDateTo" class="date-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <button @click="applyTransactionFilter()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filter
                    </button>
                </div>
            </div>

            <div class="p-4 max-h-[55vh] overflow-y-auto scrollbar-thin">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] table-fixed">
                        <thead>
                            <tr class="table-header">
                                <th class="w-28 px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Date</th>
                                <th class="w-24 px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Due</th>
                                <th class="w-24 px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                                <th class="w-28 px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Payment Method</th>
                                <th class="w-32 px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Trx ID</th>
                                <th class="w-24 px-3 py-3 text-xs font-bold text-gray-700 text-right">New Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(trx, index) in filteredTransactions" :key="index">
                                <tr class="table-row-due">
                                    <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-center" x-text="trx.date"></td>
                                    <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-semibold amount-due" x-text="$currency(trx.due)"></td>
                                    <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium amount-positive" x-text="$currency(trx.paid)"></td>
                                    <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-center">
                                        <span :class="trx.method === 'Cash' ? 'badge-cash' : 'badge-bank'" x-text="trx.method"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-center font-mono" x-text="trx.trxId"></td>
                                    <td class="px-3 py-2.5 text-xs text-right font-semibold" :class="trx.newDue > 0 ? 'amount-warning' : 'amount-positive'" x-text="$currency(trx.newDue)"></td>
                                </tr>
                            </template>
                            <template x-if="filteredTransactions.length === 0">
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-xs text-gray-500">No transactions found</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-gray-50 border-t border-gray-300 px-6 py-3 flex justify-end">
                <button @click="closeDetailModal()" class="filter-btn px-6 py-2 rounded-md text-sm font-medium text-gray-700">
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
function dueReportApp() {
    return {
        date_from: '',
        date_to: '',
        agent: '',
        loading: false,
        branches: [],
        branchModalOpen: false,
        detailModalOpen: false,
        activeTab: 'customerDue',
        selectedBranch: null,
        selectedCustomer: null,
        detailInvoiceId: null,
        filterDateFrom: '',
        filterDateTo: '',
        filteredTransactions: [],

        init() {
            this.loadData();
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.date_from) params.set('date_from', this.date_from);
                if (this.date_to) params.set('date_to', this.date_to);
                if (this.agent) params.set('agent', this.agent);
                const res = await fetch(`/api/reports/due?${params}`);
                const json = await res.json();
                this.branches = json.branches || [];
            } catch (e) {
                console.error('Failed to load due report data', e);
                this.branches = [];
            } finally {
                this.loading = false;
            }
        },

        async openBranchModal(branchId) {
            this.loading = true;
            this.activeTab = 'customerDue';
            try {
                const params = new URLSearchParams();
                if (this.date_from) params.set('date_from', this.date_from);
                if (this.date_to) params.set('date_to', this.date_to);
                const res = await fetch(`/api/reports/due/branch/${branchId}/details?${params}`);
                const json = await res.json();
                this.selectedBranch = {
                    id: branchId,
                    name: (this.branches.find(b => b.id === branchId) || {}).name || '',
                    customers: json.customers || [],
                    dateWiseData: json.dateWiseData || [],
                };
                this.branchModalOpen = true;
            } catch (e) {
                console.error('Failed to load branch details', e);
            } finally {
                this.loading = false;
            }
        },

        closeBranchModal() {
            this.branchModalOpen = false;
            this.selectedBranch = null;
        },

        async openDetailModal(invoiceId) {
            if (!this.selectedBranch) return;
            this.loading = true;
            try {
                this.detailInvoiceId = invoiceId;
                const params = new URLSearchParams();
                const res = await fetch(`/api/reports/due/customer/${invoiceId}/transactions?${params}`);
                const json = await res.json();
                this.selectedCustomer = json.customer || null;
                this.filterDateFrom = '';
                this.filterDateTo = '';
                this.filteredTransactions = json.transactions || [];
                this.detailModalOpen = true;
            } catch (e) {
                console.error('Failed to load customer transactions', e);
            } finally {
                this.loading = false;
            }
        },

        closeDetailModal() {
            this.detailModalOpen = false;
            this.selectedCustomer = null;
            this.detailInvoiceId = null;
            this.filteredTransactions = [];
        },

        backToCustomerList() {
            this.detailModalOpen = false;
            this.branchModalOpen = true;
            this.selectedCustomer = null;
            this.detailInvoiceId = null;
            this.filteredTransactions = [];
        },

        async applyTransactionFilter() {
            if (!this.detailInvoiceId) return;
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.filterDateFrom) params.set('date_from', this.filterDateFrom);
                if (this.filterDateTo) params.set('date_to', this.filterDateTo);
                const res = await fetch(`/api/reports/due/customer/${this.detailInvoiceId}/transactions?${params}`);
                const json = await res.json();
                this.filteredTransactions = json.transactions || [];
            } catch (e) {
                console.error('Failed to filter transactions', e);
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endpush
