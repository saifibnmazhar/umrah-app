@extends('layouts.app')

@section('title', 'Settings')

@section('content')
@php
    $isSettingsAdmin = auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin'])->isNotEmpty();
@endphp
<div class="w-full pt-6 px-4 md:px-6"
     x-data="{
         activeTab: (new URLSearchParams(window.location.search).get('tab')) || '{{ $isSettingsAdmin ? 'flight-date-gap' : 'fingerprint-charge' }}',
         syncTabToUrl(val) {
             const url = new URL(window.location);
             url.searchParams.set('tab', val);
             window.history.replaceState({}, '', url);
         }
     }"
     x-init="$watch('activeTab', val => syncTabToUrl(val))">
    <h1 class="text-2xl font-bold mb-6">Admin Settings</h1>

    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            @if($isSettingsAdmin)
            <button
                @click="activeTab = 'flight-date-gap'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'flight-date-gap', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'flight-date-gap' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Flight Date Gap
            </button>
            @endif
            <button
                @click="activeTab = 'fingerprint-charge'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'fingerprint-charge', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'fingerprint-charge' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Fingerprint Charge
            </button>
            @if($isSettingsAdmin)
            <button
                @click="activeTab = 'package-configuration'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'package-configuration', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'package-configuration' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Package Configuration
            </button>
            <button
                @click="activeTab = 'stay-duration-limit'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'stay-duration-limit', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'stay-duration-limit' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Stay Duration Limit
            </button>
            @endif
        </nav>
    </div>

    @if($isSettingsAdmin)
    <div x-show="activeTab === 'flight-date-gap'" x-cloak>
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
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-slate-700 mb-2">Current Configuration</h2>
                <p class="text-sm text-slate-500">
                    Default gap: <span class="font-medium text-slate-700">{{ $flightDateGap?->gap ?? 'Not set' }}</span> days
                </p>
                <p class="text-sm text-slate-500 mt-1">
                    This value is used to calculate the minimum booking date before flight departure.
                </p>
            </div>

            <form method="POST" action="{{ route('settings.flight-date-gap.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" :value="activeTab">

                <div class="space-y-4">
                    <div>
                        <label for="gap" class="block text-sm font-medium text-slate-700 mb-1">Gap Between Booking and Flight Date (Days) *</label>
                        <input type="number" id="gap" name="gap"
                            class="w-1/2 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
                            value="{{ old('gap', $flightDateGap?->gap ?? 30) }}"
                            min="1" required>
                        <p class="text-xs text-slate-500 mt-1">Minimum value is 1 day.</p>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div x-show="activeTab === 'fingerprint-charge'" x-cloak>
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
                @if($isSettingsAdmin)
                <button type="button" onclick="showFingerprintChargeModal()" id="addChargeBtn" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-slate-700" disabled>
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
                            <th class="px-3 py-2 text-left font-medium">Created By</th>
                            <th class="px-3 py-2 text-right font-medium">Charge (<span x-text="$store.currency.mode">SAR</span>)</th>
                            @if($isSettingsAdmin)
                            <th class="px-3 py-2 text-center font-medium">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="fingerprintTableBody" class="divide-y divide-slate-200">
                        @forelse($fingerprintCharges as $charge)
                            <tr class="hover:bg-slate-50" data-division="{{ $charge->district->division ?? '' }}">
                                <td class="px-3 py-2 text-slate-600">{{ $charge->district->division ?? 'N/A' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $charge->district->name ?? 'N/A' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $charge->user->name ?? 'N/A' }}</td>
                                <td class="px-3 py-2 text-right text-slate-800 font-medium">@currency($charge->fingerprint_charge, 2)</td>
                                @if($isSettingsAdmin)
                                <td class="px-3 py-2 text-center">
                                    <button onclick="editFingerprintCharge({{ $charge->id }}, {{ $charge->district_id }}, {{ $charge->fingerprint_charge }})" class="text-xs text-slate-600 hover:text-slate-800 mr-3">Edit</button>
                                    <form method="POST" action="{{ route('fingerprint-charges.destroy', $charge->id) }}" onsubmit="return confirm('Are you sure you want to delete this fingerprint charge?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="tab" :value="activeTab">
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isSettingsAdmin ? 5 : 4 }}" class="px-3 py-8 text-center text-slate-500">
                                    No fingerprint charges configured yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-center"
                 @click.prevent="
                     const el = $event.target.closest('a');
                     if (el && el.href) {
                         const url = new URL(el.href);
                         url.searchParams.set('tab', activeTab);
                         window.location.href = url.toString();
                     }
                 ">
                {{ $fingerprintCharges->appends(request()->query())->links() }}
            </div>
        </div>
    </div>

    @if($isSettingsAdmin)
    <!-- Modal -->
    <div id="fingerprintChargeModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-slate-700">Add/Update Fingerprint Charge</h3>
                <button onclick="hideFingerprintChargeModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="fingerprintChargeForm" method="POST" action="{{ route('fingerprint-charges.store') }}" x-data="{ bdtValue: 0 }">
                @csrf
                <input type="hidden" id="chargeId" name="charge_id">
                <input type="hidden" id="formMethod" name="_method" value="POST">
                <input type="hidden" name="tab" :value="activeTab">
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
                    <div x-show="$store.currency.mode === 'BDT'" x-cloak>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Fingerprint Charge (BDT) *</label>
                        <input type="number" x-model="bdtValue" @input="document.getElementById('modalFingerprintChargeInput').value = (parseFloat(bdtValue || 0) / ($store.currency.rate || 1)).toFixed(6)" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0" step="0.01" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Fingerprint Charge (SAR) *</label>
                        <input type="number" id="modalFingerprintChargeInput" name="fingerprint_charge" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0" min="0" step="0.01" required :readonly="$store.currency.mode === 'BDT'">
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

    // Apply filter on page load
    document.addEventListener('DOMContentLoaded', function() {
        filterByDivision();
    });

    function filterByDivision() {
        try {
            var selectEl = document.getElementById('filterDivisionSelect');
            var division = selectEl ? selectEl.value : '';
            var addBtn = document.getElementById('addChargeBtn');
            
            if (addBtn) {
                addBtn.disabled = !division;
            }
            
            var rows = document.querySelectorAll('#fingerprintTableBody tr');
            if (!rows || rows.length === 0) return;
            
            rows.forEach(function(row) {
                var rowDivision = row.getAttribute('data-division') || '';
                if (!division || rowDivision === division) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        } catch (e) {
            console.error('Filter error:', e);
        }
    }

    @if($isSettingsAdmin)
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
        const formEl = document.getElementById('fingerprintChargeForm');
        if (formEl._x_dataStack) {
            Alpine.$data(formEl).bdtValue = 0;
        }
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
        const formEl = document.getElementById('fingerprintChargeForm');
        if (formEl._x_dataStack && Alpine.store('currency').mode === 'BDT') {
            const rate = window.__currencyRate || 1;
            Alpine.$data(formEl).bdtValue = charge * (rate || 1);
        }
    }
    @endif
    </script>

    <script>
    function toggleDoubleTicket() {
        const isDouble = document.getElementById('modalDoubleTicketCheck').checked;
        document.getElementById('modalSingleTicketFields').classList.toggle('hidden', isDouble);
        document.getElementById('modalDoubleTicketFields').classList.toggle('hidden', !isDouble);
        if (isDouble) {
            document.getElementById('modalTicketSelect').value = '';
            document.getElementById('modalTicketSelect').required = false;
            document.getElementById('modalTicketInboundSelect').required = true;
            document.getElementById('modalTicketOutboundSelect').required = true;
            calculateModalPrices();
        } else {
            document.getElementById('modalTicketInboundSelect').value = '';
            document.getElementById('modalTicketOutboundSelect').value = '';
            document.getElementById('modalTicketInboundSelect').required = false;
            document.getElementById('modalTicketOutboundSelect').required = false;
            document.getElementById('modalTicketSelect').required = true;
            calculateModalPrices();
        }
    }

    function showPackageModal() {
        document.getElementById('modalTitle').textContent = 'Add Package';
        document.getElementById('packageForm').action = '{{ route('settings.package.store') }}';
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('packageId').value = '';
        document.getElementById('packageName').value = '';
        document.getElementById('modalTicketTypeSelect').value = '';
        document.getElementById('modalTicketSelect').value = '';
        document.getElementById('modalRegularPrice').value = '';
        document.getElementById('modalRegularPrice').dataset.sarValue = '0';
        document.getElementById('modalServiceCharge').value = 0;
        document.getElementById('modalServiceCharge').dataset.sarValue = '0';
        document.getElementById('modalOfferPrice').value = '';
        document.getElementById('modalOfferPrice').dataset.sarValue = '0';
        document.getElementById('modalOfferPrice').readOnly = false;
        document.getElementById('modalOfferPriceContainer').classList.add('hidden');
        document.getElementById('modalDoubleTicketCheck').checked = false;
        filterModalTickets();
        toggleDoubleTicket();
        document.getElementById('packageModal').classList.remove('hidden');
        syncInputCurrency();
        updateModalGross();
    }

    function editPackage(id) {
        const pkg = packages.find(p => p.id === id);
        if (!pkg) return;
        if (pkg.is_locked) {
            alert('This package cannot be edited because it has existing bookings.');
            return;
        }
        document.getElementById('modalTitle').textContent = 'Edit Package';
        document.getElementById('packageForm').action = '/settings/package/' + id;
        document.getElementById('formMethod').value = 'PUT';
        document.getElementById('packageId').value = pkg.id;
        document.getElementById('packageName').value = pkg.package_name;
        const isDouble = pkg.is_double_ticket || false;
        document.getElementById('modalDoubleTicketCheck').checked = isDouble;
        toggleDoubleTicket();
        const ticketOption = Array.from(document.getElementById('modalTicketSelect').options).find(opt => opt.value == pkg.ticket_fare_id);
        if (ticketOption) {
            document.getElementById('modalTicketTypeSelect').value = ticketOption.dataset.ticketType || '';
            filterModalTickets(pkg.ticket_fare_id);
            document.getElementById('modalTicketSelect').value = pkg.ticket_fare_id;
        }
        document.getElementById('modalTicketInboundSelect').value = pkg.ticket_fare_inbound_id || '';
        document.getElementById('modalTicketOutboundSelect').value = pkg.ticket_fare_outbound_id || '';
        document.getElementById('modalRegularPrice').dataset.sarValue = parseFloat(pkg.regular_price || 0).toFixed(6);
        document.getElementById('modalServiceCharge').dataset.sarValue = parseFloat(pkg.service_charge || 0).toFixed(6);
        if (pkg.offer_price) {
            document.getElementById('modalOfferPrice').dataset.sarValue = parseFloat(pkg.offer_price).toFixed(6);
        }
        if (!isDouble && ticketOption && ticketOption.dataset.ticketType === 'offer') {
            document.getElementById('modalOfferPriceContainer').classList.remove('hidden');
            document.getElementById('modalOfferPrice').readOnly = true;
        } else {
            document.getElementById('modalOfferPriceContainer').classList.add('hidden');
            document.getElementById('modalOfferPrice').value = '';
            document.getElementById('modalOfferPrice').dataset.sarValue = '0';
            document.getElementById('modalOfferPrice').readOnly = false;
        }
        document.getElementById('packageModal').classList.remove('hidden');
        syncInputCurrency();
        updateModalGross();
    }

    function hidePackageModal() {
        document.getElementById('packageModal').classList.add('hidden');
    }

    function filterModalTickets(exceptFareId = null) {
        const selectedType = document.getElementById('modalTicketTypeSelect').value;
        document.getElementById('modalTicketSelect').value = '';
        document.getElementById('modalTicketInboundSelect').value = '';
        document.getElementById('modalTicketOutboundSelect').value = '';
        calculateModalPrices();
        Array.from(document.getElementById('modalTicketSelect').options).forEach(option => {
            if (option.value === '') return;
            const ticketType = option.dataset.ticketType;
            const isUsed = option.dataset.used === 'true';
            const isExcepted = exceptFareId && option.value == exceptFareId;
            let show = (selectedType === '' || ticketType === selectedType);
            if (show && isUsed && !isExcepted) {
                show = false;
            }
            option.style.display = show ? '' : 'none';
        });
        Array.from(document.getElementById('modalTicketInboundSelect').options).forEach(option => {
            if (option.value === '' || !option.dataset.ticketType) return;
            option.style.display = (selectedType === '' || option.dataset.ticketType === selectedType) ? '' : 'none';
        });
        Array.from(document.getElementById('modalTicketOutboundSelect').options).forEach(option => {
            if (option.value === '' || !option.dataset.ticketType) return;
            option.style.display = (selectedType === '' || option.dataset.ticketType === selectedType) ? '' : 'none';
        });
    }

    function syncInputCurrency() {
        const currency = window.Alpine?.store('currency');
        if (!currency) return;
        const isBDT = currency.mode === 'BDT';
        const rate = currency.rate;
        document.querySelectorAll('[data-sar-value]').forEach(input => {
            const sar = parseFloat(input.dataset.sarValue) || 0;
            if (isBDT) {
                input.value = Math.round(sar * rate);
            } else {
                input.value = sar.toFixed(6);
            }
        });
        document.querySelectorAll('.currency-label').forEach(el => {
            el.textContent = isBDT ? 'BDT' : 'SAR';
        });
    }

    function updateModalGross() {
        const isDouble = document.getElementById('modalDoubleTicketCheck')?.checked;
        const regularSar = parseFloat(document.getElementById('modalRegularPrice')?.dataset?.sarValue || 0);
        const offerSar = parseFloat(document.getElementById('modalOfferPrice')?.dataset?.sarValue || 0);
        const serviceSar = parseFloat(document.getElementById('modalServiceCharge')?.dataset?.sarValue || 0);
        const selectedOption = document.getElementById('modalTicketSelect').options[document.getElementById('modalTicketSelect').selectedIndex];
        const ticketType = selectedOption?.dataset?.ticketType;
        let gross = 0;
        if (isDouble) {
            gross = regularSar + serviceSar;
        } else if (ticketType === 'offer') {
            gross = offerSar + serviceSar;
        } else {
            gross = regularSar + serviceSar;
        }
        const grossDisplay = document.getElementById('modalGrossDisplay');
        if (!grossDisplay) return;
        const sar = gross;
        const currency = window.Alpine?.store('currency');
        const isBDT = currency?.mode === 'BDT';
        const rate = currency?.rate || 1;
        grossDisplay.dataset.sar = sar.toFixed(6);
        grossDisplay.dataset.dec = '0';
        if (isBDT) {
            grossDisplay.textContent = 'BDT ' + Math.round(sar * rate).toLocaleString();
        } else {
            grossDisplay.textContent = 'SAR ' + Math.round(sar).toLocaleString();
        }
    }

    function calculateModalPrices() {
        const isDouble = document.getElementById('modalDoubleTicketCheck').checked;
        if (isDouble) {
            const inboundSelect = document.getElementById('modalTicketInboundSelect');
            const outboundSelect = document.getElementById('modalTicketOutboundSelect');
            const inboundOption = inboundSelect.options[inboundSelect.selectedIndex];
            const outboundOption = outboundSelect.options[outboundSelect.selectedIndex];
            const hasInbound = inboundOption && inboundOption.value;
            const hasOutbound = outboundOption && outboundOption.value;
            const inboundSelling = hasInbound ? parseFloat(inboundOption.dataset.sellingFare) || 0 : 0;
            const outboundSelling = hasOutbound ? parseFloat(outboundOption.dataset.sellingFare) || 0 : 0;
            const totalFare = inboundSelling + outboundSelling;
            const regularSar = totalFare > 0 ? totalFare + latestVisaPrice : 0;
            document.getElementById('modalRegularPrice').dataset.sarValue = regularSar > 0 ? regularSar.toFixed(6) : '0';

            const inboundType = hasInbound ? inboundOption.dataset.ticketType : null;
            const outboundType = hasOutbound ? outboundOption.dataset.ticketType : null;
            const serviceSar = parseFloat(document.getElementById('modalServiceCharge')?.dataset?.sarValue || 0);
            if (inboundType === 'offer' && outboundType === 'offer' && hasInbound && hasOutbound) {
                const inboundOffer = parseFloat(inboundOption.dataset.offerPrice) || 0;
                const outboundOffer = parseFloat(outboundOption.dataset.offerPrice) || 0;
                const offerSar = inboundOffer + outboundOffer + latestVisaPrice;
                document.getElementById('modalOfferPrice').dataset.sarValue = offerSar.toFixed(6);
                document.getElementById('modalOfferPriceContainer').classList.remove('hidden');
                document.getElementById('modalOfferPrice').readOnly = true;
            } else {
                document.getElementById('modalOfferPrice').dataset.sarValue = '0';
                document.getElementById('modalOfferPriceContainer').classList.add('hidden');
                document.getElementById('modalOfferPrice').value = '';
                document.getElementById('modalOfferPrice').readOnly = false;
            }
            syncInputCurrency();
            updateModalGross();
            return;
        }
        const selectedOption = document.getElementById('modalTicketSelect').options[document.getElementById('modalTicketSelect').selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            document.getElementById('modalRegularPrice').value = '';
            document.getElementById('modalRegularPrice').dataset.sarValue = '0';
            document.getElementById('modalOfferPrice').value = '';
            document.getElementById('modalOfferPrice').dataset.sarValue = '0';
            syncInputCurrency();
            updateModalGross();
            return;
        }
        const sellingFare = parseFloat(selectedOption.dataset.sellingFare) || 0;
        const offerFare = parseFloat(selectedOption.dataset.offerPrice) || 0;
        const ticketType = selectedOption.dataset.ticketType;
        if (ticketType === 'offer') {
            const regularSar = sellingFare + latestVisaPrice;
            const offerSar = offerFare + latestVisaPrice;
            document.getElementById('modalRegularPrice').dataset.sarValue = regularSar.toFixed(6);
            document.getElementById('modalOfferPrice').dataset.sarValue = offerSar.toFixed(6);
            document.getElementById('modalOfferPriceContainer').classList.remove('hidden');
            document.getElementById('modalOfferPrice').readOnly = true;
        } else {
            const regularSar = sellingFare + latestVisaPrice;
            document.getElementById('modalRegularPrice').dataset.sarValue = regularSar.toFixed(6);
            document.getElementById('modalOfferPrice').dataset.sarValue = '0';
            document.getElementById('modalOfferPriceContainer').classList.add('hidden');
            document.getElementById('modalOfferPrice').value = '';
            document.getElementById('modalOfferPrice').readOnly = false;
        }
        syncInputCurrency();
        updateModalGross();
    }

    function handlePriceInput(el) {
        const currency = window.Alpine?.store('currency');
        const isBDT = currency?.mode === 'BDT';
        const rate = currency?.rate || 1;
        if (isBDT) {
            const bdtValue = parseFloat(el.value || 0);
            el.dataset.sarValue = (bdtValue / rate).toFixed(6);
        } else {
            el.dataset.sarValue = parseFloat(el.value || 0).toFixed(6);
        }
        syncInputCurrency();
        updateModalGross();
    }

    function updateSelectLabels(selectId) {
        const currency = window.Alpine?.store('currency');
        if (!currency) return;
        const select = document.getElementById(selectId);
        if (!select) return;
        Array.from(select.options).forEach(opt => {
            if (!opt.value || !opt.dataset.displayPrefix) return;
            const sellingFare = parseFloat(opt.dataset.sellingFare) || 0;
            const offerPrice = parseFloat(opt.dataset.offerPrice) || 0;
            let display = opt.dataset.displayPrefix;
            if (currency.mode === 'BDT') {
                display += ' | BDT ' + Math.round(sellingFare * currency.rate).toLocaleString();
                if (opt.dataset.ticketType === 'offer') {
                    display += ' | BDT ' + Math.round(offerPrice * currency.rate).toLocaleString();
                }
            } else {
                display += ' | SAR ' + sellingFare.toLocaleString();
                if (opt.dataset.ticketType === 'offer') {
                    display += ' | SAR ' + offerPrice.toLocaleString();
                }
            }
            opt.textContent = display;
        });
    }

    function updateModalTicketLabels() {
        updateSelectLabels('modalTicketSelect');
        updateSelectLabels('modalTicketInboundSelect');
        updateSelectLabels('modalTicketOutboundSelect');
    }
    </script>

    @if($isSettingsAdmin)
    <div x-show="activeTab === 'package-configuration'" x-cloak>
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

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden mb-4">
            <form method="GET" action="{{ route('settings') }}" class="p-4 flex flex-wrap gap-4 items-end">
                <input type="hidden" name="tab" value="package-configuration">
                <div class="min-w-[150px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select name="status" onchange="this.form.submit()" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="">Active</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All</option>
                    </select>
                </div>
                <div>
                    <a href="{{ route('settings', ['tab' => 'package-configuration']) }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition text-sm font-medium">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-slate-700">Packages</h2>
            <button type="button" onclick="showPackageModal()" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Package
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Package Name</th>
                            <th class="px-3 py-2 text-left font-medium">Route</th>
                            <th class="px-3 py-2 text-right font-medium">Ticket Selling Fare</th>
                            <th class="px-3 py-2 text-right font-medium">Ticket Offer Fare</th>
                            <th class="px-3 py-2 text-right font-medium">Visa Selling Price</th>
                            <th class="px-3 py-2 text-right font-medium">Service Charge</th>
                            <th class="px-3 py-2 text-right font-medium">Package Price</th>
                            <th class="px-3 py-2 text-right font-medium pr-8">Offer Price</th>
                            <th class="px-3 py-2 text-left font-medium">Created At</th>
                            <th class="px-3 py-2 text-center font-medium">Status</th>
                            <th class="px-3 py-2 text-center font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($packages as $package)
                            @php
                                if ($package->is_double_ticket) {
                                    $inboundRoute = $package->ticketFareInbound?->route;
                                    $outboundRoute = $package->ticketFareOutbound?->route;
                                    $inboundName = ($inboundRoute ? ($inboundRoute->fromCity?->code ?? '?') . ' → ' . ($inboundRoute->toCity?->code ?? '?') : '?');
                                    $outboundName = ($outboundRoute ? ($outboundRoute->fromCity?->code ?? '?') . ' → ' . ($outboundRoute->toCity?->code ?? '?') : '?');
                                    $routeName = $inboundName . ' + ' . $outboundName;
                                } else {
                                    $route = $package->ticketFare?->route;
                                    if ($route && $route->multiSegments && $route->multiSegments->count() > 0) {
                                        $routeName = $route->multiSegments->map(
                                            fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?')
                                        )->implode(', ');
                                    } elseif ($route && $route->returnCity) {
                                        $routeName = ($route->fromCity?->code ?? '?') . ' - ' . ($route->toCity?->code ?? '?') . ' - ' . ($route->returnCity?->code ?? '?');
                                    } else {
                                        $routeName = ($route?->fromCity?->code ?? '?') . ' → ' . ($route?->toCity?->code ?? '?');
                                    }
                                }
                                $ticketSellingFare = $package->is_double_ticket
                                    ? (($package->ticketFareInbound?->selling_fare ?? 0) + ($package->ticketFareOutbound?->selling_fare ?? 0))
                                    : ($package->ticketFare?->selling_fare ?? 0);
                                $ticketOfferFare = $package->is_double_ticket
                                    ? (($package->ticketFareInbound?->offer_price ?? 0) + ($package->ticketFareOutbound?->offer_price ?? 0))
                                    : ($package->ticketFare?->offer_price);
                                $visaSellingPrice = $package->regular_price - $ticketSellingFare;
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-slate-800 font-medium">{{ $package->package_name }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $routeName }}</td>
                                <td class="px-3 py-2 text-right text-slate-800">@currency($ticketSellingFare, 0)</td>
                                <td class="px-3 py-2 text-right text-slate-600">
                                    @if($ticketOfferFare)
                                        @currency($ticketOfferFare, 0)
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right text-slate-600">@currency($visaSellingPrice, 0)</td>
                                <td class="px-3 py-2 text-right text-slate-600">@currency($package->service_charge ?? 0, 0)</td>
                                <td class="px-3 py-2 text-right text-slate-800 font-medium">@currency(($package->regular_price ?? 0) + ($package->service_charge ?? 0), 0)</td>
                                <td class="px-3 py-2 text-right text-slate-600 pr-8">
                                    @if(
                                        (!$package->is_double_ticket && $package->ticketFare?->ticket_type === \App\Enums\TicketType::OFFER) ||
                                        ($package->is_double_ticket && (
                                            $package->ticketFareInbound?->ticket_type === \App\Enums\TicketType::OFFER ||
                                            $package->ticketFareOutbound?->ticket_type === \App\Enums\TicketType::OFFER
                                        ))
                                    )
                                        @currency(($package->offer_price ?? 0) + ($package->service_charge ?? 0), 0)
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $package->created_at ? $package->created_at->format('d/m/y') : '-' }}</td>
                                <td class="px-3 py-2 text-center">
                                    <form method="POST" action="{{ route('packages.toggle-active', $package->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $package->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $package->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                        </button>
                                    </form>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $package->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $package->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <a href="{{ route('settings.package.show', $package->id) }}" class="text-xs text-slate-600 hover:text-slate-800 mr-3">View</a>
                                    @if($package->is_locked)
                                        <button class="text-xs text-slate-400 cursor-not-allowed mr-3" title="Has existing bookings" disabled>Edit</button>
                                        <button class="text-xs text-red-400 cursor-not-allowed" title="Has existing bookings" disabled>Delete</button>
                                    @else
                                        <button onclick="editPackage({{ $package->id }})" class="text-xs text-slate-600 hover:text-slate-800 mr-3">Edit</button>
                                        <form method="POST" action="{{ route('settings.package.destroy', $package->id) }}" onsubmit="return confirm('Are you sure you want to delete this package?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="tab" :value="activeTab">
                                            <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-3 py-8 text-center text-slate-500">No packages configured yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-center"
                 @click.prevent="
                     const el = $event.target.closest('a');
                     if (el && el.href) {
                         const url = new URL(el.href);
                         url.searchParams.set('tab', activeTab);
                         window.location.href = url.toString();
                     }
                 ">
                {{ $packages->appends(request()->query())->links() }}
            </div>
        </div>

        <!-- Package Modal -->
        <div id="packageModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-5xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="modalTitle" class="text-lg font-semibold text-slate-700">Add Package</h3>
                    <button onclick="hidePackageModal()" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <form id="packageForm" method="POST" action="{{ route('settings.package.store') }}">
                    @csrf
                    <input type="hidden" id="packageId" name="id">
                    <input type="hidden" id="formMethod" name="_method" value="POST">
                    <input type="hidden" name="tab" :value="activeTab">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Package Name *</label>
                            <input type="text" id="packageName" name="package_name" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type *</label>
                            <select id="modalTicketTypeSelect" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white" required>
                                <option value="">Select Ticket Type</option>
                                <option value="regular">REGULAR</option>
                                <option value="offer">OFFER</option>
                                <option value="group">GROUP</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="modalDoubleTicketCheck" name="is_double_ticket" value="1" class="w-4 h-4 rounded border-slate-300 text-slate-700 focus:ring-slate-400">
                            <span class="text-sm font-medium text-slate-700">Double Ticket</span>
                        </label>
                    </div>

                    <div id="modalSingleTicketFields" class="mt-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket *</label>
                        <select id="modalTicketSelect" name="ticket_fare_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Ticket</option>
                            @foreach($ticketFares as $fare)
                                @php
                                    $type = $fare['ticket_type'];
                                    $prefix = $fare['airline'] . ' | ' . $fare['route'] . ' | ' . strtoupper($type ?? '?');
                                @endphp
                                <option value="{{ $fare['id'] }}"
                                    data-ticket-type="{{ $fare['ticket_type'] }}"
                                    data-selling-fare="{{ $fare['selling_fare'] }}"
                                    data-offer-price="{{ $fare['offer_price'] ?? 0 }}"
                                    data-display-prefix="{{ $prefix }}"
                                    data-used="{{ in_array($fare['id'], $usedFareIds) ? 'true' : 'false' }}">
                                    {{ $prefix }} | SAR {{ number_format($fare['selling_fare'], 0) }}{{ $type === 'offer' ? ' | SAR ' . number_format($fare['offer_price'] ?? 0, 0) : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="modalDoubleTicketFields" class="mt-4 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Inbound *</label>
                                <select id="modalTicketInboundSelect" name="ticket_fare_inbound_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                    <option value="">Select Inbound Ticket</option>
                                    @foreach($inboundFares as $fare)
                                        @php
                                            $inType = $fare['ticket_type'];
                                            $inPrefix = $fare['airline'] . ' | ' . $fare['route'] . ' | ' . strtoupper($inType ?? '?');
                                        @endphp
                                        <option value="{{ $fare['id'] }}"
                                            data-selling-fare="{{ $fare['selling_fare'] }}"
                                            data-ticket-type="{{ $fare['ticket_type'] }}"
                                            data-offer-price="{{ $fare['offer_price'] ?? 0 }}"
                                            data-display-prefix="{{ $inPrefix }}">
                                            {{ $inPrefix }} | SAR {{ number_format($fare['selling_fare'], 0) }}{{ $inType === 'offer' ? ' | SAR ' . number_format($fare['offer_price'] ?? 0, 0) : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Outbound *</label>
                                <select id="modalTicketOutboundSelect" name="ticket_fare_outbound_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                    <option value="">Select Outbound Ticket</option>
                                    @foreach($outboundFares as $fare)
                                        @php
                                            $outType = $fare['ticket_type'];
                                            $outPrefix = $fare['airline'] . ' | ' . $fare['route'] . ' | ' . strtoupper($outType ?? '?');
                                        @endphp
                                        <option value="{{ $fare['id'] }}"
                                            data-selling-fare="{{ $fare['selling_fare'] }}"
                                            data-ticket-type="{{ $fare['ticket_type'] }}"
                                            data-offer-price="{{ $fare['offer_price'] ?? 0 }}"
                                            data-display-prefix="{{ $outPrefix }}">
                                            {{ $outPrefix }} | SAR {{ number_format($fare['selling_fare'], 0) }}{{ $outType === 'offer' ? ' | SAR ' . number_format($fare['offer_price'] ?? 0, 0) : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Regular Price (<span id="modalRegularPriceLabel" class="currency-label">SAR</span>) *</label>
                            <input type="number" id="modalRegularPrice" name="regular_price" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-slate-50" min="0" step="any" required readonly data-sar-value="0">
                        </div> 
                        <div id="modalOfferPriceContainer" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (<span id="modalOfferPriceLabel" class="currency-label">SAR</span>)</label>
                            <input type="number" id="modalOfferPrice" name="offer_price" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0" step="any" data-sar-value="0">
                        </div>
                        <div id="modalServiceChargeContainer">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge (<span id="modalServiceChargeLabel" class="currency-label">SAR</span>)</label>
                            <input type="number" id="modalServiceCharge" name="service_charge" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0" step="any" value="0" data-sar-value="0">
                        </div>                       
                    </div>

                    <div class="mt-4 p-4 bg-slate-50 rounded-lg grid grid-cols-2 gap-4">
                        <div class="text-sm text-slate-600">
                            <span class="font-medium">Visa Selling Price (Latest):</span>
                            <span class="text-slate-800 font-medium block">
                                @if($latestVisa)
                                    @currency($latestVisa->selling_price, 0)
                                @else
                                    Not configured
                                @endif
                            </span>
                        </div>
                        <div class="text-sm text-slate-600">
                            <span class="font-medium">Gross Amount:</span>
                            <span id="modalGrossDisplay" class="text-slate-800 font-medium block">@currency(0, 0)</span>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="hidePackageModal()" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-center">Cancel</button>
                        <button type="submit" class="flex-1 px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save Package</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        const ticketFares = @json($ticketFares);
        const inboundFares = @json($inboundFares);
        const outboundFares = @json($outboundFares);
        const latestVisaPrice = {{ $latestVisa?->selling_price ?? 0 }};
        const packages = @json($packages->items());
        const usedFareIds = @json($usedFareIds);
        </script>

        <script>
        document.getElementById('modalDoubleTicketCheck').addEventListener('change', toggleDoubleTicket);
        document.getElementById('modalTicketTypeSelect').addEventListener('change', filterModalTickets);
        document.getElementById('modalTicketSelect').addEventListener('change', calculateModalPrices);
        document.getElementById('modalTicketInboundSelect').addEventListener('change', calculateModalPrices);
        document.getElementById('modalTicketOutboundSelect').addEventListener('change', calculateModalPrices);

        document.getElementById('modalOfferPrice').addEventListener('input', function () { handlePriceInput(this); });
        document.getElementById('modalServiceCharge').addEventListener('input', function () { handlePriceInput(this); });

        window.addEventListener('currency-toggled', () => {
            updateModalTicketLabels();
            syncInputCurrency();
            updateModalGross();
        });
        document.addEventListener('DOMContentLoaded', () => {
            updateModalTicketLabels();
            syncInputCurrency();
            updateModalGross();
        });

        document.getElementById('packageForm').addEventListener('submit', function () {
            const currency = window.Alpine?.store('currency');
            if (currency?.mode === 'BDT') {
                document.querySelectorAll('[data-sar-value]').forEach(input => {
                    input.value = parseFloat(input.dataset.sarValue || 0).toFixed(6);
                });
            }
        });
        </script>
    </div>
    @endif

    @if($isSettingsAdmin)
    <div x-show="activeTab === 'stay-duration-limit'" x-cloak>
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
            <h2 class="text-lg font-semibold text-slate-700 mb-4">Stay Duration Limit</h2>
            <p class="text-sm text-slate-500 mb-6">Set the minimum and maximum stay duration limits for passengers. These limits will be applied in the passenger form's custom duration modal.</p>

            <form method="POST" action="{{ route('settings.stay-duration-limit.update') }}" class="max-w-md">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" value="stay-duration-limit">

                <div class="mb-4">
                    <label for="min_days" class="block text-sm font-medium text-slate-700 mb-1">Minimum Days *</label>
                    <input type="number" id="min_days" name="min_days" value="{{ old('min_days', $stayDurationLimit->min_days) }}" min="1" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="e.g. 1">
                    @error('min_days')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="max_days" class="block text-sm font-medium text-slate-700 mb-1">Maximum Days *</label>
                    <input type="number" id="max_days" name="max_days" value="{{ old('max_days', $stayDurationLimit->max_days) }}" min="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="e.g. 85">
                    @error('max_days')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                    Save
                </button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection