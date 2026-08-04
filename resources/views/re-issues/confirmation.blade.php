@extends('layouts.app')
@section('title', 'Re-Issue Confirmation')
@section('content')
<div class="max-w-4xl mx-auto py-6">
    <div id="confirmationContent" class="space-y-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="mb-6 pb-4 border-b border-slate-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-800">Re-Issue Confirmation</h2>
                        <p class="text-slate-500 text-sm mt-1">Invoice ID: <span id="invoiceId">-</span> (<span id="invoiceNo">-</span>)</p>
                    </div>
                    <span id="statusBadge" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                <div><span class="text-slate-500 text-sm">Customer</span><p class="text-slate-800 font-medium" id="customerName">-</p></div>
                <div><span class="text-slate-500 text-sm">Mobile</span><p class="text-slate-800 font-medium" id="customerMobile">-</p></div>
                <div><span class="text-slate-500 text-sm">Branch</span><p class="text-slate-800 font-medium" id="branch">-</p></div>
                <div><span class="text-slate-500 text-sm">Requested Date</span><p class="text-slate-800 font-medium" id="requestedDate">-</p></div>
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
        <p class="text-slate-500 mb-6">The re-issue confirmation request you're looking for could not be found.</p>
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
                    <div><span class="text-slate-500 text-xs">PNR</span><p class="text-slate-800 font-medium" id="infoPnr">ABCD1234</p></div>
                    <div><span class="text-slate-500 text-xs">Flight Date</span><p class="text-slate-800 font-medium" id="infoFlightDate">2026-05-15</p></div>
                    <div><span class="text-slate-500 text-xs">Route</span><p class="text-slate-800 font-medium" id="infoRoute">DAC-JED-DAC</p></div>
                    <div><span class="text-slate-500 text-xs">Airline</span><p class="text-slate-800 font-medium" id="infoAirline">Saudi Arabian Airlines</p></div>
                    <div><span class="text-slate-500 text-xs">Class</span><p class="text-slate-800 font-medium" id="infoClass">Economy</p></div>
                    <div><span class="text-slate-500 text-xs">Type</span><p class="text-slate-800 font-medium" id="infoType">Adult</p></div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Re-Issue Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date</label>
                        <input type="date" id="inputUpDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date</label>
                        <input type="date" id="inputDownDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Re-issue Date</label>
                        <input type="date" id="inputTravelDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route</label>
                        <select id="inputRoute" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Route</option>
                            <option value="DAC-JED-DAC">DAC-JED-DAC</option>
                            <option value="DAC-MED-DAC">DAC-MED-DAC</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent</label>
                        <select id="inputAgent" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Agent</option>
                            <option value="Agent A">Agent A</option>
                            <option value="Agent B">Agent B</option>
                            <option value="Agent C">Agent C</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Re-Issue Charge</label>
                        <input type="number" id="inputReIssueCharge" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Fare Difference</label>
                        <input type="number" id="inputFareDifference" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Other Costs</label>
                        <input type="number" id="inputOtherCosts" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Total Cost</label>
                        <input type="number" id="inputTotalCost" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge</label>
                        <input type="number" id="inputServiceCharge" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Total Customer Payment</label>
                        <input type="number" id="inputTotalPayment" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method</label>
                        <select id="inputPaymentMethod" onchange="handlePaymentMethodChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Payment Method</option>
                            <option value="Pay to Branch">Pay to Branch</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="bankMethodSection" class="hidden mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Bank Method</label>
                <select id="inputBankMethod" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="">Select Bank Method</option>
                    <option value="Al Rajhi Bank">Al Rajhi Bank</option>
                    <option value="National Commercial Bank">National Commercial Bank</option>
                    <option value="Riyadh Bank">Riyadh Bank</option>
                    <option value="Alinma Bank">Alinma Bank</option>
                </select>
            </div>

            <div id="branchSection" class="hidden mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Select Branch</label>
                <select id="inputBranch" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                    <option value="">Select Branch</option>
                    <option value="Riyadh Branch">Riyadh Branch</option>
                    <option value="Jeddah Branch">Jeddah Branch</option>
                    <option value="Madinah Branch">Madinah Branch</option>
                </select>
            </div>

            <div class="flex gap-3" id="confirmButtons">
                <button onclick="confirmProcess()" id="btnConfirm" class="flex-1 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">Confirm</button>
                <button onclick="closeProcessConfirmationModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>

            <div class="flex gap-3 hidden" id="holdButtons">
                <button onclick="holdProcess()" id="btnHold" class="flex-1 px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition font-medium">Hold</button>
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
const bookingBranchMap = @json($bookingBranches ?? []);
let currentRequest = null;
let currentPassengerIndex = null;
let currentTicketIndex = null;

