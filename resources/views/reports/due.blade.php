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
[x-cloak] { display: none !important; }
</style>

@livewire('report.due-report-table')

<div x-data="dueDetailsModal()"
     x-on:open-branch-details.window="openBranchDetails($event.detail)"
     x-cloak>

    <div x-show="branchModalOpen"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-cloak>
        <div class="absolute inset-0 modal-backdrop" @click="closeBranchModal()"></div>
        <div class="relative z-10 min-h-screen flex items-start justify-center p-4 pt-16">
            <div class="modal-content bg-white rounded-lg shadow-2xl w-full max-w-7xl max-h-[92vh] overflow-hidden">
                <div class="bg-slate-700 text-white px-6 py-5 flex justify-between items-center">
                    <h2 class="text-xl font-bold" x-text="'Due Details - ' + (selectedBranch?.name || '')"></h2>
                    <button @click="closeBranchModal()" class="text-white hover:text-gray-200 p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="border-b border-gray-300 bg-gray-50 px-4 pt-3">
                    <div class="flex items-center justify-between">
                        <div class="flex gap-0" id="tabButtons">
                            <button @click="activeTab = 'customerDue'" :class="activeTab === 'customerDue' ? 'tab-btn active' : 'tab-btn'" class="px-6 py-2.5 rounded-t-md text-sm font-medium text-gray-600">
                                Customer Due Report
                            </button>
                            <button @click="activeTab = 'dateWise'" :class="activeTab === 'dateWise' ? 'tab-btn active' : 'tab-btn'" class="px-6 py-2.5 rounded-t-md text-sm font-medium text-gray-600">
                                Date Wise Due
                            </button>
                        </div>
                        <div class="flex gap-2 pr-2">
                            <a :href="printCustomerUrl" target="_blank" class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                                Customers PDF
                            </a>
                            <a :href="printDateWiseUrl" target="_blank" class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium text-white bg-amber-500 hover:bg-amber-600 transition-colors">
                                Date Wise PDF
                            </a>
                        </div>
                    </div>
                </div>

                <div class="p-6 max-h-[78vh] overflow-y-auto scrollbar-thin">
                    <div x-show="activeTab === 'customerDue'" x-cloak>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1200px] table-fixed">
                                <thead>
                                    <tr class="table-header">
                                        <th class="w-40 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Customer Name</th>
                                        <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Mobile</th>
                                        <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Invoice ID</th>
                                        <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Actual Flight Date</th>
                                        <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Total Package Amount</th>
                                        <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Paid Amount</th>
                                        <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Due</th>
                                        <th class="w-24 px-4 py-3 text-sm font-bold text-gray-700 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="customer in (selectedBranch?.customers || [])" :key="customer.id">
                                        <tr class="table-row-due">
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-left" x-text="customer.name"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-center" x-text="customer.mobile"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-center font-medium" x-text="customer.invoiceId"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-center" x-text="customer.ticketDate"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium amount-positive" x-text="$currency(customer.totalPackage)"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium amount-positive" x-text="$currency(customer.paid)"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-semibold amount-due" x-text="$currency(customer.due)"></td>
                                            <td class="px-4 py-3 text-sm text-center">
                                                <button @click="openDetailModal(customer.id)" class="btn-view">View</button>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="(selectedBranch?.customers || []).length === 0">
                                        <tr>
                                            <td colspan="8" class="px-3 py-8 text-center text-sm text-gray-500">No customers found</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="activeTab === 'dateWise'" x-cloak>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1000px] table-fixed">
                                <thead>
                                    <tr class="table-header">
                                        <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Date</th>
                                        <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Due</th>
                                        <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Collected Amount Cash</th>
                                        <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Collected Amount Bank</th>
                                        <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Total Collected Amount</th>
                                        <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-right">New Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, index) in (selectedBranch?.dateWiseData || [])" :key="index">
                                        <tr class="table-row-due">
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-center" x-text="row.date"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-semibold amount-due" x-text="$currency(row.due)"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium amount-positive" x-text="$currency(row.cash)"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium amount-positive" x-text="$currency(row.bank)"></td>
                                            <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-semibold amount-positive" x-text="$currency(row.totalCollected)"></td>
                                            <td class="px-4 py-3 text-sm text-right font-semibold" :class="row.newDue > 0 ? 'amount-warning' : 'amount-positive'" x-text="$currency(row.newDue)"></td>
                                        </tr>
                                    </template>
                                    <template x-if="(selectedBranch?.dateWiseData || []).length === 0">
                                        <tr>
                                            <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">No date wise data found</td>
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

    <div x-show="detailModalOpen"
         class="fixed inset-0 z-[60] overflow-y-auto"
         x-cloak>
        <div class="absolute inset-0 modal-backdrop" @click="closeDetailModal()"></div>
        <div class="relative z-10 min-h-screen flex items-start justify-center p-4 pt-12">
            <div class="modal-content bg-white rounded-lg shadow-2xl w-full max-w-7xl max-h-[92vh] overflow-hidden">
                <div class="bg-slate-700 text-white px-6 py-5 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <button @click="backToCustomerList()" class="text-white hover:text-gray-200 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <h2 class="text-xl font-bold" x-text="'Transaction Details - ' + (selectedCustomer?.name || '')"></h2>
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
                            Filter
                        </button>
                    </div>
                </div>

                <div class="p-6 max-h-[72vh] overflow-y-auto scrollbar-thin">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] table-fixed">
                            <thead>
                                <tr class="table-header">
                                    <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Date</th>
                                    <th class="w-24 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Due</th>
                                    <th class="w-24 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                                    <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Payment Method</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Trx ID</th>
                                    <th class="w-24 px-4 py-3 text-sm font-bold text-gray-700 text-right">New Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(trx, index) in filteredTransactions" :key="index">
                                    <tr class="table-row-due">
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center" x-text="trx.date"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-semibold amount-due" x-text="$currency(trx.due)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium amount-positive" x-text="$currency(trx.paid)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center">
                                            <span :class="trx.method === 'Cash' ? 'badge-cash' : 'badge-bank'" x-text="trx.method"></span>
                                        </td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center font-mono" x-text="trx.trxId"></td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold" :class="trx.newDue > 0 ? 'amount-warning' : 'amount-positive'" x-text="$currency(trx.newDue)"></td>
                                    </tr>
                                </template>
                                <template x-if="filteredTransactions.length === 0">
                                    <tr>
                                        <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">No transactions found</td>
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

