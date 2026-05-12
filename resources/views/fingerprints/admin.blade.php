@extends('layouts.app')

@section('title', 'Fingerprint Admin')

@section('content')
<div class="max-w-7xl mx-auto pt-6" x-data="fingerprintAdmin()">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Fingerprint Admin</h1>

    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-4 border-b border-slate-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Division</label>
                    <select x-model="filters.division" @change="loadData()" class="w-full px-3 py-2 border border-slate-300 rounded-md">
                        <option value="">All Divisions</option>
                        @foreach($divisions ?? [] as $division)
                        <option value="{{ $division }}">{{ $division }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">District</label>
                    <select x-model="filters.district" @change="loadData()" class="w-full px-3 py-2 border border-slate-300 rounded-md">
                        <option value="">All Districts</option>
                        @foreach($districts ?? [] as $district)
                        <option value="{{ $district->id }}">{{ $district->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button @click="loadData()" class="px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-800">
                        Filter
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-100 text-slate-700 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-3">Invoice ID</th>
                        <th class="px-3 py-3">Booking Date</th>
                        <th class="px-3 py-3">Customer Name</th>
                        <th class="px-3 py-3">PAX</th>
                        <th class="px-3 py-3">Mobile</th>
                        <th class="px-3 py-3">District</th>
                        <th class="px-3 py-3">Deadline</th>
                        <th class="px-3 py-3 text-right">Cost (SAR)</th>
                        <th class="px-3 py-3">Assign Staff</th>
                        <th class="px-3 py-3">Passenger</th>
                        <th class="px-3 py-3">Fingerprint Status</th>
                        <th class="px-3 py-3">Required Flight</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" id="tableBody">
                    <template x-if="loading">
                        <tr>
                            <td colspan="12" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="12" class="px-3 py-8 text-center text-slate-500">No fingerprint tasks found</td>
                        </tr>
                    </template>
                    <template x-for="(row, index) in data" :key="row.fingerprint_detail_id">
                        <tr class="hover:bg-slate-50"
                            :class="index % 2 === 0 ? 'border-l-4 border-l-blue-500' : 'border-l-4 border-l-orange-500'"
                            :style="index === data.length - 1 || (index < data.length - 1 && data[index + 1]?.invoice_id !== row.invoice_id) ? 'border-bottom: 2px solid #94a3b8;' : ''">
                            <td class="px-3 py-2 font-medium text-slate-800" x-text="row.invoice_id"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.booking_date"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.customer_name"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.pax_qty"></td>
                            <td class="px-3 py-2 text-slate-600 whitespace-pre-line" x-text="row.customer_mobile + '\n' + row.passenger_mobile"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.district"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.deadline"></td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium" x-text="row.cost + ' SAR'"></td>
                            <td class="px-3 py-2">
                                <select @change="assignStaff(row.fingerprint_id, $event.target.value)"
                                        class="text-xs border border-slate-300 rounded px-2 py-1 bg-white">
                                    <option value="">Select Staff</option>
                                    <template x-for="staff in staffList" :key="staff.id">
                                        <option :value="staff.id" :selected="staff.id == row.assigned_staff_id" x-text="staff.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.passenger_name"></td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-full text-xs font-medium"
                                      :class="getStatusClass(row.fingerprint_status_display)"
                                      x-text="row.fingerprint_status_display"></span>
                            </td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.flight_date_from + ' - ' + row.flight_date_to"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fingerprintAdmin() {
    return {
        data: [],
        loading: true,
        staffList: [],
        filters: {
            division: '',
            district: '',
        },

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
                const params = new URLSearchParams(this.filters);
                const response = await fetch(`/api/fingerprints/admin?${params}`);
                const result = await response.json();
                this.data = result.data || [];
            } catch (error) {
                console.error('Failed to load fingerprint data:', error);
                this.data = [];
            } finally {
                this.loading = false;
            }
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
                    this.showToast('Staff assigned successfully');
                    const row = this.data.find(r => r.fingerprint_id === fingerprintId);
                    if (row) {
                        row.assigned_staff_id = staffId;
                        const staff = this.staffList.find(s => s.id == staffId);
                        row.assigned_staff_name = staff?.name;
                    }
                }
            } catch (error) {
                console.error('Failed to assign staff:', error);
                this.showToast('Failed to assign staff', 'error');
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