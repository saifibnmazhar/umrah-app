@extends('layouts.app')
@section('title', 'Ticket Fares')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Ticket Fares</h1>
        <a href="{{ route('ticket-fares.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
            Add New
        </a>
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

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden mb-6">
        <form method="GET" action="{{ route('ticket-fares.index') }}" class="p-4 flex flex-wrap gap-4 items-end">
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
            <div class="min-w-[150px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                <select name="status" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-600 transition text-sm font-medium">
                    Filter
                </button>
                <a href="{{ route('ticket-fares.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition text-sm font-medium ml-2">
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
                            <th class="px-4 py-3 text-left">Ticket Qty</th>
                            <th class="px-4 py-3 text-left">Created At</th>
                            <th class="px-4 py-3 text-center">Status</th>
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
                                    @if($fare->route->route_type->value === 'multi_city')
                                        @if($fare->route->multiSegments->count() > 0)
                                            {{ $fare->route->multiSegments->first()->fromCity->code ?? '-' }}-{{ $fare->route->multiSegments->first()->toCity->code ?? '-' }} ...
                                        @else
                                            -
                                        @endif
                                    @else
                                        {{ $fare->route->fromCity->code ?? '-' }}-{{ $fare->route->toCity->code ?? '-' }}
                                        @if($fare->route->route_type->value === 'round')
                                            -{{ $fare->route->returnCity->code ?? '-' }}
                                        @endif
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @switch($fare->ticket_type->value)
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
                            </td>
                            <td class="px-4 py-3 text-slate-700 font-medium">@currency($fare->net_fare, 2)</td>
                            <td class="px-4 py-3 text-slate-700 font-medium">@currency($fare->selling_fare, 2)</td>
                            <td class="px-4 py-3 text-slate-600">@if($fare->offer_price)@currency($fare->offer_price, 2)@else-@endif</td>
                            <td class="px-4 py-3 text-slate-600">{{ $fare->effective_from->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $fare->effective_to->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                @if($fare->ticket_type->value === 'group' && $fare->groupTicket)
                                    {{ $fare->groupTicket->ticket_qty }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $fare->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-center">
                                <form method="POST" action="{{ route('ticket-fares.toggle-active', $fare->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $fare->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $fare->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                </form>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $fare->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $fare->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                             <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('ticket-fares.show', $fare->id) }}" class="text-slate-600 hover:text-slate-800 font-medium">View</a>
                                    @if($fare->is_locked)
                                        <span class="text-slate-400 cursor-not-allowed" title="In use by packages or passengers">Edit</span>
                                        <span class="text-red-400 cursor-not-allowed" title="In use by packages or passengers">Delete</span>
                                    @else
                                        <a href="{{ route('ticket-fares.edit', $fare->id) }}" class="text-slate-600 hover:text-slate-800 font-medium">Edit</a>
                                        <form method="POST" action="{{ route('ticket-fares.destroy', $fare->id) }}" onsubmit="return confirm('Are you sure you want to delete this ticket fare?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-4 py-12 text-center text-slate-500">
                                No ticket fares found. <a href="{{ route('ticket-fares.create') }}" class="text-slate-800 underline hover:text-slate-600">Add one?</a>
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
@endsection