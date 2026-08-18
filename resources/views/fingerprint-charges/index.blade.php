@extends('layouts.app')
@section('title', 'Fingerprint Charges')
@section('content')
<div class="max-w-4xl mx-auto" x-data="{}">
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

    <livewire:fingerprint.fingerprint-charge-list />
</div>

@if(auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty())
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
                            <option value="{{ $district->id }}">{{ $district->name }} ({{ $district->division }})</option>
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
@endif

<script>
const districts = @json($districts ?? []);

function filterByDivision() {
    // Filtering now handled by Livewire component (AJAX)
}

document.addEventListener('DOMContentLoaded', function() {
    const selectedDivision = document.getElementById('filterDivisionSelect')?.value;
});

@if(auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty())
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
@endif
</script>
@endsection