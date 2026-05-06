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

        <form method="POST" action="{{ route('customers.update', $customer->id) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
                <input type="text" name="name" id="name" value="{{ old('name') ?? $customer->name }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('name') border-red-500 @endif" placeholder="Full Name">
                @error('name')
                    <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="iqama_type" class="block text-sm font-medium text-slate-700 mb-1">Iqama Type *</label>
                <select name="iqama_type" id="iqama_type" onchange="toggleReferralFields()" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white @error('iqama_type') border-red-500 @endif">
                    <option value="">Select</option>
                    <option value="self" {{ (old('iqama_type') ?? $customer->iqama_type) == 'self' ? 'selected' : '' }}>Self</option>
                    <option value="referral" {{ (old('iqama_type') ?? $customer->iqama_type) == 'referral' ? 'selected' : '' }}>Referral</option>
                </select>
                @error('iqama_type')
                    <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div id="referralFields" class="{{ (old('iqama_type') ?? $customer->iqama_type) == 'Referral' ? '' : 'hidden' }} space-y-4">
                <div>
                    <label for="ref_iqama_no" class="block text-sm font-medium text-slate-700 mb-1">Ref. Iqama No *</label>
                    <input type="text" name="ref_iqama_no" id="ref_iqama_no" value="{{ old('ref_iqama_no') ?? $customer->ref_iqama_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('ref_iqama_no') border-red-500 @endif" placeholder="Referrer Iqama Number">
                    @error('ref_iqama_no')
                        <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label for="ref_mobile_no" class="block text-sm font-medium text-slate-700 mb-1">Ref. Mobile No. *</label>
                    <input type="text" name="ref_mobile_no" id="ref_mobile_no" value="{{ old('ref_mobile_no') ?? $customer->ref_mobile_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('ref_mobile_no') border-red-500 @endif" placeholder="05XXXXXXXX">
                    @error('ref_mobile_no')
                        <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label for="ref_iqama_doc" class="block text-sm font-medium text-slate-700 mb-1">Ref. Iqama Doc</label>
                    <input type="text" name="ref_iqama_doc" id="ref_iqama_doc" value="{{ old('ref_iqama_doc') ?? $customer->ref_iqama_doc }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('ref_iqama_doc') border-red-500 @endif" placeholder="Document reference">
                    @error('ref_iqama_doc')
                        <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div>
                <label for="iqama_no" class="block text-sm font-medium text-slate-700 mb-1">Iqama No. *</label>
                <input type="text" name="iqama_no" id="iqama_no" value="{{ old('iqama_no') ?? $customer->iqama_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('iqama_no') border-red-500 @endif" placeholder="Iqama Number">
                @error('iqama_no')
                    <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="passport_no" class="block text-sm font-medium text-slate-700 mb-1">Passport No. *</label>
                <input type="text" name="passport_no" id="passport_no" value="{{ old('passport_no') ?? $customer->passport_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('passport_no') border-red-500 @endif" placeholder="Passport Number">
                @error('passport_no')
                    <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="mobile_no" class="block text-sm font-medium text-slate-700 mb-1">Mobile No. *</label>
                <input type="text" name="mobile_no" id="mobile_no" value="{{ old('mobile_no') ?? $customer->mobile_no }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('mobile_no') border-red-500 @endif" placeholder="05XXXXXXXX">
                @error('mobile_no')
                    <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="address" class="block text-sm font-medium text-slate-700 mb-1">Address *</label>
                <textarea name="address" id="address" rows="3" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition @error('address') border-red-500 @endif">{{ old('address') ?? $customer->address }}</textarea>
                @error('address')
                    <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
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
    
    if (iqamaType === 'Referral') {
        referralFields.classList.remove('hidden');
    } else {
        referralFields.classList.add('hidden');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleReferralFields();
});
</script>
@endsection