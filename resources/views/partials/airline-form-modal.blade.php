{{-- Add New Airline Modal (Alpine.js driven) --}}
{{-- Parent x-data must provide:
     - airlineModalOpen (bool), airlineSaving (bool)
     - airlineData: { name: '', code: '' }
     - airlineErrors: {}
     - Methods: openAirlineModal(), closeAirlineModal(), saveAirline()
--}}
<div x-show="airlineModalOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center" style="display:none;">
    <div class="fixed inset-0 bg-black/50" @click="closeAirlineModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Add New Airline</h3>
            <button type="button" @click="closeAirlineModal()" class="text-slate-400 hover:text-slate-600" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form @submit.prevent="saveAirline()">
            <div class="mb-4">
                <label for="modal_airline_name" class="block text-sm font-medium text-slate-700 mb-1">Airline Name *</label>
                <input
                    type="text"
                    id="modal_airline_name"
                    x-model="airlineData.name"
                    placeholder="Enter airline name"
                    :class="airlineErrors.name ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2"
                >
                <span x-show="airlineErrors.name" class="text-sm text-red-600 mt-1" x-text="Array.isArray(airlineErrors.name) ? airlineErrors.name[0] : airlineErrors.name"></span>
            </div>

            <div class="mb-5">
                <label for="modal_airline_code" class="block text-sm font-medium text-slate-700 mb-1">Airline Code *</label>
                <input
                    type="text"
                    id="modal_airline_code"
                    x-model="airlineData.code"
                    placeholder="Enter airline code (e.g., SV, EK)"
                    :class="airlineErrors.code ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2"
                >
                <span x-show="airlineErrors.code" class="text-sm text-red-600 mt-1" x-text="Array.isArray(airlineErrors.code) ? airlineErrors.code[0] : airlineErrors.code"></span>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-slate-200">
                <button type="submit"
                        :disabled="airlineSaving"
                        class="flex-1 px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!airlineSaving">Save</span>
                    <span x-show="airlineSaving">Saving...</span>
                </button>
                <button type="button" @click="closeAirlineModal()"
                        class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
