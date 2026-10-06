@extends('layouts.app')
@section('title', 'Ticket Statement')
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
.table-row-ticket {
    background-color: #ffffff;
    border: 1px solid #d4d4d4;
}
.table-row-ticket:hover {
    background-color: #e8f4fc !important;
}
.table-row-pymt {
    background-color: #f0fdf4;
    border: 1px solid #d4d4d4;
}
.table-row-pymt:hover {
    background-color: #dcfce7 !important;
}
.table-row-rfnd {
    background-color: #fef2f2;
    border: 1px solid #d4d4d4;
}
.table-row-rfnd:hover {
    background-color: #fee2e2 !important;
}
.table-row-reis {
    background-color: #fffbeb;
    border: 1px solid #d4d4d4;
}
.table-row-reis:hover {
    background-color: #fef3c7 !important;
}
.footer-box {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
    border: 2px solid #d4d4d4;
}
.footer-box-header {
    background: linear-gradient(to bottom, #f3f3f3 0%, #e8e8e8 100%);
    border-bottom: 1px solid #d4d4d4;
}
.section-row {
    background: linear-gradient(to bottom, #f3f3f3 0%, #e8e8e8 100%);
}
select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 8px center;
    background-repeat: no-repeat;
    background-size: 16px 16px;
    padding-right: 32px;
}
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0.5);
    cursor: pointer;
}
.scrollbar-thin::-webkit-scrollbar { height: 8px; width: 8px; }
.scrollbar-thin::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
.scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
</style>