function loadConfirmation() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id') || '{{ $id }}';

    if (id === null || id === '') {
        showNotFound();
        return;
    }

    const requestId = parseInt(id);
    let reIssueRequests = JSON.parse(localStorage.getItem('reIssueRequests') || '[]');

    const request = reIssueRequests.find(r => r.id === requestId) || reIssueRequests[0];

    if (!request) {
        showNotFound();
        return;
    }

    request.passengers = request.passengers || [];
    const invoiceId = request.invoiceId;
    const others = reIssueRequests.filter(r => r.invoiceId === invoiceId && r.status === 'Pending' && r.id !== request.id);

    if (others.length > 0) {
        others.forEach(o => {
            (o.passengers || []).forEach(op => {
                const ep = request.passengers.find(p => p.name === op.name && p.passport === op.passport);
                if (ep) {
                    const existingTickets = ep.tickets || [];
                    const newTickets = (op.tickets || []).filter(t =>
                        !existingTickets.some(et => et.ticketNumber && et.ticketNumber === t.ticketNumber)
                    );
                    if (newTickets.length > 0) {
                        ep.tickets = [...existingTickets, ...newTickets];
                    }
                } else {
                    request.passengers.push(op);
                }
            });
        });
        reIssueRequests = reIssueRequests.filter(r => !(r.invoiceId === invoiceId && r.status === 'Pending' && r.id !== request.id));
        localStorage.setItem('reIssueRequests', JSON.stringify(reIssueRequests));
    }

    currentRequest = request;
    renderConfirmation(request);
}

