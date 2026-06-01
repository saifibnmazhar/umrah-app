@extends('layouts.app')

@section('title', 'Fingerprint Admin')

@section('content')
<div class="w-full mx-auto pt-6" x-data="fingerprintAdmin({ canAssignStaff: @json($canAssignStaff) })">
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Division</label>
                    <select x-model="filters.division" @change="currentPage = 1; loadData()" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">All Divisions</option>
                        @foreach($divisions ?? [] as $division)
                        <option value="{{ $division }}">{{ $division }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">District</label>
                    <select x-model="filters.district" @change="currentPage = 1; loadData()" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                    <option value="">All Districts</option>
                    @foreach($districts ?? [] as $district)
                    <option value="{{ $district->id }}">{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fingerprint Location</label>
                    <select x-model="filters.fingerprint_location" @change="currentPage = 1; loadData()" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">All</option>
                        <option value="home">Home</option>
                        <option value="office">Office</option>
                    </select>
                </div>
            <div class="flex items-end">
                    <button @click="currentPage = 1; loadData()" class="px-4 py-2 text-sm font-medium text-white bg-slate-700 rounded-lg hover:bg-slate-800">
                        Filter
                    </button>
                </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-semibold text-slate-700 mb-6">Fingerprint Admin</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                        <th class="px-3 py-2 text-left font-medium">Booking Date</th>
                        <th class="px-3 py-2 text-left font-medium">Customer Name</th>
                        <th class="px-3 py-2 text-left font-medium">PAX Qty</th>
                        <th class="px-3 py-2 text-left font-medium">Mobile</th>
                        <th class="px-3 py-2 text-left font-medium">Fingerprint Deadline</th>
                        <th class="px-3 py-2 text-right font-medium">Fingerprint Charge</th>
                        <th class="px-3 py-2 text-left font-medium">Fingerprint Location</th>
                        <th class="px-3 py-2 text-left font-medium">District</th>
                        <th class="px-3 py-2 text-left font-medium">Assign Staff</th>
                        <th class="px-3 py-2 text-left font-medium">Passenger</th>
                        <th class="px-3 py-2 text-left font-medium">Fingerprint Status</th>
                        <th class="px-3 py-2 text-left font-medium">Required Flight Date</th>
                        <th class="px-3 py-2 text-left font-medium">Actual Flight Date</th>
                    </tr>
                </thead>
                <tbody id="fingerprintAdminTableBody">
                    <template x-if="loading">
                        <tr>
                            <td colspan="14" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="14" class="px-3 py-8 text-center text-slate-500">No fingerprint tasks found</td>
                        </tr>
                    </template>
                    <template x-for="(row, rowIndex) in data" :key="row.fingerprint_detail_id || rowIndex">
                        <tr class="hover:bg-slate-50 border-b border-slate-200"
                            :class="['border-l-[3px] border-l-solid', row._isOddInvoice ? 'border-l-blue-500' : 'border-l-orange-500']"
                            :style="row._isLastPassenger ? 'border-bottom: 2px solid #94a3b8; border-bottom-style: solid;' : ''">
                            <td class="px-3 py-2 text-slate-800 font-medium" x-text="row._isFirstPassenger ? row.invoice_id : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? row.booking_date : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? row.customer_name : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? row.pax_qty : ''"></td>
                            <td class="px-3 py-2 text-slate-600 whitespace-pre-line" x-text="(row.customer_mobile || '') + '\n' + (row.passenger_mobile || '')"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? (row.deadline || '-') : ''"></td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">
                                <span x-show="row._isFirstPassenger" x-text="row.cost != null && row.cost != '' ? row.cost + ' SAR' : 'N/A'"></span>
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                <span x-show="row._isFirstPassenger" x-text="row.fingerprint_location || '-'"></span>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? row.district : ''"></td>
                            <td class="px-3 py-2">
                                <span x-show="row._isFirstPassenger">
                                    <select @change="canAssignStaff && row.fingerprint_location !== 'office' && assignStaff(row.fingerprint_id, $event.target.value)"
                                            :disabled="!canAssignStaff || row.fingerprint_location === 'office'"
                                            class="text-xs border border-slate-300 rounded px-2 py-1 bg-white"
                                            :class="!canAssignStaff || row.fingerprint_location === 'office' ? 'opacity-60 cursor-not-allowed' : ''">
                                        <option value="">Select Staff</option>
                                        <template x-for="staff in staffList" :key="staff.id">
                                            <option :value="staff.id" :selected="staff.id == row.assigned_staff_id" x-text="staff.name"></option>
                                        </template>
                                    </select>
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.passenger_name"></td>
                            <td class="px-3 py-2">
                                <template x-if="canAssignStaff && row.fingerprint_location === 'office'">
                                    <select @change="handleStatusChange(row.fingerprint_detail_id, $event.target.value)"
                                            class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                                        <template x-for="opt in displayStatuses" :key="opt">
                                            <option :value="opt"
                                                    :selected="getSelectedStatus(row) === opt"
                                                    x-text="opt"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="!canAssignStaff || row.fingerprint_location !== 'office'">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium"
                                          :class="getStatusClass(row.fingerprint_status_display)"
                                          x-text="row.fingerprint_status_display"></span>
                                </template>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.required_flight_date || '-'"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.actual_flight_date || '-'"></td>
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

    <div x-show="showHoldModal"
         class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
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
function fingerprintAdmin(options = {}) {
    return {
        data: [],
        loading: true,
        staffList: [],
        currentPage: 1,
        lastPage: 1,
        totalRecords: 0,
        canAssignStaff: options.canAssignStaff ?? false,
        showHoldModal: false,
        currentFingerprintDetailId: null,
        holdForm: {
            reason: '',
            next_date: '',
            remarks: '',
        },
        displayStatuses: ['None', 'Processing', 'Approved', 'Partially Approved', 'Cancel', 'Hold & Ask for next Finger date?'],
        filters: {
            division: '',
            district: '',
            fingerprint_location: '',
        },
        // showHoldModal: false,
        // currentHoldDetailId: null,
        // currentHoldRowIndex: null,
        // holdForm: {
        //     reason: '',
        //     next_date: '',
        //     remarks: '',
        // },

        async init() {
            await this.loadStaffList();
            await this.loadData();
        },

        async loadStaffList() {
            try {
                const response = await fetch('/api/fingerprints/staff-list');
                const result = await response.json();
                this.staffList = result.data || [];
            } catch (error) {
                console.error('Failed to load staff list:', error);
                this.staffList = [];
            }
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ ...this.filters, page: this.currentPage });
                const response = await fetch(`/api/fingerprints/admin?${params}`);
                const result = await response.json();
                const rawData = result.data || [];
                this.data = this.processData(rawData);
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

        processData(rawData) {
            let invoiceIndex = -1;
            let lastInvoiceId = null;

            return (rawData || []).filter(row => row != null).map((row, index, arr) => {
                if (row.invoice_id !== lastInvoiceId) {
                    invoiceIndex++;
                    lastInvoiceId = row.invoice_id;
                }

                const prevRow = arr[index - 1];
                const nextRow = arr[index + 1];
                const isFirst = !prevRow || prevRow.invoice_id !== row.invoice_id;
                const isLast = !nextRow || nextRow.invoice_id !== row.invoice_id;

                return {
                    ...row,
                    _isFirstPassenger: isFirst,
                    _isLastPassenger: isLast,
                    _isOddInvoice: invoiceIndex % 2 === 1,
                };
            });
        },

        async assignStaff(fingerprintId, staffId) {
            if (!staffId) return;
            try {
                const response = await fetch(`/api/fingerprints/${fingerprintId}/staff`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ assigned_staff_id: staffId }),
                });
                const result = await response.json();
                if (result.success) {
                    window.showToast('Staff assigned successfully', 'success');
                    this.data.forEach(row => {
                        if (row.fingerprint_id === fingerprintId) {
                            row.assigned_staff_id = staffId;
                            const staff = this.staffList.find(s => s.id == staffId);
                            row.assigned_staff_name = staff?.name;
                        }
                    });
                }
            } catch (error) {
                console.error('Failed to assign staff:', error);
                window.showToast('Failed to assign staff', 'error');
            }
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

        handleStatusChange(fingerprintDetailId, value) {
            if (value === 'Hold & Ask for next Finger date?') {
                this.currentFingerprintDetailId = fingerprintDetailId;
                this.showHoldModal = true;
                this.holdForm = { reason: '', next_date: '', remarks: '' };
                return;
            }
            this.updateStatus(fingerprintDetailId, this.mapDisplayToBackend(value));
        },

        async updateStatus(fingerprintDetailId, status) {
            if (!fingerprintDetailId) {
                window.showToast('Cannot update: no fingerprint detail record', 'error');
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
                    window.showToast('Status updated successfully', 'success');
                    await this.loadData();
                }
            } catch (error) {
                console.error('Failed to update status:', error);
                window.showToast('Failed to update status', 'error');
            }
        },

        hideHoldModal() {
            this.showHoldModal = false;
            this.currentFingerprintDetailId = null;
        },

        async saveHold() {
            if (!this.holdForm.reason) {
                window.showToast('Please select a reason', 'error');
                return;
            }
            if (!this.holdForm.next_date) {
                window.showToast('Please select next finger date', 'error');
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
                    window.showToast('Hold created successfully', 'success');
                    this.hideHoldModal();
                    await this.loadData();
                }
            } catch (error) {
                console.error('Failed to save hold:', error);
                window.showToast('Failed to save hold', 'error');
            }
        },
        //     } catch (error) {
        //         console.error('Failed to save hold:', error);
        //         window.showToast('Failed to save hold', 'error');
        //     }
        // },
    };
}
</script>
@endpush
