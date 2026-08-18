@extends('layouts.app')
@section('title', 'Additional Ticket Confirmation')
@section('content')
<div class="max-w-4xl mx-auto py-6" x-data="{}">
    <div id="confirmationContent" class="space-y-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="mb-6 pb-4 border-b border-slate-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-800">Additional Ticket Confirmation</h2>
                        <p class="text-slate-500 text-sm mt-1">Invoice ID: <span id="invoiceId">-</span> (<span id="invoiceNo">-</span>)</p>
                    </div>
                    <span id="statusBadge" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                <div><span class="text-slate-500 text-sm">Customer</span><p class="text-slate-800 font-medium" id="customerName">-</p></div>
                <div><span class="text-slate-500 text-sm">Mobile</span><p class="text-slate-800 font-medium" id="customerMobile">-</p></div>
                <div><span class="text-slate-500 text-sm">Branch</span><p class="text-slate-800 font-medium" id="branch">-</p></div>
                <div><span class="text-slate-500 text-sm">Passengers</span><p class="text-slate-800 font-medium" id="passengerCount">-</p></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Passengers</h3>
            <div id="passengerList" class="space-y-3"></div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-200 flex gap-3">
            <a href="{{ route('dashboard') }}" class="px-6 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium inline-block">Back to Dashboard</a>
        </div>
    </div>

    <div id="notFound" class="hidden bg-white rounded-xl shadow-lg p-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h2 class="text-xl font-semibold text-slate-800 mb-2">Request Not Found</h2>
        <p class="text-slate-500 mb-6">The additional ticket confirmation request you're looking for could not be found.</p>
        <a href="{{ route('dashboard') }}" class="px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium inline-block">Go to Dashboard</a>
    </div>

    <div id="toastContainer" class="fixed top-4 right-4 z-[70] space-y-2"></div>

    <div id="processConfirmationModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" onclick="closeProcessConfirmationModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="text-xl font-semibold text-slate-800">Process Confirmation</h3>
                    <p class="text-slate-500 text-sm mt-1" id="modalPassengerName"></p>
                </div>
                <button onclick="closeProcessConfirmationModal()" class="text-slate-400 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Passenger Info</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div><span class="text-slate-500 text-xs">Passport No.</span><p class="text-slate-800 font-medium" id="infoPassport">-</p></div>
                    <div><span class="text-slate-500 text-xs">Mobile</span><p class="text-slate-800 font-medium" id="infoMobile">-</p></div>
                    <div><span class="text-slate-500 text-xs">Type</span><p class="text-slate-800 font-medium" id="infoType">-</p></div>
                    <div><span class="text-slate-500 text-xs">Gender</span><p class="text-slate-800 font-medium" id="infoGender">-</p></div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Ticket Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type</label>
                        <select id="inputTicketType" onchange="handleFilterChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="regular">Regular</option>
                            <option value="offer">Offer</option>
                            <option value="group">Group</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                        <select id="inputRouteType" onchange="handleFilterChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white" required>
                            <option value="">Select</option>
                            <option value="oneway_inbound">One Way-Inbound</option>
                            <option value="oneway_outbound">One Way-Outbound</option>
                            <option value="round">Round</option>
                            <option value="multi_city">Multi City</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                        <select id="inputFlightType" onchange="handleFilterChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white" required>
                            <option value="">Select</option>
                            <option value="direct">Direct</option>
                            <option value="transit">Transit</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket *</label>
                        <select id="inputTicketFare" onchange="handleTicketSelect()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white" required>
                            <option value="">Select Ticket</option>
                        </select>
                    </div>
                    <div id="fieldUpDate">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date *</label>
                        <input type="text" id="inputUpDate" placeholder="DD-MMM-YY" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div id="fieldDownDate">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date *</label>
                        <input type="text" id="inputDownDate" placeholder="DD-MMM-YY" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">PNR *</label>
                        <input type="text" id="inputPnr" placeholder="Enter PNR" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Number *</label>
                        <input type="text" id="inputTicketNumber" placeholder="Enter Ticket Number" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Issue Date *</label>
                        <input type="text" id="inputTravelDate" placeholder="DD-MMM-YY" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Agent *</label>
                        <select id="inputAgent" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white" required>
                            <option value="">Select Agent</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Travel Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route</label>
                        <input type="text" id="inputTravelRoute" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
                        <input type="text" id="inputTravelAirline" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Class</label>
                        <input type="text" id="inputTravelClass" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Passenger Type</label>
                        <input type="text" id="inputTravelPassengerType" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Fare Calculation</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div x-show="$store.currency.mode === 'SAR' || $store.currency.mode === undefined" x-cloak>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR) *</label>
                            <input type="number" id="inputSellingFare" min="0" step="0.000001" oninput="syncSellingFareFromSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div x-show="$store.currency.mode === 'BDT'" x-cloak>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (BDT) *</label>
                            <input type="number" id="inputSellingFareBdt" min="0" step="0.000001" oninput="syncSellingFareFromBdt()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                            <input type="number" id="inputSellingFareReadonly" min="0" step="0.000001" readonly class="w-full mt-1 px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <div x-show="$store.currency.mode === 'SAR' || $store.currency.mode === undefined" x-cloak>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (SAR) *</label>
                            <input type="number" id="inputNetFare" min="0" step="0.000001" oninput="syncNetFareFromSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div x-show="$store.currency.mode === 'BDT'" x-cloak>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (BDT) *</label>
                            <input type="number" id="inputNetFareBdt" min="0" step="0.000001" oninput="syncNetFareFromBdt()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                            <input type="number" id="inputNetFareReadonly" min="0" step="0.000001" readonly class="w-full mt-1 px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm">
                        </div>
                    </div>
                    <div id="offerPriceSection" class="hidden">
                        <div x-show="$store.currency.mode === 'SAR' || $store.currency.mode === undefined" x-cloak>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR) *</label>
                            <input type="number" id="inputOfferPrice" min="0" step="0.000001" oninput="syncOfferPriceFromSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div x-show="$store.currency.mode === 'BDT'" x-cloak>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (BDT) *</label>
                            <input type="number" id="inputOfferPriceBdt" min="0" step="0.000001" oninput="syncOfferPriceFromBdt()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                            <input type="number" id="inputOfferPriceReadonly" min="0" step="0.000001" readonly class="w-full mt-1 px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Baggage Info</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div id="baggageInboundSection">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Baggage (KG)</label>
                        <input type="text" id="inputBaggageInbound" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500">
                    </div>
                    <div id="baggageOutboundSection">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Baggage (KG)</label>
                        <input type="text" id="inputBaggageOutbound" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500">
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Ticket Options</h4>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" id="inputNonRefundable" disabled class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                        Non-Refundable
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" id="inputNonExchangeable" disabled class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                        Non-Exchangeable
                    </label>
                </div>
            </div>

            <div class="flex gap-3">
                <button onclick="confirmProcess()" id="btnConfirm" class="flex-1 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">Confirm</button>
                <button onclick="closeProcessConfirmationModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
