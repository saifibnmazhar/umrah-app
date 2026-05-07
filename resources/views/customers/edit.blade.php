@extends('layouts.app')
@section('title', 'Edit Customer')
@section('content')
<div class="max-w-md mx-auto">
    <div class="mb-6">
        <a href="{{ route('customers.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Customers
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-semibold text-slate-700 mb-6 pb-2 border-b border-slate-200">Edit Customer</h2>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('customers.update', $customer->id) }}" class="space-y-4" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
                <input type="text" name="name" id="name" value="{{ old('name') ?? $customer->name }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('name') border-red-500 @enderror" placeholder="Full Name">
                @error('name')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="iqama_type" class="block text-sm font-medium text-slate-700 mb-1">Iqama Type *</label>
                <select name="iqama_type" id="iqama_type" onchange="toggleReferralFields()" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white @error('iqama_type') border-red-500 @enderror">
                    <option value="">Select</option>
                    <option value="self" {{ (old('iqama_type') ?? $customer->iqama_type) == 'self' ? 'selected' : '' }}>Self</option>
                    <option value="referral" {{ (old('iqama_type') ?? $customer->iqama_type) == 'referral' ? 'selected' : '' }}>Referral</option>
                </select>
                @error('iqama_type')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div id="referralFields" class="{{ (old('iqama_type') ?? $customer->iqama_type) == 'referral' ? '' : 'hidden' }} space-y-4">
                <div>
                    <label for="ref_iqama_no" class="block text-sm font-medium text-slate-700 mb-1">Ref. Iqama No *</label>
                    <input type="text" name="ref_iqama_no" id="ref_iqama_no" value="{{ old('ref_iqama_no') ?? $customer->ref_iqama_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('ref_iqama_no') border-red-500 @enderror" placeholder="Referrer Iqama Number">
                    @error('ref_iqama_no')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="ref_mobile_no" class="block text-sm font-medium text-slate-700 mb-1">Ref. Mobile No. *</label>
                    <input type="tel" name="ref_mobile_no" id="ref_mobile_no" value="{{ old('ref_mobile_no') ?? $customer->ref_mobile_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('ref_mobile_no') border-red-500 @enderror" placeholder="05XXXXXXXX">
                    @error('ref_mobile_no')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Upload Ref. Iqama *</label>
                    <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 text-center hover:bg-slate-50 transition cursor-pointer" onclick="document.getElementById('ref_iqama_doc').click()">
                        <input type="file" id="ref_iqama_doc" name="ref_iqama_doc" class="hidden" accept=".jpg,.jpeg,.png,.pdf" onchange="handleRefIqamaFileUpload(this)">
                        <div class="text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <span id="ref_iqama_doc_filename">{{ $customer->ref_iqama_doc ?: 'click to upload' }}</span>
                        </div>
                    </div>
                    @error('ref_iqama_doc')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="iqama_no" class="block text-sm font-medium text-slate-700 mb-1">Iqama No. *</label>
                <input type="text" name="iqama_no" id="iqama_no" value="{{ old('iqama_no') ?? $customer->iqama_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('iqama_no') border-red-500 @enderror" placeholder="Iqama Number">
                @error('iqama_no')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="passport_no" class="block text-sm font-medium text-slate-700 mb-1">Passport No. *</label>
                <input type="text" name="passport_no" id="passport_no" value="{{ old('passport_no') ?? $customer->passport_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('passport_no') border-red-500 @enderror" placeholder="Passport Number">
                @error('passport_no')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="mobile_no" class="block text-sm font-medium text-slate-700 mb-1">Mobile No. *</label>
                <input type="tel" name="mobile_no" id="mobile_no" value="{{ old('mobile_no') ?? $customer->mobile_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('mobile_no') border-red-500 @enderror" placeholder="05XXXXXXXX">
                @error('mobile_no')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="address" class="block text-sm font-medium text-slate-700 mb-1">Address *</label>
                <textarea name="address" id="address" rows="3" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('address') border-red-500 @enderror">{{ old('address') ?? $customer->address }}</textarea>
                @error('address')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-4 flex gap-3">
                <button type="submit" class="flex-1 px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                    Update Customer
                </button>
                <a href="{{ route('customers.index') }}" class="px-6 py-3 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleReferralFields() {
    const iqamaType = document.getElementById('iqama_type').value;
    const referralFields = document.getElementById('referralFields');
    const refIqama = document.getElementById('ref_iqama_no');
    const refMobile = document.getElementById('ref_mobile_no');
    
    if (iqamaType === 'referral') {
        referralFields.classList.remove('hidden');
        if (refIqama) refIqama.required = true;
        if (refMobile) refMobile.required = true;
    } else {
        referralFields.classList.add('hidden');
        if (refIqama) {
            refIqama.required = false;
            refIqama.value = '';
        }
        if (refMobile) {
            refMobile.required = false;
            refMobile.value = '';
        }
    }
}

function handleRefIqamaFileUpload(input) {
    const file = input.files[0];
    const filenameDisplay = document.getElementById('ref_iqama_doc_filename');
    if (file && filenameDisplay) {
        filenameDisplay.textContent = file.name;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleReferralFields();
});
</script>
@endsection