@extends('layouts.app')
@section('title', 'Fingerprint Charges')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Fingerprint Charges</h1>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <div class="flex items-end justify-between mb-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Division</label>
                <select id="filterDivisionSelect" onchange="filterByDivision()" class="w-64 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="">Select Division</option>
                    @foreach($divisions ?? [] as $division)
                        <option value="{{ $division }}" {{ request('division') == $division ? 'selected' : '' }}>{{ $division }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" onclick="showFingerprintChargeModal()" id="addChargeBtn" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Fingerprint Charge
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Division</th>
                        <th class="px-3 py-2 text-left font-medium">District</th>
                        <th class="px-3 py-2 text-left font-medium">Created By</th>
                        <th class="px-3 py-2 text-right font-medium">Charge (SAR)</th>
                        <th class="px-3 py-2 text-center font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($fingerprintCharges as $charge)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-600">{{ $charge->district->division ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $charge->district->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $charge->user->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">{{ number_format($charge->fingerprint_charge, 2) }} SAR</td>
                            <td class="px-3 py-2 text-center">
                                <button onclick="editFingerprintCharge({{ $charge->id }}, {{ $charge->district_id }}, {{ $charge->fingerprint_charge }})" class="text-xs text-slate-600 hover:text-slate-800 mr-3">Edit</button>
                                <form method="POST" action="{{ route('fingerprint-charges.destroy', $charge->id) }}" onsubmit="return confirm('Are you sure you want to delete this fingerprint charge?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-slate-500">
                                No fingerprint charges configured yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="fingerprintChargesEmpty" class="{{ $fingerprintCharges->isEmpty() ? 'block' : 'hidden' }} text-center py-4 text-slate-500">
            No fingerprint charges configured yet.
        </div>

        <div class="mt-4 flex justify-center">
            {{ $fingerprintCharges->links() }}
        </div>
    </div>
</div>

<!-- Modal -->
<div id="fingerprintChargeModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideFingerprintChargeModal()"></div>
    <div class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-slate-700">Add/Update Fingerprint Charge</h3>
            <button onclick="hideFingerprintChargeModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="fingerprintChargeForm" method="POST" action="{{ route('fingerprint-charges.store') }}">
            @csrf
            <input type="hidden" id="chargeId" name="charge_id">
            <input type="hidden" id="formMethod" name="_method" value="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">District *</label>
                    <select id="modalDistrictSelect" name="district_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select District</option>
                        @foreach($districts ?? [] as $district)
                            <option value="{{ $district->id }}">{{ $district->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fingerprint Charge (SAR) *</label>
                    <input type="number" id="modalFingerprintChargeInput" name="fingerprint_charge" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0" min="0" step="0.01" required>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="hideFingerprintChargeModal()" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
const districts = @json($districts ?? []);

function filterByDivision() {
    const division = document.getElementById('filterDivisionSelect').value;
    const addBtn = document.getElementById('addChargeBtn');

    if (division) {
        addBtn.disabled = false;
    } else {
        addBtn.disabled = true;
    }

    const url = new URL(window.location.href);
    if (division) {
        url.searchParams.set('division', division);
    } else {
        url.searchParams.delete('division');
    }
    window.location.href = url.toString();
}

function showFingerprintChargeModal() {
    const selectedDivision = document.getElementById('filterDivisionSelect').value;
    const modalDistrictSelect = document.getElementById('modalDistrictSelect');

    modalDistrictSelect.innerHTML = '<option value="">Select District</option>';

    const filteredDistricts = districts.filter(d => !selectedDivision || d.division === selectedDivision);
    filteredDistricts.forEach(district => {
        const option = document.createElement('option');
        option.value = district.id;
        option.textContent = district.name;
        modalDistrictSelect.appendChild(option);
    });

    document.getElementById('fingerprintChargeModal').classList.remove('hidden');
    document.getElementById('chargeId').value = '';
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('fingerprintChargeForm').action = '{{ route("fingerprint-charges.store") }}';
    document.getElementById('modalFingerprintChargeInput').value = '0';
    document.getElementById('modalDistrictSelect').value = '';
}

function hideFingerprintChargeModal() {
    document.getElementById('fingerprintChargeModal').classList.add('hidden');
}

function editFingerprintCharge(id, districtId, charge) {
    const selectedDivision = document.getElementById('filterDivisionSelect').value;
    const modalDistrictSelect = document.getElementById('modalDistrictSelect');

    modalDistrictSelect.innerHTML = '<option value="">Select District</option>';

    const filteredDistricts = districts.filter(d => !selectedDivision || d.division === selectedDivision);
    filteredDistricts.forEach(district => {
        const option = document.createElement('option');
        option.value = district.id;
        option.textContent = district.name;
        modalDistrictSelect.appendChild(option);
    });

    document.getElementById('fingerprintChargeModal').classList.remove('hidden');
    document.getElementById('chargeId').value = id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('fingerprintChargeForm').action = '/fingerprint-charges/' + id;
    document.getElementById('modalFingerprintChargeInput').value = charge;
    document.getElementById('modalDistrictSelect').value = districtId;
}

document.addEventListener('DOMContentLoaded', function() {
    const selectedDivision = document.getElementById('filterDivisionSelect').value;
    const addBtn = document.getElementById('addChargeBtn');
    if (selectedDivision) {
        addBtn.disabled = false;
    }
});
</script>
@endsection