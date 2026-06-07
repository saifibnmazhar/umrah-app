{{-- Add New City Modal (Alpine.js driven) --}}
<div x-show="cityModalOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center" style="display:none;">
    <div class="fixed inset-0 bg-black/50" @click="closeCityModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Add New City</h3>
            <button type="button" @click="closeCityModal()" class="text-slate-400 hover:text-slate-600" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form @submit.prevent="saveCity()">
            <div class="mb-4">
                <label for="modal_city_name" class="block text-sm font-medium text-slate-700 mb-1">City Name *</label>
                <input
                    type="text"
                    id="modal_city_name"
                    x-model="cityData.city_name"
                    placeholder="Enter city name"
                    :class="cityErrors.city_name ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2"
                >
                <span x-show="cityErrors.city_name" class="text-sm text-red-600 mt-1" x-text="cityErrors.city_name"></span>
            </div>

            <div class="mb-4">
                <label for="modal_city_code" class="block text-sm font-medium text-slate-700 mb-1">Code *</label>
                <input
                    type="text"
                    id="modal_city_code"
                    x-model="cityData.code"
                    placeholder="Enter city code (e.g., DAC, JED)"
                    :class="cityErrors.code ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2"
                >
                <span x-show="cityErrors.code" class="text-sm text-red-600 mt-1" x-text="cityErrors.code"></span>
            </div>

            <div class="mb-5">
                <label for="modal_city_country" class="block text-sm font-medium text-slate-700 mb-1">Country *</label>
                <input
                    type="text"
                    id="modal_city_country"
                    x-model="cityData.country"
                    placeholder="Enter country name"
                    :class="cityErrors.country ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2"
                >
                <span x-show="cityErrors.country" class="text-sm text-red-600 mt-1" x-text="cityErrors.country"></span>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-slate-200">
                <button type="submit"
                        :disabled="citySaving"
                        class="flex-1 px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!citySaving">Save</span>
                    <span x-show="citySaving">Saving...</span>
                </button>
                <button type="button" @click="closeCityModal()"
                        class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
