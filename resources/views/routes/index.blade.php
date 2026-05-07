@extends('layouts.app')
@section('title', 'Routes')
@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Routes</h1>
        <a href="{{ route('routes.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
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
                                    @case('oneway_inbound')
                                        Oneway - Inbound
                                        @break
                                    @case('oneway_outbound')
                                        Oneway - Outbound
                                        @break
                                    @case('round')
                                        Round
                                        @break
                                    @case('multi_city')
                                        Multi City
                                        @break
                                    @default
                                        {{ $route->route_type->value ?? '-' }}
                                @endswitch
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $route->flight_type->value === 'transit' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($route->flight_type->value ?? '-') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700 font-medium">
                                @if($route->route_type->value === 'multi_city')
                                    @if($route->multiSegments->count() > 0)
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
                                @if($route->flight_type->value === 'transit' && $route->transits->count() > 0)
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
                                    <a href="{{ route('routes.show', $route->id) }}" class="text-slate-600 hover:text-slate-800 font-medium" aria-label="View">View</a>
                                    <a href="{{ route('routes.edit', $route->id) }}" class="text-slate-600 hover:text-slate-800 font-medium" aria-label="Edit">Edit</a>
                                    <form method="POST" action="{{ route('routes.destroy', $route->id) }}" onsubmit="return confirm('Are you sure you want to delete this route?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium" aria-label="Delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-slate-500">
                                No routes found. <a href="{{ route('routes.create') }}" class="text-slate-800 underline hover:text-slate-600">Add one?</a>
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
@endsection