@push('scripts')
function dueDetailsModal() {
    return {
        branchModalOpen: false,
        detailModalOpen: false,
        activeTab: 'customerDue',
        selectedBranch: null,
        selectedCustomer: null,
        detailInvoiceId: null,
        filterDateFrom: '',
        filterDateTo: '',
        transactions: [],
        loading: false,
        loadingTransactions: false,
        currentDateFrom: '',
        currentDateTo: '',

        openBranchDetails(detail) {
            this.currentDateFrom = detail.dateFrom || '';
            this.currentDateTo = detail.dateTo || '';
            this.openBranchModal(detail.branchId, detail.branchName);
        },

        async openBranchModal(branchId, branchName) {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.currentDateFrom) params.set('date_from', this.currentDateFrom);
                if (this.currentDateTo) params.set('date_to', this.currentDateTo);
                const res = await fetch(`/api/reports/due/branch/${branchId}/details?${params}`);
                const json = await res.json();
                this.selectedBranch = {
                    id: branchId,
                    name: branchName,
                    customers: json.customers || [],
                    dateWiseData: json.dateWiseData || []
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
            this.activeTab = 'customerDue';
        },

        async openDetailModal(invoiceId) {
            this.detailInvoiceId = invoiceId;
            this.detailModalOpen = true;
            this.filterDateFrom = '';
            this.filterDateTo = '';
            this.loadTransactions();
        },

        closeDetailModal() {
            this.detailModalOpen = false;
        },

        backToCustomerList() {
            this.detailModalOpen = false;
        },

        async loadTransactions() {
            this.loadingTransactions = true;
            try {
                const params = new URLSearchParams();
                if (this.filterDateFrom) params.set('date_from', this.filterDateFrom);
                if (this.filterDateTo) params.set('date_to', this.filterDateTo);
                const res = await fetch(`/api/reports/due/customer/${this.detailInvoiceId}/transactions?${params}`);
                const json = await res.json();
                this.selectedCustomer = json.customer || {};
                this.transactions = json.transactions || [];
            } catch (e) {
                console.error('Failed to load transactions', e);
                this.transactions = [];
            } finally {
                this.loadingTransactions = false;
            }
        },

        get printCustomerUrl() {
            if (!this.selectedBranch) return '#';
            const params = new URLSearchParams();
            if (this.currentDateFrom) params.set('date_from', this.currentDateFrom);
            if (this.currentDateTo) params.set('date_to', this.currentDateTo);
            return `/reports/due/branch/${this.selectedBranch.id}/print-customers?${params}`;
        },

        get printDateWiseUrl() {
            if (!this.selectedBranch) return '#';
            const params = new URLSearchParams();
            if (this.currentDateFrom) params.set('date_from', this.currentDateFrom);
            if (this.currentDateTo) params.set('date_to', this.currentDateTo);
            return `/reports/due/branch/${this.selectedBranch.id}/print-datewise?${params}`;
        },

        get filteredTransactions() {
            if (!this.filterDateFrom && !this.filterDateTo) {
                return this.transactions;
            }
            const from = this.filterDateFrom;
            const to = this.filterDateTo;
            return this.transactions.filter(trx => {
                if (from && trx.date < from) return false;
                if (to && trx.date > to) return false;
                return true;
            });
        },

        applyTransactionFilter() {
            this.filteredTransactions;
        }
    };
}
</script>
@endpush
