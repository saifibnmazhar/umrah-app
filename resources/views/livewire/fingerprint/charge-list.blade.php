<div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
    <div class="flex items-end justify-between mb-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Division</label>
            <select wire:model.live="divisionFilter" class="w-64 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                <option value="">Select Division</option>
                @foreach($divisions as $division)
                <option value="{{ $division }}">{{ $division }}</option>
                @endforeach
            </select>
        </div>
        @if($isAdmin)
        <button type="button" onclick="showFingerprintChargeModal()" id="addChargeBtn" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Fingerprint Charge
        </button>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2 text-left font-medium">Division</th>
                    <th class="px-3 py-2 text-left font-medium">District</th>
                    <th class="px-3 py-2 text-right font-medium">Charge (<span x-text="$store.currency.mode">SAR</span>)</th>
                    @if($isAdmin)
                    <th class="px-3 py-2 text-center font-medium">Action</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($fingerprintCharges as $charge)
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 text-slate-600">{{ $charge->district->division ?? 'N/A' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ $charge->district->name ?? 'N/A' }}</td>
                    <td class="px-3 py-2 text-right text-slate-800 font-medium">@currency($charge->fingerprint_charge, 2)</td>
                    @if($isAdmin)
                    <td class="px-3 py-2 text-center">
                        <button onclick="editFingerprintCharge({{ $charge->id }}, {{ $charge->district_id }}, {{ $charge->fingerprint_charge }})" class="text-xs text-slate-600 hover:text-slate-800 mr-3">Edit</button>
                        <form method="POST" action="{{ route('fingerprint-charges.destroy', $charge->id) }}" onsubmit="return confirm('Are you sure you want to delete this fingerprint charge?')" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                        </form>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-3 py-8 text-center text-slate-500">
                        No fingerprint charges configured yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($fingerprintCharges->isEmpty())
    <div class="text-center py-4 text-slate-500">
        No fingerprint charges available.
    </div>
    @endif

    <div class="mt-4 flex justify-center">
        {{ $fingerprintCharges->links() }}
    </div>
</div>