.modal-overlay { transition: opacity 0.2s ease; }
.modal-content { transition: transform 0.2s ease, opacity 0.2s ease; }
.toast { transition: transform 0.3s ease, opacity 0.3s ease; }
</style>

@push('scripts')
<script>
const bookingId = {{ $id }};
let allRequests = [];
let currentTicketRequestId = null;
let allTicketFares = [];
let selectedTicketFareId = null;

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
}

function loadConfirmation() {
    fetch('/bookings/' + bookingId + '/ticket-requests?type=additional', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
    })
    .then(res => res.json())
    .then(requests => {
        allRequests = requests;
        if (!requests.length) {
            showNotFound();
            return;
        }
        const first = requests[0];
        const booking = first.booking || {};
        const customer = booking.customer || {};
        const branch = booking.booking_branch || booking.bookingBranch || {};

        document.getElementById('invoiceId').textContent = booking.id || '-';
        document.getElementById('invoiceNo').textContent = booking.invoice_id || '-';
        document.getElementById('customerName').textContent = customer.name || '-';
        document.getElementById('customerMobile').textContent = customer.mobile_no || '-';
        document.getElementById('branch').textContent = branch.name || '-';
        document.getElementById('passengerCount').textContent = [...new Set(requests.map(r => r.passenger_id))].length;

        renderConfirmation(requests);
        loadAgents();
    });
}

