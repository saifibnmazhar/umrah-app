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

<div class="max-w-[1600px] mx-auto p-4" x-data="dueReportApp()">
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
                    <template x-for="(branch, index) in branches" :key="branch.id">
                        <tr class="table-row-due">
                            <td class="px-4 py-3 text-sm border-r border-gray-200 font-medium text-gray-800" x-text="branch.name"></td>
                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-semibold amount-due" x-text="formatCurrency(branch.totalDue)"></td>
                            <td class="px-4 py-3 text-sm text-center">
                                <button @click="openBranchModal(branch.id)" class="btn-view inline-block">View</button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="branches.length === 0">
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">No due data found</td>
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
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium" x-text="formatCurrency(customer.totalPackage)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium amount-positive" x-text="formatCurrency(customer.paid)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-semibold amount-due" x-text="formatCurrency(customer.due)"></td>
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
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-semibold amount-due" x-text="formatCurrency(row.due)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium amount-positive" x-text="formatCurrency(row.cash)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium amount-positive" x-text="formatCurrency(row.bank)"></td>
                                        <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-semibold amount-positive" x-text="formatCurrency(row.totalCollected)"></td>
                                        <td class="px-3 py-2.5 text-xs text-right font-semibold amount-warning" x-text="formatCurrency(row.newDue)"></td>
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
                                    <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-semibold amount-due" x-text="formatCurrency(trx.due)"></td>
                                    <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-right font-medium amount-positive" x-text="formatCurrency(trx.paid)"></td>
                                    <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-center">
                                        <span :class="trx.method === 'Cash' ? 'badge-cash' : 'badge-bank'" x-text="trx.method"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs border-r border-gray-200 text-center font-mono" x-text="trx.trxId"></td>
                                    <td class="px-3 py-2.5 text-xs text-right font-semibold" :class="trx.newDue > 0 ? 'amount-warning' : 'amount-positive'" x-text="formatCurrency(trx.newDue)"></td>
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
@endsection

