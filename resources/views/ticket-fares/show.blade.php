@extends('layouts.app')
@section('title', 'Ticket Fare Details')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Ticket Fare Details</h1>
        <a href="{{ route('ticket-fares.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition text-sm font-medium">
            Back to List
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Basic Information</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-sm text-slate-500">ID</span>
                        <p class="text-slate-800 font-medium">{{ $ticketFare->id }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Airline</span>
                        <p class="text-slate-800 font-medium">{{ $ticketFare->airline->name ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Class</span>
                        <p class="text-slate-800">{{ $ticketFare->airlineClass->travelClass->name ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Route</span>
                        <p class="text-slate-800 font-medium">
                            @if($ticketFare->route)
                                @if($ticketFare->route->route_type->value === 'multi_city')
                                    @if($ticketFare->route->multiSegments->count() > 0)
                                        {{ $ticketFare->route->multiSegments->first()->fromCity->code ?? '-' }}-{{ $ticketFare->route->multiSegments->first()->toCity->code ?? '-' }} ...
                                    @else
                                        -
                                    @endif
                                @else
                                    {{ $ticketFare->route->fromCity->code ?? '-' }}-{{ $ticketFare->route->toCity->code ?? '-' }}
                                    @if($ticketFare->route->route_type->value === 'round')
                                        -{{ $ticketFare->route->returnCity->code ?? '-' }}
                                    @endif
                                @endif
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Ticket Type</span>
                        <p class="mt-1">
                            @switch($ticketFare->ticket_type->value)
                                @case('regular')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">
                                        Regular
                                    </span>
                                    @break
                                @case('offer')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                        Offer
                                    </span>
                                    @break
                                @case('group')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
                                        Group
                                    </span>
                                    @break
                            @endswitch
                        </p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">With Meal</span>
                        <p class="text-slate-800">{{ $ticketFare->with_meal ? 'Yes' : 'No' }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Effective From</span>
                        <p class="text-slate-800">{{ $ticketFare->effective_from->format('Y-m-d') }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Effective To</span>
                        <p class="text-slate-800">{{ $ticketFare->effective_to->format('Y-m-d') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Fare Information</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <span class="text-sm text-slate-500">Net Fare</span>
                        <p class="text-slate-800 font-medium text-lg">{{ number_format($ticketFare->net_fare, 2) }} SAR</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Selling Fare</span>
                        <p class="text-slate-800 font-medium text-lg">{{ number_format($ticketFare->selling_fare, 2) }} SAR</p>
                    </div>
                    @if($ticketFare->offer_price)
                    <div>
                        <span class="text-sm text-slate-500">Offer Price</span>
                        <p class="text-green-600 font-medium text-lg">{{ number_format($ticketFare->offer_price, 2) }} SAR</p>
                    </div>
                    @endif
                    <div>
                        <span class="text-sm text-slate-500">Child Fare %</span>
                        <p class="text-slate-800">{{ $ticketFare->child_fare_percentage }}%</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Infant Fare %</span>
                        <p class="text-slate-800">{{ $ticketFare->infant_fare_percentage }}%</p>
                    </div>
                </div>
            </div>

            @if($ticketFare->groupTicket)
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Group Ticket Details</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <span class="text-sm text-slate-500">Inbound Date</span>
                        <p class="text-slate-800">{{ $ticketFare->groupTicket->inbound_date->format('Y-m-d') }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Outbound Date</span>
                        <p class="text-slate-800">{{ $ticketFare->groupTicket->outbound_date->format('Y-m-d') }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">PNR</span>
                        <p class="text-slate-800 font-medium">{{ $ticketFare->groupTicket->pnr }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Ticket Quantity</span>
                        <p class="text-slate-800">{{ $ticketFare->groupTicket->ticket_qty }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Refundable</span>
                        <p class="text-slate-800">{{ $ticketFare->groupTicket->is_refundable ? 'Yes' : 'No' }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500">Exchangable</span>
                        <p class="text-slate-800">{{ $ticketFare->groupTicket->is_exchangable ? 'Yes' : 'No' }}</p>
                    </div>
                </div>
            </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Baggage Allowances</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-slate-700 mb-3">Inbound</h3>
                        <div class="space-y-2">
                            @php
                                $inboundAllowances = $ticketFare->baggageAllowances->where('travel_direction', 'inbound');
                            @endphp
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-500">Adult</span>
                                <span class="text-slate-800">{{ $inboundAllowances->where('passenger_type', 'adult')->first()?->allowance ?? '-' }} KG</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-500">Child</span>
                                <span class="text-slate-800">{{ $inboundAllowances->where('passenger_type', 'child')->first()?->allowance ?? '-' }} KG</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-500">Infant</span>
                                <span class="text-slate-800">{{ $inboundAllowances->where('passenger_type', 'infant')->first()?->allowance ?? '-' }} KG</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-slate-700 mb-3">Outbound</h3>
                        <div class="space-y-2">
                            @php
                                $outboundAllowances = $ticketFare->baggageAllowances->where('travel_direction', 'outbound');
                            @endphp
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-500">Adult</span>
                                <span class="text-slate-800">{{ $outboundAllowances->where('passenger_type', 'adult')->first()?->allowance ?? '-' }} KG</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-500">Child</span>
                                <span class="text-slate-800">{{ $outboundAllowances->where('passenger_type', 'child')->first()?->allowance ?? '-' }} KG</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-500">Infant</span>
                                <span class="text-slate-800">{{ $outboundAllowances->where('passenger_type', 'infant')->first()?->allowance ?? '-' }} KG</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="md:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
                <h3 class="text-sm font-medium text-slate-500 mb-4">Actions</h3>
                <div class="space-y-2">
                    <a href="{{ route('ticket-fares.edit', $ticketFare->id) }}" class="block w-full text-center px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-600 transition text-sm font-medium">
                        Edit
                    </a>
                    <form method="POST" action="{{ route('ticket-fares.destroy', $ticketFare->id) }}" onsubmit="return confirm('Are you sure you want to delete this ticket fare?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="block w-full text-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition text-sm font-medium">
                            Delete
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <h3 class="text-sm font-medium text-slate-500 mb-4">Metadata</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-slate-500">Created By</span>
                        <p class="text-slate-800">{{ $ticketFare->user->name ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-slate-500">Created At</span>
                        <p class="text-slate-800">{{ $ticketFare->created_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div>
                        <span class="text-slate-500">Updated At</span>
                        <p class="text-slate-800">{{ $ticketFare->updated_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection