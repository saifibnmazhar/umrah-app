@extends('layouts.app')
@section('title', 'Edit Customer')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('customers.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Customers
        </a>
    </div>

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

    <div class="bg-white rounded-xl shadow-2xl p-6"
         x-data="editCustomerForm({
             name: '{{ old('name', $customer->name) }}',
             passport_no: '{{ old('passport_no', $customer->passport_no) }}',
             mobile_no: '{{ old('mobile_no', $customer->mobile_no) }}',
             address: '{{ old('address', $customer->address ?? '') }}',
             iqama_type: '{{ old('iqama_type', $customer->iqama_type) }}',
             iqama_no: '{{ old('iqama_no', $customer->iqama_no ?? '') }}',
             ref_iqama_no: '{{ old('ref_iqama_no', $customer->ref_iqama_no ?? '') }}',
             ref_mobile_no: '{{ old('ref_mobile_no', $customer->ref_mobile_no ?? '') }}'
         })">
        <h3 class="text-xl font-semibold text-slate-800 mb-4">Edit Customer</h3>
        <form method="POST" action="{{ route('customers.update', $customer->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Name *</label>
                    <input type="text" name="name" x-model="form.name" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('name') border-red-500 @enderror">
                    @error('name')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Passport No. *</label>
                    <input type="text" name="passport_no" x-model="form.passport_no" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('passport_no') border-red-500 @enderror">
                    @error('passport_no')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Mobile No. *</label>
                    <input type="text" name="mobile_no" x-model="form.mobile_no" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('mobile_no') border-red-500 @enderror">
                    @error('mobile_no')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Address (KSA)</label>
                    <input type="text" name="address" x-model="form.address"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('address') border-red-500 @enderror">
                    @error('address')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Iqama Type</label>
                    <select name="iqama_type" x-model="form.iqama_type" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @error('iqama_type') border-red-500 @enderror">
                        <option value="">Select</option>
                        <option value="none">None</option>
                        <option value="self">Self</option>
                        <option value="referral">Referral</option>
                    </select>
                    @error('iqama_type')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div x-show="form.iqama_type !== 'none' && form.iqama_type !== ''">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Iqama No. (Self)</label>
                    <input type="text" name="iqama_no" x-model="form.iqama_no"
                           x-show="form.iqama_type !== 'none' && form.iqama_type !== ''"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('iqama_no') border-red-500 @enderror">
                    @error('iqama_no')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Customer Docs (Self)</label>
                    <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 text-center hover:bg-slate-50 transition cursor-pointer"
                         onclick="document.getElementById('customer_docs').click()">
                        <input type="file" id="customer_docs" name="customer_docs[]" class="hidden"
                               accept=".jpg,.jpeg,.png,.pdf" multiple onchange="handleCustomerDocUpload(this)">
                        <div class="text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <span>click to upload</span>
                        </div>
                    </div>
                    <div id="customer_docs_list" class="mt-2 space-y-1"></div>
                </div>
                <div x-show="form.iqama_type === 'referral'">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Referral Iqama No.</label>
                    <input type="text" name="ref_iqama_no" x-model="form.ref_iqama_no"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('ref_iqama_no') border-red-500 @enderror">
                    @error('ref_iqama_no')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div x-show="form.iqama_type === 'referral'">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Referral Mobile No.</label>
                    <input type="text" name="ref_mobile_no" x-model="form.ref_mobile_no"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('ref_mobile_no') border-red-500 @enderror">
                    @error('ref_mobile_no')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div x-show="form.iqama_type === 'referral'">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Upload Ref. Iqama *</label>
                    <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 text-center hover:bg-slate-50 transition cursor-pointer"
                         onclick="document.getElementById('ref_iqama_doc').click()">
                        <input type="file" id="ref_iqama_doc" name="ref_iqama_doc" class="hidden"
                               accept=".jpg,.jpeg,.png,.pdf" onchange="handleRefIqamaFileUpload(this)">
                        <div class="text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <span id="ref_iqama_doc_filename">@if($customer->ref_iqama_doc) {{ $customer->ref_iqama_doc }} @else click to upload @endif</span>
                        </div>
                    </div>
                    @error('ref_iqama_doc')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-200">
                <button type="submit"
                        class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                    Update Customer
                </button>
                <a href="{{ route('customers.index') }}"
                   class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('editCustomerForm', (initial) => ({
            form: {
                name: initial.name,
                passport_no: initial.passport_no,
                mobile_no: initial.mobile_no,
                address: initial.address,
                iqama_type: initial.iqama_type,
                iqama_no: initial.iqama_no,
                ref_iqama_no: initial.ref_iqama_no,
                ref_mobile_no: initial.ref_mobile_no
            }
        }));
    });
</script>
@endpush
@endsection
