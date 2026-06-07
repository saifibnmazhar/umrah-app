@extends('layouts.app')
@section('title', 'Edit Route')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('routes.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Routes
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit Route</h1>

    <form method="POST" action="{{ route('routes.update', $route->id) }}" id="routeForm" x-data="routeCityForm()" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label for="airline_id" class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                <select name="airline_id" id="airline_id" required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('airline_id') border-red-500 @enderror">
                    <option value="">Select Airline</option>
                    @foreach(\App\Models\Airline::orderBy('name')->get() as $airline)
                        <option value="{{ $airline->id }}" {{ $route->airline_id == $airline->id ? 'selected' : '' }}>{{ $airline->name }}</option>
                    @endforeach
                </select>
                @error('airline_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="route_type" class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                <select name="route_type" id="route_type" required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('route_type') border-red-500 @enderror"
                    onchange="toggleRouteFields()">
                    <option value="">Select</option>
                    <option value="oneway_inbound" {{ $route->route_type->value == 'oneway_inbound' ? 'selected' : '' }}>Oneway - Inbound</option>
                    <option value="oneway_outbound" {{ $route->route_type->value == 'oneway_outbound' ? 'selected' : '' }}>Oneway - Outbound</option>
                    <option value="round" {{ $route->route_type->value == 'round' ? 'selected' : '' }}>Round</option>
                    <option value="multi_city" {{ $route->route_type->value == 'multi_city' ? 'selected' : '' }}>Multi City</option>
                </select>
                @error('route_type')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="flight_type" class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                <select name="flight_type" id="flight_type" required
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('flight_type') border-red-500 @enderror"
                    onchange="toggleTransitFields()">
                    <option value="">Select</option>
                    <option value="direct" {{ $route->flight_type->value == 'direct' ? 'selected' : '' }}>Direct</option>
                    <option value="transit" {{ $route->flight_type->value == 'transit' ? 'selected' : '' }}>Transit</option>
                </select>
                @error('flight_type')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div id="cityGrid" class="grid gap-4 grid-cols-3">
            <div id="fromField" class="{{ in_array($route->route_type->value, ['oneway_inbound', 'oneway_outbound', 'round']) ? '' : 'hidden' }}">
                <label for="from_city_id" class="block text-sm font-medium text-slate-700 mb-1">From *</label>
                <select name="from_city_id" id="from_city_id"
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('from_city_id') border-red-500 @enderror"
                    @change="onCitySelectChange('from_city_id', $event)">
                    <option value="">Select</option>
                    <option value="__add_new__">+ Add New City</option>
                    @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                        <option value="{{ $city->id }}" {{ $route->from_city_id == $city->id ? 'selected' : '' }}>{{ $city->code }} ({{ $city->city_name }})</option>
                    @endforeach
                </select>
                @error('from_city_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div id="toField" class="{{ in_array($route->route_type->value, ['oneway_inbound', 'oneway_outbound', 'round']) ? '' : 'hidden' }}">
                <label for="to_city_id" class="block text-sm font-medium text-slate-700 mb-1">To *</label>
                <select name="to_city_id" id="to_city_id"
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('to_city_id') border-red-500 @enderror"
                    @change="onCitySelectChange('to_city_id', $event)">
                    <option value="">Select</option>
                    <option value="__add_new__">+ Add New City</option>
                    @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                        <option value="{{ $city->id }}" {{ $route->to_city_id == $city->id ? 'selected' : '' }}>{{ $city->code }} ({{ $city->city_name }})</option>
                    @endforeach
                </select>
                @error('to_city_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div id="returnField" class="{{ $route->route_type->value == 'round' ? '' : 'hidden' }}">
                <label for="return_city_id" class="block text-sm font-medium text-slate-700 mb-1">Return To *</label>
                <select name="return_city_id" id="return_city_id"
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('return_city_id') border-red-500 @enderror"
                    @change="onCitySelectChange('return_city_id', $event)">
                    <option value="">Select</option>
                    <option value="__add_new__">+ Add New City</option>
                    @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                        <option value="{{ $city->id }}" {{ $route->return_city_id == $city->id ? 'selected' : '' }}>{{ $city->code }} ({{ $city->city_name }})</option>
                    @endforeach
                </select>
                @error('return_city_id')
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div id="multiCityFields" class="hidden">
            <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Multi City Segments</h4>
            <div id="segmentsContainer" class="space-y-3">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Inbound From</label>
                        <select id="segments_0_from_city_id" name="segments[0][from_city_id]" @change="onCitySelectChange('segments_0_from_city_id', $event)" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                            <option value="">Select</option>
                            <option value="__add_new__">+ Add New City</option>
                            @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                <option value="{{ $city->id }}" {{ isset($route->multiSegments[0]) && $route->multiSegments[0]->from_city_id == $city->id ? 'selected' : '' }}>{{ $city->code }} ({{ $city->city_name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Inbound To</label>
                        <select id="segments_0_to_city_id" name="segments[0][to_city_id]" @change="onCitySelectChange('segments_0_to_city_id', $event)" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                            <option value="">Select</option>
                            <option value="__add_new__">+ Add New City</option>
                            @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                <option value="{{ $city->id }}" {{ isset($route->multiSegments[0]) && $route->multiSegments[0]->to_city_id == $city->id ? 'selected' : '' }}>{{ $city->code }} ({{ $city->city_name }})</option>
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
                                <option value="{{ $city->id }}" {{ isset($route->multiSegments[1]) && $route->multiSegments[1]->from_city_id == $city->id ? 'selected' : '' }}>{{ $city->code }} ({{ $city->city_name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Outbound To</label>
                        <select id="segments_1_to_city_id" name="segments[1][to_city_id]" @change="onCitySelectChange('segments_1_to_city_id', $event)" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                            <option value="">Select</option>
                            <option value="__add_new__">+ Add New City</option>
                            @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                <option value="{{ $city->id }}" {{ isset($route->multiSegments[1]) && $route->multiSegments[1]->to_city_id == $city->id ? 'selected' : '' }}>{{ $city->code }} ({{ $city->city_name }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <input type="hidden" name="segments[1][segment_direction]" value="outbound">
            </div>
        </div>

        <div id="transitFields" class="hidden grid grid-cols-2 gap-4">
            <div>
                <label for="transit_0_city_id" class="block text-sm font-medium text-slate-700 mb-1">Transit City</label>
                <select name="transits[0][transit_city_id]" id="transit_0_city_id"
                    @change="onCitySelectChange('transit_0_city_id', $event)"
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                    <option value="">Select</option>
                    <option value="__add_new__">+ Add New City</option>
                    @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                        <option value="{{ $city->id }}" {{ isset($route->transits[0]) && $route->transits[0]->transit_city_id == $city->id ? 'selected' : '' }}>{{ $city->code }} ({{ $city->city_name }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Transit Time</label>
                <div class="flex items-center gap-2">
                    <input type="number" name="transits[0][transit_hours]" id="transit_hours" min="0" max="23"
                        class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border"
                        value="{{ isset($route->transits[0]) ? floor($route->transits[0]->transit_time / 60) : '' }}"
                        placeholder="HH">
                    <span class="text-slate-500 font-medium">:</span>
                    <input type="number" name="transits[0][transit_minutes]" id="transit_minutes" min="0" max="59"
                        class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border"
                        value="{{ isset($route->transits[0]) ? $route->transits[0]->transit_time % 60 : '' }}"
                        placeholder="MM">
                </div>
            </div>
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">Additional Gap (Days)</label>
            <input type="number" name="additional_gap" min="0" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" placeholder="0" value="{{ old('additional_gap', $route->additional_gap) }}">
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Update Route
            </button>
            <a href="{{ route('routes.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>

    @include('partials.city-form-modal')
</div>

<script>
function toggleRouteFields() {
    const routeType = document.getElementById('route_type').value;
    const fromField = document.getElementById('fromField');
    const toField = document.getElementById('toField');
    const returnField = document.getElementById('returnField');
    const multiCityFields = document.getElementById('multiCityFields');
    const cityGrid = document.getElementById('cityGrid');

    fromField.classList.add('hidden');
    toField.classList.add('hidden');
    returnField.classList.add('hidden');
    multiCityFields.classList.add('hidden');

    // Reset grid classes before adding new one
    cityGrid.classList.remove('grid-cols-2', 'grid-cols-3');

    if (routeType === 'oneway_inbound' || routeType === 'oneway_outbound') {
        fromField.classList.remove('hidden');
        toField.classList.remove('hidden');
        cityGrid.classList.add('grid-cols-2');
    } else if (routeType === 'round') {
        fromField.classList.remove('hidden');
        toField.classList.remove('hidden');
        returnField.classList.remove('hidden');
        cityGrid.classList.add('grid-cols-3');
    } else if (routeType === 'multi_city') {
        multiCityFields.classList.remove('hidden');
        cityGrid.classList.add('grid-cols-3');
    } else {
        // Default to 3 columns for initial load or unknown state
        cityGrid.classList.add('grid-cols-3');
    }
}

function toggleTransitFields() {
    const flightType = document.getElementById('flight_type').value;
    const transitFields = document.getElementById('transitFields');

    if (flightType === 'transit') {
        transitFields.classList.remove('hidden');
    } else {
        transitFields.classList.add('hidden');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleRouteFields();
    toggleTransitFields();
});

function routeCityForm() {
    return {
        cityModalOpen: false,
        citySaving: false,
        activeSelect: null,
        cityData: { city_name: '', code: '', country: '' },
        cityErrors: {},

        onCitySelectChange(selectId, event) {
            const value = event.target.value;
            if (value === '__add_new__') {
                event.target.value = '';
                this.activeSelect = selectId;
                this.openCityModal();
            }
        },

        openCityModal() {
            this.cityData = { city_name: '', code: '', country: '' };
            this.cityErrors = {};
            this.cityModalOpen = true;
            this.$nextTick(() => {
                const el = document.getElementById('modal_city_name');
                if (el) el.focus();
            });
        },

        closeCityModal() {
            this.cityModalOpen = false;
            this.cityErrors = {};
            this.activeSelect = null;
        },

        saveCity() {
            this.citySaving = true;
            this.cityErrors = {};

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfMeta ? csrfMeta.getAttribute('content') : ''
            };

            fetch('{{ route('city-codes.store') }}', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(this.cityData)
            })
            .then(async (response) => {
                this.citySaving = false;
                const data = await response.json().catch(() => ({}));
                if (response.status === 422 && data.errors) {
                    this.cityErrors = data.errors;
                    return;
                }
                if (response.ok && data.success && data.city) {
                    this.appendCityToAllSelects(data.city);
                    this.closeCityModal();
                    if (typeof window.showToast === 'function') {
                        window.showToast('City created successfully', 'success');
                    }
                    return;
                }
                if (typeof window.showToast === 'function') {
                    window.showToast((data && data.message) || 'Failed to create city', 'error');
                }
            })
            .catch(() => {
                this.citySaving = false;
                if (typeof window.showToast === 'function') {
                    window.showToast('Failed to create city', 'error');
                }
            });
        },

        appendCityToAllSelects(city) {
            const selects = [
                'from_city_id', 'to_city_id', 'return_city_id',
                'segments_0_from_city_id', 'segments_0_to_city_id',
                'segments_1_from_city_id', 'segments_1_to_city_id',
                'transit_0_city_id'
            ];
            const label = `${city.code} (${city.city_name})`;

            selects.forEach((id) => {
                const select = document.getElementById(id);
                if (!select) return;

                let exists = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === String(city.id)) { exists = true; break; }
                }
                if (exists) {
                    if (this.activeSelect === id) select.value = String(city.id);
                    return;
                }

                const newOption = document.createElement('option');
                newOption.value = String(city.id);
                newOption.text = label;

                let inserted = false;
                for (let i = 0; i < select.options.length; i++) {
                    const opt = select.options[i];
                    if (opt.value === '' || opt.value === '__add_new__') continue;
                    if (label.localeCompare(opt.text) < 0) {
                        select.insertBefore(newOption, opt);
                        inserted = true;
                        break;
                    }
                }
                if (!inserted) {
                    const addNewOption = select.querySelector('option[value="__add_new__"]');
                    if (addNewOption && addNewOption.parentNode === select) {
                        select.insertBefore(newOption, addNewOption);
                    } else {
                        select.appendChild(newOption);
                    }
                }

                if (this.activeSelect === id) {
                    select.value = String(city.id);
                }
            });
        }
    }
}
</script>
@endsection