function renderConfirmation(requests) {
    const passengerListEl = document.getElementById('passengerList');
    const grouped = {};
    requests.forEach(r => {
        if (!grouped[r.passenger_id]) {
            grouped[r.passenger_id] = { passenger: r.passenger, tickets: [] };
        }
        grouped[r.passenger_id].tickets.push(r);
    });

    const statusCounts = { pending: 0, processed: 0, rejected: 0 };
    requests.forEach(r => statusCounts[r.status] = (statusCounts[r.status] || 0) + 1);
    const statusBadge = document.getElementById('statusBadge');
    if (statusCounts.pending === 0 && statusCounts.processed > 0) {
        statusBadge.textContent = 'Processed';
        statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700';
    } else if (statusCounts.pending === 0 && statusCounts.rejected > 0) {
        statusBadge.textContent = 'Rejected';
        statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700';
    } else {
        statusBadge.textContent = 'Pending';
        statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700';
    }

    passengerListEl.innerHTML = Object.values(grouped).map(g => {
        const p = g.passenger || {};
        const optionMap = { up: 'Inbound', down: 'Outbound', both: 'Both' };
        return `
            <div class="bg-slate-50 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <span class="font-medium text-slate-800">${escapeHtml(p.first_name ? p.first_name + ' ' + p.last_name : '-')}</span>
                        <span class="text-slate-500 text-sm ml-2">(${escapeHtml(p.passport_no || '-')})</span>
                    </div>
                    <span class="text-sm text-slate-500">${g.tickets.length} ticket(s)</span>
                </div>
                <div class="space-y-3">
                    ${g.tickets.map(r => {
                        const isProcessed = r.status === 'processed';
                        const isRejected = r.status === 'rejected';
                        const badgeClass = isProcessed ? 'bg-green-100 text-green-700' : isRejected ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700';
                        const statusLabel = isProcessed ? 'Processed' : isRejected ? 'Rejected' : 'Pending';
                        return `
                        <div class="bg-white rounded-lg border border-slate-200 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${badgeClass}">${statusLabel}</span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-3">
                                <div class="text-sm"><span class="text-slate-500">Ticket Option: </span><span class="text-slate-800 font-medium">${optionMap[r.ticket_option] || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Probable (Inbound): </span><span class="text-slate-800 font-medium">${formatDate(r.probable_date_up)}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Probable (Outbound): </span><span class="text-slate-800 font-medium">${formatDate(r.probable_date_down)}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Visa Expiry: </span><span class="text-slate-800 font-medium">${formatDate(r.visa_expiry_date)}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Requested: </span><span class="text-slate-800 font-medium">${formatDate(r.requested_at)}</span></div>
                            </div>
                            <div class="flex gap-3">
                                <button onclick="rejectAddTicket(${r.id})" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition font-medium" ${isProcessed || isRejected ? 'disabled style="opacity:50;cursor:not-allowed"' : ''}>Reject</button>
                                <button onclick="processConfirmation(${r.id})" class="px-4 py-2 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700 transition font-medium" ${isProcessed || isRejected ? 'disabled style="opacity:50;cursor:not-allowed"' : ''}>Process Confirmation</button>
                            </div>
                        </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }).join('');
}

function processConfirmation(ticketRequestId) {
    currentTicketRequestId = ticketRequestId;
    const r = allRequests.find(req => req.id === ticketRequestId);
    if (!r) return;
    const p = r.passenger || {};

    document.getElementById('modalPassengerName').textContent = (p.first_name || '') + ' ' + (p.last_name || '') + ' (' + (p.passport_no || '-') + ')';
    document.getElementById('infoPassport').textContent = p.passport_no || '-';
    document.getElementById('infoMobile').textContent = p.mobile_no || '-';
    document.getElementById('infoType').textContent = ({ adult: 'Adult', child: 'Child', infant: 'Infant' })[p.passenger_type] || '-';
    document.getElementById('infoGender').textContent = ({ male: 'Male', female: 'Female' })[p.gender] || '-';

    document.getElementById('inputUpDate').value = r.probable_date_up ? r.probable_date_up.split('T')[0] : '';
    document.getElementById('inputDownDate').value = r.probable_date_down ? r.probable_date_down.split('T')[0] : '';
    document.getElementById('inputTravelDate').value = (() => { const d = new Date(); const ms = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; return d.getDate() + '-' + ms[d.getMonth()] + '-' + String(d.getFullYear()).slice(-2); })();
    document.getElementById('inputPnr').value = '';
    document.getElementById('inputTicketNumber').value = '';
    document.getElementById('inputTravelRoute').value = '';
    document.getElementById('inputTravelAirline').value = '';
    document.getElementById('inputTravelClass').value = '';
    document.getElementById('inputTravelPassengerType').value = '';
    document.getElementById('inputSellingFare').value = '';
    document.getElementById('inputSellingFareBdt').value = '';
    document.getElementById('inputSellingFareReadonly').value = '';
    document.getElementById('inputNetFare').value = '';
    document.getElementById('inputNetFareBdt').value = '';
    document.getElementById('inputNetFareReadonly').value = '';
    document.getElementById('inputOfferPrice').value = '';
    document.getElementById('inputOfferPriceBdt').value = '';
    document.getElementById('inputOfferPriceReadonly').value = '';
    document.getElementById('inputBaggageInbound').value = '';
    document.getElementById('inputBaggageOutbound').value = '';
    document.getElementById('inputNonRefundable').checked = false;
    document.getElementById('inputNonExchangeable').checked = false;
    document.getElementById('offerPriceSection').classList.add('hidden');

    const agentSelect = document.getElementById('inputAgent');
    agentSelect.value = '';
    agentSelect.disabled = false;

    const originalRt = ({ up: 'oneway_inbound', down: 'oneway_outbound', both: 'round' })[r.ticket_option] || p.ticket_fare?.route?.route_type || '';
    const originalTt = p.ticket_fare?.ticket_type || '';
    const originalFt = p.ticket_fare?.route?.flight_type || '';

    const rtSelect = document.getElementById('inputRouteType');
    rtSelect.value = originalRt;
    rtSelect.disabled = originalRt === 'oneway_outbound';

    const ttSelect = document.getElementById('inputTicketType');
    ttSelect.value = originalTt;

    const ftSelect = document.getElementById('inputFlightType');
    ftSelect.value = originalFt;

    selectedTicketFareId = null;

    loadTicketFares({
        route_type: originalRt,
        ticket_type: originalTt,
        flight_type: originalFt,
    });

    applyRouteType();

    document.getElementById('processConfirmationModal').classList.remove('hidden');
}

function closeProcessConfirmationModal() {
    document.getElementById('processConfirmationModal').classList.add('hidden');
    currentTicketRequestId = null;
}

function loadAgents() {
    fetch('/ticket-requests/agents', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
    })
    .then(res => res.json())
    .then(agents => {
        const select = document.getElementById('inputAgent');
        select.innerHTML = '<option value="">Select Agent</option>' +
            agents.map(a => '<option value="' + a.id + '">' + escapeHtml(a.name) + '</option>').join('');
    });
}

