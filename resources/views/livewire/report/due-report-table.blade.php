<div>
    <div class="max-w-[1600px] mx-auto p-4">
        <div class="mb-3">
            <span class="text-sm text-gray-500 font-medium">Reports</span>
            <span class="text-sm text-gray-400 mx-1">></span>
            <span class="text-sm text-gray-700 font-semibold">Due Report</span>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mb-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Date From</label>
                    <input type="date" wire:model.live="dateFrom"
                           class="date-input w-full px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Date To</label>
                    <input type="date" wire:model.live="dateTo"
                           class="date-input w-full px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] table-fixed">
                    <thead>
                        <tr class="table-header">
                            <th class="w-1/2 px-4 py-3 text-sm font-bold text-gray-700 text-left border-r border-gray-300">Branch Name</th>
                            <th class="w-1/4 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Total Due</th>
                            <th class="w-1/4 px-4 py-3 text-sm font-bold text-gray-700 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branches as $branch)
                            <tr class="table-row-due">
                                <td class="px-4 py-3 text-sm border-r border-gray-200 font-medium text-gray-800">{{ $branch['name'] }}</td>
                                <td class="px-4 py-3 text-sm border-r border-gray-200 text-right font-semibold amount-due">{{ number_format($branch['totalDue'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <button wire:click="$dispatch('openBranchDetails', {branchId: {{ $branch['id'] }}, branchName: '{{ $branch['name'] }}', dateFrom: '{{ $dateFrom }}', dateTo: '{{ $dateTo }}'}})" class="btn-view inline-block">View</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">No due data found</td>
                            </tr>
                        @endforelse

                        @if($branches->isNotEmpty())
                            <tr class="table-header font-bold">
                                <td class="px-4 py-3 text-sm border-r border-gray-300 text-gray-800" colspan="2">Grand Total</td>
                                <td class="px-4 py-3 text-sm text-center"></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