@push('scripts')
<script>
function dueReportApp() {
    return {
        date_from: '',
        date_to: '',
        agent: '',

        branches: [
            {
                id: 1,
                name: "Riyadh Branch",
                totalDue: 124500.00,
                customers: [
                    {
                        id: 1,
                        name: "Al-Rajhi Trading Co.",
                        mobile: "+966 55 123 4567",
                        invoiceId: "INV-BM-2026-001",
                        ticketDate: "15-Mar-2026",
                        totalPackage: 45000.00,
                        paid: 35000.00,
                        due: 10000.00,
                        transactions: [
                            { date: "01-Mar-2026", due: 45000.00, paid: 20000.00, method: "Bank", trxId: "TRX-789456123", newDue: 25000.00 },
                            { date: "05-Mar-2026", due: 25000.00, paid: 10000.00, method: "Cash", trxId: "CSH-456789", newDue: 15000.00 },
                            { date: "10-Mar-2026", due: 15000.00, paid: 5000.00, method: "Bank", trxId: "TRX-123456789", newDue: 10000.00 }
                        ]
                    },
                    {
                        id: 2,
                        name: "Al-Faisal Group",
                        mobile: "+966 50 234 5678",
                        invoiceId: "INV-BM-2026-002",
                        ticketDate: "20-Mar-2026",
                        totalPackage: 78500.00,
                        paid: 58500.00,
                        due: 20000.00,
                        transactions: [
                            { date: "03-Mar-2026", due: 78500.00, paid: 30000.00, method: "Bank", trxId: "TRX-456123789", newDue: 48500.00 },
                            { date: "08-Mar-2026", due: 48500.00, paid: 15000.00, method: "Cash", trxId: "CSH-789123", newDue: 33500.00 },
                            { date: "15-Mar-2026", due: 33500.00, paid: 13500.00, method: "Bank", trxId: "TRX-951753486", newDue: 20000.00 }
                        ]
                    },
                    {
                        id: 3,
                        name: "Al-Nour Enterprises",
                        mobile: "+966 55 345 6789",
                        invoiceId: "INV-BM-2026-003",
                        ticketDate: "25-Mar-2026",
                        totalPackage: 52000.00,
                        paid: 27000.00,
                        due: 25000.00,
                        transactions: [
                            { date: "05-Mar-2026", due: 52000.00, paid: 20000.00, method: "Bank", trxId: "TRX-357951486", newDue: 32000.00 },
                            { date: "12-Mar-2026", due: 32000.00, paid: 7000.00, method: "Cash", trxId: "CSH-159753", newDue: 25000.00 }
                        ]
                    }
                ],
                dateWiseData: [
                    { date: "01-Mar-2026", due: 85000.00, cash: 20000.00, bank: 30000.00, totalCollected: 50000.00, newDue: 35000.00 },
                    { date: "05-Mar-2026", due: 68000.00, cash: 10000.00, bank: 15000.00, totalCollected: 25000.00, newDue: 43000.00 },
                    { date: "08-Mar-2026", due: 73000.00, cash: 8000.00, bank: 22000.00, totalCollected: 30000.00, newDue: 43000.00 },
                    { date: "10-Mar-2026", due: 68000.00, cash: 5000.00, bank: 35000.00, totalCollected: 40000.00, newDue: 28000.00 },
                    { date: "12-Mar-2026", due: 53000.00, cash: 7000.00, bank: 0.00, totalCollected: 7000.00, newDue: 46000.00 },
                    { date: "15-Mar-2026", due: 66000.00, cash: 13500.00, bank: 0.00, totalCollected: 13500.00, newDue: 52500.00 }
                ]
            },
            {
                id: 2,
                name: "Jeddah Branch",
                totalDue: 98500.00,
                customers: [
                    {
                        id: 4,
                        name: "Al-Huda Services",
                        mobile: "+966 55 567 8901",
                        invoiceId: "INV-BM-2026-004",
                        ticketDate: "18-Mar-2026",
                        totalPackage: 38000.00,
                        paid: 28000.00,
                        due: 10000.00,
                        transactions: [
                            { date: "02-Mar-2026", due: 38000.00, paid: 15000.00, method: "Bank", trxId: "TRX-852963741", newDue: 23000.00 },
                            { date: "10-Mar-2026", due: 23000.00, paid: 8000.00, method: "Cash", trxId: "CSH-357951", newDue: 15000.00 },
                            { date: "15-Mar-2026", due: 15000.00, paid: 5000.00, method: "Bank", trxId: "TRX-741852963", newDue: 10000.00 }
                        ]
                    },
                    {
                        id: 5,
                        name: "Al-Madinah Corp.",
                        mobile: "+966 50 678 9012",
                        invoiceId: "INV-BM-2026-005",
                        ticketDate: "22-Mar-2026",
                        totalPackage: 65000.00,
                        paid: 42500.00,
                        due: 22500.00,
                        transactions: [
                            { date: "04-Mar-2026", due: 65000.00, paid: 25000.00, method: "Bank", trxId: "TRX-159357486", newDue: 40000.00 },
                            { date: "12-Mar-2026", due: 40000.00, paid: 10000.00, method: "Cash", trxId: "CSH-486159", newDue: 30000.00 },
                            { date: "18-Mar-2026", due: 30000.00, paid: 7500.00, method: "Bank", trxId: "TRX-753159842", newDue: 22500.00 }
                        ]
                    },
                    {
                        id: 6,
                        name: "Makkah Travel Agency",
                        mobile: "+966 58 789 0123",
                        invoiceId: "INV-BM-2026-006",
                        ticketDate: "28-Mar-2026",
                        totalPackage: 44000.00,
                        paid: 28000.00,
                        due: 16000.00,
                        transactions: [
                            { date: "06-Mar-2026", due: 44000.00, paid: 18000.00, method: "Bank", trxId: "TRX-951456783", newDue: 26000.00 },
                            { date: "14-Mar-2026", due: 26000.00, paid: 10000.00, method: "Cash", trxId: "CSH-284965", newDue: 16000.00 }
                        ]
                    }
                ],
                dateWiseData: [
                    { date: "02-Mar-2026", due: 45000.00, cash: 0.00, bank: 15000.00, totalCollected: 15000.00, newDue: 30000.00 },
                    { date: "04-Mar-2026", due: 60000.00, cash: 0.00, bank: 25000.00, totalCollected: 25000.00, newDue: 35000.00 },
                    { date: "06-Mar-2026", due: 61000.00, cash: 0.00, bank: 18000.00, totalCollected: 18000.00, newDue: 43000.00 },
                    { date: "10-Mar-2026", due: 58000.00, cash: 8000.00, bank: 0.00, totalCollected: 8000.00, newDue: 50000.00 },
                    { date: "12-Mar-2026", due: 65000.00, cash: 10000.00, bank: 0.00, totalCollected: 10000.00, newDue: 55000.00 },
                    { date: "14-Mar-2026", due: 57000.00, cash: 10000.00, bank: 0.00, totalCollected: 10000.00, newDue: 47000.00 },
                    { date: "15-Mar-2026", due: 52000.00, cash: 0.00, bank: 5000.00, totalCollected: 5000.00, newDue: 47000.00 },
                    { date: "18-Mar-2026", due: 62000.00, cash: 0.00, bank: 7500.00, totalCollected: 7500.00, newDue: 54500.00 }
                ]
            },
            {
                id: 3,
                name: "Dammam Branch",
                totalDue: 67800.00,
                customers: [
                    {
                        id: 7,
                        name: "Eastern Province Traders",
                        mobile: "+966 53 890 1234",
                        invoiceId: "INV-BM-2026-007",
                        ticketDate: "12-Mar-2026",
                        totalPackage: 32000.00,
                        paid: 19200.00,
                        due: 12800.00,
                        transactions: [
                            { date: "01-Mar-2026", due: 32000.00, paid: 12000.00, method: "Bank", trxId: "TRX-753951456", newDue: 20000.00 },
                            { date: "08-Mar-2026", due: 20000.00, paid: 7200.00, method: "Cash", trxId: "CSH-951753", newDue: 12800.00 }
                        ]
                    },
                    {
                        id: 8,
                        name: "Al-Qatif Group",
                        mobile: "+966 50 901 2345",
                        invoiceId: "INV-BM-2026-008",
                        ticketDate: "16-Mar-2026",
                        totalPackage: 55000.00,
                        paid: 40000.00,
                        due: 15000.00,
                        transactions: [
                            { date: "03-Mar-2026", due: 55000.00, paid: 30000.00, method: "Bank", trxId: "TRX-159486753", newDue: 25000.00 },
                            { date: "10-Mar-2026", due: 25000.00, paid: 10000.00, method: "Cash", trxId: "CSH-486159", newDue: 15000.00 }
                        ]
                    }
                ],
                dateWiseData: [
                    { date: "01-Mar-2026", due: 42000.00, cash: 0.00, bank: 12000.00, totalCollected: 12000.00, newDue: 30000.00 },
                    { date: "03-Mar-2026", due: 65000.00, cash: 0.00, bank: 30000.00, totalCollected: 30000.00, newDue: 35000.00 },
                    { date: "08-Mar-2026", due: 53000.00, cash: 7200.00, bank: 0.00, totalCollected: 7200.00, newDue: 45800.00 },
                    { date: "10-Mar-2026", due: 60800.00, cash: 10000.00, bank: 0.00, totalCollected: 10000.00, newDue: 50800.00 }
                ]
            },
            {
                id: 4,
                name: "Madinah Branch",
                totalDue: 45600.00,
                customers: [
                    {
                        id: 9,
                        name: "Madinah Tour Services",
                        mobile: "+966 56 012 3456",
                        invoiceId: "INV-BM-2026-009",
                        ticketDate: "08-Mar-2026",
                        totalPackage: 28000.00,
                        paid: 22400.00,
                        due: 5600.00,
                        transactions: [
                            { date: "02-Mar-2026", due: 28000.00, paid: 15000.00, method: "Bank", trxId: "TRX-357486159", newDue: 13000.00 },
                            { date: "05-Mar-2026", due: 13000.00, paid: 7400.00, method: "Cash", trxId: "CSH-753159", newDue: 5600.00 }
                        ]
                    },
                    {
                        id: 10,
                        name: "Al-Aqsa Group",
                        mobile: "+966 54 123 4567",
                        invoiceId: "INV-BM-2026-010",
                        ticketDate: "14-Mar-2026",
                        totalPackage: 40000.00,
                        paid: 0.00,
                        due: 40000.00,
                        transactions: [
                            { date: "06-Mar-2026", due: 40000.00, paid: 0.00, method: "Cash", trxId: "-", newDue: 40000.00 }
                        ]
                    }
                ],
                dateWiseData: [
                    { date: "02-Mar-2026", due: 48000.00, cash: 0.00, bank: 15000.00, totalCollected: 15000.00, newDue: 33000.00 },
                    { date: "05-Mar-2026", due: 53000.00, cash: 7400.00, bank: 0.00, totalCollected: 7400.00, newDue: 45600.00 },
                    { date: "06-Mar-2026", due: 85600.00, cash: 0.00, bank: 0.00, totalCollected: 0.00, newDue: 85600.00 }
                ]
            },
            {
                id: 5,
                name: "Al-Qaif Branch",
                totalDue: 32100.00,
                customers: [
                    {
                        id: 11,
                        name: "Al-Qaif Trading Est.",
                        mobile: "+966 57 234 5678",
                        invoiceId: "INV-BM-2026-011",
                        ticketDate: "10-Mar-2026",
                        totalPackage: 25000.00,
                        paid: 12500.00,
                        due: 12500.00,
                        transactions: [
                            { date: "04-Mar-2026", due: 25000.00, paid: 12500.00, method: "Bank", trxId: "TRX-951753486", newDue: 12500.00 }
                        ]
                    },
                    {
                        id: 12,
                        name: "Northern Border Co.",
                        mobile: "+966 51 345 6789",
                        invoiceId: "INV-BM-2026-012",
                        ticketDate: "18-Mar-2026",
                        totalPackage: 19600.00,
                        paid: 0.00,
                        due: 19600.00,
                        transactions: [
                            { date: "10-Mar-2026", due: 19600.00, paid: 0.00, method: "Cash", trxId: "-", newDue: 19600.00 }
                        ]
                    }
                ],
                dateWiseData: [
                    { date: "04-Mar-2026", due: 35000.00, cash: 0.00, bank: 12500.00, totalCollected: 12500.00, newDue: 22500.00 },
                    { date: "10-Mar-2026", due: 42100.00, cash: 0.00, bank: 0.00, totalCollected: 0.00, newDue: 42100.00 }
                ]
            }
        ],

        branchModalOpen: false,
        detailModalOpen: false,
        activeTab: 'customerDue',
        selectedBranch: null,
        selectedCustomer: null,
        filterDateFrom: '',
        filterDateTo: '',
        filteredTransactions: [],

        formatCurrency(amount) {
            if (amount === undefined || amount === null) amount = 0;
            return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' SAR';
        },

        loadData() {
            const params = new URLSearchParams();
            if (this.date_from) params.set('date_from', this.date_from);
            if (this.date_to) params.set('date_to', this.date_to);
            if (this.agent) params.set('agent', this.agent);
        },

        openBranchModal(branchId) {
            this.selectedBranch = this.branches.find(b => b.id === branchId);
            this.activeTab = 'customerDue';
            this.branchModalOpen = true;
        },

        closeBranchModal() {
            this.branchModalOpen = false;
            this.selectedBranch = null;
        },

        openDetailModal(customerId) {
            if (!this.selectedBranch) return;
            this.selectedCustomer = this.selectedBranch.customers.find(c => c.id === customerId);
            this.filterDateFrom = '';
            this.filterDateTo = '';
            this.filteredTransactions = this.selectedCustomer ? [...this.selectedCustomer.transactions] : [];
            this.detailModalOpen = true;
        },

        closeDetailModal() {
            this.detailModalOpen = false;
            this.selectedCustomer = null;
            this.filteredTransactions = [];
        },

        backToCustomerList() {
            this.detailModalOpen = false;
            this.branchModalOpen = true;
            this.selectedCustomer = null;
            this.filteredTransactions = [];
        },

        applyTransactionFilter() {
            if (!this.selectedBranch || !this.selectedCustomer) return;
            let transactions = [...this.selectedCustomer.transactions];

            if (this.filterDateFrom) {
                const fromDate = new Date(this.filterDateFrom);
                transactions = transactions.filter(t => {
                    const tParts = t.date.split('-');
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const tDate = new Date(parseInt(tParts[2]), months.indexOf(tParts[1]), parseInt(tParts[0]));
                    return tDate >= fromDate;
                });
            }
            if (this.filterDateTo) {
                const toDate = new Date(this.filterDateTo);
                toDate.setHours(23, 59, 59, 999);
                transactions = transactions.filter(t => {
                    const tParts = t.date.split('-');
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const tDate = new Date(parseInt(tParts[2]), months.indexOf(tParts[1]), parseInt(tParts[0]));
                    return tDate <= toDate;
                });
            }

            this.filteredTransactions = transactions;
        }
    };
}
</script>
@endpush
