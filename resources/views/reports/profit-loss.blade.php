@extends('layouts.app')
@section('title', 'Profit/Loss Report')
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

.table-row {
    background-color: #ffffff;
    border: 1px solid #d4d4d4;
}

.table-row:nth-child(even) {
    background-color: #fafafa;
}

.table-row:hover {
    background-color: #e8f4fc !important;
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
    border-bottom: none;
    transition: all 0.2s ease;
}

.tab-btn:hover {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
}

.tab-btn.active {
    background: linear-gradient(to bottom, #fff 0%, #fff 100%);
    border-bottom: 2px solid white;
    color: #1e293b;
    font-weight: 600;
}

.amount-profit {
    color: #166534;
    font-weight: 600;
}

.amount-loss {
    color: #dc2626;
    font-weight: 600;
}

.animate-fade {
    animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

.content-fade {
    transition: opacity 0.15s ease-in-out, transform 0.15s ease-in-out;
}

.content-fade.hidden {
    display: none;
}
</style>

<div x-data="profitLossReport()">
    <div class="max-w-[1600px] mx-auto p-4">
        <div class="mb-3">
            <span class="text-sm text-gray-500 font-medium">Reports</span>
            <span class="text-sm text-gray-400 mx-1">></span>
            <span class="text-sm text-gray-700 font-semibold">Profit/Loss Report</span>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 p-5 rounded-xl shadow-md mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Profit/Loss Report</h2>
                    <p class="text-sm text-gray-500 mt-1">Overview of profitability by customer and passenger</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">From:</label>
                        <input type="date" x-model="date_from" @change="loadData()" class="date-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">To:</label>
                        <input type="date" x-model="date_to" @change="loadData()" class="date-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" x-model="search" placeholder="Search by Invoice ID, Customer Name, Passenger Name, Passport, Iqama"
                               class="search-input w-72 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                </div>
            </div>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 rounded-xl shadow-md overflow-hidden">
            <div class="border-b border-gray-300 bg-gray-50 px-4 pt-3">
                <div class="flex items-center justify-between">
                    <div class="flex gap-0" id="tabButtons">
                        <button @click="activeTab = 'customer'" :class="activeTab === 'customer' ? 'tab-btn active' : 'tab-btn'" class="px-6 py-3 rounded-t-md text-sm font-medium text-gray-600">
                            Per Customer
                        </button>
                        <button @click="activeTab = 'passenger'" :class="activeTab === 'passenger' ? 'tab-btn active' : 'tab-btn'" class="px-6 py-3 rounded-t-md text-sm font-medium text-gray-600">
                            Per Passenger
                        </button>
                    </div>
                    <div class="flex items-center gap-2 pr-1 pb-3">
                        <a :href="'/reports/profit-loss/print?date_from=' + date_from + '&date_to=' + date_to + '&type=customer&currency=' + $store.currency.mode" target="_blank" class="px-3 py-1.5 rounded-md text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 border border-blue-700">Customer Print</a>
                        <a :href="'/reports/profit-loss/print?date_from=' + date_from + '&date_to=' + date_to + '&type=passenger&currency=' + $store.currency.mode" target="_blank" class="px-3 py-1.5 rounded-md text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 border border-emerald-700">Passenger Print</a>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <div x-show="activeTab === 'customer'" class="animate-fade">
                    <div class="overflow-x-auto scrollbar-thin">
                        <table class="w-full min-w-[900px] table-fixed">
                            <thead>
                                <tr class="table-header">
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Invoice ID</th>
                                    <th class="w-40 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Customer Name</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Mobile</th>
                                    <th class="w-20 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Pax Qty</th>
                                    <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Total Package Value (<span x-text="$store.currency.mode"></span>)</th>
                                    <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Total Cost (<span x-text="$store.currency.mode"></span>)</th>
                                    <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-right">Profit/Loss (<span x-text="$store.currency.mode"></span>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="loading">
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Loading...</td>
                                    </tr>
                                </template>
                                <template x-if="!loading && filteredCustomers.length === 0">
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No data found</td>
                                    </tr>
                                </template>
                                <template x-for="(row, index) in filteredCustomers" :key="index">
                                    <tr class="table-row">
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 font-medium text-gray-800" x-text="row.invoice_id"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-gray-800" x-text="row.customer_name"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center text-gray-600" x-text="row.mobile"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center font-medium text-gray-700" x-text="row.pax_qty"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700" x-text="formatCurrency(row.package_value)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700" x-text="formatCurrency(row.total_cost)"></td>
                                        <td class="px-4 py-3 text-sm text-right" :class="row.profit >= 0 ? 'amount-profit' : 'amount-loss'" x-text="formatProfitLoss(row.profit)"></td>
                                    </tr>
                                </template>
                                <template x-if="!loading && filteredCustomers.length > 0">
                                    <tr class="table-header font-bold">
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-gray-800" colspan="4">Grand Total</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800" x-text="formatCurrency(grandTotalCustomer.package_value)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800" x-text="formatCurrency(grandTotalCustomer.total_cost)"></td>
                                        <td class="px-4 py-3 text-sm text-right" :class="grandTotalCustomer.profit >= 0 ? 'amount-profit' : 'amount-loss'" x-text="formatProfitLoss(grandTotalCustomer.profit)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div x-show="activeTab === 'passenger'" class="animate-fade">
                    <div class="overflow-x-auto scrollbar-thin">
                        <table class="w-full min-w-[1000px] table-fixed">
                            <thead>
                                <tr class="table-header">
                                    <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Invoice ID</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Customer Name</th>
                                    <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Mobile</th>
                                    <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Passenger Name</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Package Value (<span x-text="$store.currency.mode"></span>)</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Total Cost (<span x-text="$store.currency.mode"></span>)</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-right">Profit/Loss (<span x-text="$store.currency.mode"></span>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="loading">
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Loading...</td>
                                    </tr>
                                </template>
                                <template x-if="!loading && filteredPassengers.length === 0">
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No data found</td>
                                    </tr>
                                </template>
                                <template x-for="(row, index) in filteredPassengers" :key="index">
                                    <tr class="table-row">
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 font-medium text-gray-800" x-text="row.invoice_id"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-gray-800" x-text="row.customer_name"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center text-gray-600" x-text="row.mobile"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-gray-700" x-text="row.passenger_name"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700" x-text="formatCurrency(row.package_value)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700" x-text="formatCurrency(row.total_cost)"></td>
                                        <td class="px-4 py-3 text-sm text-right" :class="row.profit >= 0 ? 'amount-profit' : 'amount-loss'" x-text="formatProfitLoss(row.profit)"></td>
                                    </tr>
                                </template>
                                <template x-if="!loading && filteredPassengers.length > 0">
                                    <tr class="table-header font-bold">
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-gray-800" colspan="4">Grand Total</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800" x-text="formatCurrency(grandTotalPassenger.package_value)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800" x-text="formatCurrency(grandTotalPassenger.total_cost)"></td>
                                        <td class="px-4 py-3 text-sm text-right" :class="grandTotalPassenger.profit >= 0 ? 'amount-profit' : 'amount-loss'" x-text="formatProfitLoss(grandTotalPassenger.profit)"></td>
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
@endsection

@push('scripts')
<script>
function profitLossReport() {
    return {
        date_from: '',
        date_to: '',
        search: '',
        activeTab: 'customer',
        loading: false,
        customers: [],
        passengers: [],

        init() {
            this.setDefaultDates();
            this.loadData();
            window.addEventListener('currency-toggled', () => {
                this.customers = [...this.customers];
                this.passengers = [...this.passengers];
            });
        },

        setDefaultDates() {
            const today = new Date();
            const firstDay = new Date(today);
            firstDay.setDate(today.getDate() - 30);
            this.date_from = firstDay.toISOString().split('T')[0];
            this.date_to = today.toISOString().split('T')[0];
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.date_from) params.set('date_from', this.date_from);
                if (this.date_to) params.set('date_to', this.date_to);
                const res = await fetch(`/api/reports/profit-loss?${params}`);
                const json = await res.json();
                this.customers = json.customers || [];
                this.passengers = json.passengers || [];
            } catch (e) {
                console.error('Failed to load profit/loss data', e);
                this.customers = [];
                this.passengers = [];
            } finally {
                this.loading = false;
            }
        },

        get filteredCustomers() {
            if (!this.search) return this.customers;
            const q = this.search.toLowerCase();
            return this.customers.filter(r =>
                (r.invoice_id && r.invoice_id.toLowerCase().includes(q)) ||
                (r.customer_name && r.customer_name.toLowerCase().includes(q)) ||
                (r.customer_passport && r.customer_passport.toLowerCase().includes(q)) ||
                (r.customer_iqama && r.customer_iqama.toLowerCase().includes(q))
            );
        },

        get filteredPassengers() {
            if (!this.search) return this.passengers;
            const q = this.search.toLowerCase();
            return this.passengers.filter(r =>
                (r.invoice_id && r.invoice_id.toLowerCase().includes(q)) ||
                (r.customer_name && r.customer_name.toLowerCase().includes(q)) ||
                (r.passenger_name && r.passenger_name.toLowerCase().includes(q)) ||
                (r.passenger_passport && r.passenger_passport.toLowerCase().includes(q)) ||
                (r.customer_passport && r.customer_passport.toLowerCase().includes(q)) ||
                (r.customer_iqama && r.customer_iqama.toLowerCase().includes(q))
            );
        },

        get grandTotalCustomer() {
            return this.filteredCustomers.reduce((acc, r) => {
                acc.package_value += r.package_value;
                acc.total_cost += r.total_cost;
                acc.profit += r.profit;
                return acc;
            }, { package_value: 0, total_cost: 0, profit: 0 });
        },

        get grandTotalPassenger() {
            return this.filteredPassengers.reduce((acc, r) => {
                acc.package_value += r.package_value;
                acc.total_cost += r.total_cost;
                acc.profit += r.profit;
                return acc;
            }, { package_value: 0, total_cost: 0, profit: 0 });
        },

        formatCurrency(amount) {
            const num = Number(amount) || 0;
            const store = Alpine.store('currency');
            if (store.mode === 'BDT' && store.rate > 0) {
                const bdt = num * store.rate;
                return bdt.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' BDT';
            }
            return num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' SAR';
        },

        formatProfitLoss(amount) {
            const sign = amount >= 0 ? '+' : '';
            return sign + this.formatCurrency(amount);
        },

        exportPDF() {
            const params = new URLSearchParams();
            if (this.date_from) params.set('date_from', this.date_from);
            if (this.date_to) params.set('date_to', this.date_to);
            window.open(`/reports/profit-loss/pdf?${params}`, '_blank');
        }
    };
}
</script>
@endpush
