@extends('layouts.app')

@section('title', 'Fingerprint Admin')

@section('content')
<div class="w-full mx-auto pt-6" x-data='fingerprintAdmin({ canAssignStaff: @json($canAssignStaff), flightDateRanges: @json($flightDateRanges) })'>
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
            <input type="text" x-model="filters.search" @input.debounce.400ms="currentPage = 1; loadData()"
                   placeholder="Search by Invoice ID, Customer Name, or Passenger Name..."
                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Division</label>
                <select x-model="filters.division" @change="filters.district=''; currentPage = 1; loadData()" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
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
                    <template x-for="d in districtsList.filter(x => !filters.division || x.division === filters.division)" :key="d.id">
                        <option :value="d.id" x-text="d.name"></option>
                    </template>
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
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-slate-400 mb-1">Fingerprint Status</label>
                <select x-model="filters.fingerprint_status" @change="currentPage = 1; loadData()"
                        class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">Select Status</option>
                    @foreach($fingerprintStatuses as $status)
                    <option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-slate-400 mb-1">Deadline From</label>
                <input type="date" x-model="filters.deadline_from" @change="currentPage = 1; loadData()"
                       class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-slate-400 mb-1">Deadline To</label>
                <input type="date" x-model="filters.deadline_to" @change="currentPage = 1; loadData()"
                       class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-slate-400 mb-1">Required Flight</label>
                <select x-model="filters.flight_date_range" @change="onFlightDateRangeChange"
                        class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">All</option>
                    @foreach($flightDateRanges as $range)
                    <option value="{{ $range['id'] }}">{{ $range['label'] }}</option>
                    @endforeach
                </select>
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
                        <th class="px-3 py-2 text-left font-medium">Reschedule Deadline</th>
                        <th class="px-3 py-2 text-right font-medium">Fingerprint Cost</th>
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
                            <td colspan="15" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="15" class="px-3 py-8 text-center text-slate-500">No fingerprint tasks found</td>
                        </tr>
                    </template>
                    <template x-for="(row, rowIndex) in data" :key="row.fingerprint_detail_id || rowIndex">
                        <tr class="hover:bg-slate-50 border-b border-slate-200"
                            :class="['border-l-[3px] border-l-solid', row._isOddInvoice ? 'border-l-blue-500' : 'border-l-orange-500']"
                            :style="row._isLastPassenger ? 'border-bottom: 2px solid #94a3b8; border-bottom-style: solid;' : ''">
                            <td class="px-3 py-2 text-slate-800 font-medium">
                                <span x-text="row._isFirstPassenger ? row.invoice_id : ''"></span>
                                <span x-show="row._isFirstPassenger && row.cancellation_status"
                                      class="ml-2 px-1.5 py-0.5 text-xs font-semibold rounded"
                                      :class="row.cancellation_status === 'cancellation processing' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'"
                                      x-text="row.cancellation_status === 'cancellation processing' ? 'Cancellation Processing' : 'Cancelled'"></span>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? row.booking_date : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? row.customer_name : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? row.pax_qty : ''"></td>
                            <td class="px-3 py-2 text-slate-600 whitespace-pre-line" x-text="(row.customer_mobile || '') + '\n' + (row.passenger_mobile || '')"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? (row.deadline || '-') : ''"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.reschedule_deadline || '-'"></td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">
                                <span x-show="row._isFirstPassenger" x-text="formatCost(row.cost, row.rate, currencyToggleCounter)"></span>
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                <span x-show="row._isFirstPassenger" x-text="row.fingerprint_location || '-'"></span>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row._isFirstPassenger ? row.district : ''"></td>
                            <td class="px-3 py-2">
                                <span x-show="row._isFirstPassenger">
                                    <select @change="canAssignStaff && row.fingerprint_location !== 'office' && !row.is_cancelled && assignStaff(row.fingerprint_id, $event.target.value)"
                                            :disabled="!canAssignStaff || row.fingerprint_location === 'office' || row.is_cancelled"
                                            class="text-xs border border-slate-300 rounded px-2 py-1 bg-white"
                                            :class="!canAssignStaff || row.fingerprint_location === 'office' || row.is_cancelled ? 'opacity-60 cursor-not-allowed' : ''">
                                        <option value="">Select Staff</option>
                                        <template x-for="staff in getStaffOptionsForRow(row)" :key="staff.id">
                                            <option :value="staff.id" :selected="staff.id == row.assigned_staff_id" x-text="staff.name"></option>
                                        </template>
                                    </select>
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.passenger_name"></td>
                            <td class="px-3 py-2">
                                <template x-if="canAssignStaff && row.fingerprint_location === 'office' && !row.is_cancelled && row.passenger_status !== 'Hold' && row.passenger_status !== 'Cancel'">
                                    <select @change="handleStatusChange(row.fingerprint_detail_id, $event.target.value)"
                                            class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                                        <template x-for="opt in displayStatuses" :key="opt">
                                            <option :value="opt"
                                                    :selected="getSelectedStatus(row) === opt"
                                                    x-text="opt"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="!canAssignStaff || row.fingerprint_location !== 'office' || row.is_cancelled || ['Hold', 'Cancel'].includes(row.passenger_status)">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium"
                                              :class="getStatusClass(row.fingerprint_status_display)"
                                              x-text="row.fingerprint_status_display"></span>
                                        <span x-show="['Hold', 'Cancel'].includes(row.passenger_status)"
                                              class="px-2 py-1 rounded-full text-xs font-medium"
                                              :class="row.passenger_status === 'Cancel' ? 'bg-red-100 text-red-700' : 'bg-purple-100 text-purple-700'"
                                              x-text="row.passenger_status"></span>
                                    </span>
                                </template>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.required_flight_date || '-'"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.actual_flight_date || '-'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="lastPage > 1" x-cloak class="mt-4 flex items-center justify-between border-t border-slate-200 pt-4 px-1">
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
                    Prev
                </button>
                <span class="px-4 py-1.5 text-sm font-semibold text-slate-700">
                    <span x-text="currentPage"></span>/<span x-text="lastPage"></span>
                </span>
                <button @click="changePage(currentPage + 1)"
                        :disabled="currentPage === lastPage"
                        :class="currentPage === lastPage ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors">
                    Next
                </button>
            </nav>
        </div>
    </div>

    <div x-show="showHoldModal" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
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
</div>
@endsection