function renderConfirmation(request) {
    document.getElementById('invoiceId').textContent = request.invoiceId;
    document.getElementById('invoiceNo').textContent = request.invoiceNo;
    document.getElementById('customerName').textContent = request.customerName || '-';
    document.getElementById('customerMobile').textContent = request.customerMobile || '-';
    document.getElementById('branch').textContent = (bookingBranchMap[request.invoiceId] || request.branch || '-');
    document.getElementById('requestedDate').textContent = new Date(request.requestedAt).toLocaleDateString();
    document.getElementById('passengerCount').textContent = (request.passengers || []).length;

    const statusBadge = document.getElementById('statusBadge');
    statusBadge.textContent = request.status === 'Pending' ? 'Pending' : request.status;
    statusBadge.className = `inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
        request.status === 'Pending' ? 'bg-yellow-100 text-yellow-700' :
        request.status === 'Approved' || request.status === 'Processed' ? 'bg-green-100 text-green-700' :
        request.status === 'Rejected' ? 'bg-red-100 text-red-700' :
        'bg-yellow-100 text-yellow-700'
    }`;

    const passengerListEl = document.getElementById('passengerList');

    function ticketsFor(p) {
        if (Array.isArray(p.tickets) && p.tickets.length > 0) return p.tickets;
        return [{
            ticketNumber: p.ticketNumber || '',
            pnr: p.pnr || '',
            route: p.route || '',
            ticketOption: p.ticketOption || '',
            probableDateUp: p.probableDateUp || '',
            probableDateDown: p.probableDateDown || '',
            visaExpiry: p.visaExpiry || '',
        }];
    }

    passengerListEl.innerHTML = (request.passengers || []).map((p, pIndex) => {
        const tickets = ticketsFor(p);
        return `
            <div class="bg-slate-50 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <span class="font-medium text-slate-800">${escapeHtml(p.name)}</span>
                        <span class="text-slate-500 text-sm ml-2">(${escapeHtml(p.passport)})</span>
                    </div>
                    <span class="text-sm text-slate-500">${tickets.length} ticket(s)</span>
                </div>
                <div class="space-y-3">
                    ${tickets.map((t, tIndex) => `
                        <div class="bg-white rounded-lg border border-slate-200 p-4">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-3">
                                <div class="text-sm"><span class="text-slate-500">Ticket No: </span><span class="text-slate-800 font-medium">${escapeHtml(t.ticketNumber) || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">PNR: </span><span class="text-slate-800 font-medium">${escapeHtml(t.pnr) || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Route: </span><span class="text-slate-800 font-medium">${escapeHtml(t.route) || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Ticket Option: </span><span class="text-slate-800 font-medium">${escapeHtml(t.ticketOption) || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Probable (Inbound): </span><span class="text-slate-800 font-medium">${escapeHtml(t.probableDateUp) || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Probable (Outbound): </span><span class="text-slate-800 font-medium">${escapeHtml(t.probableDateDown) || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">Visa Expiry: </span><span class="text-slate-800 font-medium">${escapeHtml(t.visaExpiry) || '-'}</span></div>
                            </div>
                            ${t.reIssueData ? `<div class="mb-3"><span class="text-sm text-green-600 font-medium">Confirmed: ${escapeHtml(t.reIssueData.travelDate) || ''}${t.reIssueData.route ? ' • ' + escapeHtml(t.reIssueData.route) : ''}</span></div>` : ''}
                            <div class="flex gap-3">
                                <button onclick="rejectReIssue(${pIndex}, ${tIndex})" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition font-medium" ${t.reIssueData ? 'disabled style="opacity:50;cursor:not-allowed"' : ''}>Reject</button>
                                <button onclick="processConfirmation(${pIndex}, ${tIndex})" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition font-medium">Process Confirmation</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }).join('');
}

function processConfirmation(passengerIndex, ticketIndex) {
    const passenger = currentRequest.passengers[passengerIndex];
    if (!passenger) return;

    const tickets = Array.isArray(passenger.tickets) && passenger.tickets.length > 0
        ? passenger.tickets
        : [{
            pnr: passenger.pnr || '',
            route: passenger.route || '',
            probableDateUp: passenger.probableDateUp || '',
            probableDateDown: passenger.probableDateDown || '',
        }];
    const ticket = tickets[ticketIndex];
    if (!ticket) return;

    currentPassengerIndex = passengerIndex;
    currentTicketIndex = ticketIndex;

    document.getElementById('modalPassengerName').textContent = passenger.name + ' (' + passenger.passport + ')';
    document.getElementById('infoPassport').textContent = passenger.passport;
    document.getElementById('infoMobile').textContent = passenger.mobile || '-';
    document.getElementById('infoPnr').textContent = ticket.pnr || 'ABCD1234';
    document.getElementById('infoFlightDate').textContent = ticket.probableDateUp || '2026-05-15';
    document.getElementById('infoRoute').textContent = ticket.route || 'DAC-JED-DAC';
    document.getElementById('infoAirline').textContent = 'Saudi Arabian Airlines';
    document.getElementById('infoClass').textContent = 'Economy';
    document.getElementById('infoType').textContent = 'Adult';

    document.getElementById('inputUpDate').value = ticket.probableDateUp || '';
    document.getElementById('inputDownDate').value = ticket.probableDateDown || '';
    document.getElementById('inputTravelDate').value = '';
    document.getElementById('inputRoute').value = '';
    document.getElementById('inputAgent').value = '';
    document.getElementById('inputReIssueCharge').value = '';
    document.getElementById('inputFareDifference').value = '';
    document.getElementById('inputOtherCosts').value = '';
    document.getElementById('inputTotalCost').value = '';
    document.getElementById('inputServiceCharge').value = '';
    document.getElementById('inputTotalPayment').value = '';
    document.getElementById('inputPaymentMethod').value = '';
    document.getElementById('bankMethodSection').classList.add('hidden');
    document.getElementById('branchSection').classList.add('hidden');
    document.getElementById('confirmButtons').classList.remove('hidden');
    document.getElementById('holdButtons').classList.add('hidden');

    document.getElementById('processConfirmationModal').classList.remove('hidden');
}

function closeProcessConfirmationModal() {
    document.getElementById('processConfirmationModal').classList.add('hidden');
    document.getElementById('inputUpDate').value = '';
    document.getElementById('inputDownDate').value = '';
    document.getElementById('inputTravelDate').value = '';
    document.getElementById('inputRoute').value = '';
    document.getElementById('inputAgent').value = '';
    document.getElementById('inputReIssueCharge').value = '';
    document.getElementById('inputFareDifference').value = '';
    document.getElementById('inputOtherCosts').value = '';
    document.getElementById('inputTotalCost').value = '';
    document.getElementById('inputServiceCharge').value = '';
    document.getElementById('inputTotalPayment').value = '';
    document.getElementById('inputPaymentMethod').value = '';
    document.getElementById('bankMethodSection').classList.add('hidden');
    document.getElementById('branchSection').classList.add('hidden');
    document.getElementById('confirmButtons').classList.remove('hidden');
    document.getElementById('holdButtons').classList.add('hidden');
}

function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('inputPaymentMethod').value;
    const bankMethodSection = document.getElementById('bankMethodSection');
    const branchSection = document.getElementById('branchSection');
    const confirmButtons = document.getElementById('confirmButtons');
    const holdButtons = document.getElementById('holdButtons');

    bankMethodSection.classList.add('hidden');
    branchSection.classList.add('hidden');
    confirmButtons.classList.remove('hidden');
    holdButtons.classList.add('hidden');

    if (paymentMethod === 'Bank Transfer') {
        bankMethodSection.classList.remove('hidden');
    } else if (paymentMethod === 'Pay to Branch') {
        branchSection.classList.remove('hidden');
        confirmButtons.classList.add('hidden');
        holdButtons.classList.remove('hidden');
    }
}

function holdProcess() {
    showToast('Process held successfully!', 'info');
    closeProcessConfirmationModal();
}

function confirmProcess() {
    const reIssueData = {
        upDate: document.getElementById('inputUpDate').value,
        downDate: document.getElementById('inputDownDate').value,
        travelDate: document.getElementById('inputTravelDate').value,
        route: document.getElementById('inputRoute').value,
        agent: document.getElementById('inputAgent').value,
        reIssueCharge: parseFloat(document.getElementById('inputReIssueCharge').value) || 0,
        fareDifference: parseFloat(document.getElementById('inputFareDifference').value) || 0,
        otherCosts: parseFloat(document.getElementById('inputOtherCosts').value) || 0,
        totalCost: parseFloat(document.getElementById('inputTotalCost').value) || 0,
        serviceCharge: parseFloat(document.getElementById('inputServiceCharge').value) || 0,
        totalPayment: parseFloat(document.getElementById('inputTotalPayment').value) || 0,
        paymentMethod: document.getElementById('inputPaymentMethod').value,
        bankMethod: document.getElementById('inputBankMethod')?.value || '',
        branch: document.getElementById('inputBranch')?.value || '',
    };

    if (currentRequest && currentPassengerIndex !== null && currentTicketIndex !== null) {
        const requests = JSON.parse(localStorage.getItem('reIssueRequests') || '[]');
        const idx = requests.findIndex(r => r.id === currentRequest.id);
        if (idx !== -1) {
            const passenger = requests[idx].passengers[currentPassengerIndex];
            if (passenger) {
                if (Array.isArray(passenger.tickets) && passenger.tickets.length > 0) {
                    if (passenger.tickets[currentTicketIndex]) {
                        passenger.tickets[currentTicketIndex].reIssueData = reIssueData;
                    }
                } else {
                    passenger.reIssueData = reIssueData;
                }
            }
            requests[idx].status = 'Processed';
            localStorage.setItem('reIssueRequests', JSON.stringify(requests));
            currentRequest = requests[idx];
            renderConfirmation(currentRequest);
        }
    }

    showToast('Process confirmed successfully!', 'success');
    closeProcessConfirmationModal();
}

function rejectReIssue(passengerIndex, ticketIndex) {
    if (!currentRequest) return;
    if (!confirm('Are you sure you want to reject this ticket\'s re-issue request?')) return;

    const requests = JSON.parse(localStorage.getItem('reIssueRequests') || '[]');
    const idx = requests.findIndex(r => r.id === currentRequest.id);
    if (idx !== -1) {
        const passenger = requests[idx].passengers[passengerIndex];
        if (passenger && Array.isArray(passenger.tickets) && passenger.tickets.length > 0) {
            passenger.tickets.splice(ticketIndex, 1);
            if (passenger.tickets.length === 0) {
                requests[idx].passengers.splice(passengerIndex, 1);
            }
        } else {
            requests[idx].passengers.splice(passengerIndex, 1);
        }
        if (requests[idx].passengers.length === 0) {
            requests[idx].status = 'Rejected';
        }
        localStorage.setItem('reIssueRequests', JSON.stringify(requests));
        currentRequest = requests[idx];
        renderConfirmation(currentRequest);
        showToast('Re-issue request rejected', 'info');
    }
}

function showNotFound() {
    document.getElementById('confirmationContent').classList.add('hidden');
    document.getElementById('notFound').classList.remove('hidden');
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast px-4 py-3 rounded-lg shadow-lg text-white ' + (
        type === 'success' ? 'bg-green-600' :
        type === 'error' ? 'bg-red-600' :
        type === 'info' ? 'bg-slate-700' : 'bg-slate-700'
    );
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

loadConfirmation();
</script>
@endpush
@endsection
