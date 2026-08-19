<div x-data="profitLossCurrency()" x-init="$store.currency.convertAll()">
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
                        <input type="date" wire:model.live="dateFrom"
                               class="date-input w-44 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">To:</label>
                        <input type="date" wire:model.live="dateTo"
                               class="date-input w-44 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model.live="search"
                               placeholder="Search by Invoice ID, Customer Name, Passenger Name, Passport, Iqama"
                               class="search-input w-72 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 rounded-xl shadow-md overflow-hidden">
            <div class="border-b border-gray-300 bg-gray-50 px-4 pt-3">
                <div class="flex items-center justify-between">
                    <div class="flex gap-0" id="tabButtons">
                        <button wire:click="$set('activeTab', 'customer')"
                                class="px-6 py-3 rounded-t-md text-sm font-medium text-gray-600"
                                :class="activeTab === 'customer' ? 'tab-btn active' : 'tab-btn'">
                            Per Customer
                        </button>
                        <button wire:click="$set('activeTab', 'passenger')"
                                class="px-6 py-3 rounded-t-md text-sm font-medium text-gray-600"
                                :class="activeTab === 'passenger' ? 'tab-btn active' : 'tab-btn'">
                            Per Passenger
                        </button>
                    </div>
                    <div class="flex items-center gap-2 pr-1 pb-3">
                        <a :href="`/reports/profit-loss/print?date_from={{ $dateFrom }}&date_to={{ $dateTo }}&type=customer&currency=` + $store.currency.mode" target="_blank"
                           class="px-3 py-1.5 rounded-md text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 border border-blue-700">Customer Print</a>
                        <a :href="`/reports/profit-loss/print?date_from={{ $dateFrom }}&date_to={{ $dateTo }}&type=passenger&currency=` + $store.currency.mode" target="_blank"
                           class="px-3 py-1.5 rounded-md text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 border border-emerald-700">Passenger Print</a>
                    </div>
                </div>
            </div>

            <div class="p-4">
                @if($activeTab === 'customer')
                <div x-show="true" class="animate-fade">
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
                                @forelse($customers as $row)
                                    <tr class="table-row">
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 font-medium text-gray-800">{{ $row['invoice_id'] }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-gray-800">{{ $row['customer_name'] }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center text-gray-600">{{ $row['mobile'] }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center font-medium text-gray-700">{{ $row['pax_qty'] }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700">{{ number_format($row['package_value'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700">{{ number_format($row['total_cost'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right {{ $row['profit'] >= 0 ? 'amount-profit' : 'amount-loss' }}">
                                            {{ $row['profit'] >= 0 ? '+' : '' }}{{ number_format($row['profit'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No data found</td>
                                    </tr>
                                @endforelse
                                @if($customers->isNotEmpty())
                                    <tr class="table-header font-bold">
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-gray-800" colspan="4">Grand Total</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800">{{ number_format($grandTotalCustomer['package_value'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800">{{ number_format($grandTotalCustomer['total_cost'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right {{ $grandTotalCustomer['profit'] >= 0 ? 'amount-profit' : 'amount-loss' }}">
                                            {{ $grandTotalCustomer['profit'] >= 0 ? '+' : '' }}{{ number_format($grandTotalCustomer['profit'], 2) }}
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                @else
                <div x-show="true" class="animate-fade">
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
                                @forelse($passengers as $row)
                                    <tr class="table-row">
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 font-medium text-gray-800">{{ $row['invoice_id'] }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-gray-800">{{ $row['customer_name'] }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-center text-gray-600">{{ $row['mobile'] }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-gray-700">{{ $row['passenger_name'] }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700">{{ number_format($row['package_value'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-medium text-gray-700">{{ number_format($row['total_cost'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right {{ $row['profit'] >= 0 ? 'amount-profit' : 'amount-loss' }}">
                                            {{ $row['profit'] >= 0 ? '+' : '' }}{{ number_format($row['profit'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No data found</td>
                                    </tr>
                                @endforelse
                                @if($passengers->isNotEmpty())
                                    <tr class="table-header font-bold">
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-gray-800" colspan="4">Grand Total</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800">{{ number_format($grandTotalPassenger['package_value'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm border-r border-gray-300 text-right text-gray-800">{{ number_format($grandTotalPassenger['total_cost'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right {{ $grandTotalPassenger['profit'] >= 0 ? 'amount-profit' : 'amount-loss' }}">
                                            {{ $grandTotalPassenger['profit'] >= 0 ? '+' : '' }}{{ number_format($grandTotalPassenger['profit'], 2) }}
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
