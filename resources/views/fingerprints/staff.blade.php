@extends('layouts.app')

@section('title', 'Fingerprint Staff')

@section('content')
<div class="w-full mx-auto pt-6" x-data="fingerprintStaff({ isFingerprintStaff: @json($isFingerprintStaff), canEditCost: @json($canEditCost) })">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-semibold text-slate-700 mb-6">Fingerprint Staff</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                        <th class="px-3 py-2 text-left font-medium">Customer Name</th>
                        <th class="px-3 py-2 text-left font-medium">PAX Qty</th>
                        <th class="px-3 py-2 text-left font-medium">Mobile</th>
                        <th class="px-3 py-2 text-left font-medium">Fingerprint Branch</th>
                        <th class="px-3 py-2 text-left font-medium">District</th>
                        <th class="px-3 py-2 text-left font-medium">Fingerprint Deadline</th>
                        <th class="px-3 py-2 text-left font-medium">Passenger</th>
                        <th class="px-3 py-2 text-left font-medium">Passenger Address</th>
                        <th class="px-3 py-2 text-right font-medium" x-text="'Cost (' + $store.currency.mode + ')'"></th>
                        <th class="px-3 py-2 text-left font-medium">Fingerprint Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="11" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="11" class="px-3 py-8 text-center text-slate-500">No fingerprint tasks assigned</td>
                        </tr>
                    </template>
                    <template x-for="(row, index) in data" :key="row.fingerprint_detail_id || index">
                        <tr :class="getRowClass(row)">
                            <td class="px-3 py-2 text-slate-800 font-medium" x-text="row.isFirstInGroup ? (row.invoice_id || '-') : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.isFirstInGroup ? (row.customer_name || '-') : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.isFirstInGroup ? row.pax_qty : ''"></td>
                            <td class="px-3 py-2 text-slate-600 whitespace-pre-line" x-text="getMobileDisplay(row)"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.isFirstInGroup ? (row.fingerprint_branch_name || '-') : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.isFirstInGroup ? (row.district || '-') : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.isFirstInGroup ? (row.deadline || '-') : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.passenger_name || '-'"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.passenger_address || '-'"></td>
                            <td class="px-3 py-2 text-right">
                                <input type="number"
                                       x-show="row.isFirstInGroup && canEditCost"
                                        :value="inputValue(row.cost, row.rate, currencyToggleCounter)"
                                        @change="updateCost(row.fingerprint_id, $event.target.value)"
                                       class="w-20 text-right text-sm border border-slate-300 rounded px-2 py-1"
                                       min="0">
