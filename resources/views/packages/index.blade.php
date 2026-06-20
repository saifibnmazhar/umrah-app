@extends('layouts.app')
@section('title', 'Packages')
@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Packages</h1>
        <button type="button" onclick="showPackageModal()" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Package
        </button>
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
                            if ($route && $route->multiSegments && $route->multiSegments->count() > 0) {
                                $routeName = $route->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
                            } elseif ($route && $route->returnCity) {
                                $routeName = ($route->fromCity?->code ?? '?') . ' - ' . ($route->toCity?->code ?? '?') . ' - ' . ($route->returnCity?->code ?? '?');
                            } else {
                                $routeName = ($route?->fromCity?->code ?? '?') . ' → ' . ($route?->toCity?->code ?? '?');
                            }
                            $seats = $package->ticketFare?->groupTicket?->ticket_qty ?? null;
                            $ticketType = $package->ticketFare?->ticket_type?->value;
                            $ticketDisplay = $routeName . ' | ' . strtoupper($ticketType ?? '?') . ' | SAR ' . number_format($package->ticketFare?->selling_fare ?? 0, 0);
                            if ($ticketType === 'offer') {
                                $ticketDisplay .= ' | SAR ' . number_format($package->ticketFare?->offer_price ?? 0, 0);
                            }
                            if ($ticketType === 'group' && $seats) {
                                $ticketDisplay .= ' | ' . $seats . ' seats';
                            }
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-800 font-medium">{{ $package->package_name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $ticketDisplay }}</td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">@currency($package->regular_price, 0)</td>
                            <td class="px-3 py-2 text-right text-slate-600">
                                @if($package->offer_price)
                                    @currency($package->offer_price, 0)
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($package->bookings_count > 0)
                                    <button class="text-xs text-slate-400 cursor-not-allowed mr-3" title="Has existing bookings" disabled>Edit</button>
                                    <button class="text-xs text-red-400 cursor-not-allowed" title="Has existing bookings" disabled>Delete</button>
                                @else
                                    <button onclick="editPackage({{ $package->id }})" class="text-xs text-slate-600 hover:text-slate-800 mr-3">Edit</button>
                                    <form method="POST" action="{{ route('packages.destroy', $package->id) }}" onsubmit="return confirm('Are you sure you want to delete this package?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                    </form>
                                @endif
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
            {{ $packages->links() }}
        </div>
    </div>
</div>

<!-- Modal -->
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
        <form id="packageForm" method="POST" action="{{ route('packages.store') }}">
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
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Regular Price (SAR) *</label>
                    <input type="number" id="modalRegularPrice" name="regular_price" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-slate-50" min="0" step="any" required readonly>
                </div>
                <div id="modalOfferPriceContainer" class="hidden">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR)</label>
                    <input type="number" id="modalOfferPrice" name="offer_price" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0" step="any">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge (SAR)</label>
                    <input type="number" id="modalServiceCharge" name="service_charge" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0" step="any" value="0">
                </div>
            </div>

            <div class="mt-4 p-4 bg-slate-50 rounded-lg">
                <p class="text-sm text-slate-600">
                    <span class="font-medium">Visa Selling Price (Latest):</span>
                    <span class="text-slate-800 font-medium">
                        @if($latestVisa)
                            @currency($latestVisa->selling_price, 0)
                        @else
                            Not configured
                        @endif
                    </span>
                </p>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" onclick="hidePackageModal()" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
const ticketFares = @json($ticketFares);
const latestVisaPrice = {{ $latestVisa?->selling_price ?? 0 }};
const packages = @json($packagesArray);
const usedFareIds = @json($usedFareIds);

function buildDisplay(fare) {
    let disp = fare.route + ' | ' + fare.ticket_type.toUpperCase() + ' | SAR ' + fare.selling_fare;
    if (fare.ticket_type === 'offer') {
        disp += ' | SAR ' + fare.offer_price;
    }
    if (fare.ticket_type === 'group' && fare.seats) {
        disp += ' | ' + fare.seats + ' seats';
    }
    return disp;
}

function showPackageModal() {
    document.getElementById('packageModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Add Package';
    document.getElementById('packageId').value = '';
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('packageForm').action = '{{ route("packages.store") }}';
    document.getElementById('packageName').value = '';
    document.getElementById('modalTicketTypeSelect').value = '';
    document.getElementById('modalRegularPrice').value = '';
    document.getElementById('modalOfferPrice').value = '';
    document.getElementById('modalOfferPriceContainer').classList.add('hidden');
    document.getElementById('modalServiceCharge').value = '0';

    populateModalTickets();
}

function hidePackageModal() {
    document.getElementById('packageModal').classList.add('hidden');
}

function populateModalTickets(includeFareId = null) {
    const select = document.getElementById('modalTicketSelect');
    const typeSelect = document.getElementById('modalTicketTypeSelect');
    const selectedType = typeSelect.value;

    select.innerHTML = '<option value="">Select Ticket</option>';
    ticketFares.forEach(fare => {
        if (selectedType && fare.ticket_type !== selectedType) return;
        if (usedFareIds.includes(fare.id) && fare.id !== includeFareId) return;
        const option = document.createElement('option');
        option.value = fare.id;
        option.dataset.ticketType = fare.ticket_type;
        option.dataset.sellingFare = fare.selling_fare;
        option.dataset.offerPrice = fare.offer_price;
        option.textContent = buildDisplay(fare);
        select.appendChild(option);
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

    document.getElementById('modalRegularPrice').value = (sellingFare + latestVisaPrice).toFixed(6);

    if (ticketType === 'offer') {
        document.getElementById('modalOfferPriceContainer').classList.remove('hidden');
        document.getElementById('modalOfferPrice').value = (offerFare + latestVisaPrice).toFixed(6);
    } else {
        document.getElementById('modalOfferPriceContainer').classList.add('hidden');
        document.getElementById('modalOfferPrice').value = '';
    }
}

function editPackage(id) {
    const pkg = packages.find(p => p.id === id);
    if (!pkg) return;
    if (pkg.is_locked) {
        alert('This package cannot be edited because it has existing bookings.');
        return;
    }

    document.getElementById('packageModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Edit Package';
    document.getElementById('packageId').value = pkg.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('packageForm').action = '/packages/' + id;
    document.getElementById('packageName').value = pkg.package_name;

    document.getElementById('modalServiceCharge').value = pkg.service_charge ?? 0;

    populateModalTickets(pkg.ticket_fare_id);
    document.getElementById('modalTicketSelect').value = pkg.ticket_fare_id;
    calculateModalPrices();
}

document.getElementById('modalTicketTypeSelect').addEventListener('change', populateModalTickets);
document.getElementById('modalTicketSelect').addEventListener('change', calculateModalPrices);
</script>
@endsection