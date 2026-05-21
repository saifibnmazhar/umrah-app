@extends('layouts.app')
@section('title', 'Passenger Details')
@section('content')
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
                            <p class="text-slate-800">{{ match($passenger->service_required?->value) { 'all' => 'All', 'visa_only' => 'Visa Only', 'ticket_only' => 'Ticket Only', default => '-' } }}</p>
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
                            <span class="text-xs text-slate-400">Address</span>
                            <p class="text-slate-800">{{ $passenger->address ?? '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Financial Details</h3>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-xs text-slate-400">Ticket Fare (SAR)</span>
                                <p class="text-slate-800 font-medium">{{ number_format($ticketFare, 2) }}</p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Visa Cost (SAR)</span>
                                <p class="text-slate-800 font-medium">{{ number_format($visaCost, 2) }}</p>
                            </div>
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
                    <input type="file" id="passenger_doc_input" class="hidden" accept=".pdf,.jpg,.jpeg,.png" onchange="handleDocumentUpload(this)">
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

            <div class="mt-6 pt-4 border-t border-slate-200 flex gap-3">
                <a href="{{ route('bookings.index', ['tab' => 'passenger']) }}" class="px-6 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Back to List</a>
            </div>
        </div>
    </div>
</div>

<script>
const passengerId = {{ $passenger->id }};

function handleDocumentUpload(input) {
    const file = input.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);

    fetch(`/passengers/${passengerId}/documents`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to upload document');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to upload document');
    });

    input.value = '';
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
            location.reload();
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
</script>
@endsection