@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="w-full pt-6 px-4 md:px-6" x-data="{ activeTab: 'flight-date-gap' }">
    <h1 class="text-2xl font-bold mb-6">Admin Settings</h1>

    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <button
                @click="activeTab = 'flight-date-gap'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'flight-date-gap', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'flight-date-gap' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Flight Date Gap
            </button>
            <button
                @click="activeTab = 'fingerprint-charge'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'fingerprint-charge', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'fingerprint-charge' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Fingerprint Charge
            </button>
            <button
                @click="activeTab = 'package-configuration'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'package-configuration', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'package-configuration' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Package Configuration
            </button>
        </nav>
    </div>

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

            <form method="POST" action="{{ route('flight-date-gaps.update', $flightDateGap?->id ?? 1) }}">
                @csrf
                @method('PUT')

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
                <button type="button" onclick="showFingerprintChargeModal()" id="addChargeBtn" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-slate-700" disabled>
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
                    <tbody id="fingerprintTableBody" class="divide-y divide-slate-200">
                        @forelse($fingerprintCharges as $charge)
                            <tr class="hover:bg-slate-50" data-division="{{ $charge->district->division ?? '' }}">
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

            <div class="mt-4 flex justify-center">
                {{ $fingerprintCharges->appends(request()->query())->links() }}
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
    </script>

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
                            <th class="px-3 py-2 text-left font-medium">Ticket</th>
                            <th class="px-3 py-2 text-right font-medium">Regular Price</th>
                            <th class="px-3 py-2 text-right font-medium">Offer Price</th>
                            <th class="px-3 py-2 text-center font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($packages as $package)
                            @php
                                $route = $package->ticketFare?->route;
                                if ($route && $route->returnCity) {
                                    $routeName = ($route->fromCity?->code ?? '?') . ' - ' . ($route->toCity?->code ?? '?') . ' - ' . ($route->returnCity?->code ?? '?');
                                } else {
                                    $routeName = ($route?->fromCity?->code ?? '?') . ' → ' . ($route?->toCity?->code ?? '?');
                                }
                                $ticketType = $package->ticketFare?->ticket_type?->value;
                                $ticketDisplay = $routeName . ' | ' . strtoupper($ticketType ?? '?') . ' | BDT ' . number_format($package->ticketFare?->selling_fare ?? 0, 0);
                                if ($ticketType === 'offer') {
                                    $ticketDisplay .= ' | BDT ' . number_format($package->ticketFare?->offer_price ?? 0, 0);
                                }
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-slate-800 font-medium">{{ $package->package_name }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $ticketDisplay }}</td>
                                <td class="px-3 py-2 text-right text-slate-800 font-medium">BDT {{ number_format($package->regular_price, 0) }}</td>
                                <td class="px-3 py-2 text-right text-slate-600">
                                    @if($package->offer_price)
                                        BDT {{ number_format($package->offer_price, 0) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <a href="{{ route('settings.package.show', $package->id) }}" class="text-xs text-slate-600 hover:text-slate-800 mr-3">View</a>
                                    <button onclick="editPackage({{ $package->id }})" class="text-xs text-slate-600 hover:text-slate-800 mr-3">Edit</button>
                                    <form method="POST" action="{{ route('settings.package.destroy', $package->id) }}" onsubmit="return confirm('Are you sure you want to delete this package?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-slate-500">
                                    No packages configured yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-center">
                {{ $packages->appends(request()->query())->links() }}
            </div>
        </div>

        <!-- Package Modal -->
        <div id="packageModal" class="fixed inset-0 z-50 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hidePackageModal()"></div>
            <div class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-xl w-full max-w-5xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
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
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket *</label>
                        <select id="modalTicketSelect" name="ticket_fare_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white" required>
                            <option value="">Select Ticket</option>
                            @foreach($ticketFares as $fare)
                                @php
                                    $type = $fare['ticket_type'];
                                    $display = $fare['airline'] . ' | ' . $fare['route'] . ' | ' . strtoupper($type ?? '?') . ' | BDT ' . number_format($fare['selling_fare'], 0);
                                    if ($type === 'offer') {
                                        $display .= ' | BDT ' . number_format($fare['offer_price'] ?? 0, 0);
                                    }
                                @endphp
                                <option value="{{ $fare['id'] }}"
                                    data-ticket-type="{{ $fare['ticket_type'] }}"
                                    data-selling-fare="{{ $fare['selling_fare'] }}"
                                    data-offer-price="{{ $fare['offer_price'] ?? 0 }}">
                                    {{ $display }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Regular Price (BDT) *</label>
                            <input type="number" id="modalRegularPrice" name="regular_price" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-slate-50" min="0" step="0.01" required readonly>
                        </div>
                        <div id="modalOfferPriceContainer" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (BDT)</label>
                            <input type="number" id="modalOfferPrice" name="offer_price" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0" step="0.01">
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-slate-50 rounded-lg">
                        <p class="text-sm text-slate-600">
                            <span class="font-medium">Visa Selling Price (Latest):</span>
                            <span class="text-slate-800 font-medium">
                                @if($latestVisa)
                                    BDT {{ number_format($latestVisa->selling_price, 0) }}
                                @else
                                    Not configured
                                @endif
                            </span>
                        </p>
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
        const latestVisaPrice = {{ $latestVisa?->selling_price ?? 0 }};
        const packages = @json($packages->items());

        function showPackageModal() {
            document.getElementById('modalTitle').textContent = 'Add Package';
            document.getElementById('packageForm').action = '{{ route('settings.package.store') }}';
            document.getElementById('formMethod').value = 'POST';
            document.getElementById('packageId').value = '';
            document.getElementById('packageName').value = '';
            document.getElementById('modalTicketTypeSelect').value = '';
            document.getElementById('modalTicketSelect').value = '';
            document.getElementById('modalRegularPrice').value = '';
            document.getElementById('modalOfferPrice').value = '';
            document.getElementById('modalOfferPriceContainer').classList.add('hidden');
            filterModalTickets();
            document.getElementById('packageModal').classList.remove('hidden');
        }

        function editPackage(id) {
            const pkg = packages.find(p => p.id === id);
            if (!pkg) return;

            document.getElementById('modalTitle').textContent = 'Edit Package';
            document.getElementById('packageForm').action = '/settings/package/' + id;
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('packageId').value = pkg.id;
            document.getElementById('packageName').value = pkg.package_name;
            
            // Find ticket type from ticket_fare
            const ticketOption = Array.from(document.getElementById('modalTicketSelect').options).find(opt => opt.value == pkg.ticket_fare_id);
            if (ticketOption) {
                document.getElementById('modalTicketTypeSelect').value = ticketOption.dataset.ticketType || '';
                filterModalTickets();
                document.getElementById('modalTicketSelect').value = pkg.ticket_fare_id;
            }
            
            document.getElementById('modalRegularPrice').value = pkg.regular_price;
            document.getElementById('modalOfferPrice').value = pkg.offer_price || '';
            
            if (ticketOption && ticketOption.dataset.ticketType === 'offer') {
                document.getElementById('modalOfferPriceContainer').classList.remove('hidden');
            } else {
                document.getElementById('modalOfferPriceContainer').classList.add('hidden');
            }
            
            document.getElementById('packageModal').classList.remove('hidden');
        }

        function hidePackageModal() {
            document.getElementById('packageModal').classList.add('hidden');
        }

        function filterModalTickets() {
            const selectedType = document.getElementById('modalTicketTypeSelect').value;
            Array.from(document.getElementById('modalTicketSelect').options).forEach(option => {
                if (option.value === '') return;
                const ticketType = option.dataset.ticketType;
                option.style.display = (selectedType === '' || ticketType === selectedType) ? '' : 'none';
            });
        }

        function calculateModalPrices() {
            const selectedOption = document.getElementById('modalTicketSelect').options[document.getElementById('modalTicketSelect').selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                document.getElementById('modalRegularPrice').value = '';
                document.getElementById('modalOfferPrice').value = '';
                return;
            }

            const sellingFare = parseFloat(selectedOption.dataset.sellingFare) || 0;
            const offerFare = parseFloat(selectedOption.dataset.offerPrice) || 0;
            const ticketType = selectedOption.dataset.ticketType;

            document.getElementById('modalRegularPrice').value = (sellingFare + latestVisaPrice).toFixed(2);

            if (ticketType === 'offer') {
                document.getElementById('modalOfferPriceContainer').classList.remove('hidden');
                document.getElementById('modalOfferPrice').value = (offerFare + latestVisaPrice).toFixed(2);
            } else {
                document.getElementById('modalOfferPriceContainer').classList.add('hidden');
                document.getElementById('modalOfferPrice').value = '';
            }
        }

        document.getElementById('modalTicketTypeSelect').addEventListener('change', filterModalTickets);
        document.getElementById('modalTicketSelect').addEventListener('change', calculateModalPrices);
        </script>
    </div>
</div>
@endsection