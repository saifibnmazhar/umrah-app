@extends('layouts.app')
@section('title', 'Refund Confirmation')
@section('content')
<div class="max-w-4xl mx-auto py-6">
    <div id="confirmationContent" class="space-y-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="mb-6 pb-4 border-b border-slate-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-800">Refund Confirmation</h2>
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
        <p class="text-slate-500 mb-6">The refund confirmation request you're looking for could not be found.</p>
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
                <h4 class="text-sm font-medium text-slate-700 mb-3 pb-2 border-b border-slate-200">Refund Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                        <select id="inputReason" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Reason</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">IATA Refund Amount</label>
                        <input type="number" id="inputAgentRefundAmount" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer Refund Amount</label>
                        <input type="number" id="inputCustomerRefundAmount" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method</label>
                        <select id="inputPaymentMethod" onchange="handlePaymentMethodChange()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Payment Method</option>
                            <option value="Pay from Branch">Pay from Branch</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div id="bankMethodSection" class="hidden">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bank Method</label>
                        <select id="inputBankMethod" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Bank Method</option>
                            <option value="Al Rajhi Bank">Al Rajhi Bank</option>
                            <option value="National Commercial Bank">National Commercial Bank</option>
                            <option value="Riyadh Bank">Riyadh Bank</option>
                            <option value="Alinma Bank">Alinma Bank</option>
                        </select>
                    </div>
                    <div id="branchSection" class="hidden">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Branch</label>
                        <select id="inputBranch" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Branch</option>
                            <option value="Riyadh Branch">Riyadh Branch</option>
                            <option value="Jeddah Branch">Jeddah Branch</option>
                            <option value="Madinah Branch">Madinah Branch</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button onclick="confirmProcess()" class="flex-1 px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium">Confirm</button>
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

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
}

function loadConfirmation() {
    fetch('/bookings/' + bookingId + '/ticket-requests?type=refund', {
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
        document.getElementById('requestedDate').textContent = formatDate(first.requested_at);
        document.getElementById('passengerCount').textContent = [...new Set(requests.map(r => r.passenger_id))].length;

        renderConfirmation(requests);
        loadReasons();
    });
}

function loadReasons() {
    fetch('/ticket-requests/reasons?type=refund', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
    })
    .then(res => res.json())
    .then(reasons => {
        const select = document.getElementById('inputReason');
        select.innerHTML = '<option value="">Select Reason</option>' +
            reasons.map(r => '<option value="' + r.id + '">' + escapeHtml(r.name) + '</option>').join('');
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
                        const t = r.issued_ticket || {};
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
                                <div class="text-sm"><span class="text-slate-500">Ticket No: </span><span class="text-slate-800 font-medium">${escapeHtml(t.ticket_number) || '-'}</span></div>
                                <div class="text-sm"><span class="text-slate-500">PNR: </span><span class="text-slate-800 font-medium">${escapeHtml(t.pnr) || '-'}</span></div>
                            </div>
                            <div class="flex gap-3">
                                <button onclick="rejectRefund(${r.id})" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition font-medium" ${isProcessed || isRejected ? 'disabled style="opacity:50;cursor:not-allowed"' : ''}>Reject</button>
                                <button onclick="processConfirmation(${r.id})" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-lg hover:bg-orange-600 transition font-medium" ${isProcessed || isRejected ? 'disabled style="opacity:50;cursor:not-allowed"' : ''}>Process Confirmation</button>
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
    const t = r.issued_ticket || {};

    document.getElementById('modalPassengerName').textContent = (p.first_name || '') + ' ' + (p.last_name || '') + ' (' + (p.passport_no || '-') + ')';
    document.getElementById('infoPassport').textContent = p.passport_no || '-';
    document.getElementById('infoMobile').textContent = p.mobile_no || '-';
    document.getElementById('infoPnr').textContent = t.pnr || '-';
    document.getElementById('infoFlightDate').textContent = formatDate(t.outbound_date || t.inbound_date) || '-';
    document.getElementById('infoRoute').textContent = formatRoute(t.ticket_fare?.route) || '-';
    document.getElementById('infoAirline').textContent = t.ticket_fare?.airline?.name || '-';
    document.getElementById('infoClass').textContent = t.ticket_fare?.airline_class?.class?.name || '-';
    document.getElementById('infoType').textContent = ({ adult: 'Adult', child: 'Child', infant: 'Infant' })[p.passenger_type] || '-';

    document.getElementById('inputAgentRefundAmount').value = '';
    document.getElementById('inputCustomerRefundAmount').value = '';
    document.getElementById('inputReason').value = '';
    document.getElementById('inputPaymentMethod').value = '';
    document.getElementById('bankMethodSection').classList.add('hidden');
    document.getElementById('branchSection').classList.add('hidden');

    document.getElementById('processConfirmationModal').classList.remove('hidden');
}

function closeProcessConfirmationModal() {
    document.getElementById('processConfirmationModal').classList.add('hidden');
    currentTicketRequestId = null;
}

function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('inputPaymentMethod').value;
    document.getElementById('bankMethodSection').classList.add('hidden');
    document.getElementById('branchSection').classList.add('hidden');
    if (paymentMethod === 'Bank Transfer') {
        document.getElementById('bankMethodSection').classList.remove('hidden');
    } else if (paymentMethod === 'Pay from Branch') {
        document.getElementById('branchSection').classList.remove('hidden');
    }
}

function confirmProcess() {
    if (!currentTicketRequestId) return;

    const payload = {
        reason_id: document.getElementById('inputReason').value,
        iata_refund: parseFloat(document.getElementById('inputAgentRefundAmount').value) || 0,
        customer_refund: parseFloat(document.getElementById('inputCustomerRefundAmount').value) || 0,
        service_charge: 0,
        payment_method: document.getElementById('inputPaymentMethod').value || null,
        bank_method: document.getElementById('inputBankMethod')?.value || null,
        branch: document.getElementById('inputBranch')?.value || null,
    };

    if (!payload.reason_id) {
        showToast('Please select a reason', 'error');
        return;
    }

    fetch('/ticket-requests/' + currentTicketRequestId + '/process-refund', {
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
            showToast('Refund processed successfully!', 'success');
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

function rejectRefund(ticketRequestId) {
    if (!confirm('Are you sure you want to reject this ticket\'s refund request?')) return;

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
            showToast('Refund request rejected', 'info');
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
