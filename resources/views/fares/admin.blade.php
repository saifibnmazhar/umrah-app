@extends('layouts.app')

@section('title', 'Fare Admin')

@section('content')
<div class="max-w-3xl mx-auto pt-6" x-data="{
    showModal: false,
    editMode: false,
    fare: { id: null, airline: '', route: '', fare_amount: '', tax: '', pax_type: '' },
    fares: [
        { id: 1, airline: 'Saudi Airlines', route: 'JED-CAI', fare_amount: 1200, tax: 150, pax_type: 'Adult' },
        { id: 2, airline: 'Egypt Air', route: 'CAI-JED', fare_amount: 950, tax: 120, pax_type: 'Adult' },
        { id: 3, airline: 'Flynas', route: 'JED-MED', fare_amount: 450, tax: 80, pax_type: 'Adult' },
        { id: 4, airline: 'Saudi Airlines', route: 'JED-CAI', fare_amount: 800, tax: 100, pax_type: 'Child' },
        { id: 5, airline: 'Egypt Air', route: 'CAI-JED', fare_amount: 500, tax: 60, pax_type: 'Infant' }
    ],
    openModal(f = null) {
        if (f) {
            this.editMode = true;
            this.fare = { ...f };
        } else {
            this.editMode = false;
            this.fare = { id: null, airline: '', route: '', fare_amount: '', tax: '', pax_type: '' };
        }
        this.showModal = true;
    },
    closeModal() {
        this.showModal = false;
        this.editMode = false;
    },
    saveFare() {
        if (this.editMode) {
            const index = this.fares.findIndex(f => f.id === this.fare.id);
            if (index !== -1) this.fares[index] = { ...this.fare };
        } else {
            this.fare.id = Date.now();
            this.fares.push({ ...this.fare });
        }
        this.closeModal();
    },
    deleteFare(id) {
        this.fares = this.fares.filter(f => f.id !== id);
    }
}">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Fare Admin</h1>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-slate-700">Manage Fares</h2>
            <button @click="openModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md text-sm font-medium transition">
                Add New Fare
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-100 text-slate-700 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 rounded-tl-md">Airline</th>
                        <th class="px-4 py-3">Route</th>
                        <th class="px-4 py-3">Fare</th>
                        <th class="px-4 py-3">Tax</th>
                        <th class="px-4 py-3">Pax Type</th>
                        <th class="px-4 py-3 rounded-tr-md">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <template x-for="fare in fares" :key="fare.id">
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3" x-text="fare.airline"></td>
                            <td class="px-4 py-3" x-text="fare.route"></td>
                            <td class="px-4 py-3" x-text="fare.fare_amount"></td>
                            <td class="px-4 py-3" x-text="fare.tax"></td>
                            <td class="px-4 py-3" x-text="fare.pax_type"></td>
                            <td class="px-4 py-3">
                                <button @click="openModal(fare)" class="text-blue-600 hover:text-blue-800 mr-3">Edit</button>
                                <button @click="deleteFare(fare.id)" class="text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="fares.length === 0" class="text-center py-8 text-slate-500">
            No fares found. Add your first fare.
        </div>
    </div>

    <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div x-show="showModal" x-transition.opacity class="fixed inset-0 bg-black bg-opacity-50" @click="closeModal()"></div>
            <div x-show="showModal" x-transition class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6 z-10">
                <h3 class="text-lg font-semibold text-slate-800 mb-4" x-text="editMode ? 'Edit Fare' : 'Add New Fare'"></h3>
                <form @submit.prevent="saveFare()">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
                            <input type="text" x-model="fare.airline" name="airline" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route</label>
                            <input type="text" x-model="fare.route" name="route" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" placeholder="e.g., JED-CAI" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fare Amount</label>
                            <input type="number" x-model="fare.fare_amount" name="selling_fare" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tax</label>
                            <input type="number" x-model="fare.tax" name="tax" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Pax Type</label>
                            <select x-model="fare.pax_type" name="pax_type" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                                <option value="">Select Pax Type</option>
                                <option value="Adult">Adult</option>
                                <option value="Child">Child</option>
                                <option value="Infant">Infant</option>
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