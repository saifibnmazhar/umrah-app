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

    <livewire:package.package-list-table />

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
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="modalDoubleTicketCheck" name="is_double_ticket" value="1" class="w-4 h-4 rounded border-slate-300 text-slate-700 focus:ring-slate-400">
                    <span class="text-sm font-medium text-slate-700">Double Ticket</span>
                </label>
            </div>

            <div id="modalSingleTicketFields" class="mt-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Ticket *</label>
                <select id="modalTicketSelect" name="ticket_fare_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="">Select Ticket</option>
                </select>
            </div>

            <div id="modalDoubleTicketFields" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Inbound *</label>
                    <select id="modalTicketInboundSelect" name="ticket_fare_inbound_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Inbound Ticket</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Outbound *</label>
                    <select id="modalTicketOutboundSelect" name="ticket_fare_outbound_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Outbound Ticket</option>
                    </select>
                </div>
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
const inboundFares = @json($inboundFares);
const outboundFares = @json($outboundFares);
const latestVisaPrice = {{ $latestVisa?->selling_price ?? 0 }};
const packages = @json($packagesArray);
const usedFareIds = @json($usedFareIds);
</script>

<script>
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
    document.getElementById('modalDoubleTicketCheck').checked = false;

    populateModalTickets();
    toggleModalDoubleTicket();
}

function hidePackageModal() {
    document.getElementById('packageModal').classList.add('hidden');
}

function toggleModalDoubleTicket() {
    const isDouble = document.getElementById('modalDoubleTicketCheck').checked;
    document.getElementById('modalSingleTicketFields').classList.toggle('hidden', isDouble);
    document.getElementById('modalDoubleTicketFields').classList.toggle('hidden', !isDouble);

    document.getElementById('modalSingleTicketFields').querySelectorAll('select, input').forEach(el => {
        if (el.id !== 'modalTicketSelect') return;
        el.required = !isDouble;
    });
    document.getElementById('modalDoubleTicketFields').querySelectorAll('select').forEach(el => el.required = isDouble);

    if (isDouble) {
        document.getElementById('modalTicketSelect').value = '';
        calculateModalPrices();
    } else {
        document.getElementById('modalTicketInboundSelect').value = '';
        document.getElementById('modalTicketOutboundSelect').value = '';
        calculateModalPrices();
    }
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

    const inboundSelect = document.getElementById('modalTicketInboundSelect');
    inboundSelect.innerHTML = '<option value="">Select Inbound Ticket</option>';
    inboundFares.forEach(fare => {
        if (selectedType && fare.ticket_type !== selectedType) return;
        const option = document.createElement('option');
        option.value = fare.id;
        option.dataset.ticketType = fare.ticket_type;
        option.dataset.sellingFare = fare.selling_fare;
        option.dataset.offerPrice = fare.offer_price;
        option.textContent = buildDisplay(fare);
        inboundSelect.appendChild(option);
    });

    const outboundSelect = document.getElementById('modalTicketOutboundSelect');
    outboundSelect.innerHTML = '<option value="">Select Outbound Ticket</option>';
    outboundFares.forEach(fare => {
        if (selectedType && fare.ticket_type !== selectedType) return;
        const option = document.createElement('option');
        option.value = fare.id;
        option.dataset.ticketType = fare.ticket_type;
        option.dataset.sellingFare = fare.selling_fare;
        option.dataset.offerPrice = fare.offer_price;
        option.textContent = buildDisplay(fare);
        outboundSelect.appendChild(option);
    });
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

        document.getElementById('modalRegularPrice').value = totalFare > 0 ? (totalFare + latestVisaPrice).toFixed(6) : '';

        const inboundType = hasInbound ? inboundOption.dataset.ticketType : null;
        const outboundType = hasOutbound ? outboundOption.dataset.ticketType : null;
        const serviceCharge = parseFloat(document.getElementById('modalServiceCharge')?.value || 0);
        if (inboundType === 'offer' && outboundType === 'offer' && hasInbound && hasOutbound) {
            const inboundOffer = parseFloat(inboundOption.dataset.offerPrice) || 0;
            const outboundOffer = parseFloat(outboundOption.dataset.offerPrice) || 0;
            document.getElementById('modalOfferPrice').value = (inboundOffer + outboundOffer + latestVisaPrice).toFixed(6);
            document.getElementById('modalOfferPriceContainer').classList.remove('hidden');
        } else {
            document.getElementById('modalOfferPriceContainer').classList.add('hidden');
            document.getElementById('modalOfferPrice').value = '';
        }
        return;
    }

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

    const isDouble = pkg.is_double_ticket || false;
    document.getElementById('modalDoubleTicketCheck').checked = isDouble;
    toggleModalDoubleTicket();

    populateModalTickets(pkg.ticket_fare_id);
    document.getElementById('modalTicketSelect').value = pkg.ticket_fare_id;
    document.getElementById('modalTicketInboundSelect').value = pkg.ticket_fare_inbound_id || '';
    document.getElementById('modalTicketOutboundSelect').value = pkg.ticket_fare_outbound_id || '';
    calculateModalPrices();
}

document.getElementById('modalDoubleTicketCheck').addEventListener('change', toggleModalDoubleTicket);
document.getElementById('modalTicketTypeSelect').addEventListener('change', populateModalTickets);
document.getElementById('modalTicketSelect').addEventListener('change', calculateModalPrices);
document.getElementById('modalTicketInboundSelect').addEventListener('change', calculateModalPrices);
document.getElementById('modalTicketOutboundSelect').addEventListener('change', calculateModalPrices);
</script>
@endsection