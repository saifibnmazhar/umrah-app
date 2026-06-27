@extends('layouts.app')
@section('title', 'Edit Ticket Fare')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Edit Ticket Fare</h1>
        <a href="{{ route('ticket-fares.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition text-sm font-medium">
            Back to List
        </a>
    </div>

    @php
        $currentRouteType = old('route_type', $ticketFare->route->route_type->value ?? '');
        $currentTicketType = old('ticket_type', $ticketFare->ticket_type->value);
        $hasInboundBaggage = in_array($currentRouteType, ['oneway_inbound', 'round', 'multi_city']);
        $hasOutboundBaggage = in_array($currentRouteType, ['oneway_outbound', 'round', 'multi_city']);
    @endphp

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ticket-fares.update', $ticketFare->id) }}" x-data="{
        fares: {
            net_fare: { sar: {{ old('net_fare', $ticketFare->net_fare) }}, bdt: 0 },
            selling_fare: { sar: {{ old('selling_fare', $ticketFare->selling_fare) }}, bdt: 0 },
            offer_price: { sar: {{ old('offer_price', $ticketFare->offer_price) }}, bdt: 0 },
        },
        init() {
            const rate = window.__currencyRate || 0;
            if (rate > 0) {
                this.fares.net_fare.bdt = (parseFloat(this.fares.net_fare.sar) * rate).toFixed(6);
                this.fares.selling_fare.bdt = (parseFloat(this.fares.selling_fare.sar) * rate).toFixed(6);
                this.fares.offer_price.bdt = (parseFloat(this.fares.offer_price.sar) * rate).toFixed(6);
            }
            const component = this;
            window.addEventListener('currency-toggled', function () {
                const r = window.__currencyRate || 0;
                if (r > 0) {
                    component.fares.net_fare.bdt = (parseFloat(component.fares.net_fare.sar) * r).toFixed(6);
                    component.fares.selling_fare.bdt = (parseFloat(component.fares.selling_fare.sar) * r).toFixed(6);
                    component.fares.offer_price.bdt = (parseFloat(component.fares.offer_price.sar) * r).toFixed(6);
                }
            });
        },
        handleSarInput(field) {
            const fare = this.fares[field];
            const rate = window.__currencyRate || 0;
            if (rate > 0) {
                fare.bdt = (parseFloat(fare.sar) * rate).toFixed(6);
            }
        },
        handleBdtInput(field) {
            const fare = this.fares[field];
            const rate = window.__currencyRate || 0;
            if (rate > 0) {
                fare.sar = (parseFloat(fare.bdt) / rate).toFixed(6);
            }
        },
    }">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                    <input type="date" value="{{ $ticketFare->created_at->format('Y-m-d') }}" readonly class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm bg-slate-50">
                </div> --}}
                @php
                    $currentFlightType = old('flight_type', $ticketFare->route->flight_type->value ?? '');
                @endphp
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                    <select name="route_type" id="routeType" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" onchange="handleRouteTypeChange()">
                        <option value="">Select Type</option>
                        <option value="oneway_inbound" {{ $currentRouteType == 'oneway_inbound' ? 'selected' : '' }}>One Way - Inbound</option>
                        <option value="oneway_outbound" {{ $currentRouteType == 'oneway_outbound' ? 'selected' : '' }}>One Way - Outbound</option>
                        <option value="round" {{ $currentRouteType == 'round' ? 'selected' : '' }}>Round</option>
                        <option value="multi_city" {{ $currentRouteType == 'multi_city' ? 'selected' : '' }}>Multi City</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                    <select name="flight_type" id="flightType" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" onchange="handleFlightTypeChange()">
                        <option value="">Select Flight Type</option>
                        <option value="direct" {{ $currentFlightType == 'direct' ? 'selected' : '' }}>Direct</option>
                        <option value="transit" {{ $currentFlightType == 'transit' ? 'selected' : '' }}>Transit</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                    <select name="airline_id" id="airlineId" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" onchange="handleAirlineChange()">
                        <option value="">Select Airline</option>
                        @foreach($airlines as $airline)
                            <option value="{{ $airline->id }}" {{ old('airline_id', $ticketFare->airline_id) == $airline->id ? 'selected' : '' }}>
                                {{ $airline->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Class *</label>
                    <select name="airline_classes_id" id="classId" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="">Select Class</option>
                        @foreach($airlineClasses as $class)
                            <option value="{{ $class->id }}" data-airline-id="{{ $class->airline_id }}" {{ old('airline_classes_id', $ticketFare->airline_classes_id) == $class->id ? 'selected' : '' }}>
                                {{ $class->travelClass->name ?? 'Class ' . $class->id }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Route *</label>
                    <select name="route_id" id="routeId" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" onchange="handleRouteChange()">
                        <option value="">Select Route</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}"
                                    data-route-type="{{ $route->route_type->value }}"
                                    data-flight-type="{{ $route->flight_type->value ?? '' }}"
                                    {{ old('route_id', $ticketFare->route_id) == $route->id ? 'selected' : '' }}>
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
                        <option value="regular" {{ $currentTicketType == 'regular' ? 'selected' : '' }}>Regular</option>
                        <option value="offer" {{ $currentTicketType == 'offer' ? 'selected' : '' }}>Offer</option>
                        <option value="group" {{ $currentTicketType == 'group' ? 'selected' : '' }}>Group</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Effective From *</label>
                    <input type="date" name="effective_from" value="{{ old('effective_from', $ticketFare->effective_from->format('Y-m-d')) }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Effective To *</label>
                    <input type="date" name="effective_to" value="{{ old('effective_to', $ticketFare->effective_to->format('Y-m-d')) }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div class="flex items-center">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="with_meal" value="1" {{ old('with_meal', $ticketFare->with_meal) ? 'checked' : '' }} class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-500">
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
                    <input type="number" name="net_fare" x-model="fares.net_fare.sar" @input="handleSarInput('net_fare')" :readonly="$store.currency.mode === 'BDT'" :class="{'bg-slate-100 cursor-not-allowed': $store.currency.mode === 'BDT'}" step="any" min="0" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <div x-show="$store.currency.mode === 'BDT'" x-cloak class="mt-1">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (BDT) *</label>
                        <input type="number" x-model="fares.net_fare.bdt" @input="handleBdtInput('net_fare')" step="any" min="0" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR) *</label>
                    <input type="number" name="selling_fare" x-model="fares.selling_fare.sar" @input="handleSarInput('selling_fare')" :readonly="$store.currency.mode === 'BDT'" :class="{'bg-slate-100 cursor-not-allowed': $store.currency.mode === 'BDT'}" step="any" min="0" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <div x-show="$store.currency.mode === 'BDT'" x-cloak class="mt-1">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (BDT) *</label>
                        <input type="number" x-model="fares.selling_fare.bdt" @input="handleBdtInput('selling_fare')" step="any" min="0" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
                <div id="offerPriceField" class="{{ $currentTicketType !== 'offer' ? 'hidden' : '' }}">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR) *</label>
                    <input type="number" name="offer_price" x-model="fares.offer_price.sar" @input="handleSarInput('offer_price')" :readonly="$store.currency.mode === 'BDT'" :class="{'bg-slate-100 cursor-not-allowed': $store.currency.mode === 'BDT'}" step="any" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <div x-show="$store.currency.mode === 'BDT'" x-cloak class="mt-1">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (BDT) *</label>
                        <input type="number" x-model="fares.offer_price.bdt" @input="handleBdtInput('offer_price')" step="any" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Child Fare (%) *</label>
                    <input type="number" name="child_fare_percentage" value="{{ old('child_fare_percentage', $ticketFare->child_fare_percentage) }}" step="0.01" min="0" max="100" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Infant Fare (%) *</label>
                    <input type="number" name="infant_fare_percentage" value="{{ old('infant_fare_percentage', $ticketFare->infant_fare_percentage) }}" step="0.01" min="0" max="100" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div id="groupTicketSection" class="{{ $currentTicketType !== 'group' ? 'hidden' : '' }} bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Group Ticket Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">PNR *</label>
                    <input type="text" name="pnr" value="{{ old('pnr', $ticketFare->groupTicket?->pnr) }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Quantity *</label>
                    <input type="number" name="ticket_qty" value="{{ old('ticket_qty', $ticketFare->groupTicket?->ticket_qty) }}" min="1" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div id="inboundDateField" class="{{ in_array($currentRouteType, ['oneway_outbound']) ? 'hidden' : '' }}">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date <span class="required-mark">*</span></label>
                    <input type="date" name="inbound_date" value="{{ old('inbound_date', $ticketFare->groupTicket?->inbound_date?->format('Y-m-d')) }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div id="outboundDateField" class="{{ in_array($currentRouteType, ['oneway_inbound']) ? 'hidden' : '' }}">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date <span class="required-mark">*</span></label>
                    <input type="date" name="outbound_date" value="{{ old('outbound_date', $ticketFare->groupTicket?->outbound_date?->format('Y-m-d')) }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
            </div>
            <div class="flex justify-start mt-5 gap-4">                
                <div class="flex items-center">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="is_non_refundable" value="1" {{ old('is_non_refundable', !$ticketFare->groupTicket?->is_refundable) ? 'checked' : '' }} class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-500">
                        <span class="ml-2 text-sm text-slate-700">Non-Refundable</span>
                    </label>
                </div>
                <div class="flex items-center">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="is_non_exchangable" value="1" {{ old('is_non_exchangable', !$ticketFare->groupTicket?->is_exchangable) ? 'checked' : '' }} class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-500">
                        <span class="ml-2 text-sm text-slate-700">Non-Exchangable</span>
                    </label>
                </div>
            </div>
        </div>

        <div id="baggageSection" class="{{ !$currentRouteType ? 'hidden' : '' }} bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Baggage Allowances (KG)</h2>
            <div id="inboundBaggage" class="{{ !$hasInboundBaggage ? 'hidden' : '' }} mb-4">
                <h3 class="text-sm font-medium text-slate-700 mb-3">Inbound</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Adult</label>
                        <input type="number" name="inbound_adult" value="{{ old('inbound_adult', $ticketFare->baggageAllowances->where('passenger_type', 'adult')->where('travel_direction', 'inbound')->first()?->allowance ?? 30) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Child</label>
                        <input type="number" name="inbound_child" value="{{ old('inbound_child', $ticketFare->baggageAllowances->where('passenger_type', 'child')->where('travel_direction', 'inbound')->first()?->allowance ?? 30) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Infant</label>
                        <input type="number" name="inbound_infant" value="{{ old('inbound_infant', $ticketFare->baggageAllowances->where('passenger_type', 'infant')->where('travel_direction', 'inbound')->first()?->allowance ?? 0) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
            <div id="outboundBaggage" class="{{ !$hasOutboundBaggage ? 'hidden' : '' }}">
                <h3 class="text-sm font-medium text-slate-700 mb-3">Outbound</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Adult</label>
                        <input type="number" name="outbound_adult" value="{{ old('outbound_adult', $ticketFare->baggageAllowances->where('passenger_type', 'adult')->where('travel_direction', 'outbound')->first()?->allowance ?? 50) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Child</label>
                        <input type="number" name="outbound_child" value="{{ old('outbound_child', $ticketFare->baggageAllowances->where('passenger_type', 'child')->where('travel_direction', 'outbound')->first()?->allowance ?? 50) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Infant</label>
                        <input type="number" name="outbound_infant" value="{{ old('outbound_infant', $ticketFare->baggageAllowances->where('passenger_type', 'infant')->where('travel_direction', 'outbound')->first()?->allowance ?? 0) }}" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('ticket-fares.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition text-sm font-medium">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition text-sm font-medium">
                Update Ticket Fare
            </button>
        </div>
    </form>
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
        if (option.value === '') return;

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
</script>
@endsection