<span x-show="row.isFirstInGroup && !canEditCost"
      class="text-sm text-slate-700 font-medium"
      x-text="formatCost(row.cost, row.rate, currencyToggleCounter)"></span>
                            </td>
                            <td class="px-3 py-2">
                                <select x-show="canEditStatus"
                                        @change="handleStatusChange(row.fingerprint_detail_id, $event, row.cost, row.fingerprint_location)"
                                        class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                                    <template x-for="opt in displayStatuses" :key="opt">
                                        <option :value="opt"
                                                :selected="getSelectedStatus(row) === opt"
                                                x-text="opt"></option>
                                    </template>
                                </select>
                                <span x-show="!canEditStatus"
                                      class="px-2 py-1 rounded-full text-xs font-medium"
                                      :class="getStatusClass(row.fingerprint_status_display)"
                                      x-text="row.fingerprint_status_display"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="lastPage > 1" class="mt-4 flex items-center justify-between border-t border-slate-200 pt-4 px-1">
            <div class="text-sm text-slate-600">
                Showing <span class="font-medium" x-text="((currentPage - 1) * 10 + 1)"></span>
                to <span class="font-medium" x-text="Math.min(currentPage * 10, totalRecords)"></span>
                of <span class="font-medium" x-text="totalRecords"></span> results
            </div>
            <nav class="flex items-center gap-1">
                <button @click="changePage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors">
                    Previous
                </button>
                <template x-for="page in Array.from({ length: lastPage }, (_, i) => i + 1)" :key="page">
                    <button @click="changePage(page)"
                            :class="page === currentPage ? 'bg-slate-700 text-white border-slate-700' : 'text-slate-600 hover:bg-slate-100 border-slate-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-lg border bg-white transition-colors"
                            x-text="page">
                    </button>
                </template>
                <button @click="changePage(currentPage + 1)"
                        :disabled="currentPage === lastPage"
                        :class="currentPage === lastPage ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors">
                    Next
                </button>
            </nav>
        </div>
    </div>

    <!-- Hold Modal -->
    <div x-show="showHoldModal"
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
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
                <div x-show="holdForm.reason === 'others'">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Other Reason</label>
                    <input type="text" x-model="holdForm.other_reason" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2" placeholder="Enter other reason">
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

    <div x-show="toastVisible"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="translate-x-0 opacity-100"
         x-transition:leave-end="translate-x-full opacity-0"
         class="fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white font-medium"
         :class="{
             'bg-slate-700': toastType === 'info',
             'bg-emerald-600': toastType === 'success',
             'bg-red-500': toastType === 'error',
             'bg-amber-500': toastType === 'warning'
         }">
        <span x-text="toastMessage"></span>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fingerprintStaff(options = {}) {
    return {
        data: [],
        loading: true,
        currentPage: 1,
        lastPage: 1,
        totalRecords: 0,
        canEditStatus: options.isFingerprintStaff ?? false,
        canEditCost: options.canEditCost ?? false,
        currencyToggleCounter: 0,
        showHoldModal: false,
        currentFingerprintDetailId: null,
        toastMessage: '',
        toastType: 'error',
        toastVisible: false,
        holdForm: {
            reason: '',
            next_date: '',
            remarks: '',
            other_reason: '',
        },
        displayStatuses: [
            'None',
            'Processing',
            'Approved',
            'Partially Approved',
            'Cancel',
            'Hold & Ask for next Finger date?'
        ],

        async init() {
            window.addEventListener('currency-toggled', () => {
                this.currencyToggleCounter++;
            });
            await this.loadData();
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ page: this.currentPage });
                const response = await fetch(`/api/fingerprints/staff?${params}`);
                const result = await response.json();
                const rawData = result.data || [];
                this.data = this.enrichData(rawData);
                if (result.pagination) {
                    this.currentPage = result.pagination.current_page;
                    this.lastPage = result.pagination.last_page;
                    this.totalRecords = result.pagination.total;
                }
            } catch (error) {
                console.error('Failed to load fingerprint data:', error);
                this.data = [];
            } finally {
                this.loading = false;
            }
        },

        changePage(page) {
            if (page < 1 || page > this.lastPage || page === this.currentPage) return;
            this.currentPage = page;
            this.loadData();
        },

        enrichData(data) {
            if (!data || !data.length) return [];

            const groups = {};
            const groupOrder = [];

            data.forEach(row => {
                const groupKey = row.invoice_id || `fp_${row.fingerprint_id}`;
                if (!groups[groupKey]) {
                    groups[groupKey] = { invoice_id: row.invoice_id, rows: [] };
                    groupOrder.push(groupKey);
                }
                groups[groupKey].rows.push(row);
            });

            const result = [];
            groupOrder.forEach((groupKey, invIdx) => {
                const group = groups[groupKey];
                group.rows.forEach((row, paxIdx) => {
                    result.push({
                        ...row,
                        isFirstInGroup: paxIdx === 0,
                        isLastInGroup: paxIdx === group.rows.length - 1,
                        invoiceIndex: invIdx,
                    });
                });
            });

            return result;
        },

        getRowClass(row) {
            const isOddInvoice = row.invoiceIndex % 2 !== 0;
            let cls = 'hover:bg-slate-100 ';
            cls += isOddInvoice ? 'bg-slate-50 ' : 'bg-white ';
            cls += 'border-l-4 ';
            cls += isOddInvoice ? 'border-l-blue-500' : 'border-l-orange-500';
            if (row.isLastInGroup) {
                cls += ' border-b-2 border-slate-400';
            }
            return cls;
        },

        getMobileDisplay(row) {
            const parts = [];
            if (row.customer_mobile) parts.push(row.customer_mobile);
            if (row.passenger_mobile) parts.push(row.passenger_mobile);
            return parts.join('\n') || '-';
        },

        getSelectedStatus(row) {
            if (row.fingerprint_status_display === 'Partially Approved') {
                return 'Partially Approved';
            }
            const map = {
                'none': 'None',
                'processing': 'Processing',
                'approved': 'Approved',
                'cancelled': 'Cancel',
            };
            return map[row.fingerprint_status] || 'None';
        },

        mapDisplayToBackend(displayValue) {
            const map = {
                'None': 'none',
                'Processing': 'processing',
                'Approved': 'approved',
                'Partially Approved': 'approved',
                'Cancel': 'cancelled',
            };
            return map[displayValue] || 'none';
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

        handleStatusChange(fingerprintDetailId, event, cost, fingerprintLocation) {
            const value = event.target.value;

            if (value === 'Hold & Ask for next Finger date?') {
                this.currentFingerprintDetailId = fingerprintDetailId;
                this.showHoldModal = true;
                this.holdForm = { reason: '', next_date: '', remarks: '', other_reason: '' };
                return;
            }

            if (fingerprintLocation === 'home' && (!cost || parseFloat(cost) <= 0)) {
                const row = this.data.find(r => r.fingerprint_detail_id === fingerprintDetailId);
                if (row) {
                    event.target.value = this.getSelectedStatus(row);
                }
                this.showToast('Please set the fingerprint cost first', 'error');
                return;
            }

            this.updateStatus(fingerprintDetailId, this.mapDisplayToBackend(value));
        },

        async updateStatus(fingerprintDetailId, status) {
            if (!fingerprintDetailId) {
                this.showToast('Cannot update: no fingerprint detail record', 'error');
                return;
            }
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
                    await this.loadData();
                } else {
                    this.showToast(result.message || 'Failed to update status', 'error');
                }
            } catch (error) {
                console.error('Failed to update status:', error);
                this.showToast('Failed to update status', 'error');
            }
        },

        async updateCost(fingerprintId, cost) {
            if (!fingerprintId) return;
            const store = Alpine.store('currency');
            let inputVal = parseFloat(cost) || 0;
            let costInSar = inputVal;
            if (store.mode === 'BDT' && store.rate > 0) {
                costInSar = inputVal / store.rate;
            }
            costInSar = parseFloat(costInSar.toFixed(6));
            try {
                const response = await fetch(`/api/fingerprints/${fingerprintId}/cost`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ cost: costInSar }),
                });
                const result = await response.json();
                if (result.success) {
                    this.showToast('Cost updated successfully');
                    this.data.forEach(row => {
                        if (row.fingerprint_id === fingerprintId) {
                            row.cost = costInSar;
                        }
                    });
                    this.data = this.enrichData(this.data);
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
            if (this.holdForm.reason === 'others' && !this.holdForm.other_reason) {
                this.showToast('Please enter other reason', 'error');
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

        showToast(message, type = 'success') {
            this.toastMessage = message;
            this.toastType = type;
            this.toastVisible = true;
            if (this._toastTimer) clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => this.toastVisible = false, 3000);
        },

        formatCost(cost, rate, _) {
            if (cost == null || cost === '') return '';
            return Alpine.store('currency').format(cost, 6, rate);
        },

        inputValue(cost, rate, _) {
            if (cost == null || cost === '') return '';
            const store = Alpine.store('currency');
            const num = Number(cost) || 0;
            if (store.mode === 'SAR') return num;
            const effectiveRate = rate !== null ? rate : store.rate;
            if (effectiveRate <= 0) return '';
            return parseFloat((num * effectiveRate).toFixed(6));
        },
    };
}
</script>
@endpush
