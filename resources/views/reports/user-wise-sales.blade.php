@extends('layouts.app')
@section('title', 'User Wise Sales Report')
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

.table-row-sales {
    background-color: #ffffff;
    border: 1px solid #d4d4d4;
}

.table-row-sales:nth-child(even) {
    background-color: #fafafa;
}

.table-row-sales:hover {
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

.percent-bar {
    height: 20px;
    background-color: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.percent-fill {
    height: 100%;
    background: linear-gradient(to right, #3b82f6, #2563eb);
    transition: width 0.3s ease;
}
</style>

<div x-data="userSalesReport()">
    <div class="max-w-[1600px] mx-auto p-4">
        <div class="sticky top-0 z-30 bg-white py-2 mb-3">
            <span class="text-sm text-gray-500 font-medium">Report</span>
            <span class="text-sm text-gray-400 mx-1">></span>
            <span class="text-sm text-gray-700 font-semibold">User Wise Sales Report</span>
        </div>

        <div class="sticky top-[40px] z-20 bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">Branch</label>
                    <select x-model="branch" @change="loadData()" class="search-input w-40 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="all">All Branches</option>
                        <template x-for="b in branches" :key="b.id">
                            <option :value="b.id" x-text="b.name"></option>
                        </template>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">User</label>
                    <select x-model="user" @change="loadData()" class="search-input w-40 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="all">All Users</option>
                        <template x-for="u in users" :key="u.id">
                            <option :value="u.id" x-text="u.name"></option>
                        </template>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">Date</label>
                    <label class="text-xs text-gray-500">From</label>
                    <input type="date" x-model="date_from" @change="loadData()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <label class="text-xs text-gray-500">To</label>
                    <input type="date" x-model="date_to" @change="loadData()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button @click="resetFilters()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin flex flex-col" style="max-height: calc(100vh - 280px);">
            <div class="overflow-auto flex-1 min-h-0">
                <table class="w-full min-w-[900px] table-fixed">
                    <thead class="sticky top-0 z-10">
                        <tr class="table-header">
                            <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Branch</th>
                            <th class="w-40 px-2 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">User</th>
                            <th class="w-28 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total Invoice</th>
                            <th class="w-28 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total Passenger</th>
                            <th class="w-36 px-2 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Total Package Value</th>
                            <th class="w-40 px-2 py-3 text-xs font-bold text-gray-700 text-center">Sales Percent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Loading...</td>
                            </tr>
                        </template>
                        <template x-if="!loading && rows.length === 0">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No data found</td>
                            </tr>
                        </template>
                        <template x-for="(row, index) in paginatedRows" :key="index">
                            <tr class="table-row-sales">
                                <td class="px-2 py-3 text-xs text-center border-r border-gray-200 font-medium" x-text="row.branch"></td>
                                <td class="px-2 py-3 text-xs text-left border-r border-gray-200 font-medium" x-text="row.user"></td>
                                <td class="px-2 py-3 text-xs text-center border-r border-gray-200" x-text="row.total_invoices"></td>
                                <td class="px-2 py-3 text-xs text-center border-r border-gray-200" x-text="row.total_passengers"></td>
                                <td class="px-2 py-3 text-xs text-right border-r border-gray-200 font-medium" x-text="formatCurrency(row.total_package_value)"></td>
                                <td class="px-2 py-3 text-xs text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="percent-bar w-20">
                                            <div class="percent-fill" :style="`width: ${row.sales_percent}%`"></div>
                                        </div>
                                        <span class="font-bold text-blue-600" x-text="row.sales_percent.toFixed(2) + '%'"></span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                </tbody>
            </table>
        </div>
    </div>

    <nav x-show="rowTotalPages > 1" class="flex justify-end" aria-label="Pagination Navigation">
        <span class="inline-flex items-center gap-2">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage <= 1"
                    :class="currentPage <= 1 ? 'px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5' : 'px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100'">
                Prev
            </button>
            <span class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md leading-5">
                <span x-text="currentPage"></span>/<span x-text="rowTotalPages"></span>
            </span>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage >= rowTotalPages"
                    :class="currentPage >= rowTotalPages ? 'px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5' : 'px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100'">
                Next
            </button>
        </span>
    </nav>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mt-0">
        <div class="flex flex-wrap gap-6">
            <div class="footer-box rounded-lg overflow-hidden min-w-[320px]">
                <div class="footer-box-header px-4 py-2">
                    <span class="text-sm font-bold text-gray-700">User Wise Sales Summary</span>
                </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-x-8 gap-y-2">
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Users:</span>
                                <span class="text-xs font-bold text-gray-800" x-text="summary.total_users"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Branches:</span>
                                <span class="text-xs font-bold text-gray-800" x-text="summary.total_branches"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Invoices:</span>
                                <span class="text-xs font-bold text-gray-800" x-text="summary.total_invoices"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Passengers:</span>
                                <span class="text-xs font-bold text-gray-800" x-text="summary.total_passengers"></span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 mt-1">
                                <span class="text-xs text-gray-600">Total Package Value:</span>
                                <span class="text-xs font-bold text-green-700" x-text="formatCurrency(summary.total_package_value)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{--<div class="flex flex-col gap-4">
                    <div class="footer-box rounded-lg overflow-hidden">
                        <div class="footer-box-header px-4 py-2">
                            <span class="text-sm font-bold text-gray-700">Export Options</span>
                        </div>
                        <div class="p-4 flex gap-3">
                            <button @click="exportPDF()" class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                                <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                    <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                                </svg>
                                PDF
                            </button>
                        </div>
                    </div>
                </div>--}}
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center">
            <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function userSalesReport() {
    return {
        branch: 'all',
        user: 'all',
        date_from: '',
        date_to: '',
        loading: false,
        rows: [],
        branches: [],
        users: [],
        summary: {},
        currentPage: 1,
        perPage: 25,

        get rowTotalPages() {
            return Math.max(1, Math.ceil(this.rows.length / this.perPage));
        },

        get paginatedRows() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.rows.slice(start, start + this.perPage);
        },

        init() {
            this.setDefaultDates();
            this.loadFilters();
            this.loadData();
        },

        setDefaultDates() {
            const today = new Date();
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            this.date_from = thirtyDaysAgo.toISOString().split('T')[0];
            this.date_to = today.toISOString().split('T')[0];
        },

        async loadFilters() {
            try {
                const res = await fetch('/api/reports/user-wise-sales/filters');
                const json = await res.json();
                this.branches = json.branches || [];
                this.users = json.users || [];
            } catch (e) {
                console.error('Failed to load filters', e);
            }
        },

        async loadData() {
            this.loading = true;
            this.currentPage = 1;
            try {
                const params = new URLSearchParams();
                if (this.branch !== 'all') params.set('branch_id', this.branch);
                if (this.user !== 'all') params.set('user_id', this.user);
                if (this.date_from) params.set('date_from', this.date_from);
                if (this.date_to) params.set('date_to', this.date_to);
                const res = await fetch(`/api/reports/user-wise-sales?${params}`);
                const json = await res.json();
                this.rows = json.rows || [];
                this.summary = json.summary || {};
            } catch (e) {
                console.error('Failed to load user sales data', e);
                this.rows = [];
                this.summary = {};
            } finally {
                this.loading = false;
            }
        },

        resetFilters() {
            this.branch = 'all';
            this.user = 'all';
            this.setDefaultDates();
            this.loadData();
        },

        goToPage(page) {
            if (page < 1 || page > this.rowTotalPages) return;
            this.currentPage = page;
        },

        formatCurrency(amount) {
            const num = Number(amount) || 0;
            const formatted = Alpine.store('currency').format(num, 0);
            return formatted + (Alpine.store('currency').mode === 'BDT' ? ' BDT' : ' SAR');
        },

        exportPDF() {
            const params = new URLSearchParams();
            if (this.branch !== 'all') params.set('branch_id', this.branch);
            if (this.user !== 'all') params.set('user_id', this.user);
            if (this.date_from) params.set('date_from', this.date_from);
            if (this.date_to) params.set('date_to', this.date_to);
            window.open(`/reports/user-wise-sales/pdf?${params}`, '_blank');
        }
    };
}
</script>
@endpush