<div class="max-w-[1600px] mx-auto p-4" x-data="statementReport()">
    <div class="sticky top-0 z-30 bg-white py-2 mb-3">
        <span class="text-sm text-gray-500 font-medium">Report</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Ticket Statement</span>
    </div>

    <div class="sticky top-[40px] z-20 bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">Date Type</label>
                <select x-model="filters.date_type" @change="loadData()" class="search-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="issue">Issue Date</option>
                    <option value="flight">Flight Date (Inbound Date)</option>
                    <option value="return">Return Date (Outbound Date)</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="filters.date_from" @change="loadData()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="filters.date_to" @change="loadData()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">Agents</label>
                <select x-model="filters.agent_id" @change="loadData()" class="search-input w-48 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Agents</option>
                    @foreach($agents as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">SEARCH BOX</label>
                <input type="text" x-model="filters.search" @input.debounce.300ms="loadData()" placeholder="PNR / Ticket No / Passport / Invoice"
                       class="search-input w-72 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="flex items-center gap-2">
                <button @click="loadData()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700">Search</button>
            </div>
            <div class="flex items-center gap-3 ml-auto text-xs text-gray-600">
                <label class="flex items-center gap-1"><input type="checkbox" x-model="showCustomerAmount"> Customer Amount</label>
                <label class="flex items-center gap-1"><input type="checkbox" x-model="showMarkup"> MARKUP</label>
                <label class="flex items-center gap-1"><input type="checkbox" x-model="showCustomerRefund"> Customer Refund</label>
            </div>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin flex flex-col" style="max-height: calc(100vh - 280px);">
        <div class="overflow-auto flex-1 min-h-0">
            <table class="w-full min-w-[1600px] table-fixed">
                <thead class="sticky top-0 z-10">
                    <tr class="table-header">
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Issue Date</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Ticket No</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-gray-300">PAX Name</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-gray-300">PNR</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Sector</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Flight Date</th>
                        <th x-show="showCustomerAmount" rowspan="2" class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Customer Amount</th>
                        <th rowspan="2" class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Agent Fare (Net)</th>
                        <th x-show="showMarkup" rowspan="2" class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-gray-300">MARKUP</th>
                        <th x-show="showCustomerRefund" rowspan="2" class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Customer Refund</th>
                        <th rowspan="2" class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-gray-300">IATA Refund</th>
                        <th rowspan="2" class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payment to IATA</th>
                        <th rowspan="2" class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Balance Agent</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left">IATA Agent</th>
                    </tr>
                    <tr class="table-header">
                        <th class="px-2 py-2 text-xs font-semibold text-gray-600 text-left border-r border-gray-300">Category</th>
                        <th class="px-2 py-2 text-xs font-semibold text-gray-600 text-left border-r border-gray-300">Reference ID</th>
                        <th class="px-2 py-2 text-xs font-semibold text-gray-600 text-left border-r border-gray-300">Customer Name</th>
                        <th class="px-2 py-2 text-xs font-semibold text-gray-600 text-left border-r border-gray-300">Passport</th>
                        <th class="px-2 py-2 text-xs font-semibold text-gray-600 text-left border-r border-gray-300">Carrier | Class | Pay</th>
                        <th class="px-2 py-2 text-xs font-semibold text-gray-600 text-left border-r border-gray-300">Return Date</th>
                        <th class="px-2 py-2 text-xs font-semibold text-gray-600 text-left">Ticket Staff</th>
                    </tr>
                </thead>
                <!-- One <tbody>; x-if only on <template>, x-for has a single
                     <tr> root, kind-switching via x-show on the <td>s (x-show
                     works on any element; bare <td> under <template> does not
                     survive table parsing). Hidden cells take no layout space.
                     Money cells use rowspan="2": the primary row's tall cell
                     spans into the secondary row, so secondary rows carry no
                     money <td>s. Same for the 7 money header <th>s. -->
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="14" class="px-4 py-8 text-sm text-center text-slate-500">Loading...</td></tr>
                    </template>
                    <template x-if="!loading && blocks.length === 0">
                        <tr><td colspan="14" class="px-4 py-8 text-sm text-center text-gray-500">No records found for the selected filters.</td></tr>
                    </template>
                    <template x-if="!loading && blocks.length > 0">
                        <template x-for="b in blocks" :key="b.key">
                            <tr :class="b.trClass">
                                <td colspan="14" x-show="b.kind === 'section-header'" class="px-4 py-2 text-sm font-bold text-gray-800">
                                    <span x-text="b.agent_name"></span>
                                    <span class="font-medium text-gray-600"> — Opening B/L: <span x-text="sar(b.opening_balance)"></span></span>
                                </td>
                                <td x-show="b.kind === 'primary'" class="px-2 py-1 text-xs border-r border-gray-200" x-text="b.row?.date"></td>
                                <td x-show="b.kind === 'primary'" class="px-2 py-1 text-xs border-r border-gray-200" x-text="b.row?.ticket_no"></td>
                                <td x-show="b.kind === 'primary'" class="px-2 py-1 text-xs border-r border-gray-200" x-text="b.row?.pax_name"></td>
                                <td x-show="b.kind === 'primary'" class="px-2 py-1 text-xs border-r border-gray-200" x-text="b.row?.pnr"></td>
                                <td x-show="b.kind === 'primary'" class="px-2 py-1 text-xs border-r border-gray-200" x-text="b.row?.sector"></td>
                                <td x-show="b.kind === 'primary'" class="px-2 py-1 text-xs border-r border-gray-200" x-text="b.row?.flight_date"></td>
                                <td x-show="b.kind === 'primary' && showCustomerAmount" rowspan="2" class="px-2 py-1 text-xs text-right border-r border-gray-200" x-text="sar(b.row?.customer_amount)"></td>
                                <td x-show="b.kind === 'primary'" rowspan="2" class="px-2 py-1 text-xs text-right border-r border-gray-200" x-text="sar(b.row?.agent_fare)"></td>
                                <td x-show="b.kind === 'primary' && showMarkup" rowspan="2" class="px-2 py-1 text-xs text-right border-r border-gray-200" x-text="sar(b.row?.markup)"></td>
                                <td x-show="b.kind === 'primary' && showCustomerRefund" rowspan="2" class="px-2 py-1 text-xs text-right border-r border-gray-200" x-text="sar(b.row?.customer_refund)"></td>
                                <td x-show="b.kind === 'primary'" rowspan="2" class="px-2 py-1 text-xs text-right border-r border-gray-200" x-text="sar(b.row?.iata_refund)"></td>
                                <td x-show="b.kind === 'primary'" rowspan="2" class="px-2 py-1 text-xs text-right border-r border-gray-200" x-text="sar(b.row?.payment_to_iata)"></td>
                                <td x-show="b.kind === 'primary'" rowspan="2" class="px-2 py-1 text-xs text-right font-semibold border-r border-gray-200" x-text="sar(b.row?.balance)"></td>
                                <td x-show="b.kind === 'primary'" class="px-2 py-1 text-xs" x-text="b.row?.agent_name"></td>
                                <td x-show="b.kind === 'secondary'" class="px-2 py-1 text-xs text-gray-600 border-r border-gray-200" x-text="b.row?.category"></td>
                                <td x-show="b.kind === 'secondary'" class="px-2 py-1 text-xs text-gray-600 border-r border-gray-200" x-text="b.row?.reference_id"></td>
                                <td x-show="b.kind === 'secondary'" class="px-2 py-1 text-xs text-gray-600 border-r border-gray-200" x-text="b.row?.customer_name"></td>
                                <td x-show="b.kind === 'secondary'" class="px-2 py-1 text-xs text-gray-600 border-r border-gray-200" x-text="b.row?.passport"></td>
                                <td x-show="b.kind === 'secondary'" class="px-2 py-1 text-xs text-gray-600 border-r border-gray-200" x-text="b.row?.carrier_class_pay"></td>
                                <td x-show="b.kind === 'secondary'" class="px-2 py-1 text-xs text-gray-600 border-r border-gray-200" x-text="b.row?.return_date"></td>
                                <td x-show="b.kind === 'secondary'" class="px-2 py-1 text-xs text-gray-600" x-text="b.row?.staff_name"></td>
                                <td colspan="14" x-show="b.kind === 'section-total'" class="px-4 py-2 text-sm font-bold text-gray-800 text-right">
                                    <span x-text="b.agent_name"></span>
                                    <span class="font-medium text-gray-600"> — Closing B/L: <span x-text="sar(b.closing_balance)"></span></span>
                                </td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-start mt-4">
        <div class="footer-box rounded-lg overflow-hidden w-full max-w-md">
            <div class="footer-box-header px-4 py-2 text-sm font-bold text-gray-800">Ticket Statement Summary</div>
            <div class="px-4 py-3 grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                <div class="text-gray-600">Opening Balance</div><div class="text-right font-semibold" x-text="sar(summary.opening_balance)"></div>
                <div class="text-gray-600">Closing Balance</div><div class="text-right font-semibold" x-text="sar(summary.closing_balance)"></div>
                <div class="text-gray-600">Total Tickets</div><div class="text-right font-semibold" x-text="summary.total_tickets ?? 0"></div>
                <div class="text-gray-600">Total Sale Amount</div><div class="text-right font-semibold" x-text="sar(summary.total_sale_amount)"></div>
                <div class="text-gray-600">Total Customer Refund</div><div class="text-right font-semibold" x-text="sar(summary.total_customer_refund)"></div>
                <div class="text-gray-600">Total Agent Fare</div><div class="text-right font-semibold" x-text="sar(summary.total_agent_fare)"></div>
                <div class="text-gray-600">Total Markup</div><div class="text-right font-semibold" x-text="sar(summary.total_markup)"></div>
                <div class="text-gray-600">Total Agent Refund</div><div class="text-right font-semibold" x-text="sar(summary.total_agent_refund)"></div>
                <div class="text-gray-600">Total Re-Issue Cost</div><div class="text-right font-semibold" x-text="sar(summary.total_reissue_cost)"></div>
                <div class="text-gray-600">Total Paid</div><div class="text-right font-semibold" x-text="sar(summary.total_paid)"></div>
            </div>
        </div>
    </div>
</div>

<script>
function statementReport() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const iso = (d) => d.toISOString().slice(0, 10);
    return {
        filters: {
            date_type: 'issue',
            date_from: iso(firstDay),
            date_to: iso(today),
            agent_id: '',
            search: '',
        },
        flatRows: [],
        sections: [],
        blocks: [],
        summary: {
            opening_balance: 0, closing_balance: 0, total_tickets: 0,
            total_sale_amount: 0, total_customer_refund: 0, total_agent_fare: 0,
            total_markup: 0, total_agent_refund: 0, total_reissue_cost: 0, total_paid: 0,
        },
        loading: false,
        showCustomerAmount: true,
        showMarkup: true,
        showCustomerRefund: true,
        init() {
            this.loadData();
            window.addEventListener('currency-toggled', () => { this.blocks = [...this.blocks]; });
        },
        rowClass(row) {
            return {
                'Ticket': 'table-row-ticket',
                'Payment': 'table-row-pymt',
                'Refund': 'table-row-rfnd',
                'Re-issue': 'table-row-reis',
            }[row.category] || 'table-row-ticket';
        },
        fmt(v) {
            const n = Number(v ?? 0);
            return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        sar(v) {
            if (v === null || v === undefined) return '-';
            const store = Alpine.store('currency');
            const bdt = store && store.mode === 'BDT' && store.rate > 0;
            const val = bdt ? (Number(v) || 0) * store.rate : v;
            return this.fmt(val) + (bdt ? ' BDT' : ' SAR');
        },
        resetState() {
            this.flatRows = [];
            this.sections = [];
            this.blocks = [];
            this.summary = {
                opening_balance: 0, closing_balance: 0, total_tickets: 0,
                total_sale_amount: 0, total_customer_refund: 0, total_agent_fare: 0,
                total_markup: 0, total_agent_refund: 0, total_reissue_cost: 0, total_paid: 0,
            };
        },
        rangeDaysExceeded() {
            const { date_from, date_to } = this.filters;
            if (!date_from || !date_to) return false;
            return (new Date(date_to) - new Date(date_from)) / (1000 * 60 * 60 * 24) > 92;
        },
        async loadData() {
            this.loading = true;
            try {
                if (this.rangeDaysExceeded()) {
                    this.resetState();
                    window.showToast('Date filter exceeds date range cap (92 days)', 'error');
                    return;
                }
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value) params.set(key, value);
                });
                const response = await fetch(`/api/reports/statement?${params}`);
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.message || 'Failed to load ticket statement');
                }
                const result = await response.json();
                this.flatRows = result.rows || [];
                this.sections = result.sections || [];
                if (result.summary) this.summary = result.summary;
                this.buildBlocks();
            } catch (error) {
                console.error('Failed to load ticket statement:', error);
                this.resetState();
                window.showToast(error.message || 'Failed to load ticket statement', 'error');
            } finally {
                this.loading = false;
            }
        },
        buildBlocks() {
            const blocks = [];
            const pushRecord = (row, keyBase) => {
                const trClass = this.rowClass(row);
                blocks.push({ kind: 'primary', key: keyBase + '-p', trClass, row });
                blocks.push({ kind: 'secondary', key: keyBase + '-s', trClass, row });
            };
            if (this.sections.length > 0) {
                this.sections.forEach((section) => {
                    blocks.push({ kind: 'section-header', key: 'sec-' + section.agent_id + '-open', trClass: 'section-row', agent_name: section.agent_name, opening_balance: section.opening_balance });
                    section.rows.forEach((row, idx) => pushRecord(row, 'sec-' + section.agent_id + '-' + idx));
                    blocks.push({ kind: 'section-total', key: 'sec-' + section.agent_id + '-close', trClass: 'section-row', agent_name: section.agent_name, closing_balance: section.closing_balance });
                });
            } else {
                this.flatRows.forEach((row, idx) => pushRecord(row, 'row-' + idx));
            }
            this.blocks = blocks;
        },
    };
}
</script>
@endsection
