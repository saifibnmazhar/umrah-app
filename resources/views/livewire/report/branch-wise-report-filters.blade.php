<div>
    <form wire:submit.prevent="refreshReport" class="flex flex-wrap items-end gap-4 mb-6 bg-white rounded-lg border border-slate-200 shadow-sm p-4">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Date From</label>
            <input type="date" wire:model.live="dateFrom"
                   class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Date To</label>
            <input type="date" wire:model.live="dateTo"
                   class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none">
        </div>
        @if(!$userBranchId)
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Branch</label>
            <select wire:model.live="branchId" class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                <option value="">All Branches</option>
                <option value="central">Central</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="flex items-end">
            <a href="{{ route('report.branch-wise') }}" class="px-4 py-2 bg-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-300 transition">Reset</a>
        </div>
    </form>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] table-fixed">
                <thead>
                    <tr class="table-header">
                        <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Date</th>
                        <th class="w-40 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Cash Received</th>
                        <th class="w-40 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Bank Received</th>
                        <th class="w-44 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">BD Office Collection</th>
                        <th class="w-44 px-4 py-3 text-sm font-bold text-gray-700 text-right">KSA Office Collection</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dailyPayments as $day)
                    <tr class="even:bg-[#fafafa] hover:bg-[#e8f4fc] cursor-pointer" @click="openModal('{{ $day['date'] }}')">
                        <td class="px-4 py-3 text-sm text-center border-r border-gray-200 font-medium text-gray-700">{{ date('d-M-Y', strtotime($day['date'])) }}</td>
                        <td class="px-4 py-3 text-sm text-right border-r border-gray-200 {{ $day['cash'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['cash'], 2)</td>
                        <td class="px-4 py-3 text-sm text-right border-r border-gray-200 {{ $day['bank'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['bank'], 2)</td>
                        <td class="px-4 py-3 text-sm text-right border-r border-gray-200 {{ $day['bd_office'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['bd_office'], 2)</td>
                        <td class="px-4 py-3 text-sm text-right {{ $day['ksa_office'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['ksa_office'], 2)</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No records found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