function loadTicketFares(filters = {}) {
    const params = new URLSearchParams();
    if (filters.route_type) params.append('route_type', filters.route_type);
    if (filters.ticket_type) params.append('ticket_type', filters.ticket_type);
    if (filters.flight_type) params.append('flight_type', filters.flight_type);

    fetch('/ticket-fares/options?' + params.toString(), {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
    })
    .then(res => res.json())
    .then(fares => {
        allTicketFares = fares;
        const select = document.getElementById('inputTicketFare');
        const currentVal = select.value || selectedTicketFareId;
        select.innerHTML = '<option value="">Select Ticket</option>' +
            fares.map(f => {
                const route = f.route || {};
                const airline = f.airline || {};
                const cls = f.airline_class?.class || {};
                const routeLabel = formatRoute(route);
                return '<option value="' + f.id + '">' +
                    escapeHtml(f.ticket_type || '') + ' - ' +
                    escapeHtml(routeLabel) + ' - ' +
                    escapeHtml(airline.name || '') + ' - ' +
                    escapeHtml(cls.name || '') +
                    '</option>';
            }).join('');
        if (currentVal) select.value = currentVal;
    });
}

function handleFilterChange() {
    clearTicketFields();
    const filters = {
        route_type: document.getElementById('inputRouteType').value,
        ticket_type: document.getElementById('inputTicketType').value,
        flight_type: document.getElementById('inputFlightType').value,
    };
    loadTicketFares(filters);
    applyRouteType();
}

function clearTicketFields() {
    selectedTicketFareId = null;
    const select = document.getElementById('inputTicketFare');
    if (select) select.value = '';
    document.getElementById('inputTravelRoute').value = '';
    document.getElementById('inputTravelAirline').value = '';
    document.getElementById('inputTravelClass').value = '';
    document.getElementById('inputTravelPassengerType').value = '';
    document.getElementById('inputSellingFare').value = '';
    document.getElementById('inputSellingFareBdt').value = '';
    document.getElementById('inputSellingFareReadonly').value = '';
    document.getElementById('inputNetFare').value = '';
    document.getElementById('inputNetFareBdt').value = '';
    document.getElementById('inputNetFareReadonly').value = '';
    document.getElementById('inputOfferPrice').value = '';
    document.getElementById('inputOfferPriceBdt').value = '';
    document.getElementById('inputOfferPriceReadonly').value = '';
    document.getElementById('inputBaggageInbound').value = '';
    document.getElementById('inputBaggageOutbound').value = '';
    document.getElementById('inputNonRefundable').checked = false;
    document.getElementById('inputNonExchangeable').checked = false;
    document.getElementById('offerPriceSection').classList.add('hidden');
}

