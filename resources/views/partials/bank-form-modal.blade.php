{{-- Add New Bank Modal (Alpine.js driven) --}}
{{-- Parent x-data must provide:
     - bankModalOpen (bool), bankSaving (bool)
     - bankData: { name: '', description: '', currency: '', location: '' }
     - bankErrors: {}
     - activeBankField: 'sender' | 'receiver'
     - Methods: openBankModal(field), closeBankModal(), saveBank()
--}}
<div x-show="bankModalOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center" style="display:none;">
    <div class="fixed inset-0 bg-black/50" @click="closeBankModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Add New Bank</h3>
            <button type="button" @click="closeBankModal()" class="text-slate-400 hover:text-slate-600" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div>
            <div class="mb-4">
                <label for="modal_bank_name" class="block text-sm font-medium text-slate-700 mb-1">Bank Name *</label>
                <input
                    type="text"
                    id="modal_bank_name"
                    x-model="bankData.name"
                    placeholder="Enter bank name"
                    :class="bankErrors.name ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2"
                >
                <span x-show="bankErrors.name" class="text-sm text-red-600 mt-1" x-text="Array.isArray(bankErrors.name) ? bankErrors.name[0] : bankErrors.name"></span>
            </div>

            <div class="mb-4">
                <label for="modal_bank_description" class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <input
                    type="text"
                    id="modal_bank_description"
                    x-model="bankData.description"
                    placeholder="Enter description (optional)"
                    :class="bankErrors.description ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2"
                >
                <span x-show="bankErrors.description" class="text-sm text-red-600 mt-1" x-text="Array.isArray(bankErrors.description) ? bankErrors.description[0] : bankErrors.description"></span>
            </div>

            <div class="mb-4">
                <label for="modal_bank_currency" class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                <select
                    id="modal_bank_currency"
                    x-model="bankData.currency"
                    :class="bankErrors.currency ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 bg-white"
                >
                    <option value="">Select Currency</option>
                    <option value="SAR">SAR</option>
                    <option value="BDT">BDT</option>
                </select>
                <span x-show="bankErrors.currency" class="text-sm text-red-600 mt-1" x-text="Array.isArray(bankErrors.currency) ? bankErrors.currency[0] : bankErrors.currency"></span>
            </div>

            <div class="mb-5">
                <label for="modal_bank_location" class="block text-sm font-medium text-slate-700 mb-1">Location</label>
                <select
                    id="modal_bank_location"
                    x-model="bankData.location"
                    :class="bankErrors.location ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 bg-white"
                >
                    <option value="">Select Location</option>
                    <option value="KSA">KSA</option>
                    <option value="BD">Bangladesh</option>
                </select>
                <span x-show="bankErrors.location" class="text-sm text-red-600 mt-1" x-text="Array.isArray(bankErrors.location) ? bankErrors.location[0] : bankErrors.location"></span>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-slate-200">
                <button type="button" @click="saveBank()"
                        :disabled="bankSaving"
                        class="flex-1 px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!bankSaving">Save</span>
                    <span x-show="bankSaving">Saving...</span>
                </button>
                <button type="button" @click="closeBankModal()"
                        class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
