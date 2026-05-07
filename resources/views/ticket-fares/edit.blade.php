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

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ticket-fares.update', $ticketFare->id) }}">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                    <select name="airline_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
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
                    <select name="airline_classes_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="">Select Class</option>
                        @foreach($airlineClasses as $class)
                            <option value="{{ $class->id }}" {{ old('airline_classes_id', $ticketFare->airline_classes_id) == $class->id ? 'selected' : '' }}>
                                {{ $class->travelClass->name ?? 'Class ' . $class->id }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Route *</label>
                    <select name="route_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="">Select Route</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}" {{ old('route_id', $ticketFare->route_id) == $route->id ? 'selected' : '' }}>
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
                    <select name="ticket_type" id="ticketType" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" onchange="toggleTicketTypeFields()">
                        <option value="">Select Type</option>
                        <option value="regular" {{ old('ticket_type', $ticketFare->ticket_type->value) == 'regular' ? 'selected' : '' }}>Regular</option>
                        <option value="offer" {{ old('ticket_type', $ticketFare->ticket_type->value) == 'offer' ? 'selected' : '' }}>Offer</option>
                        <option value="group" {{ old('ticket_type', $ticketFare->ticket_type->value) == 'group' ? 'selected' : '' }}>Group</option>
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
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Fare Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (SAR) *</label>
                    <input type="number" name="net_fare" value="{{ old('net_fare', $ticketFare->net_fare) }}" step="0.01" min="0" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR) *</label>
                    <input type="number" name="selling_fare" value="{{ old('selling_fare', $ticketFare->selling_fare) }}" step="0.01" min="0" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div id="offerPriceField" class="{{ $ticketFare->ticket_type->value !== 'offer' ? 'hidden' : '' }}">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR) *</label>
                    <input type="number" name="offer_price" value="{{ old('offer_price', $ticketFare->offer_price) }}" step="0.01" min="0" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Child Fare (%) *</label>
                    <input type="number" name="child_fare_percentage" value="{{ old('child_fare_percentage', $ticketFare->child_fare_percentage) }}" step="0.01" min="0" max="100" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Infant Fare (%) *</label>
                    <input type="number" name="infant_fare_percentage" value="{{ old('infant_fare_percentage', $ticketFare->infant_fare_percentage) }}" step="0.01" min="0" max="100" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">With Meal</label>
                    <select name="with_meal" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="0" {{ old('with_meal', $ticketFare->with_meal) == '0' ? 'selected' : '' }}>No</option>
                        <option value="1" {{ old('with_meal', $ticketFare->with_meal) == '1' ? 'selected' : '' }}>Yes</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="groupTicketSection" class="{{ $ticketFare->ticket_type->value !== 'group' ? 'hidden' : '' }} bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Group Ticket Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date *</label>
                    <input type="date" name="inbound_date" value="{{ old('inbound_date', $ticketFare->groupTicket?->inbound_date?->format('Y-m-d')) }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date *</label>
                    <input type="date" name="outbound_date" value="{{ old('outbound_date', $ticketFare->groupTicket?->outbound_date?->format('Y-m-d')) }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">PNR *</label>
                    <input type="text" name="pnr" value="{{ old('pnr', $ticketFare->groupTicket?->pnr) }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Quantity *</label>
                    <input type="number" name="ticket_qty" value="{{ old('ticket_qty', $ticketFare->groupTicket?->ticket_qty) }}" min="1" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Refundable</label>
                    <select name="is_refundable" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="0" {{ old('is_refundable', $ticketFare->groupTicket?->is_refundable ?? '1') == '0' ? 'selected' : '' }}>No</option>
                        <option value="1" {{ old('is_refundable', $ticketFare->groupTicket?->is_refundable ?? '1') == '1' ? 'selected' : '' }}>Yes</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Exchangable</label>
                    <select name="is_exchangable" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="0" {{ old('is_exchangable', $ticketFare->groupTicket?->is_exchangable ?? '1') == '0' ? 'selected' : '' }}>No</option>
                        <option value="1" {{ old('is_exchangable', $ticketFare->groupTicket?->is_exchangable ?? '1') == '1' ? 'selected' : '' }}>Yes</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Baggage Allowances (KG)</h2>
            <p class="text-sm text-slate-500 mb-4">Baggage allowances are based on the selected route type.</p>
            <div class="mb-4">
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
            <div>
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
function toggleTicketTypeFields() {
    const ticketType = document.getElementById('ticketType').value;
    const offerPriceField = document.getElementById('offerPriceField');
    const groupTicketSection = document.getElementById('groupTicketSection');

    if (ticketType === 'offer') {
        offerPriceField.classList.remove('hidden');
    } else {
        offerPriceField.classList.add('hidden');
    }

    if (ticketType === 'group') {
        groupTicketSection.classList.remove('hidden');
    } else {
        groupTicketSection.classList.add('hidden');
    }
}
</script>
@endsection