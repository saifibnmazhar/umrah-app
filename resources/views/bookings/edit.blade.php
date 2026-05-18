@extends('layouts.app')
@section('title', 'Edit Booking')
@section('content')
<div class="max-w-5xl mx-auto">
    <div id="editBookingContent" class="space-y-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-start mb-6 pb-4 border-b border-slate-200">
                <div class="flex items-center gap-3">
                    <a href="{{ route('bookings.show', $booking->id) }}" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h2 class="text-xl font-semibold text-slate-800">Edit Booking</h2>
                        <p class="text-slate-500 text-sm mt-1">ID: {{ $booking->id }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm">
                        Print
                    </button>
                    <button type="submit" form="editForm" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">
                        Save
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('bookings.update', $booking->id) }}" id="editForm" class="space-y-6">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Customer</label>
                        <p class="px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                            {{ $booking->customer->name ?? '-' }}
                        </p>
                        <input type="hidden" name="customer_id" value="{{ $booking->customer_id }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Fingerprint Location</label>
                        <select name="fingerprint_location" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white transition">
                            <option value="Office" {{ $booking->fingerprint_location === 'Office' ? 'selected' : '' }}>Office</option>
                            <option value="Home" {{ $booking->fingerprint_location === 'Home' ? 'selected' : '' }}>Home</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Fingerprint Office</label>
                        <select name="fingerprint_office" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white transition">
                            <option value="">Select Office</option>
                            <option value="BMT-Dhaka" {{ $booking->fingerprint_office === 'BMT-Dhaka' ? 'selected' : '' }}>BMT-Dhaka</option>
                            <option value="BMT-Chattogram" {{ $booking->fingerprint_office === 'BMT-Chattogram' ? 'selected' : '' }}>BMT-Chattogram</option>
                            <option value="BMT-Sylhet" {{ $booking->fingerprint_office === 'BMT-Sylhet' ? 'selected' : '' }}>BMT-Sylhet</option>
                            <option value="BMT-Rangpur" {{ $booking->fingerprint_office === 'BMT-Rangpur' ? 'selected' : '' }}>BMT-Rangpur</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">District</label>
                        <select name="district_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white transition">
                            <option value="">Select District</option>
                            @foreach(\App\Models\District::orderBy('name')->get() as $district)
                            <option value="{{ $district->id }}" {{ $booking->district_id == $district->id ? 'selected' : '' }}>{{ $district->name }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="fingerprint_charge_id" value="{{ $booking->fingerprint_charge_id }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Package</label>
                        <select name="package_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white transition">
                            <option value="">Select Package</option>
                            @foreach(\App\Models\Package::orderBy('package_name')->get() as $package)
                            <option value="{{ $package->id }}" {{ $booking->package_id == $package->id ? 'selected' : '' }}>{{ $package->package_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Discount Type</label>
                        <select name="discount_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white transition">
                            <option value="fixed" {{ $booking->discount_type === 'fixed' ? 'selected' : '' }}>Fixed (SAR)</option>
                            <option value="percentage" {{ $booking->discount_type === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Discount Value</label>
                        <input type="number" name="discount_value" value="{{ $booking->discount_value ?? 0 }}" min="0" step="0.01" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Remarks</label>
                        <textarea name="remarks" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition">{{ $booking->remarks ?? '' }}</textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-slate-200">
                    <a href="{{ route('bookings.show', $booking->id) }}" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-center">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        {{-- Passengers Section --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-slate-700">Passengers</h3>
            </div>
            
            <div id="editPassengers" class="space-y-3">
            @forelse($booking->passengers as $index => $passenger)
            @php
                $passengerTotal = ($passenger->ticketFare?->fare ?? 0) + ($passenger->package_value ?? 0);
            @endphp
            <div class="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
                <div>
                    <span class="font-medium text-slate-800">{{ $passenger->first_name ?? '' }} {{ $passenger->last_name ?? '' }}</span>
                    <span class="text-slate-500 text-sm ml-2">({{ $passenger->passport_no ?? 'N/A' }})</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-slate-800 font-medium">{{ number_format($passengerTotal) }} SAR</span>
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-400 text-center py-4">No passengers found.</p>
            @endforelse
            </div>
        </div>

        {{-- Documents Section --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-slate-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                    <h3 class="text-sm font-medium text-slate-500">Customer Documents</h3>
                    <div class="flex gap-2">
                        <button onclick="downloadAllCustomerDocs()" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs font-medium">Download All</button>
                    </div>
                </div>
                <div class="space-y-2 overflow-y-auto" style="max-height: 16rem;">
                    @forelse($booking->documents as $doc)
                    <div class="flex justify-between items-center bg-white p-2 rounded border border-slate-200">
                        <span class="text-sm text-slate-700 truncate">{{ $doc->display_name ?? 'Document' }}</span>
                        <button onclick="downloadDoc({{ $doc->id }})" class="text-blue-600 hover:text-blue-800 text-xs">Download</button>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400">No customer documents</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-slate-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                    <h3 class="text-sm font-medium text-slate-500">Passenger Documents</h3>
                    <div class="flex gap-2">
                        <button onclick="downloadAllPassengerDocs()" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs font-medium">Download All</button>
                    </div>
                </div>
                <div class="space-y-2 overflow-y-auto" style="max-height: 16rem;">
                    @forelse($booking->passengers->flatMap->documents() as $doc)
                    <div class="flex justify-between items-center bg-white p-2 rounded border border-slate-200">
                        <span class="text-sm text-slate-700 truncate">{{ $doc->display_name ?? 'Document' }}</span>
                        <button onclick="downloadDoc({{ $doc->id }})" class="text-blue-600 hover:text-blue-800 text-xs">Download</button>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400">No passenger documents</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Back Button --}}
        <div class="mt-6 pt-4 border-t border-slate-200">
            <a href="{{ route('bookings.index') }}" class="px-6 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">
                Back to List
            </a>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

<style>
@media print {
    body { background: white; }
    nav, .no-print { display: none !important; }
    .bg-slate-100 { background: white; }
    .shadow-lg, .shadow-xl { box-shadow: none; }
    .bg-white { border: 1px solid #e2e8f0; }
    a[href]:after { content: none !important; }
}
</style>

@push('scripts')
<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-2 rounded shadow text-white ${type === 'error' ? 'bg-red-600' : 'bg-slate-700'}`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}

function downloadAllCustomerDocs() {
    const docs = document.querySelectorAll('#customerDocumentsList .text-blue-600, .bg-slate-50 .text-blue-600');
    if (docs.length === 0) {
        showToast('No customer documents to download', 'error');
        return;
    }
    docs.forEach(doc => doc.click());
    showToast('Downloading customer documents...');
}

function downloadAllPassengerDocs() {
    const docs = document.querySelectorAll('#passengerDocumentsList .text-blue-600, .bg-slate-50:nth-child(2) .text-blue-600');
    if (docs.length === 0) {
        showToast('No passenger documents to download', 'error');
        return;
    }
    docs.forEach(doc => doc.click());
    showToast('Downloading passenger documents...');
}

function downloadDoc(docId) {
    window.open('/documents/' + docId + '/download', '_blank');
}
</script>
@endpush

@endsection