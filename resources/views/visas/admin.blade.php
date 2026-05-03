@extends('layouts.app')

@section('title', 'Visa Admin')

@section('content')
<div class="max-w-3xl mx-auto pt-6" x-data="{
    showModal: false,
    editMode: false,
    visa: { id: null, passport: '', name: '', mobile: '', visa_agent: '', submission_date: '', status: 'Pending' },
    visas: [
        { id: 1, passport: 'A12345678', name: 'Ahmed Ali', mobile: '+966501234567', visa_agent: 'Ali Travel', submission_date: '2026-04-15', status: 'Approved' },
        { id: 2, passport: 'B98765432', name: 'Mohammed Hassan', mobile: '+966509876543', visa_agent: 'Saudi Visas', submission_date: '2026-04-20', status: 'Pending' },
        { id: 3, passport: 'C55667788', name: 'Ibrahim Omar', mobile: '+966551122334', visa_agent: 'Umrah Services', submission_date: '2026-04-22', status: 'Rejected' },
        { id: 4, passport: 'D11223344', name: 'Youssef Ahmed', mobile: '+966552233445', visa_agent: 'Ali Travel', submission_date: '2026-04-25', status: 'Pending' }
    ],
    openModal(v = null) {
        if (v) {
            this.editMode = true;
            this.visa = { ...v };
        } else {
            this.editMode = false;
            this.visa = { id: null, passport: '', name: '', mobile: '', visa_agent: '', submission_date: '', status: 'Pending' };
        }
        this.showModal = true;
    },
    closeModal() {
        this.showModal = false;
        this.editMode = false;
    },
    saveVisa() {
        if (this.editMode) {
            const index = this.visas.findIndex(v => v.id === this.visa.id);
            if (index !== -1) this.visas[index] = { ...this.visa };
        } else {
            this.visa.id = Date.now();
            this.visas.push({ ...this.visa });
        }
        this.closeModal();
    },
    deleteVisa(id) {
        this.visas = this.visas.filter(v => v.id !== id);
    }
}">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Visa Admin</h1>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-slate-700">Manage Visas</h2>
            <button @click="openModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md text-sm font-medium transition">
                Add New Visa
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-100 text-slate-700 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 rounded-tl-md">Passport</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Mobile</th>
                        <th class="px-4 py-3">Visa Agent</th>
                        <th class="px-4 py-3">Submission Date</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 rounded-tr-md">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <template x-for="visa in visas" :key="visa.id">
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3" x-text="visa.passport"></td>
                            <td class="px-4 py-3" x-text="visa.name"></td>
                            <td class="px-4 py-3" x-text="visa.mobile"></td>
                            <td class="px-4 py-3" x-text="visa.visa_agent"></td>
                            <td class="px-4 py-3" x-text="visa.submission_date"></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800': visa.status === 'Approved',
                                        'bg-yellow-100 text-yellow-800': visa.status === 'Pending',
                                        'bg-red-100 text-red-800': visa.status === 'Rejected'
                                    }" x-text="visa.status"></span>
                            </td>
                            <td class="px-4 py-3">
                                <button @click="openModal(visa)" class="text-blue-600 hover:text-blue-800 mr-3">Edit</button>
                                <button @click="deleteVisa(visa.id)" class="text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="visas.length === 0" class="text-center py-8 text-slate-500">
            No visas found. Add your first visa.
        </div>
    </div>

    <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div x-show="showModal" x-transition.opacity class="fixed inset-0 bg-black bg-opacity-50" @click="closeModal()"></div>
            <div x-show="showModal" x-transition class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6 z-10">
                <h3 class="text-lg font-semibold text-slate-800 mb-4" x-text="editMode ? 'Edit Visa' : 'Add New Visa'"></h3>
                <form @submit.prevent="saveVisa()">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Passport</label>
                            <input type="text" x-model="visa.passport" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                            <input type="text" x-model="visa.name" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Mobile</label>
                            <input type="text" x-model="visa.mobile" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., +966501234567" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent</label>
                            <input type="text" x-model="visa.visa_agent" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Submission Date</label>
                            <input type="date" x-model="visa.submission_date" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                            <select x-model="visa.status" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" required>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="closeModal()" class="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium transition">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection