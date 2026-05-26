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
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Refund Amount</label>
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
let currentRequest = null;

function loadConfirmation() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id') || '{{ $id }}';

    if (id === null || id === '') {
        showNotFound();
        return;
    }

    const requestId = parseInt(id);
    let refundRequests = JSON.parse(localStorage.getItem('refundRequests') || '[]');

    if (refundRequests.length === 0) {
        const seedData = [
            {
                id: 1,
                invoiceId: 1001,
                invoiceNo: 'INV-2024-001',
                customerName: 'Karim Hussein',
                customerMobile: '0551234567',
                branch: 'Madinah Branch',
                requestedAt: new Date(Date.now() - 86400000 * 4).toISOString(),
                status: 'Pending',
                passengers: [
                    { name: 'Karim Hussein', passport: 'P1122445', pnr: 'YZA567' },
                    { name: 'Laila Mohamed', passport: 'P6677889', pnr: 'BCD890' },
                ]
            }
        ];
        refundRequests = seedData;
        localStorage.setItem('refundRequests', JSON.stringify(seedData));
        localStorage.setItem('refundRequests_seed', JSON.stringify(seedData));
    }

    const request = refundRequests.find(r => r.id === requestId) || refundRequests[0];

    if (!request) {
        showNotFound();
        return;
    }

    currentRequest = request;
    renderConfirmation(request);
}

function renderConfirmation(request) {
    document.getElementById('invoiceId').textContent = request.invoiceId;
    document.getElementById('invoiceNo').textContent = request.invoiceNo;
    document.getElementById('customerName').textContent = request.customerName || '-';
    document.getElementById('customerMobile').textContent = request.customerMobile || '-';
    document.getElementById('branch').textContent = request.branch || '-';
    document.getElementById('requestedDate').textContent = new Date(request.requestedAt).toLocaleDateString();
    document.getElementById('passengerCount').textContent = request.passengers.length;

    const statusBadge = document.getElementById('statusBadge');
    statusBadge.textContent = request.status === 'Pending' ? 'Pending' : request.status;
    statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' + (
        request.status === 'Pending' ? 'bg-yellow-100 text-yellow-700' :
        request.status === 'Approved' || request.status === 'Processed' ? 'bg-green-100 text-green-700' :
        request.status === 'Rejected' ? 'bg-red-100 text-red-700' :
        'bg-yellow-100 text-yellow-700'
    );

    const passengerListEl = document.getElementById('passengerList');
    passengerListEl.innerHTML = request.passengers.map((p, pIndex) => `
        <div class="flex justify-between items-center p-4 bg-slate-50 rounded-lg">
            <div class="flex items-center gap-3">
                <span class="font-medium text-slate-800">${escapeHtml(p.name)}</span>
                <span class="text-slate-500 text-sm ml-2">(${escapeHtml(p.passport)}${p.pnr ? ' | PNR: ' + escapeHtml(p.pnr) : ''})</span>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="rejectRefund(${pIndex})" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition font-medium">Reject</button>
                <button onclick="processConfirmation(${pIndex})" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-lg hover:bg-orange-600 transition font-medium">Process Confirmation</button>
            </div>
        </div>
    `).join('');
}

function processConfirmation(passengerIndex) {
    const passenger = currentRequest.passengers[passengerIndex];
    if (!passenger) return;

    document.getElementById('modalPassengerName').textContent = passenger.name + ' (' + passenger.passport + ')';
    document.getElementById('infoPassport').textContent = passenger.passport;
    document.getElementById('infoMobile').textContent = passenger.mobile || '-';
    document.getElementById('infoPnr').textContent = passenger.pnr || 'ABCD1234';
    document.getElementById('infoFlightDate').textContent = '2026-05-15';
    document.getElementById('infoRoute').textContent = 'DAC-JED-DAC';
    document.getElementById('infoAirline').textContent = 'Saudi Arabian Airlines';
    document.getElementById('infoClass').textContent = 'Economy';
    document.getElementById('infoType').textContent = 'Adult';

    document.getElementById('inputAgentRefundAmount').value = '';
    document.getElementById('inputCustomerRefundAmount').value = '';
    document.getElementById('inputPaymentMethod').value = '';
    document.getElementById('bankMethodSection').classList.add('hidden');
    document.getElementById('branchSection').classList.add('hidden');

    document.getElementById('processConfirmationModal').classList.remove('hidden');
}

function closeProcessConfirmationModal() {
    document.getElementById('processConfirmationModal').classList.add('hidden');
    document.getElementById('inputAgentRefundAmount').value = '';
    document.getElementById('inputCustomerRefundAmount').value = '';
    document.getElementById('inputPaymentMethod').value = '';
    document.getElementById('bankMethodSection').classList.add('hidden');
    document.getElementById('branchSection').classList.add('hidden');
}

function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('inputPaymentMethod').value;
    const bankMethodSection = document.getElementById('bankMethodSection');
    const branchSection = document.getElementById('branchSection');

    bankMethodSection.classList.add('hidden');
    branchSection.classList.add('hidden');

    if (paymentMethod === 'Bank Transfer') {
        bankMethodSection.classList.remove('hidden');
    } else if (paymentMethod === 'Pay from Branch') {
        branchSection.classList.remove('hidden');
    }
}

function confirmProcess() {
    const refundData = {
        agentRefundAmount: parseFloat(document.getElementById('inputAgentRefundAmount').value) || 0,
        customerRefundAmount: parseFloat(document.getElementById('inputCustomerRefundAmount').value) || 0,
        paymentMethod: document.getElementById('inputPaymentMethod').value,
        bankMethod: document.getElementById('inputBankMethod')?.value || '',
        branch: document.getElementById('inputBranch')?.value || '',
    };

    if (currentRequest) {
        const requests = JSON.parse(localStorage.getItem('refundRequests') || '[]');
        const idx = requests.findIndex(r => r.id === currentRequest.id);
        if (idx !== -1) {
            requests[idx].refundData = refundData;
            requests[idx].status = 'Processed';
            localStorage.setItem('refundRequests', JSON.stringify(requests));
            currentRequest = requests[idx];
            renderConfirmation(currentRequest);
        }
    }

    showToast('Refund process confirmed successfully!', 'success');
    closeProcessConfirmationModal();
}

function rejectRefund(passengerIndex) {
    if (!currentRequest) return;
    if (!confirm('Are you sure you want to reject this passenger\'s refund request?')) return;

    const requests = JSON.parse(localStorage.getItem('refundRequests') || '[]');
    const idx = requests.findIndex(r => r.id === currentRequest.id);
    if (idx !== -1) {
        requests[idx].passengers.splice(passengerIndex, 1);
        if (requests[idx].passengers.length === 0) {
            requests[idx].status = 'Rejected';
        }
        localStorage.setItem('refundRequests', JSON.stringify(requests));
        currentRequest = requests[idx];
        renderConfirmation(currentRequest);
        showToast('Refund request rejected', 'info');
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
