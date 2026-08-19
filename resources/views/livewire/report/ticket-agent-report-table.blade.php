<div x-init="$store.currency.convertAll()" x-cloak>
    <div class="max-w-[1600px] mx-auto p-4">
        <div class="mb-3">
            <span class="text-sm text-gray-500 font-medium">Report</span>
            <span class="text-sm text-gray-400 mx-1">></span>
            <span class="text-sm text-gray-700 font-semibold">Ticket Agent Report</span>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">Date</label>
                    <label class="text-xs text-gray-500">From</label>
                    <input type="date" wire:model.live="dateFrom" class="search-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <label class="text-xs text-gray-500">To</label>
                    <input type="date" wire:model.live="dateTo" class="search-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">Ticket Agent</label>
                    <select wire:model.live="agentId" class="w-56 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">All Agents</option>
                        @foreach($agentsList as $agent)
                            <option value="{{ $agent['id'] }}">{{ $agent['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] table-fixed">
                    <thead>
                        <tr class="table-header">
                            <th class="w-56 px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Agent Name</th>
                            <th class="w-32 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                            <th class="w-32 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                            <th class="w-32 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Balance</th>
                            <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData as $agent)
                            <tr class="table-row-agent">
                                <td class="px-4 py-3 text-sm text-left border-r border-gray-200 font-medium text-gray-800">{{ $agent['name'] }}</td>
                                <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-medium">@currency($agent['payable'], 2)</td>
                                <td class="px-4 py-3 text-sm text-right border-r border-gray-200"
                                    @class(['text-green-700' => $agent['paid'] > 0, 'text-gray-600' => $agent['paid'] <= 0])>
                                    @currency($agent['paid'], 2)
                                </td>
                                <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-semibold"
                                    @class([
                                        'text-green-700' => $agent['due'] > 0,
                                        'text-red-600' => $agent['due'] < 0,
                                        'text-gray-600' => $agent['due'] == 0,
                                    ])>
                                    {{ $agent['due'] > 0 ? '+' : ($agent['due'] < 0 ? '-' : '') }}@currency(abs($agent['due']), 2)
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button wire:click="$dispatch('openAgentDetails', { agent: @json($agent) })"
                                            class="view-btn text-white px-4 py-1.5 rounded text-xs font-medium transition-all"
                                            wire:ignore>
                                        View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-sm text-center text-gray-500">No agents found matching your criteria.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
            <div class="flex flex-wrap gap-6">
                <div class="footer-box rounded-lg overflow-hidden min-w-[240px]">
                    <div class="footer-box-header px-4 py-2">
                        <span class="text-sm font-bold text-gray-700">Agent Stats</span>
                    </div>
                    <div class="p-4">
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Agents:</span>
                                <span class="text-xs font-bold text-gray-800">{{ $summary['totalAgents'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Agents with Due:</span>
                                <span class="text-xs font-bold text-red-700">{{ $summary['agentsWithDue'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer-box rounded-lg overflow-hidden min-w-[240px]">
                    <div class="footer-box-header px-4 py-2">
                        <span class="text-sm font-bold text-gray-700">Payment Summary</span>
                    </div>
                    <div class="p-4">
                        <div class="flex flex-col gap-2">
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
                                    @class(['text-green-700' => $summary['totalDue'] > 0, 'text-red-700' => $summary['totalDue'] < 0, 'text-gray-700' => $summary['totalDue'] == 0])>
                                    {{ $summary['totalDue'] > 0 ? '+' : ($summary['totalDue'] < 0 ? '-' : '') }}@currency(abs($summary['totalDue']), 2)
                                </span>
                            </div>
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
