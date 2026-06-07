{{-- Add New Class Modal (Alpine.js driven) --}}
{{-- Parent x-data must provide:
     - classModalOpen (bool), classSaving (bool)
     - classData: { airline_id: '', class_id: '' }
     - classErrors: {}
     - Methods: openClassModal(), closeClassModal(), saveClass()
     - View must provide: $airlines (Airline::orderBy('name')->get()), $travelClasses (TravelClass::orderBy('name')->get())
--}}
<div x-show="classModalOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center" style="display:none;">
    <div class="fixed inset-0 bg-black/50" @click="closeClassModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Add New Class</h3>
            <button type="button" @click="closeClassModal()" class="text-slate-400 hover:text-slate-600" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form @submit.prevent="saveClass()">
            <div class="mb-4">
                <label for="modal_class_airline_id" class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                <select
                    id="modal_class_airline_id"
                    name="airline_id"
                    x-model="classData.airline_id"
                    :class="classErrors.airline_id ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2"
                >
                    <option value="">Select Airline</option>
                    @foreach($airlines as $airline)
                        <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                    @endforeach
                </select>
                <span x-show="classErrors.airline_id" class="text-sm text-red-600 mt-1" x-text="Array.isArray(classErrors.airline_id) ? classErrors.airline_id[0] : classErrors.airline_id"></span>
            </div>

            <div class="mb-5">
                <label for="modal_class_class_id" class="block text-sm font-medium text-slate-700 mb-1">Class *</label>
                <select
                    id="modal_class_class_id"
                    name="class_id"
                    x-model="classData.class_id"
                    :class="classErrors.class_id ? 'border-red-500' : 'border-slate-300'"
                    class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2"
                >
                    <option value="">Select Class</option>
                    @foreach($travelClasses as $travelClass)
                        <option value="{{ $travelClass->id }}">{{ $travelClass->name }}</option>
                    @endforeach
                </select>
                <span x-show="classErrors.class_id" class="text-sm text-red-600 mt-1" x-text="Array.isArray(classErrors.class_id) ? classErrors.class_id[0] : classErrors.class_id"></span>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-slate-200">
                <button type="submit"
                        :disabled="classSaving"
                        class="flex-1 px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!classSaving">Save</span>
                    <span x-show="classSaving">Saving...</span>
                </button>
                <button type="button" @click="closeClassModal()"
                        class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
