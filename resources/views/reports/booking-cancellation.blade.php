@extends('layouts.app')
@section('title', 'Booking Cancellation Report')
@section('content')
<div class="w-full mx-auto pt-6" x-data="cancellationReport({
    filters: {
        date_from: '{{ request('date_from') }}',
        date_to: '{{ request('date_to') }}',
        branch_id: '{{ request('branch_id') }}',
        status: '{{ request('status') }}',
        search: '{{ request('search') }}',
    }
})">
    <div class="sticky top-0 z-30 bg-white py-2 mb-3">
        <span class="text-sm text-gray-500 font-medium">Report</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Booking Cancellation Report</span>
    </div>

    <div class="sticky top-[40px] z-20 bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700 whitespace-nowrap">Search</label>
                <input type="text" x-model="filters.search" @input.debounce.400ms="currentPage = 1; loadData()"
                       placeholder="Invoice ID, Customer, Mobile"
                       class="w-72 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Cancel Date</span>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="filters.date_from" @change="currentPage = 1; loadData()"
                       class="w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="filters.date_to" @change="currentPage = 1; loadData()"
                       class="w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Branch</span>
                <select x-model="filters.branch_id" @change="currentPage = 1; loadData()"
                        class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Branches</option>
                    @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Status</span>
                <select x-model="filters.status" @change="currentPage = 1; loadData()"
                        class="w-40 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All</option>
                    <option value="cancellation processing">Processing</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <button @click="resetFilters()"
                        class="px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1 border border-gray-300 bg-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 shadow-sm flex flex-col" style="max-height: calc(100vh - 280px);">
        <div class="overflow-auto flex-1 min-h-0">
            <table class="w-full min-w-[1600px] text-sm">
                <thead class="sticky top-0 z-10 bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-left border border-gray-300">Invoice ID</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-left border border-gray-300">Customer</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-left border border-gray-300">Mobile</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-left border border-gray-300">Booking Branch</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-left border border-gray-300">Cancellation Branch</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-right border border-gray-300">Total Paid</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-right border border-gray-300">Deduction</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-right border border-gray-300">Refund</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-center border border-gray-300">Method</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-left border border-gray-300">Remarks</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-left border border-gray-300">Cancel Date</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-left border border-gray-300">Refund Date</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-left border border-gray-300">Refunded By</th>
                        <th class="px-3 py-2 text-xs font-bold text-gray-700 text-center border border-gray-300">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="14" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="14" class="px-3 py-8 text-center text-slate-500">No cancellation records found</td>
                        </tr>
                    </template>
                    <template x-for="(row, idx) in data" :key="row.id || idx">
                        <tr class="hover:bg-slate-50" :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/50'">
                            <td class="px-3 py-2 text-xs border-r border-gray-200" x-text="row.invoice_id || '—'"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200" x-text="row.customer_name || '—'"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200" x-text="row.mobile || '—'"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200" x-text="row.booking_branch || '—'"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200" x-text="row.cancellation_branch || '—'"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200 text-right font-medium" x-text="$currency(row.total_paid, 2)"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200 text-right">
                                <template x-if="row.service_charge_deduction !== null">
                                    <span x-text="$currency(row.service_charge_deduction, 2)"></span>
                                </template>
                                <template x-if="row.service_charge_deduction === null">
                                    <span class="text-slate-400">—</span>
                                </template>
                            </td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200 text-right font-semibold text-blue-700" x-text="$currency(row.refund_amount, 2)"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200 text-center" x-text="row.method || '—'"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200 max-w-[200px] truncate" x-text="row.remarks || '—'" :title="row.remarks || ''"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200" x-text="row.cancelled_at || '—'"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200" x-text="row.refunded_at || '—'"></td>
                            <td class="px-3 py-2 text-xs border-r border-gray-200" x-text="row.refunded_by || '—'"></td>
                            <td class="px-3 py-2 text-xs text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                      :class="row.status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'"
                                      x-text="row.status === 'cancelled' ? 'Cancelled' : 'Processing'"></span>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length > 0">
                        <tr class="bg-gray-50 font-semibold border-t-2 border-gray-400">
                            <td colspan="5" class="px-3 py-2 text-xs text-right text-gray-700">Summary:</td>
                            <td class="px-3 py-2 text-xs text-right text-gray-800" x-text="$currency(summary.total_paid, 2)"></td>
                            <td class="px-3 py-2 text-xs text-right text-gray-800" x-text="$currency(summary.total_deduction, 2)"></td>
                            <td class="px-3 py-2 text-xs text-right text-blue-700" x-text="$currency(summary.total_refund, 2)"></td>
                            <td colspan="7"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-200 flex-shrink-0" x-show="!loading && pagination.last_page > 1">
            <nav class="flex justify-end" aria-label="Pagination Navigation">
                <span class="inline-flex items-center gap-2">
                    <button @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                            :class="pagination.current_page <= 1 ? 'px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5' : 'px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100'">
                        Prev
                    </button>
                    <span class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md leading-5">
                        <span x-text="pagination.current_page"></span>/<span x-text="pagination.last_page"></span>
                    </span>
                    <button @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
                            :class="pagination.current_page >= pagination.last_page ? 'px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5' : 'px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100'">
                        Next
                    </button>
                </span>
            </nav>
                <button @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
                        class="px-3 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">
                    Next
                </button>
            </div>
>>>>>>> 1783ebca2fbc0f2adc616189a09b3397996001ee
        </div>
    </div>
</div>

@push('scripts')
<script>
function cancellationReport(initial) {
    return {
        filters: {
            date_from: initial.filters.date_from || '',
            date_to: initial.filters.date_to || '',
            branch_id: initial.filters.branch_id || '',
            status: initial.filters.status || '',
            search: initial.filters.search || '',
        },
        data: [],
        summary: { total_paid: 0, total_deduction: 0, total_refund: 0 },
        pagination: { current_page: 1, last_page: 1, total: 0, per_page: 20 },
        currentPage: 1,
        loading: false,

        init() {
            this.loadData();
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ page: this.currentPage });
                Object.entries(this.filters).forEach(([k, v]) => {
                    if (v) params.set(k, v);
                });
                const res = await fetch(`/api/reports/booking-cancellation?${params}`);
                const json = await res.json();
                this.data = json.data || [];
                this.summary = json.summary || { total_paid: 0, total_deduction: 0, total_refund: 0 };
                this.pagination = json.pagination || { current_page: 1, last_page: 1, total: 0, per_page: 20 };
            } catch (e) {
                this.data = [];
                this.summary = { total_paid: 0, total_deduction: 0, total_refund: 0 };
            } finally {
                this.loading = false;
            }
        },

        resetFilters() {
            this.filters = { date_from: '', date_to: '', branch_id: '', status: '', search: '' };
            this.currentPage = 1;
            this.loadData();
        },

        goToPage(page) {
            if (page < 1 || page > this.pagination.last_page) return;
            this.currentPage = page;
            this.loadData();
        },
    };
}
</script>
@endpush
@endsection