@push('scripts')
<script>
function fingerprintAdmin(options = {}) {
    return {
        data: [],
        loading: true,
        staffListsByBranch: {},
        currentPage: 1,
        lastPage: 1,
        totalRecords: 0,
        canAssignStaff: options.canAssignStaff ?? false,
        currencyToggleCounter: 0,
        showHoldModal: false,
        currentFingerprintDetailId: null,
        holdForm: {
            reason: '',
            next_date: '',
            remarks: '',
            other_reason: '',
        },
        flightDateRanges: options.flightDateRanges || [],
        displayStatuses: ['None', 'Processing', 'Fingerprint Done', 'Approved', 'Partially Approved', 'Cancel', 'Hold & Ask for next Finger date?'],
        districtsList: @json($districts->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'division' => $d->division])),
        filters: {
            search: '',
            division: '',
            district: '',
            fingerprint_location: '',
            fingerprint_status: '',
            deadline_from: '',
            deadline_to: '',
            flight_date_range: '',
            flight_date_from: '',
            flight_date_to: '',
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
            window.addEventListener('currency-toggled', () => {
                this.currencyToggleCounter++;
            });
            await this.loadData();
        },

        async loadStaffListsForBranches(data) {
            const items = data || this.data;
            const branchIds = [...new Set(
                (items || [])
                    .map(r => r && r.fingerprint_branch_id)
                    .filter(id => id !== null && id !== undefined && id !== '')
            )];

            await Promise.all(branchIds.map(async (branchId) => {
                const key = String(branchId);
                if (this.staffListsByBranch[key]) return;
                try {
                    const response = await fetch(`/api/fingerprints/staff-list?fingerprint_branch_id=${encodeURIComponent(branchId)}`);
                    const result = await response.json();
                    this.staffListsByBranch[key] = result.data || [];
                } catch (error) {
                    console.error(`Failed to load staff list for branch ${branchId}:`, error);
                    this.staffListsByBranch[key] = [];
                }
            }));
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ ...this.filters, page: this.currentPage });
                const response = await fetch(`/api/fingerprints/admin?${params}`);
                const result = await response.json();
                const rawData = result.data || [];
                const processedData = this.processData(rawData);

                await this.loadStaffListsForBranches(processedData);

                this.data = processedData;

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

        onFlightDateRangeChange() {
            if (this.filters.flight_date_range) {
                const range = this.flightDateRanges.find(r => r.id == this.filters.flight_date_range);
                if (range) {
                    this.filters.flight_date_from = range.start;
                    this.filters.flight_date_to = range.end;
                }
            } else {
                this.filters.flight_date_from = '';
                this.filters.flight_date_to = '';
            }
            this.currentPage = 1;
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
                            const options = this.getStaffOptionsForRow(row);
                            const staff = options.find(s => s.id == staffId);
                            row.assigned_staff_name = staff?.name;
                        }
                    });
                } else {
                    window.showToast(result.message || 'Failed to assign staff', 'error');
                }
            } catch (error) {
                console.error('Failed to assign staff:', error);
                window.showToast('Failed to assign staff', 'error');
            }
        },

        getStaffOptionsForRow(row) {
            const branchKey = row && row.fingerprint_branch_id != null ? String(row.fingerprint_branch_id) : '';
            const list = (branchKey && this.staffListsByBranch[branchKey]) || [];
            if (!row || !row.assigned_staff_id) return list;
            const exists = list.some(s => s.id == row.assigned_staff_id);
            if (exists) return list;
            const pinned = {
                id: row.assigned_staff_id,
                name: row.assigned_staff_name || `Staff #${row.assigned_staff_id}`,
                _pinned: true,
            };
            return [pinned, ...list];
        },

        getStatusClass(status) {
            const classes = {
                'none': 'bg-gray-100 text-gray-800',
                'processing': 'bg-yellow-100 text-yellow-800',
                'approved': 'bg-green-100 text-green-800',
                'Partially Approved': 'bg-green-100 text-green-800',
                'cancelled': 'bg-red-100 text-red-800',
                'done': 'bg-blue-100 text-blue-800',
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
                'done': 'Fingerprint Done',
                'approved': 'Approved',
                'cancelled': 'Cancel',
            };
            return map[row.fingerprint_status] || 'None';
        },

        mapDisplayToBackend(displayValue) {
            const map = {
                'None': 'none',
                'Processing': 'processing',
                'Fingerprint Done': 'done',
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
                this.holdForm = { reason: '', next_date: '', remarks: '', other_reason: '' };
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
                } else {
                    window.showToast(result.message || 'Failed to update status', 'error');
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
            if (this.holdForm.reason === 'others' && !this.holdForm.other_reason) {
                window.showToast('Please enter other reason', 'error');
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

        formatCost(cost, rate, _) {
            if (cost == null || cost === '') return 'N/A';
            return Alpine.store('currency').format(cost, 2, rate);
        },
    };
}
</script>
@endpush
