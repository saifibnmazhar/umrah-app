@extends('layouts.app')

@section('title', 'Ticket Admin')

@section('content')
<div class="max-w-7xl mx-auto pt-6" x-data="{
    activeTab: 'agents',
    showAgentModal: false,
    editAgentMode: false,
    agent: { id: null, name: '', address: '', contacts: '' },
    showFareModal: false,
    editFareMode: false,
    fare: { id: null, airline_id: '', airline_classes_id: '', route_id: '', ticket_type: 'regular', effective_from: '', effective_to: '', net_fare: '', selling_fare: '', child_fare_percentage: 75, infant_fare_percentage: 10, offer_price: '', with_meal: false },
    showRouteModal: false,
    editRouteMode: false,
    route: { id: null, airline_id: '', route_type: 'oneway_outbound', flight_type: 'direct', from_city_id: '', to_city_id: '', return_city_id: '' }
}">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Ticket Admin</h1>

    <div class="border-b border-slate-200 mb-6">
        <nav class="-mb-px flex gap-6">
            <button @click="activeTab = 'agents'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'agents', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeTab !== 'agents' }" class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                Ticket Agents
            </button>
            <button @click="activeTab = 'fares'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'fares', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeTab !== 'fares' }" class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                Ticket Fares
            </button>
            <button @click="activeTab = 'routes'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'routes', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeTab !== 'routes' }" class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                Routes
            </button>
        </nav>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div x-show="activeTab === 'agents'" x-cloak>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-slate-700">Ticket Agents</h2>
            <button @click="editAgentMode = false; agent = { id: null, name: '', address: '', contacts: '' }; showAgentModal = true" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition text-sm font-medium">
                Add New
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-xs font-medium uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4 text-left">Name</th>
                            <th class="px-6 py-4 text-left">Address</th>
                            <th class="px-6 py-4 text-left">Contacts</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($ticketAgents as $ticketAgent)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-slate-700 font-medium">{{ $ticketAgent->name }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $ticketAgent->address ?? '—' }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $ticketAgent->contacts ?? '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-4">
                                        <button @click="editAgentMode = true; agent = { id: {{ $ticketAgent->id }}, name: '{{ $ticketAgent->name }}', address: '{{ $ticketAgent->address ?? '' }}', contacts: '{{ $ticketAgent->contacts ?? '' }}' }; showAgentModal = true" class="text-slate-600 hover:text-slate-800 font-medium text-sm">Edit</button>
                                        <form method="POST" action="{{ route('fare.admin.agent.destroy', $ticketAgent->id) }}" onsubmit="return confirm('Are you sure you want to delete this ticket agent?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                    No ticket agents found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex justify-center">
            {{ $ticketAgents->appends(request()->query())->links() }}
        </div>
    </div>

    <div x-show="activeTab === 'fares'" x-cloak>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-slate-700">Ticket Fares</h2>
            <button @click="editFareMode = false; fare = { id: null, airline_id: '', airline_classes_id: '', route_id: '', ticket_type: 'regular', effective_from: '', effective_to: '', net_fare: '', selling_fare: '', child_fare_percentage: 75, infant_fare_percentage: 10, offer_price: '', with_meal: false }; showFareModal = true" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition text-sm font-medium">
                Add New
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden mb-6">
            <form method="GET" action="{{ route('fare.admin') }}" class="p-4 flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by airline..." class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div class="min-w-[150px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
                    <select name="airline_id" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="">All Airlines</option>
                        @foreach($airlines as $airline)
                            <option value="{{ $airline->id }}" {{ request('airline_id') == $airline->id ? 'selected' : '' }}>
                                {{ $airline->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[150px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type</label>
                    <select name="ticket_type" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="">All Types</option>
                        <option value="regular" {{ request('ticket_type') == 'regular' ? 'selected' : '' }}>Regular</option>
                        <option value="offer" {{ request('ticket_type') == 'offer' ? 'selected' : '' }}>Offer</option>
                        <option value="group" {{ request('ticket_type') == 'group' ? 'selected' : '' }}>Group</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-600 transition text-sm font-medium">
                        Filter
                    </button>
                    <a href="{{ route('fare.admin') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition text-sm font-medium ml-2">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-xs font-medium uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">ID</th>
                            <th class="px-4 py-3 text-left">Airline</th>
                            <th class="px-4 py-3 text-left">Class</th>
                            <th class="px-4 py-3 text-left">Route</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Net Fare</th>
                            <th class="px-4 py-3 text-left">Selling Fare</th>
                            <th class="px-4 py-3 text-left">Offer Price</th>
                            <th class="px-4 py-3 text-left">Effective From</th>
                            <th class="px-4 py-3 text-left">Effective To</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($ticketFares as $fare)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-700">{{ $fare->id }}</td>
                                <td class="px-4 py-3 text-slate-700 font-medium">{{ $fare->airline->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $fare->airlineClass->travelClass->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700 font-medium">
                                    @if($fare->route)
                                        {{ $fare->route->fromCity->code ?? '-' }}-{{ $fare->route->toCity->code ?? '-' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @switch($fare->ticket_type->value)
                                        @case('regular')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">Regular</span>
                                            @break
                                        @case('offer')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Offer</span>
                                            @break
                                        @case('group')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Group</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="px-4 py-3 text-slate-700 font-medium">{{ number_format($fare->net_fare, 2) }} SAR</td>
                                <td class="px-4 py-3 text-slate-700 font-medium">{{ number_format($fare->selling_fare, 2) }} SAR</td>
                                <td class="px-4 py-3 text-slate-600">{{ $fare->offer_price ? number_format($fare->offer_price, 2) . ' SAR' : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $fare->effective_from->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $fare->effective_to->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <button @click="editFareMode = true; fare = { id: {{ $fare->id }}, airline_id: '{{ $fare->airline_id }}', airline_classes_id: '{{ $fare->airline_classes_id }}', route_id: '{{ $fare->route_id }}', ticket_type: '{{ $fare->ticket_type->value }}', effective_from: '{{ $fare->effective_from->format('Y-m-d') }}', effective_to: '{{ $fare->effective_to->format('Y-m-d') }}', net_fare: '{{ $fare->net_fare }}', selling_fare: '{{ $fare->selling_fare }}', child_fare_percentage: '{{ $fare->child_fare_percentage }}', infant_fare_percentage: '{{ $fare->infant_fare_percentage }}', offer_price: '{{ $fare->offer_price ?? '' }}', with_meal: {{ $fare->with_meal ? 'true' : 'false' }} }; showFareModal = true" class="text-slate-600 hover:text-slate-800 font-medium">Edit</button>
                                        <form method="POST" action="{{ route('fare.admin.fare.destroy', $fare->id) }}" onsubmit="return confirm('Are you sure you want to delete this ticket fare?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-slate-500">
                                    No ticket fares found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex justify-center">
            {{ $ticketFares->appends(request()->query())->links() }}
        </div>
    </div>

    <div x-show="activeTab === 'routes'" x-cloak>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Routes</h1>
            <a href="#" @click.prevent="editRouteMode = false; route = { id: null, airline_id: '', route_type: 'oneway_outbound', flight_type: 'direct', from_city_id: '', to_city_id: '', return_city_id: '' }; showRouteModal = true" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Add New
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-xs font-medium uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">ID</th>
                            <th class="px-4 py-3 text-left">Airline</th>
                            <th class="px-4 py-3 text-left">Route Type</th>
                            <th class="px-4 py-3 text-left">Flight Type</th>
                            <th class="px-4 py-3 text-left">Route</th>
                            <th class="px-4 py-3 text-left">Transit Info</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($routes as $route)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-700">{{ $route->id }}</td>
                                <td class="px-4 py-3 text-slate-700 font-medium">{{ $route->airline->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    @switch($route->route_type->value)
                                        @case('oneway_inbound') Oneway - Inbound @break
                                        @case('oneway_outbound') Oneway - Outbound @break
                                        @case('round') Round @break
                                        @case('multi_city') Multi City @break
                                        @default {{ $route->route_type->value ?? '-' }}
                                    @endswitch
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $route->flight_type->value === 'transit' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ ucfirst($route->flight_type->value ?? '-') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700 font-medium">
                                    @if($route->route_type->value === 'multi_city')
                                        @if($route->multiSegments && $route->multiSegments->count() > 0)
                                            {{ $route->multiSegments->first()->fromCity->code ?? '-' }}-{{ $route->multiSegments->first()->toCity->code ?? '-' }} ...
                                        @else
                                            -
                                        @endif
                                    @else
                                        {{ $route->fromCity->code ?? '-' }}-{{ $route->toCity->code ?? '-' }}
                                        @if($route->route_type->value === 'round')
                                            -{{ $route->returnCity->code ?? '-' }}
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if($route->flight_type->value === 'transit' && $route->transits && $route->transits->count() > 0)
                                        @foreach($route->transits as $transit)
                                            @php
                                                $hours = floor($transit->transit_time / 60);
                                                $minutes = $transit->transit_time % 60;
                                            @endphp
                                            <span class="block">{{ $transit->transitCity->code ?? '-' }} ({{ str_pad($hours, 2, '0', STR_PAD_LEFT) }}:{{ str_pad($minutes, 2, '0', STR_PAD_LEFT) }})</span>
                                        @endforeach
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('routes.show', $route->id) }}" class="text-slate-600 hover:text-slate-800 font-medium">View</a>
                                        <button @click="editRouteMode = true; route = { id: {{ $route->id }}, airline_id: '{{ $route->airline_id }}', route_type: '{{ $route->route_type->value }}', flight_type: '{{ $route->flight_type->value }}', from_city_id: '{{ $route->from_city_id ?? '' }}', to_city_id: '{{ $route->to_city_id ?? '' }}', return_city_id: '{{ $route->return_city_id ?? '' }}' }; showRouteModal = true" class="text-slate-600 hover:text-slate-800 font-medium">Edit</button>
                                        <form method="POST" action="{{ route('routes.destroy', $route->id) }}" onsubmit="return confirm('Are you sure you want to delete this route?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">
                                    No routes found. <button @click="editRouteMode = false; route = { id: null, airline_id: '', route_type: 'oneway_outbound', flight_type: 'direct', from_city_id: '', to_city_id: '', return_city_id: '' }; showRouteModal = true" class="text-slate-800 underline hover:text-slate-600">Add one?</button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex justify-center">
            {{ $routes->appends(request()->query())->links() }}
        </div>
    </div>

    <div x-show="showAgentModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div x-show="showAgentModal" x-transition.opacity class="fixed inset-0 bg-black bg-opacity-50" @click="showAgentModal = false"></div>
            <div x-show="showAgentModal" x-transition class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6 z-10">
                <h3 class="text-lg font-semibold text-slate-800 mb-4" x-text="editAgentMode ? 'Edit Ticket Agent' : 'Add New Ticket Agent'"></h3>
                <form method="POST" :action="editAgentMode ? '/fares/admin/agent/' + agent.id : '{{ route('fare.admin.agent.store') }}'">
                    @csrf
                    <template x-if="editAgentMode">
                        <input type="hidden" name="_method" value="PUT">
                    </template>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
                            <input type="text" name="name" x-model="agent.name" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                            <textarea name="address" x-model="agent.address" rows="2" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Contacts</label>
                            <input type="text" name="contacts" x-model="agent.contacts" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showAgentModal = false" class="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white rounded-md text-sm font-medium transition" x-text="editAgentMode ? 'Update' : 'Create'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showFareModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div x-show="showFareModal" x-transition.opacity class="fixed inset-0 bg-black bg-opacity-50" @click="showFareModal = false"></div>
            <div x-show="showFareModal" x-transition class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6 z-10">
                <h3 class="text-lg font-semibold text-slate-800 mb-4" x-text="editFareMode ? 'Edit Ticket Fare' : 'Add New Ticket Fare'"></h3>
                <form method="POST" :action="editFareMode ? '/fares/admin/fare/' + fare.id : '{{ route('fare.admin.fare.store') }}'">
                    @csrf
                    <template x-if="editFareMode">
                        <input type="hidden" name="_method" value="PUT">
                    </template>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                            <select name="airline_id" x-model="fare.airline_id" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                                <option value="">Select Airline</option>
                                @foreach($airlines as $airline)
                                    <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type *</label>
                            <select name="ticket_type" x-model="fare.ticket_type" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                                <option value="regular">Regular</option>
                                <option value="offer">Offer</option>
                                <option value="group">Group</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare *</label>
                                <input type="number" name="net_fare" x-model="fare.net_fare" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare *</label>
                                <input type="number" name="selling_fare" x-model="fare.selling_fare" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Child Fare %</label>
                                <input type="number" name="child_fare_percentage" x-model="fare.child_fare_percentage" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Infant Fare %</label>
                                <input type="number" name="infant_fare_percentage" x-model="fare.infant_fare_percentage" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Effective From *</label>
                                <input type="date" name="effective_from" x-model="fare.effective_from" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Effective To *</label>
                                <input type="date" name="effective_to" x-model="fare.effective_to" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" required>
                            </div>
                        </div>
                        <div x-show="fare.ticket_type === 'offer'">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price *</label>
                            <input type="number" name="offer_price" x-model="fare.offer_price" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="with_meal" x-model="fare.with_meal" class="rounded border-slate-300">
                                <span class="text-sm text-slate-700">With Meal</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showFareModal = false" class="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white rounded-md text-sm font-medium transition" x-text="editFareMode ? 'Update' : 'Create'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showRouteModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div x-show="showRouteModal" x-transition.opacity class="fixed inset-0 bg-black bg-opacity-50" @click="showRouteModal = false"></div>
            <div x-show="showRouteModal" x-transition class="relative bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 z-10">
                <h3 class="text-lg font-semibold text-slate-800 mb-4" x-text="editRouteMode ? 'Edit Route' : 'Add Route'"></h3>
                <form method="POST" :action="editRouteMode ? '/routes/' + route.id : '{{ route('routes.store') }}'" id="routeFormModal">
                    @csrf
                    <template x-if="editRouteMode">
                        <input type="hidden" name="_method" value="PUT">
                    </template>
                    
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                            <select name="airline_id" x-model="route.airline_id" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" required>
                                <option value="">Select Airline</option>
                                @foreach($airlines as $airline)
                                    <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                            <select name="route_type" x-model="route.route_type" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" required onchange="toggleRouteFieldsModal()">
                                <option value="">Select</option>
                                <option value="oneway_inbound">Oneway - Inbound</option>
                                <option value="oneway_outbound">Oneway - Outbound</option>
                                <option value="round">Round</option>
                                <option value="multi_city">Multi City</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                            <select name="flight_type" x-model="route.flight_type" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" required onchange="toggleTransitFieldsModal()">
                                <option value="">Select</option>
                                <option value="direct">Direct</option>
                                <option value="transit">Transit</option>
                            </select>
                        </div>
                    </div>

                    <div id="cityGridModal" class="grid gap-4 mt-4">
                        <div id="fromFieldModal" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">From *</label>
                                <select name="from_city_id" x-model="route.from_city_id" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                    <option value="">Select</option>
                                    @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                        <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">To *</label>
                                <select name="to_city_id" x-model="route.to_city_id" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                    <option value="">Select</option>
                                    @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                        <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div id="returnFieldModal" class="hidden">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Return To *</label>
                            <select name="return_city_id" x-model="route.return_city_id" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                <option value="">Select</option>
                                @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                    <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="multiCityFieldsModal" class="hidden mt-4">
                        <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Multi City Segments</h4>
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Inbound From</label>
                                <select name="segments[0][from_city_id]" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                    <option value="">Select</option>
                                    @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                        <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Inbound To</label>
                                <select name="segments[0][to_city_id]" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                    <option value="">Select</option>
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
                                <select name="segments[1][from_city_id]" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                    <option value="">Select</option>
                                    @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                        <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Outbound To</label>
                                <select name="segments[1][to_city_id]" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                    <option value="">Select</option>
                                    @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                        <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <input type="hidden" name="segments[1][segment_direction]" value="outbound">
                    </div>

                    <div id="transitFieldsModal" class="hidden grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Transit City</label>
                            <select name="transits[0][transit_city_id]" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border">
                                <option value="">Select</option>
                                @foreach(\App\Models\CityCode::orderBy('code')->get() as $city)
                                    <option value="{{ $city->id }}">{{ $city->code }} ({{ $city->city_name }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Transit Time</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="transits[0][transit_hours]" min="0" max="23" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" placeholder="HH">
                                <span class="text-slate-500 font-medium">:</span>
                                <input type="number" name="transits[0][transit_minutes]" min="0" max="59" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border" placeholder="MM">
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex items-center gap-4">
                        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition" x-text="editRouteMode ? 'Update Route' : 'Create Route'"></button>
                        <button type="button" @click="showRouteModal = false" class="text-slate-600 hover:text-slate-800 text-sm">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleRouteFieldsModal() {
        const routeType = document.querySelector('select[name="route_type"]').value;
        const fromField = document.getElementById('fromFieldModal');
        const returnField = document.getElementById('returnFieldModal');
        const multiCityFields = document.getElementById('multiCityFieldsModal');
        const cityGrid = document.getElementById('cityGridModal');

        fromField.classList.add('hidden');
        returnField.classList.add('hidden');
        multiCityFields.classList.add('hidden');

        if (routeType === 'oneway_inbound' || routeType === 'oneway_outbound') {
            fromField.classList.remove('hidden');
        } else if (routeType === 'round') {
            fromField.classList.remove('hidden');
            returnField.classList.remove('hidden');
        } else if (routeType === 'multi_city') {
            multiCityFields.classList.remove('hidden');
        }
    }

    function toggleTransitFieldsModal() {
        const flightType = document.querySelector('select[name="flight_type"]').value;
        const transitFields = document.getElementById('transitFieldsModal');

        if (flightType === 'transit') {
            transitFields.classList.remove('hidden');
        } else {
            transitFields.classList.add('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleRouteFieldsModal();
        toggleTransitFieldsModal();
    });
    </script>
</div>
@endsection