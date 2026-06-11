@extends('layouts.app')
@section('title', 'Visa Sales Report')
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

.table-row-visa {
    background-color: #ffffff;
    border: 1px solid #d4d4d4;
}

.table-row-visa:nth-child(even) {
    background-color: #fafafa;
}

.table-row-visa:hover {
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

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
}

.status-pending {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #f59e0b;
}

.status-processing {
    background-color: #dbeafe;
    color: #1e40af;
    border: 1px solid #3b82f6;
}

.status-approved {
    background-color: #dcfce7;
    color: #166534;
    border: 1px solid #22c55e;
}

.status-rejected {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 8px center;
    background-repeat: no-repeat;
    background-size: 16px 16px;
    padding-right: 32px;
}
</style>

<div class="max-w-[1600px] mx-auto p-4" x-data="visaReport({
    filters: {
        search: '{{ request('search') }}',
        visa_submit_date_from: '{{ request('visa_submit_date_from') }}',
        visa_submit_date_to: '{{ request('visa_submit_date_to') }}',
        flight_date_from: '{{ request('flight_date_from') }}',
        flight_date_to: '{{ request('flight_date_to') }}',
        status: '{{ request('status') }}',
    }
})">
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Report</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Visa Sales Report</span>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">SEARCH BOX</label>
                <input type="text" x-model="filters.search" @keydown.enter="currentPage = 1; loadData()" placeholder="Search by Invoice No, Customer Name, PAX Name, Mobile, Visa Number"
                       class="search-input w-96 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Visa Submit Date</span>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="filters.visa_submit_date_from" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="filters.visa_submit_date_to" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Flight Date</span>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="filters.flight_date_from" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="filters.flight_date_to" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Visa Status</span>
                <select x-model="filters.status" class="search-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="submitted">Processing</option>
                    <option value="issued">Approved</option>
                    <option value="cancelled">Rejected</option>
                </select>
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <button @click="currentPage = 1; loadData()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filter
                </button>
                <button @click="resetFilters()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1400px] table-fixed">
                <thead>
                    <tr class="table-header">
                        <th class="w-28 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Invoice No</th>
                        <th class="w-40 px-2 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Customer Info</th>
                        <th class="w-40 px-2 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Pax Info</th>
                        <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Mobile</th>
                        <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Visa Submit Date</th>
                        <th class="w-28 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Visa Status</th>
                        <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Flight Date</th>
                        <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Visa Number</th>
                        <th class="w-36 px-2 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Visa Agent</th>
                        <th class="w-28 px-2 py-3 text-xs font-bold text-gray-700 text-right">Agent Cost</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <template x-if="loading">
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500">No visa records found</td>
                        </tr>
                    </template>
                    <template x-for="(row, rowIndex) in data" :key="row.id || rowIndex">
                        <tr class="table-row-visa">
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium" x-text="row.invoice_no"></td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200">
                                <div x-text="row.customer_name"></div>
                                <div class="text-[11px] font-semibold text-gray-700" x-text="row.customer_iqama"></div>
                            </td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200">
                                <div x-text="row.pax_name"></div>
                                <div class="text-[11px] font-semibold text-gray-700" x-text="row.pax_passport"></div>
                            </td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row.mobile"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row.visa_submit_date"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200">
                                <span class="status-badge"
                                      :class="{
                                          'status-pending': row.visa_status === 'pending',
                                          'status-processing': row.visa_status === 'submitted',
                                          'status-approved': row.visa_status === 'issued',
                                          'status-rejected': row.visa_status === 'cancelled'
                                      }"
                                      x-text="{
                                          pending: 'Pending',
                                          submitted: 'Processing',
                                          issued: 'Approved',
                                          cancelled: 'Rejected'
                                      }[row.visa_status] || row.visa_status">
                                </span>
                            </td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row.flight_date"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row.visa_number"></td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200" x-text="row.visa_agent"></td>
                            <td class="px-2 py-2 text-xs text-right font-medium" x-text="$currency(row.agent_cost)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="lastPage > 1" class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-600">
                Showing <span class="font-medium" x-text="((currentPage - 1) * perPage + 1)"></span>
                to <span class="font-medium" x-text="Math.min(currentPage * perPage, totalRecords)"></span>
                of <span class="font-medium" x-text="totalRecords"></span> results
            </div>
            <nav class="flex items-center gap-1">
                <button @click="changePage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors">
                    Previous
                </button>
                <template x-for="page in paginationPages" :key="page">
                    <button @click="changePage(page)"
                            :class="page === currentPage ? 'bg-slate-700 text-white border-slate-700' : 'text-slate-600 hover:bg-slate-100 border-slate-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-lg border bg-white transition-colors"
                            x-text="page">
                    </button>
                </template>
                <button @click="changePage(currentPage + 1)"
                        :disabled="currentPage === lastPage"
                        :class="currentPage === lastPage ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors">
                    Next
                </button>
            </nav>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mt-0">
        <div class="flex flex-wrap gap-6">
            <div class="footer-box rounded-lg overflow-hidden min-w-[320px]">
                <div class="footer-box-header px-4 py-2">
                    <span class="text-sm font-bold text-gray-700">Visa Report Summary</span>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Records:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="summary.total_records"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Invoices:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="summary.total_invoices"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Agent Cost:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="$currency(summary.total_agent_cost)"></span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 mt-1">
                            <span class="text-xs text-gray-600">Pending:</span>
                            <span class="text-xs font-bold text-yellow-700" x-text="summary.pending"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Processing:</span>
                            <span class="text-xs font-bold text-blue-700" x-text="summary.submitted"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Approved:</span>
                            <span class="text-xs font-bold text-green-700" x-text="summary.issued"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Rejected:</span>
                            <span class="text-xs font-bold text-red-700" x-text="summary.cancelled"></span>
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
            <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function visaReport(options = {}) {
    return {
        data: [],
        loading: true,
        currentPage: 1,
        lastPage: 1,
        totalRecords: 0,
        perPage: 25,
        filters: options.filters || {
            search: '',
            visa_submit_date_from: '',
            visa_submit_date_to: '',
            flight_date_from: '',
            flight_date_to: '',
            status: 'all',
        },
        summary: {
            total_records: 0,
            total_invoices: 0,
            total_agent_cost: 0,
            pending: 0,
            submitted: 0,
            issued: 0,
            cancelled: 0,
        },

        get paginationPages() {
            const pages = [];
            const start = Math.max(1, this.currentPage - 2);
            const end = Math.min(this.lastPage, this.currentPage + 2);
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },

        init() {
            this.loadData();
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value) params.set(key, value);
                });
                params.set('page', this.currentPage);
                params.set('per_page', this.perPage);
                const response = await fetch(`/api/reports/visa?${params}`);
                const result = await response.json();
                this.data = result.data || [];
                if (result.summary) {
                    this.summary = result.summary;
                }
                if (result.pagination) {
                    this.currentPage = result.pagination.current_page;
                    this.lastPage = result.pagination.last_page;
                    this.totalRecords = result.pagination.total;
                }
            } catch (error) {
                console.error('Failed to load visa report:', error);
                this.data = [];
            } finally {
                this.loading = false;
            }
        },

        changePage(page) {
            if (page < 1 || page > this.lastPage || page === this.currentPage) return;
            this.currentPage = page;
            this.loadData();
        },

        resetFilters() {
            this.filters = {
                search: '',
                visa_submit_date_from: '',
                visa_submit_date_to: '',
                flight_date_from: '',
                flight_date_to: '',
                status: 'all',
            };
            this.currentPage = 1;
            this.loadData();
        },
    };
}
</script>
@endpush