function handleTicketSelect() {
    const fareId = document.getElementById('inputTicketFare').value;
    selectedTicketFareId = fareId || null;

    if (!fareId) {
        clearTicketFields();
        return;
    }

    const fare = allTicketFares.find(f => f.id == fareId);
    if (!fare) return;

    const route = fare.route || {};
    const airline = fare.airline || {};
    const cls = fare.airline_class?.class || {};

    document.getElementById('inputTravelRoute').value = formatRoute(route);
    document.getElementById('inputTravelAirline').value = airline.name || '';
    document.getElementById('inputTravelClass').value = cls.name || '';

    document.getElementById('inputSellingFare').value = fare.selling_fare || 0;
    document.getElementById('inputNetFare').value = fare.net_fare || 0;
    document.getElementById('inputSellingFareReadonly').value = fare.selling_fare || 0;
    document.getElementById('inputNetFareReadonly').value = fare.net_fare || 0;

    const r1 = window.__currencyRate || 0;
    if (r1 > 0) {
        const sellingBdt = document.getElementById('inputSellingFareBdt');
        const netBdt = document.getElementById('inputNetFareBdt');
        if (sellingBdt) sellingBdt.value = Math.round(parseFloat(fare.selling_fare || 0) * r1);
        if (netBdt) netBdt.value = Math.round(parseFloat(fare.net_fare || 0) * r1);
    }

    if (fare.ticket_type === 'offer' && fare.offer_price) {
        document.getElementById('offerPriceSection').classList.remove('hidden');
        document.getElementById('inputOfferPrice').value = fare.offer_price || 0;
        document.getElementById('inputOfferPriceReadonly').value = fare.offer_price || 0;
        if (r1 > 0) {
            const offerBdt = document.getElementById('inputOfferPriceBdt');
            if (offerBdt) offerBdt.value = Math.round(parseFloat(fare.offer_price || 0) * r1);
        }
    } else {
        document.getElementById('offerPriceSection').classList.add('hidden');
        document.getElementById('inputOfferPrice').value = '';
        document.getElementById('inputOfferPriceReadonly').value = '';
    }

    const baggageAllowances = fare.baggage_allowances || [];
    const req = allRequests.find(x => x.id === currentTicketRequestId);
    const passengerType = req?.passenger?.passenger_type || 'adult';
    document.getElementById('inputTravelPassengerType').value = passengerType;
    const inboundBaggage = baggageAllowances.find(b => b.travel_direction === 'inbound' && b.passenger_type === passengerType);
    const outboundBaggage = baggageAllowances.find(b => b.travel_direction === 'outbound' && b.passenger_type === passengerType);
    document.getElementById('inputBaggageInbound').value = inboundBaggage ? inboundBaggage.allowance : '';
    document.getElementById('inputBaggageOutbound').value = outboundBaggage ? outboundBaggage.allowance : '';

    document.getElementById('inputNonRefundable').checked = !fare.is_refundable;
    document.getElementById('inputNonExchangeable').checked = !fare.is_exchangeable;
}

function currencyRate() {
    return parseFloat(window.__currencyRate) || 0;
}

function syncSellingFareFromSar() {
    const rate = currencyRate();
    if (rate <= 0) return;
    const sar = parseFloat(document.getElementById('inputSellingFare').value) || 0;
    const bdt = Math.round(sar * rate);
    document.getElementById('inputSellingFareBdt').value = bdt;
    document.getElementById('inputSellingFareReadonly').value = sar;
}

function syncSellingFareFromBdt() {
    const rate = currencyRate();
    if (rate <= 0) return;
    const bdt = parseFloat(document.getElementById('inputSellingFareBdt').value) || 0;
    const sar = (Math.round(bdt / rate * 1e6) / 1e6).toFixed(6);
    document.getElementById('inputSellingFare').value = sar;
    document.getElementById('inputSellingFareReadonly').value = sar;
}

function syncNetFareFromSar() {
    const rate = currencyRate();
    if (rate <= 0) return;
    const sar = parseFloat(document.getElementById('inputNetFare').value) || 0;
    const bdt = Math.round(sar * rate);
    document.getElementById('inputNetFareBdt').value = bdt;
    document.getElementById('inputNetFareReadonly').value = sar;
}

