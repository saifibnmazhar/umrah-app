<div x-data x-init="$store.currency.convertAll()" x-cloak>
    <div class="max-w-[1600px] mx-auto p-4">
        <div class="mb-3">
            <span class="text-sm text-gray-500 font-medium">Report</span>
            <span class="text-sm text-gray-400 mx-1">></span>
            <span class="text-sm text-gray-700 font-semibold">Visa Agent Report</span>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm no-print">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">SEARCH BOX</label>
                    <input type="text" wire:model.live="search" placeholder="Search by Agent Name"
                           class="search-input w-80 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">Visa Agent</label>
                    <select wire:model.live="visaAgentId" class="w-56 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300 bg-white">
                        <option value="">All Agents</option>
                        @foreach($visaAgents as $agent)
                            <option value="{{ $agent['id'] }}">{{ $agent['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">Date</label>
                    <label class="text-xs text-gray-500">From</label>
                    <input type="date" wire:model.live="dateFrom" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <label class="text-xs text-gray-500">To</label>
                    <input type="date" wire:model.live="dateTo" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <div class="flex items-center gap-2 ml-auto no-print">
                    <button wire:click="resetFilters()" class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="print-area">
            <div class="print-heading">
                <h1>Visa Agent Report</h1>
                <p class="sub">
                    {{ $dateFrom ?: '...' }} – {{ $dateTo ?: '...' }}
                </p>
                <hr>
            </div>
            <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1400px] table-fixed">
                        <thead>
                            <tr class="table-header">
                                <th class="w-56 px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Agent Name</th>
                                <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Submitted</th>
                                <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Issued</th>
                                <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Price<br>(Max/Min/Avg)</th>
                                <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                                <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                                <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Balance</th>
                                <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Cancellation<br>Fee</th>
                                <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData as $agent)
                                <tr class="table-row-agent">
                                    <td class="px-2 py-3 text-sm text-left border-r border-gray-200 font-medium text-gray-800">{{ $agent['name'] }}</td>
                                    <td class="px-2 py-3 text-sm text-center border-r border-gray-200 font-medium">{{ $agent['totalSubmitted'] }}</td>
                                    <td class="px-2 py-3 text-sm text-center border-r border-gray-200 font-medium">{{ $agent['totalIssued'] }}</td>
                                    <td class="px-2 py-3 text-xs text-center border-r border-gray-200 whitespace-nowrap">
                                        @if($agent['price']['max'] > 0)
                                            <span>
                                                <span class="text-red-600">@currency($agent['price']['max'], 2)</span>
                                                <span class="text-gray-400"> / </span>
                                                <span class="text-green-600">@currency($agent['price']['min'], 2)</span>
                                                <span class="text-gray-400"> / </span>
                                                <span class="text-blue-600">@currency($agent['price']['avg'], 2)</span>
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3 text-sm text-right border-r border-gray-200 font-medium">@currency($agent['payable'], 2)</td>
                                    <td class="px-2 py-3 text-sm text-right border-r border-gray-200"
                                        @class(['text-green-700' => $agent['paid'] > 0, 'text-gray-600' => $agent['paid'] <= 0])>
                                        @currency($agent['paid'], 2)
                                    </td>
                                    <td class="px-2 py-3 text-sm text-right border-r border-gray-200 font-semibold"
                                        @class([
                                            'text-green-700' => $agent['balance'] > 0,
                                            'text-red-600' => $agent['balance'] < 0,
                                            'text-gray-600' => $agent['balance'] == 0,
                                        ])>
                                        {{ $agent['balance'] > 0 ? '+' : ($agent['balance'] < 0 ? '-' : '') }}@currency(abs($agent['balance']), 2)
                                    </td>
                                    <td class="px-2 py-3 text-sm text-right border-r border-gray-200"
                                        @class(['text-red-600' => $agent['cancellationFee'] > 0, 'text-gray-600' => $agent['cancellationFee'] <= 0])>
                                        {{ $agent['cancellationFee'] > 0 ? '' : '-' }}@currency($agent['cancellationFee'], 2)
                                    </td>
                                    <td class="px-2 py-3 text-center whitespace-nowrap">
                                        <button wire:click="$dispatch('openAgentDetails', agentId: {{ $agent['id'] }})"
                                                class="view-btn text-white px-3 py-1 rounded text-xs font-medium transition-all">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">No data found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mt-0">
                <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center no-print">
                    <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
                </div>
            </div>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mt-0">
            <div class="flex flex-wrap gap-6">
                <div class="footer-box rounded-lg overflow-hidden min-w-[320px]">
                    <div class="footer-box-header px-4 py-2">
                        <span class="text-sm font-bold text-gray-700">Visa Agent Report Summary</span>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-x-8 gap-y-2">
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Agents:</span>
                                <span class="text-xs font-bold text-gray-800">{{ $summary['totalAgents'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Agents with Due:</span>
                                <span class="text-xs font-bold text-red-700">{{ $summary['agentsWithDue'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Payable:</span>
                                <span class="text-xs font-bold text-gray-800">@currency($summary['totalPayable'], 2)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Paid:</span>
                                <span class="text-xs font-bold text-green-700">@currency($summary['totalPaid'], 2)</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 mt-1">
                                <span class="text-xs font-bold text-gray-700">Total Balance:</span>
                                <span class="text-xs font-bold"
                                    @class(['text-green-700' => $summary['totalBalance'] > 0, 'text-red-700' => $summary['totalBalance'] < 0])>
                                    {{ $summary['totalBalance'] > 0 ? '+' : ($summary['totalBalance'] < 0 ? '-' : '') }}@currency(abs($summary['totalBalance']), 2)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center no-print">
                <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
            </div>
        </div>
    </div>
</div>
