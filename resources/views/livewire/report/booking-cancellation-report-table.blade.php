<div x-init="$store.currency.convertAll()" x-cloak>
    <div class="w-full mx-auto pt-6">
        <div class="sticky top-0 z-30 bg-white py-2 mb-3">
            <span class="text-sm text-gray-500 font-medium">Report</span>
            <span class="text-sm text-gray-400 mx-1">></span>
            <span class="text-sm text-gray-700 font-semibold">Booking Cancellation Report</span>
        </div>

        <div class="sticky top-[40px] z-20 bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700 whitespace-nowrap">Search</label>
                    <input type="text" wire:model.live="search"
                           placeholder="Invoice ID, Customer, Mobile"
                           class="w-72 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Cancel Date</span>
                    <label class="text-xs text-gray-500">From</label>
                    <input type="date" wire:model.live="dateFrom"
                           class="w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                    <label class="text-xs text-gray-500">To</label>
                    <input type="date" wire:model.live="dateTo"
                           class="w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Branch</span>
                    <select wire:model.live="branchId"
                            class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Status</span>
                    <select wire:model.live="status"
                            class="w-40 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">All</option>
                        <option value="cancellation processing">Processing</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button wire:click="resetFilters()"
                            class="px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1 border border-gray-300 bg-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 11-15.357-2m15.357 2H15"/>
                        </svg>
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 shadow-sm flex flex-col">
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
                        @if(empty($data))
                            <tr>
                                <td colspan="14" class="px-3 py-8 text-center text-slate-500">No cancellation records found</td>
                            </tr>
                        @else
                            @foreach($data as $idx => $row)
                                <tr class="hover:bg-slate-50" @class(['bg-white' => $idx % 2 === 0, 'bg-slate-50/50' => $idx % 2 !== 0])>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200">{{ $row['invoice_id'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200">{{ $row['customer_name'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200">{{ $row['mobile'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200">{{ $row['booking_branch'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200">{{ $row['cancellation_branch'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200 text-right font-medium">@currency($row['total_paid'] ?? 0, 2)</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200 text-right">
                                        @if($row['service_charge_deduction'] !== null)
                                            @currency($row['service_charge_deduction'], 2)
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200 text-right font-semibold text-blue-700">@currency($row['refund_amount'] ?? 0, 2)</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200 text-center">{{ $row['method'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200 max-w-[200px] truncate" :title="$row['remarks'] ?? ''">{{ $row['remarks'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200">{{ $row['cancelled_at'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200">{{ $row['refunded_at'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs border-r border-gray-200">{{ $row['refunded_by'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                              @class([
                                                  'bg-red-100 text-red-800' => ($row['status'] ?? '') === 'cancelled',
                                                  'bg-yellow-100 text-yellow-800' => ($row['status'] ?? '') !== 'cancelled',
                                              ])>
                                            {{ ($row['status'] ?? '') === 'cancelled' ? 'Cancelled' : 'Processing' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            @if($pagination['last_page'] > 1)
                <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200">
                    <span class="text-xs text-gray-600">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }} ({{ $pagination['total'] }} records)
                    </span>
                    <div class="flex gap-2">
                        <button wire:click="goToPage({{ $pagination['current_page'] - 1 }})"
                                @if($pagination['current_page'] <= 1) disabled @endif
                                class="px-3 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">
                            Previous
                        </button>
                        <button wire:click="goToPage({{ $pagination['current_page'] + 1 }})"
                                @if($pagination['current_page'] >= $pagination['last_page']) disabled @endif
                                class="px-3 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">
                            Next
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mt-0">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Paid</p>
                    <p class="text-xl font-bold text-gray-800 mt-1">@currency($summary['total_paid'] ?? 0, 2)</p>
                </div>
                <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Deduction</p>
                    <p class="text-xl font-bold text-red-700 mt-1">@currency($summary['total_deduction'] ?? 0, 2)</p>
                </div>
                <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Refund</p>
                    <p class="text-xl font-bold text-blue-700 mt-1">@currency($summary['total_refund'] ?? 0, 2)</p>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center no-print">
                <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
            </div>
        </div>
    </div>
</div>
