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
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">Profit/Loss:</label>
                        <select x-model="profitLossFilter" class="px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 border border-gray-300">
                            <option value="all">All</option>
                            <option value="profit">Profit</option>
                            <option value="loss">Loss</option>
                        </select>
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

            <div class="p-4 pt-4">
                <div x-show="activeTab === 'customer'" x-cloak class="animate-fade mb-4">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="bg-gray-50 border border-gray-300 rounded-xl shadow-sm p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Customers</div>
                            <div class="mt-1 text-lg font-bold text-gray-800" x-text="filteredCustomers.length"></div>
                        </div>
                        <div class="bg-gray-50 border border-gray-300 rounded-xl shadow-sm p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Fingerprint Profit</div>
                            <div class="mt-1 text-lg font-bold" :class="bdClass(grandTotalCustomer.fingerprint_profit)" x-text="formatProfitLoss(grandTotalCustomer.fingerprint_profit)"></div>
                        </div>
                        <div class="bg-gray-50 border border-gray-300 rounded-xl shadow-sm p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Passenger Profit</div>
                            <div class="mt-1 text-lg font-bold" :class="bdClass(grandTotalCustomer.passenger_profit_total)" x-text="formatProfitLoss(grandTotalCustomer.passenger_profit_total)"></div>
                        </div>
                        <div class="bg-gray-50 border border-gray-300 rounded-xl shadow-sm p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Discount</div>
                            <div class="mt-1 text-lg font-bold text-gray-800" x-text="formatCurrency(grandTotalCustomer.discount)"></div>
                        </div>
                        <div class="bg-gray-50 border border-gray-300 rounded-xl shadow-sm p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Profit</div>
                            <div class="mt-1 text-lg font-bold" :class="grandTotalCustomer.total_profit >= 0 ? 'amount-profit' : 'amount-loss'" x-text="formatProfitLoss(grandTotalCustomer.total_profit)"></div>
                        </div>
                    </div>
                </div>

                <div x-show="activeTab === 'passenger'" x-cloak class="animate-fade mb-4">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 border border-gray-300 rounded-xl shadow-sm p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Passengers</div>
                            <div class="mt-1 text-lg font-bold text-gray-800" x-text="filteredPassengers.length"></div>
                        </div>
                        <div class="bg-gray-50 border border-gray-300 rounded-xl shadow-sm p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Package Value</div>
                            <div class="mt-1 text-lg font-bold text-gray-800" x-text="formatCurrency(grandTotalPassenger.package_value)"></div>
                        </div>
                        <div class="bg-gray-50 border border-gray-300 rounded-xl shadow-sm p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Profit</div>
                            <div class="mt-1 text-lg font-bold" :class="grandTotalPassenger.total_profit >= 0 ? 'amount-profit' : 'amount-loss'" x-text="formatProfitLoss(grandTotalPassenger.total_profit)"></div>
                        </div>
                    </div>
                </div>

                <div x-show="activeTab === 'customer'" x-cloak class="animate-fade">
                    <div class="overflow-x-auto scrollbar-thin">
                        <table class="w-full min-w-[1100px] table-fixed">
                            <thead>
                                <tr class="table-header">
                                    <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Invoice ID</th>
                                    <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Customer Name</th>
                                    <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Mobile</th>
                                    <th class="w-16 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Pax Qty</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Package Value (<span x-text="$store.currency.mode"></span>)</th>
                                    <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Fingerprint Profit</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Passenger Profit</th>
                                    <th class="w-24 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Discount</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-right">Total Profit (<span x-text="$store.currency.mode"></span>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="loading">
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">Loading...</td>
                                    </tr>
                                </template>
                                <template x-if="!loading && filteredCustomers.length === 0">
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">No data found</td>
                                    </tr>
                                </template>
                                <template x-for="(row, index) in filteredCustomers" :key="index">
                                    <tr class="table-row">
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 font-medium text-gray-800" x-text="row.invoice_id"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-gray-800" x-text="row.customer_name"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center text-gray-600" x-text="row.mobile"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center font-medium text-gray-700" x-text="row.pax_qty"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700" x-text="formatCurrency(row.package_value)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right cursor-pointer hover:bg-blue-50 transition-colors"
                                            :class="bdClass(row.fingerprint_profit)" x-text="bdText(row.fingerprint_profit)"
                                            @click="openFingerprintBreakdown(row)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right cursor-pointer hover:bg-blue-50 transition-colors"
                                            :class="bdClass(row.passenger_profit_total)" x-text="bdText(row.passenger_profit_total)"
                                            @click="openPassengerProfitBreakdown(row)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700" x-text="formatCurrency(row.discount)"></td>
                                        <td class="px-4 py-3 text-sm text-right cursor-pointer hover:bg-blue-50 transition-colors"
                                            :class="row.total_profit >= 0 ? 'amount-profit' : 'amount-loss'"
                                            @click="openBreakdown(row)" x-text="formatProfitLoss(row.total_profit)"></td>
                                    </tr>
                                </template>
                                <template x-if="!loading && filteredCustomers.length > 0">
                                    <tr class="table-header font-bold">
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-gray-800" colspan="5">Grand Total</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800" x-text="formatCurrency(grandTotalCustomer.fingerprint_profit)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800" x-text="formatCurrency(grandTotalCustomer.passenger_profit_total)"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800" x-text="formatCurrency(grandTotalCustomer.discount)"></td>
                                        <td class="px-4 py-3 text-sm text-right" :class="grandTotalCustomer.total_profit >= 0 ? 'amount-profit' : 'amount-loss'" x-text="formatProfitLoss(grandTotalCustomer.total_profit)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div x-show="activeTab === 'passenger'" x-cloak class="animate-fade">
                    <div class="overflow-x-auto scrollbar-thin">
                        <table class="w-full min-w-[1000px] table-fixed">
                            <thead>
                                <tr class="table-header">
                                    <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Invoice ID</th>
                                    <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Customer Name</th>
                                    <th class="w-28 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Mobile</th>
                                    <th class="w-36 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Passenger Name</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Package Value (<span x-text="$store.currency.mode"></span>)</th>
                                    <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-right">Total Profit (<span x-text="$store.currency.mode"></span>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="loading">
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Loading...</td>
                                    </tr>
                                </template>
                                <template x-if="!loading && filteredPassengers.length === 0">
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No data found</td>
                                    </tr>
                                </template>
                                <template x-for="(row, index) in filteredPassengers" :key="index">
                                    <tr class="table-row">
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 font-medium text-gray-800" x-text="row.invoice_id"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-gray-800" x-text="row.customer_name"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center text-gray-600" x-text="row.mobile"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-gray-700" x-text="row.passenger_name"></td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700" x-text="formatCurrency(row.package_value)"></td>
                                        <td class="px-4 py-3 text-sm text-right cursor-pointer hover:bg-blue-50 transition-colors"
                                            :class="row.total_profit >= 0 ? 'amount-profit' : 'amount-loss'"
                                            @click="openBreakdown(row)" x-text="formatProfitLoss(row.total_profit)"></td>
                                    </tr>
                                </template>
                                <template x-if="!loading && filteredPassengers.length > 0">
                                    <tr class="table-header font-bold">
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-gray-800" colspan="4">Grand Total</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800" x-text="formatCurrency(grandTotalPassenger.package_value)"></td>
                                        <td class="px-4 py-3 text-sm text-right" :class="grandTotalPassenger.total_profit >= 0 ? 'amount-profit' : 'amount-loss'" x-text="formatProfitLoss(grandTotalPassenger.total_profit)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Profit Breakdown Modal -->
            <div x-show="breakdownModalOpen" x-cloak
                 class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 animate-fade"
                 @click.self="closeBreakdown()" @keydown.escape.window="closeBreakdown()">
                <div class="bg-white rounded-xl shadow-2xl w-[36rem] max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between bg-slate-800 text-white px-4 py-3 sticky top-0">
                        <span class="text-sm font-semibold">Profit Breakdown</span>
                        <button @click="closeBreakdown()" class="text-white/70 hover:text-white text-lg leading-none">&times;</button>
                    </div>

                    <!-- Customer breakdown -->
                    <template x-if="breakdownType === 'customer' && selectedBreakdown">
                        <div class="p-4 text-sm">
                            <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-200">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Per Customer</span>
                                <span class="text-xs text-gray-500" x-text="breakdownHeader"></span>
                            </div>

                            <div class="mb-2">
                                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Passengers</div>
                                <template x-for="(p, i) in (selectedBreakdown.passengers || [])" :key="i">
                                    <div class="flex justify-between py-1">
                                        <span class="text-gray-700" x-text="p.name"></span>
                                        <template x-if="p.effective">
                                            <span :class="bdClass(p.profit)" x-text="'profit = ' + bdText(p.profit)"></span>
                                        </template>
                                        <template x-if="!p.effective">
                                            <span class="italic text-gray-400">profit not effective</span>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <div class="flex justify-between py-1">
                                <span class="text-gray-700">Fingerprint Profit</span>
                                <template x-if="selectedBreakdown.fingerprint.effective">
                                    <span :class="bdClass(selectedBreakdown.fingerprint.profit)" x-text="bdText(selectedBreakdown.fingerprint.profit)"></span>
                                </template>
                                <template x-if="!selectedBreakdown.fingerprint.effective">
                                    <span class="italic text-gray-400">fingerprint profit not effective</span>
                                </template>
                            </div>

                            <div class="flex justify-between py-1">
                                <span class="text-gray-700">Discount</span>
                                <template x-if="selectedBreakdown.discount.effective">
                                    <span class="amount-loss" x-text="'-' + formatCurrency(Math.abs(Number(selectedBreakdown.discount.amount) || 0))"></span>
                                </template>
                                <template x-if="!selectedBreakdown.discount.effective">
                                    <span class="italic text-gray-400">discount not effective</span>
                                </template>
                            </div>

                            <div class="border-t mt-2 pt-2 flex justify-between font-bold">
                                <span>Total</span>
                                <span :class="Number(selectedBreakdown.total) >= 0 ? 'amount-profit' : 'amount-loss'" x-text="bdText(selectedBreakdown.total)"></span>
                            </div>
                        </div>
                    </template>

                    <!-- Passenger breakdown -->
                    <template x-if="breakdownType === 'passenger' && selectedBreakdown">
                        <div class="p-4 text-sm">
                            <!-- Visa Profit -->
                            <template x-if="selectedBreakdown.visa">
                                <div class="mb-3">
                                    <div class="flex justify-between py-1 font-semibold">
                                        <span>Visa Profit</span>
                                        <span :class="bdClass(selectedBreakdown.visa.profit)" x-text="bdText(selectedBreakdown.visa.profit)"></span>
                                    </div>
                                    <div class="pl-4 border-l-2 border-gray-200 text-gray-600">
                                        <div class="flex justify-between py-0.5"><span>Selling Price</span><span x-text="formatCurrency(selectedBreakdown.visa.selling_price)"></span></div>
                                        <div class="flex justify-between py-0.5"><span>Net Visa Cost</span><span class="amount-loss" x-text="'-' + formatCurrency(Math.abs(Number(selectedBreakdown.visa.net_visa_cost) || 0))"></span></div>
                                        <div class="flex justify-between py-0.5"><span>Agent Commission</span><span class="amount-loss" x-text="'-' + formatCurrency(Math.abs(Number(selectedBreakdown.visa.agent_commission) || 0))"></span></div>
                                        <div class="flex justify-between py-0.5"><span>Additional Cost</span><span class="amount-loss" x-text="'-' + formatCurrency(Math.abs(Number(selectedBreakdown.visa.additional_cost) || 0))"></span></div>
                                        <div class="flex justify-between py-0.5"><span>Cancellation Fees</span><span class="amount-loss" x-text="'-' + formatCurrency(Math.abs(Number(selectedBreakdown.visa.cancellation_fees) || 0))"></span></div>
                                    </div>
                                </div>
                            </template>

                            <!-- Ticket Profit -->
                            <template x-if="selectedBreakdown.ticket">
                                <div class="mb-3">
                                    <div class="flex justify-between py-1 font-semibold">
                                        <span>Ticket Profit</span>
                                        <span :class="bdClass(selectedBreakdown.ticket.profit)" x-text="bdText(selectedBreakdown.ticket.profit)"></span>
                                    </div>
                                    <div class="pl-4 border-l-2 border-gray-200 text-gray-600">
                                        <div class="flex justify-between py-0.5"><span>Selling Fare (Package)</span><span x-text="formatCurrency(selectedBreakdown.ticket.selling_fare)"></span></div>
                                        <template x-for="(nf, i) in (selectedBreakdown.ticket.net_fares || [])" :key="i">
                                            <div class="flex justify-between py-0.5">
                                                <span class="pl-3" x-text="'Net Fare · ' + nf.label"></span>
                                                <span class="amount-loss" x-text="'-' + formatCurrency(Math.abs(Number(nf.net_fare) || 0))"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Additional Ticket Profit -->
                            <template x-if="(selectedBreakdown.additional_tickets?.items || []).length > 0">
                                <div class="mb-3">
                                    <div class="flex justify-between py-1 font-semibold">
                                        <span>Additional Ticket</span>
                                        <span :class="bdClass(selectedBreakdown.additional_tickets.profit)" x-text="bdText(selectedBreakdown.additional_tickets.profit)"></span>
                                    </div>
                                    <div class="pl-4 border-l-2 border-gray-200 text-gray-600">
                                        <template x-for="(item, i) in (selectedBreakdown.additional_tickets.items || [])" :key="i">
                                            <div class="py-0.5">
                                                <div class="flex justify-between"><span x-text="'#' + (i + 1)"></span></div>
                                                <div class="flex justify-between pl-3"><span>Selling Fare</span><span x-text="formatCurrency(item.selling_fare)"></span></div>
                                                <div class="flex justify-between pl-3"><span>Net Fare</span><span class="amount-loss" x-text="'-' + formatCurrency(Math.abs(Number(item.net_fare) || 0))"></span></div>
                                                <div class="flex justify-between pl-3 font-medium" :class="bdClass(item.profit)"><span>Profit</span><span x-text="bdText(item.profit)"></span></div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Other single-line values -->
                            <div class="flex justify-between py-1"><span>Re-Issue Profit</span><span :class="bdClass(selectedBreakdown.re_issue_profit)" x-text="bdText(selectedBreakdown.re_issue_profit)"></span></div>
                            <div class="flex justify-between py-1"><span>Refund Profit</span><span :class="bdClass(selectedBreakdown.refund_profit)" x-text="bdText(selectedBreakdown.refund_profit)"></span></div>
                            <div class="flex justify-between py-1 text-red-600"><span>Re-Issue Cost</span><span x-text="'-' + formatCurrency(Math.abs(Number(selectedBreakdown.re_issue_cost) || 0))"></span></div>
                            <div class="flex justify-between py-1"><span>Service Charge</span><span :class="bdClass(selectedBreakdown.service_charge)" x-text="bdText(selectedBreakdown.service_charge)"></span></div>
                            <div class="border-t mt-2 pt-2 flex justify-between font-bold">
                                <span>Total</span>
                                <span :class="Number(selectedBreakdown.total) >= 0 ? 'amount-profit' : 'amount-loss'" x-text="bdText(selectedBreakdown.total)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Fingerprint Profit Modal -->
            <div x-show="fingerprintModalOpen" x-cloak
                 class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 animate-fade"
                 @click.self="closeFingerprintBreakdown()" @keydown.escape.window="closeFingerprintBreakdown()">
                <div class="bg-white rounded-xl shadow-2xl w-[28rem] max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between bg-slate-800 text-white px-4 py-3 sticky top-0">
                        <span class="text-sm font-semibold">Fingerprint Profit</span>
                        <button @click="closeFingerprintBreakdown()" class="text-white/70 hover:text-white text-lg leading-none">&times;</button>
                    </div>

                    <template x-if="selectedFingerprint">
                        <div class="p-4 text-sm">
                            <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-200">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Fingerprint Profit</span>
                                <span class="text-xs text-gray-500" x-text="fingerprintHeader"></span>
                            </div>

                            <div class="flex justify-between py-1">
                                <span class="text-gray-700">Fingerprint Location</span>
                                <span class="capitalize" x-text="selectedFingerprint.location || '—'"></span>
                            </div>

                            <template x-if="selectedFingerprint.effective">
                                <div>
                                    <div class="flex justify-between py-1">
                                        <span class="text-gray-700">Fingerprint Charge</span>
                                        <span x-text="formatCurrency(selectedFingerprint.charge)"></span>
                                    </div>
                                    <div class="flex justify-between py-1">
                                        <span class="text-gray-700">Fingerprint Cost</span>
                                        <span x-text="formatCurrency(selectedFingerprint.cost)"></span>
                                    </div>
                                    <div class="border-t mt-2 pt-2 flex justify-between font-bold">
                                        <span>Profit</span>
                                        <span :class="bdClass(selectedFingerprint.profit)" x-text="bdText(selectedFingerprint.profit)"></span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="!selectedFingerprint.effective">
                                <div>
                                    <div class="border-t mt-2 pt-2">
                                        <div class="italic text-gray-400 mb-1">profit not effective</div>
                                        <div class="italic text-gray-400 text-xs" x-text="selectedFingerprint.reason"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Passenger Profit Modal -->
            <div x-show="passengerProfitModalOpen" x-cloak
                 class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 animate-fade"
                 @click.self="closePassengerProfitBreakdown()" @keydown.escape.window="closePassengerProfitBreakdown()">
                <div class="bg-white rounded-xl shadow-2xl w-[28rem] max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between bg-slate-800 text-white px-4 py-3 sticky top-0">
                        <span class="text-sm font-semibold">Passenger Profit</span>
                        <button @click="closePassengerProfitBreakdown()" class="text-white/70 hover:text-white text-lg leading-none">&times;</button>
                    </div>

                    <template x-if="selectedPassengerProfit">
                        <div class="p-4 text-sm">
                            <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-200">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Passenger Profit</span>
                                <span class="text-xs text-gray-500" x-text="passengerProfitHeader"></span>
                            </div>

                            <template x-for="(p, i) in (selectedPassengerProfit.passengers || [])" :key="i">
                                <div class="flex justify-between py-1">
                                    <span class="text-gray-700" x-text="p.name"></span>
                                    <template x-if="p.effective">
                                        <span :class="bdClass(p.profit)" x-text="bdText(p.profit)"></span>
                                    </template>
                                    <template x-if="!p.effective">
                                        <span class="italic text-gray-400">profit not effective</span>
                                    </template>
                                </div>
                            </template>

                            <div class="border-t mt-2 pt-2 flex justify-between font-bold">
                                <span>Total Passenger Profit</span>
                                <span :class="bdClass(selectedPassengerProfit.passenger_profit_total)" x-text="bdText(selectedPassengerProfit.passenger_profit_total)"></span>
                            </div>
                        </div>
                    </template>
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
        profitLossFilter: 'all',
        activeTab: 'customer',
        loading: false,
        customers: [],
        passengers: [],
        breakdownModalOpen: false,
        selectedBreakdown: null,
        breakdownType: null,
        breakdownHeader: '',
        fingerprintModalOpen: false,
        selectedFingerprint: null,
        fingerprintHeader: '',
        passengerProfitModalOpen: false,
        selectedPassengerProfit: null,
        passengerProfitHeader: '',

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
            let rows = this.customers;
            if (this.search) {
                const q = this.search.toLowerCase();
                rows = rows.filter(r =>
                    (r.invoice_id && r.invoice_id.toLowerCase().includes(q)) ||
                    (r.customer_name && r.customer_name.toLowerCase().includes(q)) ||
                    (r.customer_passport && r.customer_passport.toLowerCase().includes(q)) ||
                    (r.customer_iqama && r.customer_iqama.toLowerCase().includes(q))
                );
            }
            return this.applyProfitLoss(rows);
        },

        get filteredPassengers() {
            let rows = this.passengers;
            if (this.search) {
                const q = this.search.toLowerCase();
                rows = rows.filter(r =>
                    (r.invoice_id && r.invoice_id.toLowerCase().includes(q)) ||
                    (r.customer_name && r.customer_name.toLowerCase().includes(q)) ||
                    (r.passenger_name && r.passenger_name.toLowerCase().includes(q)) ||
                    (r.passenger_passport && r.passenger_passport.toLowerCase().includes(q)) ||
                    (r.customer_passport && r.customer_passport.toLowerCase().includes(q)) ||
                    (r.customer_iqama && r.customer_iqama.toLowerCase().includes(q))
                );
            }
            return this.applyProfitLoss(rows);
        },

        applyProfitLoss(rows) {
            if (this.profitLossFilter === 'profit') {
                return rows.filter(r => (Number(r.total_profit) || 0) >= 0);
            }
            if (this.profitLossFilter === 'loss') {
                return rows.filter(r => (Number(r.total_profit) || 0) < 0);
            }
            return rows;
        },

        get grandTotalCustomer() {
            return this.filteredCustomers.reduce((acc, r) => {
                acc.package_value += Number(r.package_value) || 0;
                acc.fingerprint_profit += Number(r.fingerprint_profit) || 0;
                acc.passenger_profit_total += Number(r.passenger_profit_total) || 0;
                acc.discount += Number(r.discount) || 0;
                acc.total_profit += Number(r.total_profit) || 0;
                return acc;
            }, { package_value: 0, fingerprint_profit: 0, passenger_profit_total: 0, discount: 0, total_profit: 0 });
        },

        get grandTotalPassenger() {
            return this.filteredPassengers.reduce((acc, r) => {
                acc.package_value += Number(r.package_value) || 0;
                acc.total_profit += Number(r.total_profit) || 0;
                return acc;
            }, { package_value: 0, total_profit: 0 });
        },

        openBreakdown(row) {
            if (!row?.breakdown) return;
            this.selectedBreakdown = row.breakdown;
            this.breakdownType = row.breakdown.passengers ? 'customer' : 'passenger';
            this.breakdownHeader = row.customer_name ? (row.invoice_id + ' · ' + row.customer_name) : '';
            this.breakdownModalOpen = true;
        },

        closeBreakdown() {
            this.breakdownModalOpen = false;
            this.selectedBreakdown = null;
            this.breakdownType = null;
            this.breakdownHeader = '';
        },

        openFingerprintBreakdown(row) {
            if (!row?.breakdown) return;
            this.selectedFingerprint = row.breakdown.fingerprint;
            this.fingerprintHeader = row.customer_name ? (row.invoice_id + ' · ' + row.customer_name) : '';
            this.fingerprintModalOpen = true;
        },

        closeFingerprintBreakdown() {
            this.fingerprintModalOpen = false;
            this.selectedFingerprint = null;
            this.fingerprintHeader = '';
        },

        openPassengerProfitBreakdown(row) {
            if (!row?.breakdown) return;
            this.selectedPassengerProfit = {
                passengers: row.breakdown.passengers || [],
                passenger_profit_total: row.passenger_profit_total,
            };
            this.passengerProfitHeader = row.customer_name ? (row.invoice_id + ' · ' + row.customer_name) : '';
            this.passengerProfitModalOpen = true;
        },

        closePassengerProfitBreakdown() {
            this.passengerProfitModalOpen = false;
            this.selectedPassengerProfit = null;
            this.passengerProfitHeader = '';
        },

        bdClass(v) {
            return (Number(v) || 0) >= 0 ? 'amount-profit' : 'amount-loss';
        },

        bdText(v) {
            return this.formatProfitLoss(Number(v) || 0);
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
