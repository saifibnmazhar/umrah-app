@extends('layouts.app')
@section('title', 'Add Customer')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('customers.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Customers
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-2xl p-6" x-data="customerForm()">
        <h3 class="text-xl font-semibold text-slate-800 mb-4">Add New Customer</h3>
        <form @submit.prevent="submitNewCustomer()">
            <div class="grid grid-cols-1 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Name *</label>
                    <input type="text" x-model="newCustomer.name" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Passport No. *</label>
                    <input type="text" x-model="newCustomer.passport_no" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Mobile No. *</label>
                    <input type="text" x-model="newCustomer.mobile_no" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Address (KSA)</label>
                    <input type="text" x-model="newCustomer.address"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Iqama Type</label>
                    <select x-model="newCustomer.iqama_type" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select</option>
                        <option value="none">None</option>
                        <option value="self">Self</option>
                        <option value="referral">Referral</option>
                    </select>
                </div>
                <div x-show="newCustomer.iqama_type !== 'none' && newCustomer.iqama_type !== ''" x-cloak>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Iqama No. (Self)</label>
                    <input type="text" x-model="newCustomer.iqama_no"
                           x-show="newCustomer.iqama_type !== 'none' && newCustomer.iqama_type !== ''" x-cloak
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
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
                    <p class="text-xs text-slate-400 mt-1">Max 5 MB per file, 20 MB total. Allowed: PDF, JPG, PNG</p>
                    <div id="customer_docs_list" class="mt-2 space-y-1"></div>
                </div>
                <div x-show="newCustomer.iqama_type === 'referral'">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Referral Iqama No.</label>
                    <input type="text" x-model="newCustomer.ref_iqama_no"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
                <div x-show="newCustomer.iqama_type === 'referral'">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Referral Mobile No.</label>
                    <input type="text" x-model="newCustomer.ref_mobile_no"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                </div>
                <div x-show="newCustomer.iqama_type === 'referral'">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Upload Ref. Iqama *</label>
                    <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 text-center hover:bg-slate-50 transition cursor-pointer"
                         onclick="document.getElementById('ref_iqama_doc').click()">
                        <input type="file" id="ref_iqama_doc" name="ref_iqama_doc" class="hidden"
                               accept=".jpg,.jpeg,.png,.pdf" onchange="handleRefIqamaFileUpload(this)">
                        <div class="text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <span id="ref_iqama_doc_filename">click to upload</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Max 5 MB per file, 20 MB total. Allowed: PDF, JPG, PNG</p>
                </div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-200">
                <button type="submit"
                        class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                    Save Customer
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
        Alpine.data('customerForm', () => ({
            newCustomer: {
                name: '',
                iqama_type: '',
                iqama_no: '',
                passport_no: '',
                mobile_no: '',
                ref_iqama_no: '',
                ref_mobile_no: '',
                address: ''
            },
            async submitNewCustomer() {
                try {
                    const formData = new FormData();
                    Object.keys(this.newCustomer).forEach(key => {
                        if (this.newCustomer[key] !== null) {
                            formData.append(key, this.newCustomer[key]);
                        }
                    });
                    const fileInput = document.getElementById('ref_iqama_doc');
                    if (fileInput && fileInput.files[0]) {
                        formData.append('ref_iqama_doc', fileInput.files[0]);
                    }
                    const docsInput = document.getElementById('customer_docs');
                    if (docsInput) {
                        Array.from(docsInput.files).forEach(file => {
                            formData.append('customer_docs[]', file);
                        });
                    }
                    const response = await fetch('/customers', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: formData
                    });
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (parseError) {
                        alert('Server error: Received non-JSON response. Check console for details.');
                        return;
                    }
                    if (data.success) {
                        alert('Customer added successfully');
                        window.location.href = '{{ route('customers.index') }}';
                    } else {
                        alert(data.message || 'Failed to add customer');
                    }
                } catch (e) {
                    console.error('Error:', e);
                    alert('Failed to add customer');
                }
            }
        }));
    });
</script>
@endpush
@endsection
