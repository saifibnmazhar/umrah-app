@extends('layouts.app')
@section('title', 'Passenger Details')
@section('content')
<div class="max-w-3xl mx-auto pt-6" x-data="passengerDetailsApp(@js($passenger))">
    <div id="passengerDetailsContent" class="space-y-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-start mb-6 pb-4 border-b border-slate-200">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">{{ trim($passenger->first_name . ' ' . $passenger->last_name) }}</h2>
                    <p class="text-slate-500 text-sm mt-1">Invoice: <span>{{ $passenger->booking?->invoice?->id ?? '-' }}</span></p>
                </div>
                <div class="flex items-center gap-2">
                    <button x-show="!isEditing" @click="enterEditMode()" type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </button>
                    <button x-show="isEditing" @click="saveEdit()" type="button" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Save
                    </button>
                    <button x-show="isEditing" @click="cancelEdit()" type="button" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium text-sm flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel
                    </button>
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
                            <p x-show="!isEditing" class="text-slate-800 font-medium">{{ trim($passenger->first_name . ' ' . $passenger->last_name) ?: '-' }}</p>
                            <div x-show="isEditing" class="grid grid-cols-2 gap-2">
                                <input type="text" x-model="form.first_name" placeholder="First Name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <input type="text" x-model="form.last_name" placeholder="Last Name" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-xs text-slate-400">Passport No.</span>
                                <p x-show="!isEditing" class="text-slate-800">{{ $passenger->passport_no ?? '-' }}</p>
                                <input x-show="isEditing" type="text" x-model="form.passport_no" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Passport Expiry</span>
                                <p x-show="!isEditing" class="text-slate-800">{{ $passenger->passport_expiry?->format('d M Y') ?? '-' }}</p>
                                <input x-show="isEditing" type="date" x-model="form.passport_expiry" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-xs text-slate-400">Date of Birth</span>
                                <p x-show="!isEditing" class="text-slate-800">{{ $passenger->date_of_birth?->format('d M Y') ?? '-' }}</p>
                                <input x-show="isEditing" type="date" x-model="form.date_of_birth" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Passenger Type</span>
                                <p x-show="!isEditing" class="text-slate-800">{{ ucfirst($passenger->passenger_type?->value ?? '-') }}</p>
                                <select x-show="isEditing" x-model="form.passenger_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select Type</option>
                                    <option value="adult">Adult</option>
                                    <option value="child">Child</option>
                                    <option value="infant">Infant</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Mobile No.</span>
                            <p x-show="!isEditing" class="text-slate-800">{{ $passenger->mobile_no ?? '-' }}</p>
                            <input x-show="isEditing" type="text" x-model="form.mobile_no" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                            <p x-show="!isEditing" class="text-slate-800">{{ match($passenger->service_required?->value) { 'all' => 'All', 'visa_only' => 'Visa Only', 'ticket_only' => 'Ticket Only', default => '-' } }}</p>
                            <select x-show="isEditing" x-model="form.service_required" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Service</option>
                                <option value="All">All</option>
                                <option value="Visa Only">Visa Only</option>
                                <option value="Ticket Only">Ticket Only</option>
                            </select>
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
                                <p class="text-slate-800">{{ $passenger->ticketFare?->airlineClass?->class_name ?? '-' }}</p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Flight Date Range</span>
                                <p x-show="!isEditing" class="text-slate-800">
                                    @if($passenger->flight_date_from && $passenger->flight_date_to)
                                        {{ $passenger->flight_date_from->format('d M Y') }} → {{ $passenger->flight_date_to->format('d M Y') }}
                                    @else
                                        -
                                    @endif
                                </p>
                                <div x-show="isEditing" class="grid grid-cols-2 gap-2">
                                    <input type="date" x-model="form.flight_date_from" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <input type="date" x-model="form.flight_date_to" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400">Address</span>
                            <p x-show="!isEditing" class="text-slate-800">{{ $passenger->address ?? '-' }}</p>
                            <textarea x-show="isEditing" x-model="form.address" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="2"></textarea>
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
function passengerDetailsApp(passenger) {
    return {
        isEditing: false,
        form: {
            first_name: '',
            last_name: '',
            passport_no: '',
            date_of_birth: '',
            passport_expiry: '',
            mobile_no: '',
            passenger_type: '',
            service_required: '',
            flight_date_from: '',
            flight_date_to: '',
            address: '',
        },

        enterEditMode() {
            this.form.first_name = passenger.first_name ?? '';
            this.form.last_name = passenger.last_name ?? '';
            this.form.passport_no = passenger.passport_no ?? '';
            this.form.date_of_birth = this.toDateValue(passenger.date_of_birth);
            this.form.passport_expiry = this.toDateValue(passenger.passport_expiry);
            this.form.mobile_no = passenger.mobile_no ?? '';
            this.form.passenger_type = passenger.passenger_type ?? '';
            this.form.service_required = this.normalizeServiceRequired(passenger.service_required);
            this.form.flight_date_from = this.toDateValue(passenger.flight_date_from);
            this.form.flight_date_to = this.toDateValue(passenger.flight_date_to);
            this.form.address = passenger.address ?? '';
            this.isEditing = true;
        },

        cancelEdit() {
            this.isEditing = false;
        },

        toDateValue(value) {
            if (!value) return '';
            if (typeof value === 'string') {
                return value.split(' ')[0].split('T')[0];
            }
            return '';
        },

        normalizeServiceRequired(value) {
            if (!value) return '';
            const map = { 'all': 'All', 'visa_only': 'Visa Only', 'ticket_only': 'Ticket Only', 'All': 'All', 'Visa Only': 'Visa Only', 'Ticket Only': 'Ticket Only' };
            return map[value] ?? '';
        },

        async saveEdit() {
            if (!this.form.first_name || !this.form.last_name || !this.form.passport_no || !this.form.date_of_birth) {
                alert('First name, last name, passport number and date of birth are required.');
                return;
            }

            try {
                const response = await fetch(`/passengers/${passenger.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });

                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to update passenger');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while updating the passenger.');
            }
        },
    };
}

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