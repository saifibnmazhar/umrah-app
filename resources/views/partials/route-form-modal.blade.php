{{-- Add/Edit Route Modal (Alpine.js driven) --}}
{{-- Parent x-data must provide:
     - route: { id, airline_id, route_type, flight_type, from_city_id, to_city_id, return_city_id, additional_gap, transits[2] }
     - showRouteModal (bool), editRouteMode (bool), routeSaving (bool), routeErrors (object)
     - cityModalOpen, citySaving, activeSelect, cityData, cityErrors
     - Methods: openRouteModal(), closeRouteModal(), saveRoute(), toggleRouteFields(), toggleTransitFields(),
                onCitySelectChange(), openCityModal(), closeCityModal(), saveCity(), appendCityToAllSelects()
--}}
<div x-show="showRouteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div x-show="showRouteModal" x-transition.opacity class="fixed inset-0 bg-black/50" @click="closeRouteModal()"></div>
        <div x-show="showRouteModal" x-transition class="relative bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 z-10 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-800" x-text="editRouteMode ? 'Edit Route' : 'Add Route'"></h3>
                <button type="button" @click="closeRouteModal()" class="text-slate-400 hover:text-slate-600" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="routeFormModal" @submit.prevent="saveRoute()">
                @csrf

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                        <select name="airline_id" x-model="route.airline_id" :class="routeErrors.airline_id ? 'border-red-500' : 'border-slate-300'" class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2" required>
                            <option value="">Select Airline</option>
                            @foreach(\App\Models\Airline::orderBy('name')->get() as $airline)
                                <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                            @endforeach
                        </select>
                        <span x-show="routeErrors.airline_id" class="text-sm text-red-600 mt-1" x-text="routeErrors.airline_id"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                        <select name="route_type" x-model="route.route_type" :class="routeErrors.route_type ? 'border-red-500' : 'border-slate-300'" class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2" required @change="route.flight_type = ''">
                            <option value="">Select</option>
                            <option value="oneway_inbound">Oneway - Inbound</option>
                            <option value="oneway_outbound">Oneway - Outbound</option>
                            <option value="round">Round</option>
                            <option value="multi_city">Multi City</option>
                        </select>
                        <span x-show="routeErrors.route_type" class="text-sm text-red-600 mt-1" x-text="routeErrors.route_type"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                        <select name="flight_type" x-model="route.flight_type" :disabled="!route.route_type" :class="routeErrors.flight_type ? 'border-red-500' : 'border-slate-300'" class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 disabled:bg-slate-100 disabled:cursor-not-allowed" required>
                            <option value="">Select</option>
                            <option value="direct">Direct</option>
                            <option value="transit">Transit</option>
                        </select>
                        <span x-show="routeErrors.flight_type" class="text-sm text-red-600 mt-1" x-text="routeErrors.flight_type"></span>
                    </div>
                </div>

                <div id="cityGridModal"
                     x-show="route.route_type === 'oneway_inbound' || route.route_type === 'oneway_outbound' || route.route_type === 'round'"
                     :class="route.route_type === 'round' ? 'grid-cols-3' : 'grid-cols-2'"
                     class="grid gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">From *</label>
                        <select id="from_city_id" name="from_city_id" x-model="route.from_city_id" @change="onCitySelectChange('from_city_id', $event)" :class="routeErrors.from_city_id ? 'border-red-500' : 'border-slate-300'" class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2">
                            <option value="">Select</option>
                            <option value="__add_new__">+ Add New City</option>
                            @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                            @endforeach
                        </select>
                        <span x-show="routeErrors.from_city_id" class="text-sm text-red-600 mt-1" x-text="routeErrors.from_city_id"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">To *</label>
                        <select id="to_city_id" name="to_city_id" x-model="route.to_city_id" @change="onCitySelectChange('to_city_id', $event)" :class="routeErrors.to_city_id ? 'border-red-500' : 'border-slate-300'" class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2">
                            <option value="">Select</option>
                            <option value="__add_new__">+ Add New City</option>
                            @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                            @endforeach
                        </select>
                        <span x-show="routeErrors.to_city_id" class="text-sm text-red-600 mt-1" x-text="routeErrors.to_city_id"></span>
                    </div>
                    <div id="returnFieldModal" x-show="route.route_type === 'round'">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Return To *</label>
                        <select id="return_city_id" name="return_city_id" x-model="route.return_city_id" @change="onCitySelectChange('return_city_id', $event)" :class="routeErrors.return_city_id ? 'border-red-500' : 'border-slate-300'" class="block w-full rounded-md border shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2">
                            <option value="">Select</option>
                            <option value="__add_new__">+ Add New City</option>
                            @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                            @endforeach
                        </select>
                        <span x-show="routeErrors.return_city_id" class="text-sm text-red-600 mt-1" x-text="routeErrors.return_city_id"></span>
                    </div>
                </div>

                <div id="multiCityFieldsModal" x-show="route.route_type === 'multi_city'" class="mt-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Multi City Segments</h4>
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-sm text-slate-600 mb-1">Inbound From</label>
                            <select id="segments_0_from_city_id" name="segments[0][from_city_id]" @change="onCitySelectChange('segments_0_from_city_id', $event)" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                <option value="">Select</option>
                                <option value="__add_new__">+ Add New City</option>
                                @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                    <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-600 mb-1">Inbound To</label>
                            <select id="segments_0_to_city_id" name="segments[0][to_city_id]" @change="onCitySelectChange('segments_0_to_city_id', $event)" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                <option value="">Select</option>
                                <option value="__add_new__">+ Add New City</option>
                                @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                    <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="segments[0][segment_direction]" value="inbound">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-slate-600 mb-1">Outbound From</label>
                            <select id="segments_1_from_city_id" name="segments[1][from_city_id]" @change="onCitySelectChange('segments_1_from_city_id', $event)" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                <option value="">Select</option>
                                <option value="__add_new__">+ Add New City</option>
                                @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                    <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-600 mb-1">Outbound To</label>
                            <select id="segments_1_to_city_id" name="segments[1][to_city_id]" @change="onCitySelectChange('segments_1_to_city_id', $event)" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                <option value="">Select</option>
                                <option value="__add_new__">+ Add New City</option>
                                @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                    <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="segments[1][segment_direction]" value="outbound">
                </div>

                <div id="transitFieldsModal" x-show="route.flight_type === 'transit'" class="grid grid-cols-2 gap-4 mt-4">
                    <div x-show="route.flight_type === 'transit' && (route.route_type === 'oneway_inbound' || route.route_type === 'round' || route.route_type === 'multi_city')">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Transit City (Inbound)</label>
                        <select id="transit_0_city_id" name="transits[0][transit_city_id]" x-model="route.transits[0].transit_city_id" @change="onCitySelectChange('transit_0_city_id', $event)" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                            <option value="">Select</option>
                            <option value="__add_new__">+ Add New City</option>
                            @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="transits[0][route_direction]" value="inbound">
                    </div>
                    <div x-show="route.flight_type === 'transit' && (route.route_type === 'oneway_inbound' || route.route_type === 'round' || route.route_type === 'multi_city')">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Transit Time (Inbound)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="transits[0][transit_hours]" x-model="route.transits[0].transit_hours" min="0" max="23" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" placeholder="HH">
                            <span class="text-slate-500 font-medium">:</span>
                            <input type="number" name="transits[0][transit_minutes]" x-model="route.transits[0].transit_minutes" min="0" max="59" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" placeholder="MM">
                        </div>
                    </div>
                    <div x-show="route.flight_type === 'transit' && (route.route_type === 'oneway_outbound' || route.route_type === 'round' || route.route_type === 'multi_city')">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Transit City (Outbound)</label>
                        <select id="transit_1_city_id" name="transits[1][transit_city_id]" x-model="route.transits[1].transit_city_id" @change="onCitySelectChange('transit_1_city_id', $event)" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                            <option value="">Select</option>
                            <option value="__add_new__">+ Add New City</option>
                            @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="transits[1][route_direction]" value="outbound">
                    </div>
                    <div x-show="route.flight_type === 'transit' && (route.route_type === 'oneway_outbound' || route.route_type === 'round' || route.route_type === 'multi_city')">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Transit Time (Outbound)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="transits[1][transit_hours]" x-model="route.transits[1].transit_hours" min="0" max="23" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" placeholder="HH">
                            <span class="text-slate-500 font-medium">:</span>
                            <input type="number" name="transits[1][transit_minutes]" x-model="route.transits[1].transit_minutes" min="0" max="59" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" placeholder="MM">
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Additional Gap (Days)</label>
                    <input type="number" name="additional_gap" x-model="route.additional_gap" min="0" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" placeholder="0">
                </div>

                <div class="pt-4 flex items-center gap-4">
                    <button type="submit" :disabled="routeSaving" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                        <span x-show="!routeSaving" x-text="editRouteMode ? 'Update Route' : 'Create Route'"></span>
                        <span x-show="routeSaving">Saving...</span>
                    </button>
                    <button type="button" @click="closeRouteModal()" class="text-slate-600 hover:text-slate-800 text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('partials.city-form-modal')
