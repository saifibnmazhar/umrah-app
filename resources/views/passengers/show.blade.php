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
                                <p class="text-slate-800 font-medium">@currency($ticketFare, 2)</p>
                            </div>
                            @endif
                            @if(!$isTicketPersonnel)
                            <div>
                                <span class="text-xs text-slate-400">Visa Price (SAR)</span>
                                <p class="text-slate-800 font-medium">@currency($visaCost, 2)</p>
                            </div>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-xs text-slate-400">Fingerprint Charge (SAR)</span>
                                <p class="text-slate-800 font-medium">@currency($fingerprintCost, 2)</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200">
                            <div>
                                <span class="text-xs text-slate-400">Due (SAR)</span>
                                <p class="text-red-600 font-medium">@currency($due, 2)</p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Paid (SAR)</span>
                                <p class="text-green-600 font-medium">@currency($paid, 2)</p>
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
                            @if($passenger->visaSubmission && $passenger->visaSubmission->status?->value === 'submitted' && $canEditVisa)
                            <button onclick="openCancellationModal()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium text-sm">Cancel</button>
                            @endif
                            @if($passenger->visaSubmission && $passenger->visaSubmission->status?->value === 'cancelled' && $canEditVisa)
                            <button onclick="openVisaResubmitModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">Visa Re-Submit</button>
                            @endif
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Date</th>
                                    <th class="px-3 py-2 text-left font-medium">Agent</th>
                                    <th class="px-3 py-2 text-right font-medium">Agent Cost</th>
                                    <th class="px-3 py-2 text-left font-medium">Status</th>
                                    <th class="px-3 py-2 text-right font-medium">Cancellation Fee</th>
                                </tr>
                            </thead>
                            <tbody id="visaSubmissionHistoryBody" class="divide-y divide-slate-200">
                                @if($passenger->visaSubmission)
                                @php $vs = $passenger->visaSubmission; @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 text-slate-600">{{ $vs->created_at->format('d M Y') }}</td>
                                    <td class="px-3 py-2 text-slate-800">{{ $vs->visaAgent?->name ?? '-' }}</td>
                                    @php $agentCost = (float)$vs->net_visa_cost + (float)$vs->additional_cost; @endphp
                                    <td class="px-3 py-2 text-slate-800 text-right font-medium">@if($agentCost)<span data-sar="{{ $agentCost }}" data-dec="2">SAR {{ number_format($agentCost, 2) }}</span>@else-@endif</td>
                                    <td class="px-3 py-2">
                                        @switch($vs->status?->value)
                                        @case('submitted')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Submitted</span>
                                        @break
                                        @case('issued')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Issued</span>
                                        @break
                                        @case('cancelled')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Cancelled</span>
                                        @break
                                        @default
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ ucfirst($vs->status?->value ?? 'Pending') }}</span>
                                        @endswitch
                                    </td>
                                    <td class="px-3 py-2 text-slate-800 text-right font-medium">
                                        @if($vs->cancelledSubmission)<span data-sar="{{ (float)$vs->cancelledSubmission->cancellation_fee }}" data-dec="2">SAR {{ number_format((float)$vs->cancelledSubmission->cancellation_fee, 2) }}</span>@else-@endif
                                    </td>
                                </tr>
                                @foreach($vs->logs as $log)
                                <tr class="hover:bg-slate-50 text-slate-500">
                                    <td class="px-3 py-2">{{ $log->created_at->format('d M Y') }}</td>
                                    <td class="px-3 py-2">{{ $log->action }}</td>
                                    <td class="px-3 py-2 text-right" colspan="3">{{ $log->user?->name ?? 'System' }}</td>
                                </tr>
                                @endforeach
                                @else
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-center text-slate-400">No visa submission found</td>
                                </tr>
                                @endif
                            </tbody>
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
<div id="cancellationModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="modal-overlay absolute inset-0 bg-black/50" onclick="closeCancellationModal()"></div>
    <div class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4">Cancel Visa</h3>
        <form id="cancellationForm" onsubmit="handleCancellation(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Name</label>
                    <input type="text" id="cancelAgentName" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Cost (SAR)</label>
                    <input type="text" id="cancelAgentCost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cancellation Fee (SAR) *</label>
                    <input type="number" id="cancellationFee" required min="0" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter cancellation fee">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                    <textarea id="cancelRemarks" rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Optional remarks"></textarea>
                </div>
            </div>
            <div id="cancelError" class="text-red-600 text-sm hidden mt-2"></div>
            <div class="flex gap-3 mt-6">
                <button type="submit" id="cancelSubmitBtn" class="flex-1 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">Submit Cancellation</button>
                <button type="button" onclick="closeCancellationModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Close</button>
            </div>
        </form>
    </div>
</div>

<div id="visaResubmitModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" x-data="resubmitData()">
    <div class="modal-overlay absolute inset-0 bg-black/50" @click="closeModal()"></div>
    <div class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-xl font-semibold text-slate-800 mb-4">Visa Re-Submit</h3>
        <form @submit.prevent="handleSubmit">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent *</label>
                    <select x-model="form.visa_agent_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Agent</option>
                        <template x-for="agent in agents" :key="agent.id">
                            <option :value="agent.id" x-text="agent.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Commission Agent</label>
                    <select x-model="form.commission_agent_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Commission Agent</option>
                        <template x-for="ca in commissionAgents" :key="ca.id">
                            <option :value="ca.id" x-text="ca.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Visa Selling Price (SAR)</label>
                    <input type="text" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600" x-model="sellingPriceDisplay">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (SAR)</label>
                    <input type="text" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600" x-model="netVisaCostDisplay">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (SAR)</label>
                    <input type="number" x-model="form.agent_commission" min="0" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Final Visa Cost (SAR)</label>
                    <input type="text" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold" x-model="finalCostDisplay">
                </div>
            </div>
            <div id="resubmitError" class="text-red-600 text-sm hidden mt-2"></div>
            <div class="flex gap-3 mt-6">
                <button type="submit" id="resubmitSubmitBtn" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                <button type="button" @click="closeModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif

@php
$visaSubData = $passenger->visaSubmission ? [
    'id' => $passenger->visaSubmission->id,
    'visa_agent' => $passenger->visaSubmission->visaAgent
        ? ['id' => $passenger->visaSubmission->visaAgent->id, 'name' => $passenger->visaSubmission->visaAgent->name]
        : null,
    'net_visa_cost' => (float)($passenger->visaSubmission->net_visa_cost ?? 0),
    'additional_cost' => (float)($passenger->visaSubmission->additional_cost ?? 0),
    'final_cost' => (float)($passenger->visaSubmission->final_cost ?? 0),
    'status' => $passenger->visaSubmission->status?->value,
] : null;

$visaSellingPriceValue = (float)(
    $passenger->visaSubmission?->visaSellingPrice?->selling_price
    ?? $passenger->booking?->package?->visaSellingPrice?->selling_price
    ?? 0
);
@endphp

<script>
const passengerId = {{ $passenger->id }};
const bookingId = {{ $passenger->booking_id ?? 'null' }};
const csrfToken = '{{ csrf_token() }}';

// ============================================
// Document Upload
// ============================================
function handleDocumentUpload(input) {
    const files = input.files;
    if (!files || files.length === 0) return;

    const formData = new FormData();
    Array.from(files).forEach(file => {
        formData.append('files[]', file);
    });

    fetch(`/passengers/${passengerId}/documents`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken },
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
        headers: { 'X-CSRF-TOKEN': csrfToken }
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
// Cancellation
// ============================================
const visaSubmission = @json($visaSubData);

function openCancellationModal() {
    if (!visaSubmission) return;

    document.getElementById('cancelAgentName').value = visaSubmission.visa_agent?.name || '-';
    document.getElementById('cancelAgentCost').value = visaSubmission.final_cost ? visaSubmission.final_cost.toFixed(2) : '0.00';
    document.getElementById('cancellationFee').value = '';
    document.getElementById('cancelRemarks').value = '';
    document.getElementById('cancelError').classList.add('hidden');

    document.getElementById('cancellationModal').classList.remove('hidden');
}

function closeCancellationModal() {
    document.getElementById('cancellationModal').classList.add('hidden');
}

function handleCancellation(e) {
    e.preventDefault();

    const cancellationFee = parseFloat(document.getElementById('cancellationFee').value) || 0;
    const remarks = document.getElementById('cancelRemarks').value;
    const btn = document.getElementById('cancelSubmitBtn');
    const errorEl = document.getElementById('cancelError');

    btn.disabled = true;
    btn.textContent = 'Processing...';
    errorEl.classList.add('hidden');

    fetch(`/bookings/${bookingId}/passengers/${passengerId}/visa-cancel`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ cancellation_fee: cancellationFee, remarks: remarks })
    })
    .then(response => response.json().then(data => ({ ok: response.ok, data })))
    .then(({ ok, data }) => {
        if (ok && data.success) {
            location.reload();
        } else {
            errorEl.textContent = data.message || 'Cancellation failed';
            errorEl.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = 'Submit Cancellation';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        errorEl.textContent = 'Network error occurred';
        errorEl.classList.remove('hidden');
        btn.disabled = false;
        btn.textContent = 'Submit Cancellation';
    });
}

// ============================================
// Re-Submit (Alpine component)
// ============================================
const visaAgents = @json($visaAgents);
const sellingPrice = @json($visaSellingPriceValue);

function resubmitData() {
    return {
        agents: visaAgents,
        form: {
            visa_agent_id: '',
            commission_agent_id: '',
            agent_commission: 0,
        },
        get selectedAgent() {
            return this.agents.find(a => a.id == this.form.visa_agent_id);
        },
        get commissionAgents() {
            return this.selectedAgent?.commission_agents || [];
        },
        get netVisaCost() {
            return this.selectedAgent?.cost || 0;
        },
        get finalCost() {
            return this.netVisaCost + (parseFloat(this.form.agent_commission) || 0);
        },
        get sellingPriceDisplay() {
            return sellingPrice.toFixed(2);
        },
        get netVisaCostDisplay() {
            return this.netVisaCost.toFixed(2);
        },
        get finalCostDisplay() {
            return this.finalCost.toFixed(2);
        },
        openModal() {
            this.form.visa_agent_id = '';
            this.form.commission_agent_id = '';
            this.form.agent_commission = 0;
            document.getElementById('visaResubmitModal').classList.remove('hidden');
        },
        closeModal() {
            document.getElementById('visaResubmitModal').classList.add('hidden');
        },
        handleSubmit() {
            const btn = document.getElementById('resubmitSubmitBtn');
            const errorEl = document.getElementById('resubmitError');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            errorEl.classList.add('hidden');

            fetch(`/bookings/${bookingId}/passengers/${passengerId}/visa-resubmit`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(this.form)
            })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.success) {
                    location.reload();
                } else {
                    errorEl.textContent = data.message || 'Re-submit failed';
                    errorEl.classList.remove('hidden');
                    btn.disabled = false;
                    btn.textContent = 'Save';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorEl.textContent = 'Network error occurred';
                errorEl.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = 'Save';
            });
        }
    };
}

function openVisaResubmitModal() {
    const el = document.querySelector('[x-data="resubmitData()"]');
    if (el && el.__x) {
        el.__x.$data.openModal();
    }
}
</script>
@endsection