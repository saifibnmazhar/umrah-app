@extends('layouts.app')

@section('title', 'Fingerprint Staff')

@section('content')
<div class="max-w-7xl mx-auto pt-6" x-data="fingerprintStaff()">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Fingerprint Staff</h1>

    <div class="bg-white rounded-lg shadow-md">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-100 text-slate-700 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-3">Invoice ID</th>
                        <th class="px-3 py-3">Customer Name</th>
                        <th class="px-3 py-3">PAX</th>
                        <th class="px-3 py-3">Mobile</th>
                        <th class="px-3 py-3">Office</th>
                        <th class="px-3 py-3">District</th>
                        <th class="px-3 py-3">Deadline</th>
                        <th class="px-3 py-3">Passenger</th>
                        <th class="px-3 py-3 text-right">Cost (SAR)</th>
                        <th class="px-3 py-3">Fingerprint Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" id="tableBody">
                    <template x-if="loading">
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500">No fingerprint tasks found</td>
                        </tr>
                    </template>
                    <template x-for="(row, index) in data" :key="row.fingerprint_detail_id">
                        <tr class="hover:bg-slate-50"
                            :class="index % 2 !== 0 ? 'bg-slate-50' : 'bg-white'"
                            :style="index === data.length - 1 || (index < data.length - 1 && data[index + 1]?.invoice_id !== row.invoice_id) ? 'border-bottom: 2px solid #94a3b8;' : ''">
                            <td class="px-3 py-2 font-medium text-slate-800" x-text="row.invoice_id"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.customer_name"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.pax_qty"></td>
                            <td class="px-3 py-2 text-slate-600 whitespace-pre-line" x-text="row.customer_mobile + '\n' + row.passenger_mobile"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.office"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.district"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.deadline"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.passenger_name"></td>
                            <td class="px-3 py-2">
                                <input type="number"
                                       :value="row.cost"
                                       @change="updateCost(row.fingerprint_id, $event.target.value)"
                                       class="w-20 text-right text-sm border border-slate-300 rounded px-2 py-1"
                                       min="0">
                            </td>
                            <td class="px-3 py-2">
                                <select @change="handleStatusChange(row.fingerprint_detail_id, $event.target.value)"
                                        class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                                    <option value="none" :selected="row.fingerprint_status === 'none'">none</option>
                                    <option value="processing" :selected="row.fingerprint_status === 'processing'">processing</option>
                                    <option value="approved" :selected="row.fingerprint_status === 'approved'">approved</option>
                                    <option value="cancelled" :selected="row.fingerprint_status === 'cancelled'">cancelled</option>
                                    <option value="hold">Hold and ask for next fingerprint date?</option>
                                </select>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Hold Modal -->
    <div x-show="showHoldModal"
         class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="flex justify-between items-center px-6 py-4 border-b border-slate-200 bg-slate-50 rounded-t-xl">
                <h3 class="text-lg font-bold text-slate-800">Hold by BMT/Client</h3>
                <button @click="hideHoldModal()" class="text-slate-500 hover:text-slate-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                    <select x-model="holdForm.reason" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">Select Reason</option>
                        <option value="rescheduled_by_client">Reschedule by Client</option>
                        <option value="rescheduled_by_bmt">Reschedule by BMT</option>
                        <option value="nfc_problem">NFC Problem</option>
                        <option value="others">Others</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Next Finger Date</label>
                    <input type="date" x-model="holdForm.next_date" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                    <input type="text" x-model="holdForm.remarks" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2" placeholder="Enter remarks">
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-xl">
                <button @click="hideHoldModal()" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Cancel</button>
                <button @click="saveHold()" class="px-4 py-2 text-sm font-medium text-white bg-slate-700 rounded-lg hover:bg-slate-800">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fingerprintStaff() {
    return {
        data: [],
        loading: true,
        showHoldModal: false,
        currentFingerprintDetailId: null,
        holdForm: {
            reason: '',
            next_date: '',
            remarks: '',
        },

        async init() {
            await this.loadData();
        },

        async loadData() {
            this.loading = true;
            try {
                const response = await fetch('/api/fingerprints/staff');
                const result = await response.json();
                this.data = result.data || [];
            } catch (error) {
                console.error('Failed to load fingerprint data:', error);
                this.data = [];
            } finally {
                this.loading = false;
            }
        },

        handleStatusChange(fingerprintDetailId, value) {
            if (value === 'hold') {
                this.currentFingerprintDetailId = fingerprintDetailId;
                this.showHoldModal = true;
                this.holdForm = {
                    reason: '',
                    next_date: '',
                    remarks: '',
                };
            } else {
                this.updateStatus(fingerprintDetailId, value);
            }
        },

        async updateStatus(fingerprintDetailId, status) {
            try {
                const response = await fetch(`/api/fingerprints/detail/${fingerprintDetailId}/status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ status }),
                });
                const result = await response.json();
                if (result.success) {
                    this.showToast('Status updated successfully');
                    const row = this.data.find(r => r.fingerprint_detail_id === fingerprintDetailId);
                    if (row) {
                        row.fingerprint_status = status;
                        row.fingerprint_status_display = this.computeStatusDisplay(status, row.invoice_id);
                    }
                }
            } catch (error) {
                console.error('Failed to update status:', error);
                this.showToast('Failed to update status', 'error');
            }
        },

        async updateCost(fingerprintId, cost) {
            try {
                const response = await fetch(`/api/fingerprints/${fingerprintId}/cost`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ cost: parseFloat(cost) }),
                });
                const result = await response.json();
                if (result.success) {
                    this.showToast('Cost updated successfully');
                    this.data.forEach(row => {
                        if (row.fingerprint_id === fingerprintId) {
                            row.cost = parseFloat(cost);
                        }
                    });
                }
            } catch (error) {
                console.error('Failed to update cost:', error);
                this.showToast('Failed to update cost', 'error');
            }
        },

        hideHoldModal() {
            this.showHoldModal = false;
            this.currentFingerprintDetailId = null;
        },

        async saveHold() {
            if (!this.holdForm.reason) {
                this.showToast('Please select a reason', 'error');
                return;
            }
            if (!this.holdForm.next_date) {
                this.showToast('Please select next finger date', 'error');
                return;
            }

            try {
                const response = await fetch(`/api/fingerprints/detail/${this.currentFingerprintDetailId}/hold`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.holdForm),
                });
                const result = await response.json();
                if (result.success) {
                    this.showToast('Hold created successfully');
                    this.hideHoldModal();
                    await this.loadData();
                }
            } catch (error) {
                console.error('Failed to save hold:', error);
                this.showToast('Failed to save hold', 'error');
            }
        },

        computeStatusDisplay(status, invoiceId) {
            if (status !== 'approved') {
                return status;
            }

            const invoiceRows = this.data.filter(r => r.invoice_id === invoiceId);
            const allApproved = invoiceRows.every(r => r.fingerprint_status === 'approved');

            if (allApproved) {
                return 'approved';
            }

            return 'Partially Approved';
        },

        getStatusClass(status) {
            const classes = {
                'none': 'bg-gray-100 text-gray-800',
                'processing': 'bg-yellow-100 text-yellow-800',
                'approved': 'bg-green-100 text-green-800',
                'Partially Approved': 'bg-green-100 text-green-800',
                'cancelled': 'bg-red-100 text-red-800',
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-2 rounded shadow-lg ${type === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        },
    };
}
</script>
@endpush