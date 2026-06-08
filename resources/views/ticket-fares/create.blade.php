@extends('layouts.app')
@section('title', 'Create Ticket Fare')
@section('content')
<div class="max-w-4xl mx-auto" x-data="ticketFareForm()">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Create Ticket Fare</h1>
        <a href="{{ route('ticket-fares.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition text-sm font-medium">
            Back to List
        </a>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ticket-fares.store') }}">
        @csrf
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                    <input type="date" value="{{ date('Y-m-d') }}" readonly class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm bg-slate-50">
                </div> --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                    <select name="route_type" id="routeType" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" onchange="handleRouteTypeChange()">
                        <option value="">Select Type</option>
                        <option value="oneway_inbound" {{ old('route_type') == 'oneway_inbound' ? 'selected' : '' }}>One Way - Inbound</option>
                        <option value="oneway_outbound" {{ old('route_type') == 'oneway_outbound' ? 'selected' : '' }}>One Way - Outbound</option>
                        <option value="round" {{ old('route_type') == 'round' ? 'selected' : '' }}>Round</option>
                        <option value="multi_city" {{ old('route_type') == 'multi_city' ? 'selected' : '' }}>Multi City</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                    <select name="flight_type" id="flightType" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" onchange="handleFlightTypeChange()">
                        <option value="">Select Flight Type</option>
                        <option value="direct" {{ old('flight_type') == 'direct' ? 'selected' : '' }}>Direct</option>
                        <option value="transit" {{ old('flight_type') == 'transit' ? 'selected' : '' }}>Transit</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                    <select name="airline_id" id="airlineId" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" @change="onAirlineSelectChange($event)" onchange="handleAirlineChange()">
                        <option value="">Select Airline</option>
                        <option value="__add_new__">+ Add New Airline</option>
                        @foreach($airlines as $airline)
                            <option value="{{ $airline->id }}" {{ old('airline_id') == $airline->id ? 'selected' : '' }}>
                                {{ $airline->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Class *</label>
                    <select name="airline_classes_id" id="classId" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" @change="onClassSelectChange($event)">
                        <option value="">Select Class</option>
                        <option value="__add_new__">+ Add New Class</option>
                        @foreach($airlineClasses as $class)
                            <option value="{{ $class->id }}" data-airline-id="{{ $class->airline_id }}" {{ old('airline_classes_id') == $class->id ? 'selected' : '' }}>
                                {{ $class->travelClass->name ?? 'Class ' . $class->id }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Route *</label>
                    <select name="route_id" id="routeId" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" @change="onRouteSelectChange($event)">
                        <option value="">Select Route</option>
                        <option value="__add_new__">+ Add New Route</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}"
                                    data-route-type="{{ $route->route_type->value }}"
                                    data-flight-type="{{ $route->flight_type->value ?? '' }}"
                                    {{ old('route_id') == $route->id ? 'selected' : '' }}>
                                @if($route->route_type->value === 'multi_city')
                                    @if($route->multiSegments->count() > 0)
                                        {{ $route->multiSegments->first()->fromCity->code ?? '-' }}-{{ $route->multiSegments->first()->toCity->code ?? '-' }} ... ({{ $route->airline->name }})
                                    @else
                                        {{ $route->airline->name }} (Multi City)
                                    @endif
                                @else
                                    {{ $route->fromCity->code ?? '-' }}-{{ $route->toCity->code ?? '-' }}{{ $route->route_type->value === 'round' ? '-' . ($route->returnCity->code ?? '-') : '' }} ({{ $route->airline->name }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type *</label>
                    <select name="ticket_type" id="ticketType" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" onchange="toggleFields()">
                        <option value="">Select Type</option>
                        <option value="regular" {{ old('ticket_type') == 'regular' ? 'selected' : '' }}>Regular</option>
                        <option value="offer" {{ old('ticket_type') == 'offer' ? 'selected' : '' }}>Offer</option>
                        <option value="group" {{ old('ticket_type') == 'group' ? 'selected' : '' }}>Group</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Effective From *</label>
                    <input type="date" name="effective_from" value="{{ old('effective_from') }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Effective To *</label>
                    <input type="date" name="effective_to" value="{{ old('effective_to') }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div class="flex items-center">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="with_meal" value="1" {{ old('with_meal') ? 'checked' : '' }} class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-500">
                        <span class="ml-2 text-sm text-slate-700">With Meal</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Fare Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (SAR) *</label>
                    <input type="number" name="net_fare" value="{{ old('net_fare') }}" step="0.01" min="0" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR) *</label>
                    <input type="number" name="selling_fare" value="{{ old('selling_fare') }}" step="0.01" min="0" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div id="offerPriceField" class="{{ old('ticket_type') !== 'offer' ? 'hidden' : '' }}">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR) *</label>
                    <input type="number" name="offer_price" value="{{ old('offer_price') }}" step="0.01" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Child Fare (%) *</label>
                    <input type="number" name="child_fare_percentage" value="{{ old('child_fare_percentage', 70) }}" step="0.01" min="0" max="100" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Infant Fare (%) *</label>
                    <input type="number" name="infant_fare_percentage" value="{{ old('infant_fare_percentage', 30) }}" step="0.01" min="0" max="100" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div id="groupTicketSection" class="{{ old('ticket_type') !== 'group' ? 'hidden' : '' }} bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Group Ticket Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">PNR *</label>
                    <input type="text" name="pnr" value="{{ old('pnr') }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Quantity *</label>
                    <input type="number" name="ticket_qty" value="{{ old('ticket_qty') }}" min="1" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div id="inboundDateField" class="{{ in_array(old('route_type'), ['oneway_outbound']) ? 'hidden' : '' }}">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date <span class="required-mark">*</span></label>
                    <input type="date" name="inbound_date" value="{{ old('inbound_date') }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div id="outboundDateField" class="{{ in_array(old('route_type'), ['oneway_inbound']) ? 'hidden' : '' }}">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date <span class="required-mark">*</span></label>
                    <input type="date" name="outbound_date" value="{{ old('outbound_date') }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
            </div>
            <div class="flex justify-start mt-5 gap-4">
                <div class="flex items-center">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="is_non_refundable" value="1" {{ old('is_non_refundable') ? 'checked' : '' }} class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-500">
                        <span class="ml-2 text-sm text-slate-700">Non-Refundable</span>
                    </label>
                </div>
                <div class="flex items-center">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="is_non_exchangable" value="1" {{ old('is_non_exchangable') ? 'checked' : '' }} class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-500">
                        <span class="ml-2 text-sm text-slate-700">Non-Exchangable</span>
                    </label>
                </div>
            </div>
        </div>

        <div id="baggageSection" class="hidden bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Baggage Allowances (KG)</h2>
            <div id="inboundBaggage" class="{{ in_array(old('route_type'), ['oneway_outbound']) ? 'hidden' : '' }} mb-4">
                <h3 class="text-sm font-medium text-slate-700 mb-3">Inbound</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Adult</label>
                        <input type="number" name="inbound_adult" value="{{ old('inbound_adult', 30) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Child</label>
                        <input type="number" name="inbound_child" value="{{ old('inbound_child', 30) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Infant</label>
                        <input type="number" name="inbound_infant" value="{{ old('inbound_infant', 10) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
            <div id="outboundBaggage" class="{{ in_array(old('route_type'), ['oneway_inbound']) ? 'hidden' : '' }}">
                <h3 class="text-sm font-medium text-slate-700 mb-3">Outbound</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Adult</label>
                        <input type="number" name="outbound_adult" value="{{ old('outbound_adult', 50) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Child</label>
                        <input type="number" name="outbound_child" value="{{ old('outbound_child', 50) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Infant</label>
                        <input type="number" name="outbound_infant" value="{{ old('outbound_infant', 10) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('ticket-fares.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition text-sm font-medium">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition text-sm font-medium">
                Create Ticket Fare
            </button>
        </div>
    </form>

    @include('partials.route-form-modal')
    @include('partials.airline-form-modal')
    @include('partials.class-form-modal')
</div>

<script>
function toggleFields() {
    const routeType = document.getElementById('routeType').value;
    const ticketType = document.getElementById('ticketType').value;
    const offerPriceField = document.getElementById('offerPriceField');
    const groupTicketSection = document.getElementById('groupTicketSection');
    const baggageSection = document.getElementById('baggageSection');
    const inboundDateField = document.getElementById('inboundDateField');
    const outboundDateField = document.getElementById('outboundDateField');
    const inboundBaggage = document.getElementById('inboundBaggage');
    const outboundBaggage = document.getElementById('outboundBaggage');

    // Toggle offer price
    if (ticketType === 'offer') {
        offerPriceField.classList.remove('hidden');
    } else {
        offerPriceField.classList.add('hidden');
    }

    // Toggle group ticket section
    if (ticketType === 'group') {
        groupTicketSection.classList.remove('hidden');
    } else {
        groupTicketSection.classList.add('hidden');
    }

    // Toggle baggage section and group ticket dates based on route type
    if (routeType) {
        baggageSection.classList.remove('hidden');

        if (routeType === 'oneway_inbound') {
            inboundDateField.classList.remove('hidden');
            outboundDateField.classList.add('hidden');
            inboundBaggage.classList.remove('hidden');
            outboundBaggage.classList.add('hidden');
        } else if (routeType === 'oneway_outbound') {
            inboundDateField.classList.add('hidden');
            outboundDateField.classList.remove('hidden');
            inboundBaggage.classList.add('hidden');
            outboundBaggage.classList.remove('hidden');
        } else {
            inboundDateField.classList.remove('hidden');
            outboundDateField.classList.remove('hidden');
            inboundBaggage.classList.remove('hidden');
            outboundBaggage.classList.remove('hidden');
        }
    } else {
        baggageSection.classList.add('hidden');
    }
}

function filterRoutes() {
    const routeType = document.getElementById('routeType').value;
    const flightType = document.getElementById('flightType').value;
    const routeSelect = document.getElementById('routeId');
    const options = routeSelect.querySelectorAll('option');

    options.forEach(function(option) {
        if (option.value === '') return;

        const optionRouteType = option.getAttribute('data-route-type');
        const optionFlightType = option.getAttribute('data-flight-type');

        const matchesRouteType = !routeType || optionRouteType === routeType;
        const matchesFlightType = !flightType || optionFlightType === flightType;

        if (matchesRouteType && matchesFlightType) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });

    if (routeSelect.value) {
        const selectedOption = routeSelect.options[routeSelect.selectedIndex];
        const selectedRouteType = selectedOption.getAttribute('data-route-type');
        const selectedFlightType = selectedOption.getAttribute('data-flight-type');

        if ((routeType && selectedRouteType !== routeType) || (flightType && selectedFlightType !== flightType)) {
            routeSelect.value = '';
            document.getElementById('flightType').value = '';
        }
    }
}

function handleRouteTypeChange() {
    filterRoutes();
    toggleFields();
}

function handleFlightTypeChange() {
    filterRoutes();
}

function handleRouteChange() {
    const routeSelect = document.getElementById('routeId');
    const flightTypeSelect = document.getElementById('flightType');

    if (routeSelect.value) {
        const selectedOption = routeSelect.options[routeSelect.selectedIndex];
        const flightType = selectedOption.getAttribute('data-flight-type');
        if (flightType) {
            flightTypeSelect.value = flightType;
        }
    } else {
        flightTypeSelect.value = '';
    }
}

function filterClasses() {
    const airlineId = document.getElementById('airlineId').value;
    const classSelect = document.getElementById('classId');

    // Filter class options based on airline
    const options = classSelect.querySelectorAll('option');
    options.forEach(function(option) {
        if (option.value === '' || option.value === '__add_new__') return;

        const optionAirlineId = option.getAttribute('data-airline-id');

        if (airlineId && optionAirlineId !== airlineId) {
            option.style.display = 'none';
        } else {
            option.style.display = '';
        }
    });

    // Reset class selection if not valid for new airline
    if (classSelect.value) {
        const selectedOption = classSelect.options[classSelect.selectedIndex];
        const selectedAirlineId = selectedOption.getAttribute('data-airline-id');

        if (airlineId && selectedAirlineId !== airlineId) {
            classSelect.value = '';
        }
    }
}

function handleAirlineChange() {
    filterClasses();
}

document.addEventListener('DOMContentLoaded', function() {
    toggleFields();
    filterRoutes();
    filterClasses();
});

function ticketFareForm() {
    return {
        showRouteModal: false,
        editRouteMode: false,
        routeSaving: false,
        routeErrors: {},
        route: {
            id: null,
            airline_id: '',
            route_type: '',
            flight_type: '',
            from_city_id: '',
            to_city_id: '',
            return_city_id: '',
            additional_gap: '',
            transits: [
                { transit_city_id: '', transit_hours: '', transit_minutes: '' },
                { transit_city_id: '', transit_hours: '', transit_minutes: '' }
            ]
        },

        cityModalOpen: false,
        citySaving: false,
        activeSelect: null,
        cityData: { city_name: '', code: '', country: '' },
        cityErrors: {},

        airlineModalOpen: false,
        airlineSaving: false,
        airlineData: { name: '', code: '' },
        airlineErrors: {},

        classModalOpen: false,
        classSaving: false,
        classData: { airline_id: '', class_id: '' },
        classErrors: {},

        onRouteSelectChange(event) {
            if (event.target.value === '__add_new__') {
                event.target.value = '';
                this.openRouteModal();
            } else {
                window.handleRouteChange();
            }
        },

        openRouteModal() {
            this.editRouteMode = false;
            this.routeErrors = {};
            this.route = {
                id: null,
                airline_id: '',
                route_type: '',
                flight_type: '',
                from_city_id: '',
                to_city_id: '',
                return_city_id: '',
                additional_gap: '',
                transits: [
                    { transit_city_id: '', transit_hours: '', transit_minutes: '' },
                    { transit_city_id: '', transit_hours: '', transit_minutes: '' }
                ]
            };
            this.showRouteModal = true;
        },

        closeRouteModal() {
            this.showRouteModal = false;
            this.routeErrors = {};
        },

        onAirlineSelectChange(event) {
            if (event.target.value === '__add_new__') {
                event.target.value = '';
                this.openAirlineModal();
            }
        },

        openAirlineModal() {
            this.airlineData = { name: '', code: '' };
            this.airlineErrors = {};
            this.airlineModalOpen = true;
        },

        closeAirlineModal() {
            this.airlineModalOpen = false;
            this.airlineErrors = {};
        },

        saveAirline() {
            this.airlineSaving = true;
            this.airlineErrors = {};
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            fetch('{{ route('airlines.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta ? csrfMeta.getAttribute('content') : ''
                },
                body: JSON.stringify(this.airlineData)
            })
            .then(async (response) => {
                this.airlineSaving = false;
                const data = await response.json().catch(() => ({}));
                if (response.status === 422 && data.errors) {
                    this.airlineErrors = data.errors;
                    return;
                }
                if (response.ok && data.success && data.airline) {
                    this.appendAirlineToSelect(data.airline);
                    this.closeAirlineModal();
                    if (typeof window.showToast === 'function') {
                        window.showToast('Airline created successfully', 'success');
                    }
                    return;
                }
                if (typeof window.showToast === 'function') {
                    window.showToast((data && data.message) || 'Failed to create airline', 'error');
                }
            })
            .catch(() => {
                this.airlineSaving = false;
                if (typeof window.showToast === 'function') {
                    window.showToast('Failed to create airline', 'error');
                }
            });
        },

        appendAirlineToSelect(airline) {
            const sel = document.getElementById('airlineId');
            if (!sel) return;

            let exists = false;
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === String(airline.id)) { exists = true; break; }
            }

            if (!exists) {
                const newOpt = document.createElement('option');
                newOpt.value = String(airline.id);
                newOpt.text = airline.name;
                newOpt.selected = true;

                const addNewOpt = sel.querySelector('option[value="__add_new__"]');
                if (addNewOpt) {
                    sel.insertBefore(newOpt, addNewOpt);
                } else {
                    sel.appendChild(newOpt);
                }
            } else {
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === String(airline.id)) {
                        sel.options[i].selected = true;
                        break;
                    }
                }
            }

            sel.value = String(airline.id);

            if (typeof window.handleAirlineChange === 'function') {
                window.handleAirlineChange();
            }

            const classAirlineSel = document.getElementById('modal_class_airline_id');
            if (classAirlineSel) {
                let classExists = false;
                for (let i = 0; i < classAirlineSel.options.length; i++) {
                    if (classAirlineSel.options[i].value === String(airline.id)) { classExists = true; break; }
                }
                if (!classExists) {
                    const newOpt = document.createElement('option');
                    newOpt.value = String(airline.id);
                    newOpt.text = airline.name;
                    const placeholder = classAirlineSel.querySelector('option[value=""]');
                    if (placeholder && placeholder.nextSibling) {
                        classAirlineSel.insertBefore(newOpt, placeholder.nextSibling);
                    } else {
                        classAirlineSel.appendChild(newOpt);
                    }
                }
            }
        },

        onClassSelectChange(event) {
            if (event.target.value === '__add_new__') {
                event.target.value = '';
                this.openClassModal();
            }
        },

        openClassModal() {
            this.classData = { airline_id: '', class_id: '' };
            this.classErrors = {};
            this.classModalOpen = true;
        },

        closeClassModal() {
            this.classModalOpen = false;
            this.classErrors = {};
        },

        saveClass() {
            this.classSaving = true;
            this.classErrors = {};
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            fetch('{{ route('airline-classes.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta ? csrfMeta.getAttribute('content') : ''
                },
                body: JSON.stringify(this.classData)
            })
            .then(async (response) => {
                this.classSaving = false;
                const data = await response.json().catch(() => ({}));
                if (response.status === 422 && data.errors) {
                    this.classErrors = data.errors;
                    return;
                }
                if (response.ok && data.success && data.airline_class) {
                    this.appendClassToSelect(data.airline_class);
                    this.closeClassModal();
                    if (typeof window.showToast === 'function') {
                        window.showToast('Class created successfully', 'success');
                    }
                    return;
                }
                if (typeof window.showToast === 'function') {
                    window.showToast((data && data.message) || 'Failed to create class', 'error');
                }
            })
            .catch(() => {
                this.classSaving = false;
                if (typeof window.showToast === 'function') {
                    window.showToast('Failed to create class', 'error');
                }
            });
        },

        appendClassToSelect(airlineClass) {
            const sel = document.getElementById('classId');
            if (!sel) return;

            let exists = false;
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === String(airlineClass.id)) { exists = true; break; }
            }

            const className = (airlineClass.class && airlineClass.class.name)
                ? airlineClass.class.name
                : ((airlineClass.travelClass && airlineClass.travelClass.name) ? airlineClass.travelClass.name : ('Class ' + airlineClass.id));

            if (!exists) {
                const newOpt = document.createElement('option');
                newOpt.value = String(airlineClass.id);
                newOpt.setAttribute('data-airline-id', String(airlineClass.airline_id));
                newOpt.text = className;
                newOpt.selected = true;

                const addNewOpt = sel.querySelector('option[value="__add_new__"]');
                if (addNewOpt) {
                    sel.insertBefore(newOpt, addNewOpt);
                } else {
                    sel.appendChild(newOpt);
                }
            } else {
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === String(airlineClass.id)) {
                        sel.options[i].selected = true;
                        break;
                    }
                }
            }

            sel.value = String(airlineClass.id);

            if (typeof window.filterClasses === 'function') {
                window.filterClasses();
            }
        },

        saveRoute() {
            this.routeSaving = true;
            this.routeErrors = {};
            const form = document.getElementById('routeFormModal');
            const formData = new FormData(form);

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            fetch('{{ route('routes.store') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta ? csrfMeta.getAttribute('content') : ''
                },
                body: formData
            })
            .then(async (response) => {
                this.routeSaving = false;
                const data = await response.json().catch(() => ({}));
                if (response.status === 422 && data.errors) {
                    const flat = {};
                    for (const key in data.errors) {
                        flat[key] = Array.isArray(data.errors[key]) ? data.errors[key][0] : data.errors[key];
                    }
                    this.routeErrors = flat;
                    if (typeof window.showToast === 'function') {
                        window.showToast('Please fix the highlighted errors', 'error');
                    }
                    return;
                }
                if (response.ok && data.success && data.route) {
                    this.appendRouteToSelect(data.route);
                    this.closeRouteModal();
                    if (typeof window.showToast === 'function') {
                        window.showToast('Route created successfully', 'success');
                    }
                    return;
                }
                if (typeof window.showToast === 'function') {
                    window.showToast((data && data.message) || 'Failed to create route', 'error');
                }
            })
            .catch(() => {
                this.routeSaving = false;
                if (typeof window.showToast === 'function') {
                    window.showToast('Failed to create route', 'error');
                }
            });
        },

        appendRouteToSelect(route) {
            const sel = document.getElementById('routeId');
            if (!sel) return;

            const routeType = (route.route_type && route.route_type.value !== undefined)
                ? route.route_type.value
                : (route.route_type || '');

            const flightType = (route.flight_type && route.flight_type.value !== undefined)
                ? route.flight_type.value
                : (route.flight_type || '');

            let label = '';
            if (routeType === 'multi_city') {
                const seg = (route.multi_segments && route.multi_segments.length > 0)
                    ? route.multi_segments[0]
                    : (route.multiSegments && route.multiSegments.length > 0 ? route.multiSegments[0] : null);
                if (seg) {
                    const fromCode = (seg.from_city && seg.from_city.code) || (seg.fromCity && seg.fromCity.code) || '-';
                    const toCode = (seg.to_city && seg.to_city.code) || (seg.toCity && seg.toCity.code) || '-';
                    const airlineName = (route.airline && route.airline.name) || '';
                    label = `${fromCode}-${toCode} ... (${airlineName})`;
                } else {
                    label = `${(route.airline && route.airline.name) || 'Route'} (Multi City)`;
                }
            } else {
                const fromCode = (route.from_city && route.from_city.code) || (route.fromCity && route.fromCity.code) || '-';
                const toCode = (route.to_city && route.to_city.code) || (route.toCity && route.toCity.code) || '-';
                const returnCode = (route.return_city && route.return_city.code) || (route.returnCity && route.returnCity.code) || '';
                const airlineName = (route.airline && route.airline.name) || '';
                label = `${fromCode}-${toCode}${routeType === 'round' ? '-' + (returnCode || '-') : ''} (${airlineName})`;
            }

            const newOpt = document.createElement('option');
            newOpt.value = String(route.id);
            newOpt.text = label;
            newOpt.setAttribute('data-route-type', routeType);
            newOpt.setAttribute('data-flight-type', flightType);
            newOpt.selected = true;

            let inserted = false;
            for (let i = 0; i < sel.options.length; i++) {
                const opt = sel.options[i];
                if (opt.value === '' || opt.value === '__add_new__') continue;
                if (opt.value === String(route.id)) {
                    opt.selected = true;
                    inserted = true;
                    break;
                }
            }
            if (!inserted) {
                sel.appendChild(newOpt);
            }

            sel.value = String(route.id);

            if (typeof window.filterRoutes === 'function') {
                window.filterRoutes();
            }
            if (typeof window.handleRouteChange === 'function') {
                window.handleRouteChange();
            }
        },

        onCitySelectChange(selectKey, event) {
            if (event.target.value === '__add_new__') {
                event.target.value = '';
                const nonRouteKeys = [
                    'segments_0_from_city_id', 'segments_0_to_city_id',
                    'segments_1_from_city_id', 'segments_1_to_city_id',
                    'transit_0_city_id', 'transit_1_city_id'
                ];
                if (!nonRouteKeys.includes(selectKey)) {
                    this.route[selectKey] = '';
                }
                this.activeSelect = selectKey;
                this.openCityModal();
            }
        },

        openCityModal() {
            this.cityData = { city_name: '', code: '', country: '' };
            this.cityErrors = {};
            this.cityModalOpen = true;
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
            fetch('{{ route('city-codes.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta ? csrfMeta.getAttribute('content') : ''
                },
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
            const routeKeys = ['from_city_id', 'to_city_id', 'return_city_id'];
            const segmentKeys = ['segments_0_from_city_id', 'segments_0_to_city_id', 'segments_1_from_city_id', 'segments_1_to_city_id'];
            const transitKeys = ['transit_0_city_id', 'transit_1_city_id'];
            const allKeys = [...routeKeys, ...segmentKeys, ...transitKeys];
            const label = `${city.code} (${city.city_name})`;

            const setActiveValue = (k) => {
                if (routeKeys.includes(k)) {
                    this.route[k] = String(city.id);
                } else if (transitKeys.includes(k)) {
                    const idx = parseInt(k.split('_')[1], 10);
                    this.route.transits[idx].transit_city_id = String(city.id);
                } else {
                    const sel = document.getElementById(k);
                    if (sel) sel.value = String(city.id);
                }
            };

            allKeys.forEach((k) => {
                const sel = document.getElementById(k);
                if (!sel) return;

                let exists = false;
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === String(city.id)) { exists = true; break; }
                }
                if (exists) {
                    if (this.activeSelect === k) setActiveValue(k);
                    return;
                }

                const newOpt = document.createElement('option');
                newOpt.value = String(city.id);
                newOpt.text = label;

                let inserted = false;
                for (let i = 0; i < sel.options.length; i++) {
                    const opt = sel.options[i];
                    if (opt.value === '' || opt.value === '__add_new__') continue;
                    if (label.localeCompare(opt.text) < 0) {
                        sel.insertBefore(newOpt, opt);
                        inserted = true;
                        break;
                    }
                }
                if (!inserted) {
                    const addNewOpt = sel.querySelector('option[value="__add_new__"]');
                    if (addNewOpt) {
                        sel.insertBefore(newOpt, addNewOpt);
                    } else {
                        sel.appendChild(newOpt);
                    }
                }

                if (this.activeSelect === k) setActiveValue(k);
            });
        }
    }
}
</script>
@endsection