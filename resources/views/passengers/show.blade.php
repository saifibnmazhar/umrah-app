@extends('layouts.app')
@section('title', 'Passenger Details')
@section('content')
@php
    $isVisaPersonnel = auth()->user()->roles->pluck('name')->intersect(['Visa Admin', 'Visa Staff'])->isNotEmpty();
    $isTicketPersonnel = auth()->user()->roles->pluck('name')->intersect(['Ticket Admin', 'Ticket Staff'])->isNotEmpty();
    $isBranchPersonnel = auth()->user()->roles->pluck('name')->intersect(['Branch Manager', 'Branch Staff'])->isNotEmpty();
@endphp
<div class="max-w-3xl mx-auto pt-6">
    <div id="passengerDetailsContent" class="space-y-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-start mb-6 pb-4 border-b border-slate-200">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">{{ trim($passenger->first_name . ' ' . $passenger->last_name) }}</h2>
                    <p class="text-slate-500 text-sm mt-1">Invoice: <span>{{ $passenger->booking?->invoice?->id ?? '-' }}</span></p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('passengers.edit', $passenger->id) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </a>
                    <a href="{{ route('bookings.index') }}" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-slate-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Basic Information</h3>
                    <div class="space-y-3">
                        <div>
                            <span class="text-xs text-slate-400">Full Name</span>
                            <p class="text-slate-800 font-medium">{{ trim($passenger->first_name . ' ' . $passenger->last_name) ?: '-' }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-xs text-slate-400">Passport No.</span>
                                <p class="text-slate-800">{{ $passenger->passport_no ?? '-' }}</p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Passport Expiry</span>
                                <p class="text-slate-800">{{ $passenger->passport_expiry?->format('d M Y') ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-xs text-slate-400">Date of Birth</span>
                                <p class="text-slate-800">{{ $passenger->date_of_birth?->format('d M Y') ?? '-' }}</p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Passenger Type</span>
                                <p class="text-slate-800">{{ ucfirst($passenger->passenger_type?->value ?? '-') }}</p>
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Mobile No.</span>
                            <p class="text-slate-800">{{ $passenger->mobile_no ?? '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Service Information</h3>
                    <div class="space-y-3">
                        <div>
                            <span class="text-xs text-slate-400">Package</span>
                            <p class="text-slate-800">{{ $passenger->booking?->package?->package_name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Service Required</span>
                            <p class="text-slate-800">{{ match($passenger->service_required?->value) { 'all' => 'Visa + Ticket', 'visa_only' => 'Visa Only', 'ticket_only' => 'Ticket Only', default => '-' } }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Status</span>
                            <p class="text-slate-800">{{ $passenger->status?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Fingerprint Location</span>
                            <p class="text-slate-800">{{ match($passenger->booking?->fingerprint_location?->value) { 'home' => 'Home', 'office' => 'Office', default => '-' } }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Travel Details</h3>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-xs text-slate-400">Route</span>
                                <p class="text-slate-800">{{ $routeDisplay ?? '-' }}</p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Airline</span>
                                <p class="text-slate-800">{{ $passenger->ticketFare?->airline?->name ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-xs text-slate-400">Class</span>
                                <p class="text-slate-800">{{ $passenger->ticketFare?->airlineClass?->class?->name ?? '-' }}</p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Flight Date Range</span>
                                <p class="text-slate-800">
                                    @if($passenger->flight_date_from && $passenger->flight_date_to)
                                        {{ $passenger->flight_date_from->format('d M Y') }} → {{ $passenger->flight_date_to->format('d M Y') }}
                                    @else
                                        -
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Address (BD)</span>
                            <p class="text-slate-800">{{ $passenger->address ?? '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Financial Details</h3>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            @if(!$isVisaPersonnel)
                            <div>
                                <span class="text-xs text-slate-400">Ticket Fare (SAR)</span>
                                <p class="text-slate-800 font-medium">{{ number_format($ticketFare, 2) }}</p>
                            </div>
                            @endif
                            @if(!$isTicketPersonnel)
                            <div>
                                <span class="text-xs text-slate-400">Visa Cost (SAR)</span>
                                <p class="text-slate-800 font-medium">{{ number_format($visaCost, 2) }}</p>
                            </div>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-xs text-slate-400">Fingerprint Cost (SAR)</span>
                                <p class="text-slate-800 font-medium">{{ number_format($fingerprintCost, 2) }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200">
                            <div>
                                <span class="text-xs text-slate-400">Due (SAR)</span>
                                <p class="text-red-600 font-medium">{{ number_format($due, 2) }}</p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Paid (SAR)</span>
                                <p class="text-green-600 font-medium">{{ number_format($paid, 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-lg p-4 md:col-span-2">
                    <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                        <h3 class="text-sm font-medium text-slate-500">Documents</h3>
                        <div class="flex gap-2">
                            <button onclick="document.getElementById('passenger_doc_input').click()" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs font-medium">Upload</button>
                            @if($passenger->documents->count() > 0)
                            <button onclick="downloadAllDocuments()" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs font-medium">Download All</button>
                            @endif
                        </div>
                    </div>
                    <input type="file" id="passenger_doc_input" class="hidden" accept=".pdf,.jpg,.jpeg,.png" multiple onchange="handleDocumentUpload(this)">
                    <div id="documents_list" class="space-y-2">
                        @forelse($passenger->documents as $doc)
                        <div class="flex items-center justify-between bg-white rounded px-3 py-2 border border-slate-200">
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="text-sm text-slate-700">{{ $doc->display_name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('passengers.documents.download', ['passenger' => $passenger->id, 'document' => $doc->id]) }}" class="text-blue-500 hover:text-blue-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                                <button onclick="deleteDocument({{ $doc->id }})" class="text-red-500 hover:text-red-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-slate-400">No documents uploaded</p>
                        @endforelse
                    </div>
                </div>
            </div>

            @if(!$isTicketPersonnel && !$isBranchPersonnel)
            {{-- Visa Submission History --}}
            <div class="mt-6 pt-4 border-t border-slate-200">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-slate-800">Visa Submission History</h3>
                        <div class="flex gap-2">
                            <button onclick="openCancellationModal()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium text-sm">Cancel</button>
                            <button onclick="openVisaResubmitModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">Visa Re-Submit</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Date</th>
                                    <th class="px-3 py-2 text-left font-medium">Agent</th>
                                    <th class="px-3 py-2 text-right font-medium">Agent Cost</th>
                                    <th class="px-3 py-2 text-left font-medium">Flight Date</th>
                                    <th class="px-3 py-2 text-left font-medium">Status</th>
                                    <th class="px-3 py-2 text-right font-medium">Cancellation Fee</th>
                                </tr>
                            </thead>
                            <tbody id="visaSubmissionHistoryBody" class="divide-y divide-slate-200"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <div class="mt-6 pt-4 border-t border-slate-200 flex gap-3">
                <a href="{{ route('bookings.index', ['tab' => 'passenger']) }}" class="px-6 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Back to List</a>
            </div>
        </div>
    </div>
</div>

<style>
.modal-overlay { transition: opacity 0.2s ease; }
.modal-content { transition: transform 0.2s ease, opacity 0.2s ease; }
</style>

@if(!$isTicketPersonnel && !$isBranchPersonnel)
{{-- Cancellation Modal --}}
<div id="cancellationModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="modal-overlay absolute inset-0 bg-black/50" onclick="closeCancellationModal()"></div>
    <div class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4">Cancellation</h3>
        <form id="cancellationForm" onsubmit="handleCancellation(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Name</label>
                    <input type="text" id="cancelAgentName" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Cost (SAR)</label>
                    <input type="number" id="cancelAgentCost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cancellation Fee (SAR) *</label>
                    <input type="number" id="cancellationFee" required min="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter cancellation fee">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">Submit Cancellation</button>
                <button type="button" onclick="closeCancellationModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Visa Re-Submit Modal --}}
<div id="visaResubmitModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="modal-overlay absolute inset-0 bg-black/50" onclick="closeVisaResubmitModal()"></div>
    <div class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4">Visa Re-Submit</h3>
        <form id="visaResubmitForm" onsubmit="handleVisaResubmit(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent *</label>
                    <select id="visaResubmitAgent" required onchange="updateResubmitCommissionAgentOptions()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Agent</option>
                        <option value="Visa Agent A">Visa Agent A</option>
                        <option value="Visa Agent B">Visa Agent B</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Commission Agent *</label>
                    <select id="visaResubmitCommissionAgent" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Commission Agent</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Visa Selling Price (SAR)</label>
                    <input type="number" id="visaResubmitSellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600" value="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (SAR)</label>
                    <input type="number" id="visaResubmitAgentCommission" oninput="updateVisaResubmitFinal()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0" min="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (SAR)</label>
                    <input type="number" id="visaResubmitNetVisaCost" oninput="updateVisaResubmitFinal()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0" min="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Final Visa Cost (SAR)</label>
                    <input type="number" id="visaResubmitFinal" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold" value="0">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                <button type="button" onclick="closeVisaResubmitModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
const passengerId = {{ $passenger->id }};

function handleDocumentUpload(input) {
    const files = input.files;
    if (!files || files.length === 0) return;

    const formData = new FormData();
    Array.from(files).forEach(file => {
        formData.append('files[]', file);
    });

    fetch(`/passengers/${passengerId}/documents`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.documents) {
            const list = document.getElementById('documents_list');
            const emptyState = list.querySelector('p.text-slate-400');
            if (emptyState) emptyState.remove();

            data.documents.forEach(doc => {
                const item = document.createElement('div');
                item.className = 'flex items-center justify-between bg-white rounded px-3 py-2 border border-slate-200';
                item.innerHTML = `
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="text-sm text-slate-700">${doc.display_name}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="/passengers/${passengerId}/documents/${doc.id}/download" class="text-blue-500 hover:text-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                        <button onclick="deleteDocument(${doc.id})" class="text-red-500 hover:text-red-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                `;
                list.appendChild(item);
            });
            input.value = '';
        } else {
            alert(data.message || 'Failed to upload documents');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to upload documents');
    });
}

function deleteDocument(documentId) {
    if (!confirm('Are you sure you want to delete this document?')) return;

    fetch(`/passengers/${passengerId}/documents/${documentId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const list = document.getElementById('documents_list');
            const docItem = list.querySelector(`button[onclick*="${documentId}"]`)?.closest('.flex.items-center');
            if (docItem) docItem.remove();
            if (list.children.length === 0) {
                list.innerHTML = '<p class="text-sm text-slate-400">No documents uploaded</p>';
            }
        } else {
            alert(data.message || 'Failed to delete document');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete document');
    });
}

function downloadAllDocuments() {
    const documents = @json($passenger->documents);
    documents.forEach(doc => {
        window.open(`/passengers/${passengerId}/documents/${doc.id}/download`, '_blank');
    });
}

// ============================================
// Visa Submission History
// ============================================
const visaHistoryData = [
    { date: '2026-02-01', agent: 'Visa Agent A', agentCost: 2500, flightDate: '2026-04-20', status: 'Submitted', cancellationFee: 0 },
    { date: '2026-02-15', agent: 'Visa Agent A', agentCost: 1800, flightDate: '2026-04-05', status: 'Cancelled', cancellationFee: 500 },
    { date: '2026-03-01', agent: 'Visa Agent B', agentCost: 2200, flightDate: '2026-04-15', status: 'Submitted', cancellationFee: 0 },
    { date: '2026-03-10', agent: 'Visa Agent B', agentCost: 3000, flightDate: '2026-04-20', status: 'Issued', cancellationFee: 0 }
];

function renderVisaSubmissionHistory() {
    const tbody = document.getElementById('visaSubmissionHistoryBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    visaHistoryData.forEach(item => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';

        let statusBadge = '';
        switch(item.status) {
            case 'Submitted':
                statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Submitted</span>';
                break;
            case 'Cancelled':
                statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Cancelled</span>';
                break;
            case 'Issued':
                statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Issued</span>';
                break;
        }

        tr.innerHTML = `
            <td class="px-3 py-2 text-slate-600">${item.date}</td>
            <td class="px-3 py-2 text-slate-800">${item.agent}</td>
            <td class="px-3 py-2 text-slate-800 text-right font-medium">${item.agentCost.toLocaleString()} SAR</td>
            <td class="px-3 py-2 text-slate-600">${item.flightDate}</td>
            <td class="px-3 py-2">${statusBadge}</td>
            <td class="px-3 py-2 text-slate-800 text-right font-medium">${item.cancellationFee ? item.cancellationFee.toLocaleString() + ' SAR' : '-'}</td>
        `;
        tbody.appendChild(tr);
    });
}

let currentHistoryItem = null;

function openCancellationModal() {
    currentHistoryItem = visaHistoryData[0];

    document.getElementById('cancelAgentName').value = currentHistoryItem?.agent || '';
    document.getElementById('cancelAgentCost').value = currentHistoryItem?.agentCost || 0;
    document.getElementById('cancellationFee').value = '';

    document.getElementById('cancellationModal').classList.remove('hidden');
}

function closeCancellationModal() {
    document.getElementById('cancellationModal').classList.add('hidden');
    currentHistoryItem = null;
}

function handleCancellation(e) {
    e.preventDefault();

    const cancellationFee = parseFloat(document.getElementById('cancellationFee').value) || 0;

    const newRow = {
        date: new Date().toISOString().split('T')[0],
        agent: currentHistoryItem.agent,
        agentCost: currentHistoryItem.agentCost,
        flightDate: currentHistoryItem.flightDate,
        status: 'Cancelled',
        cancellationFee: cancellationFee
    };

    visaHistoryData.push(newRow);
    renderVisaSubmissionHistory();
    closeCancellationModal();
}

function openVisaResubmitModal() {
    document.getElementById('visaResubmitAgent').value = '';
    document.getElementById('visaResubmitCommissionAgent').innerHTML = '<option value="">Select Commission Agent</option>';
    document.getElementById('visaResubmitSellingPrice').value = 500;
    document.getElementById('visaResubmitAgentCommission').value = 0;
    document.getElementById('visaResubmitNetVisaCost').value = 0;
    document.getElementById('visaResubmitFinal').value = 500;

    document.getElementById('visaResubmitModal').classList.remove('hidden');
}

function closeVisaResubmitModal() {
    document.getElementById('visaResubmitModal').classList.add('hidden');
}

function updateResubmitCommissionAgentOptions() {
    const agent = document.getElementById('visaResubmitAgent').value;
    const commissionSelect = document.getElementById('visaResubmitCommissionAgent');

    const agents = {
        "Visa Agent A": ["Commission Agent 1", "Commission Agent 2"],
        "Visa Agent B": ["Commission Agent 3", "Commission Agent 4"]
    };

    commissionSelect.innerHTML = '<option value="">Select Commission Agent</option>';
    if (agents[agent]) {
        agents[agent].forEach(a => {
            const option = document.createElement('option');
            option.value = a;
            option.textContent = a;
            commissionSelect.appendChild(option);
        });
    }
}

function updateVisaResubmitFinal() {
    const sellingPrice = parseFloat(document.getElementById('visaResubmitSellingPrice').value) || 0;
    const commission = parseFloat(document.getElementById('visaResubmitAgentCommission').value) || 0;
    const netCost = parseFloat(document.getElementById('visaResubmitNetVisaCost').value) || 0;
    document.getElementById('visaResubmitFinal').value = sellingPrice + commission + netCost;
}

function handleVisaResubmit(e) {
    e.preventDefault();

    const agentCommission = parseFloat(document.getElementById('visaResubmitAgentCommission').value) || 0;
    const netVisaCost = parseFloat(document.getElementById('visaResubmitNetVisaCost').value) || 0;

    const visaData = {
        agent: document.getElementById('visaResubmitAgent').value,
        commissionAgent: document.getElementById('visaResubmitCommissionAgent').value,
        sellingPrice: parseFloat(document.getElementById('visaResubmitSellingPrice').value) || 0,
        agentCommission: agentCommission,
        netVisaCost: netVisaCost,
        finalCost: agentCommission + netVisaCost,
        resubmittedAt: new Date().toISOString()
    };

    const newRow = {
        date: new Date().toISOString().split('T')[0],
        agent: visaData.agent,
        agentCost: visaData.finalCost,
        flightDate: '-',
        status: 'Submitted',
        cancellationFee: 0
    };

    visaHistoryData.push(newRow);
    renderVisaSubmissionHistory();
    closeVisaResubmitModal();
}

renderVisaSubmissionHistory();
</script>
@endsection