function syncNetFareFromBdt() {
    const rate = currencyRate();
    if (rate <= 0) return;
    const bdt = parseFloat(document.getElementById('inputNetFareBdt').value) || 0;
    const sar = (Math.round(bdt / rate * 1e6) / 1e6).toFixed(6);
    document.getElementById('inputNetFare').value = sar;
    document.getElementById('inputNetFareReadonly').value = sar;
}

function syncOfferPriceFromSar() {
    const rate = currencyRate();
    if (rate <= 0) return;
    const sar = parseFloat(document.getElementById('inputOfferPrice').value) || 0;
    const bdt = Math.round(sar * rate);
    document.getElementById('inputOfferPriceBdt').value = bdt;
    document.getElementById('inputOfferPriceReadonly').value = sar;
}

function syncOfferPriceFromBdt() {
    const rate = currencyRate();
    if (rate <= 0) return;
    const bdt = parseFloat(document.getElementById('inputOfferPriceBdt').value) || 0;
    const sar = (Math.round(bdt / rate * 1e6) / 1e6).toFixed(6);
    document.getElementById('inputOfferPrice').value = sar;
    document.getElementById('inputOfferPriceReadonly').value = sar;
}

function applyRouteType() {
    const rt = document.getElementById('inputRouteType').value;
    document.getElementById('fieldUpDate').classList.toggle('hidden', rt === 'oneway_outbound');
    document.getElementById('fieldDownDate').classList.toggle('hidden', rt === 'oneway_inbound');
    document.getElementById('baggageInboundSection').classList.toggle('hidden', rt === 'oneway_outbound');
    document.getElementById('baggageOutboundSection').classList.toggle('hidden', rt === 'oneway_inbound');
}

function confirmProcess() {
    if (!currentTicketRequestId) return;

    const payload = {
        ticket_fare_id: document.getElementById('inputTicketFare').value || selectedTicketFareId,
        pnr: document.getElementById('inputPnr').value || null,
        ticket_number: document.getElementById('inputTicketNumber').value || null,
        inbound_date: document.getElementById('inputUpDate').value || null,
        outbound_date: document.getElementById('inputDownDate').value || null,
        issued_date: document.getElementById('inputTravelDate').value || null,
        ticket_agent_id: document.getElementById('inputAgent').value || null,
    };

    if (!payload.ticket_fare_id) {
        showToast('Please select a ticket', 'error');
        return;
    }

    fetch('/ticket-requests/' + currentTicketRequestId + '/process-additional', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Additional ticket issued successfully!', 'success');
            closeProcessConfirmationModal();
            loadConfirmation();
        } else {
            showToast(data.message || 'Failed to process', 'error');
        }
    })
    .catch(err => {
        showToast('Error processing request', 'error');
    });
}

function rejectAddTicket(ticketRequestId) {
    if (!confirm('Are you sure you want to reject this additional ticket request?')) return;

    fetch('/ticket-requests/' + ticketRequestId + '/reject', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Additional ticket request rejected', 'info');
            loadConfirmation();
        } else {
            showToast(data.message || 'Failed to reject', 'error');
        }
    });
}

function showNotFound() {
    document.getElementById('confirmationContent').classList.add('hidden');
    document.getElementById('notFound').classList.remove('hidden');
}

function formatDate(val) {
    if (!val) return '-';
    const parts = val.split('T')[0].split('-');
    if (parts.length === 3) {
        const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        if (!isNaN(d.getTime())) return d.toLocaleDateString();
    }
    const d = new Date(val);
    if (!isNaN(d.getTime())) return d.toLocaleDateString();
    return val;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatRoute(route) {
    if (!route) return '-';
    const rt = route.route_type || '';
    if (rt === 'multi_city' && route.multi_segments?.length) {
        return route.multi_segments.map(s => (s.from_city?.code || '?') + '-' + (s.to_city?.code || '?')).join(', ');
    }
    const from = route.from_city?.code || '?';
    const to = route.to_city?.code || '?';
    const ret = route.return_city?.code || '';
    if (rt === 'round' && ret) return from + '-' + to + '-' + ret;
    return from + '-' + to;
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast px-4 py-3 rounded-lg shadow-lg text-white ' + (
        type === 'success' ? 'bg-green-600' :
        type === 'error' ? 'bg-red-600' :
        'bg-slate-700'
    );
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

loadConfirmation();
</script>
@endpush
@endsection
