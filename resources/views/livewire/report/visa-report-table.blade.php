<div x-data x-init="$store.currency.convertAll()" x-cloak>
    <div class="max-w-[1600px] mx-auto p-4">
        <div class="mb-3">
            <span class="text-sm text-gray-500 font-medium">Report</span>
            <span class="text-sm text-gray-400 mx-1">></span>
            <span class="text-sm text-gray-700 font-semibold">Visa Sales Report</span>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">SEARCH BOX</label>
                    <input type="text" wire:model.live="search" placeholder="Search by Invoice No, Customer Name, PAX Name, Mobile, Visa Number"
                           class="search-input w-96 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Visa Submit Date</span>
                    <label class="text-xs text-gray-500">From</label>
                    <input type="date" wire:model.live="visaSubmitDateFrom" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <label class="text-xs text-gray-500">To</label>
                    <input type="date" wire:model.live="visaSubmitDateTo" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Flight Date</span>
                    <label class="text-xs text-gray-500">From</label>
                    <input type="date" wire:model.live="flightDateFrom" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <label class="text-xs text-gray-500">To</label>
                    <input type="date" wire:model.live="flightDateTo" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Visa Status</span>
                    <select wire:model.live="status" class="search-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="submitted">Processing</option>
                        <option value="issued">Approved</option>
                        <option value="cancelled">Rejected</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button wire:click="resetFilters()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
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
                    <tbody>
                        @forelse($data as $row)
                            <tr class="table-row-visa">
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium">{{ $row['invoice_no'] }}</td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">
                                    <div>{{ $row['customer_name'] }}</div>
                                    <div class="text-[11px] font-semibold text-gray-700">{{ $row['customer_iqama'] }}</div>
                                </td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">
                                    <div>{{ $row['pax_name'] }}</div>
                                    <div class="text-[11px] font-semibold text-gray-700">{{ $row['pax_passport'] }}</div>
                                </td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">
                                    <div>P: {{ $row['mobile'] }}</div>
                                    <div class="text-[11px] font-semibold text-gray-700">C: {{ $row['customer_mobile'] }}</div>
                                </td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">{{ $row['visa_submit_date'] }}</td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">
                                    <span class="status-badge
                                        @class([
                                            'status-pending' => $row['visa_status'] === 'pending',
                                            'status-processing' => $row['visa_status'] === 'submitted',
                                            'status-approved' => $row['visa_status'] === 'issued',
                                            'status-rejected' => $row['visa_status'] === 'cancelled',
                                        ])">
                                        @php
                                            $statusLabels = [
                                                'pending' => 'Pending',
                                                'submitted' => 'Processing',
                                                'issued' => 'Approved',
                                                'cancelled' => 'Rejected',
                                            ];
                                        @endphp
                                        {{ $statusLabels[$row['visa_status']] ?? $row['visa_status'] }}
                                    </span>
                                </td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">{{ $row['flight_date'] }}</td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">{{ $row['visa_number'] }}</td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">{{ $row['visa_agent'] }}</td>
                                <td class="px-2 py-2 text-xs text-right border-r border-gray-200 font-medium">@currency($row['agent_cost'], 2, $row['rate'])</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-slate-500">No visa records found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($lastPage > 1)
            <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Showing <span class="font-medium">{{ max(0, ($currentPage - 1) * $perPage) + 1 }}</span>
                        to <span class="font-medium">{{ min($currentPage * $perPage, $totalRecords) }}</span>
                        of <span class="font-medium">{{ $totalRecords }}</span> results
                    </div>
                    <nav class="flex items-center gap-1">
                        <button wire:click="changePage({{ $currentPage - 1 }})"
                                @if($currentPage === 1) disabled @endif
                                class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors @if($currentPage === 1) text-slate-300 cursor-not-allowed @else text-slate-600 hover:bg-slate-100 @endif">
                            Previous
                        </button>
                        @foreach($paginationPages as $page)
                            <button wire:click="changePage({{ $page }})"
                                    class="px-3 py-1.5 text-sm font-medium rounded-lg border bg-white transition-colors @if($page === $currentPage) bg-slate-700 text-white border-slate-700 @else text-slate-600 hover:bg-slate-100 border-slate-300 @endif">
                                {{ $page }}
                            </button>
                        @endforeach
                        <button wire:click="changePage({{ $currentPage + 1 }})"
                                @if($currentPage === $lastPage) disabled @endif
                                class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors @if($currentPage === $lastPage) text-slate-300 cursor-not-allowed @else text-slate-600 hover:bg-slate-100 @endif">
                            Next
                        </button>
                    </nav>
                </div>
            </div>
        @endif

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
                                <span class="text-xs font-bold text-gray-800">{{ $summary['total_records'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Invoices:</span>
                                <span class="text-xs font-bold text-gray-800">{{ $summary['total_invoices'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Agent Cost:</span>
                                <span class="text-xs font-bold text-gray-800">@currency($summary['total_agent_cost'])</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 mt-1">
                                <span class="text-xs text-gray-600">Pending:</span>
                                <span class="text-xs font-bold text-yellow-700">{{ $summary['pending'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Processing:</span>
                                <span class="text-xs font-bold text-blue-700">{{ $summary['submitted'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Approved:</span>
                                <span class="text-xs font-bold text-green-700">{{ $summary['issued'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Rejected:</span>
                                <span class="text-xs font-bold text-red-700">{{ $summary['cancelled'] }}</span>
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
</div>
