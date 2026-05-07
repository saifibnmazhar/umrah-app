@extends('layouts.app')
@section('title', 'Route Details')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('routes.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Routes
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Route Details</h1>

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <div class="grid grid-cols-2 gap-6">
            <div>
                <span class="text-sm text-slate-500">Airline</span>
                <p class="text-slate-800 font-medium">{{ $route->airline->name ?? '-' }}</p>
            </div>
            <div>
                <span class="text-sm text-slate-500">Route Type</span>
                <p class="text-slate-800 font-medium">
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
                            {{ $route->route_type->value }}
                    @endswitch
                </p>
            </div>
            <div>
                <span class="text-sm text-slate-500">Flight Type</span>
                <p class="text-slate-800 font-medium">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $route->flight_type->value === 'transit' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ ucfirst($route->flight_type->value) }}
                    </span>
                </p>
            </div>
            <div>
                <span class="text-sm text-slate-500">Route</span>
                <p class="text-slate-800 font-medium">
                    @if($route->route_type->value === 'multi_city')
                        @if($route->multiSegments->count() > 0)
                            @foreach($route->multiSegments as $segment)
                                <span class="block">{{ $segment->fromCity->code ?? '-' }}-{{ $segment->toCity->code ?? '-' }} ({{ $segment->segment_direction->value }})</span>
                            @endforeach
                        @else
                            -
                        @endif
                    @else
                        {{ $route->fromCity->code ?? '-' }}-{{ $route->toCity->code ?? '-' }}
                        @if($route->route_type->value === 'round')
                            -{{ $route->returnCity->code ?? '-' }}
                        @endif
                    @endif
                </p>
            </div>
            @if($route->flight_type->value === 'transit' && $route->transits->count() > 0)
            <div>
                <span class="text-sm text-slate-500">Transit Info</span>
                @foreach($route->transits as $transit)
                    @php
                        $hours = floor($transit->transit_time / 60);
                        $minutes = $transit->transit_time % 60;
                    @endphp
                    <p class="text-slate-800">{{ $transit->transitCity->code ?? '-' }} - {{ str_pad($hours, 2, '0', STR_PAD_LEFT) }}:{{ str_pad($minutes, 2, '0', STR_PAD_LEFT) }}</p>
                @endforeach
            </div>
            @endif
            <div>
                <span class="text-sm text-slate-500">Created At</span>
                <p class="text-slate-600">{{ $route->created_at->format('M d, Y H:i') }}</p>
            </div>
            <div>
                <span class="text-sm text-slate-500">Updated At</span>
                <p class="text-slate-600">{{ $route->updated_at->format('M d, Y H:i') }}</p>
            </div>
        </div>

        <div class="mt-6 pt-6 border-t border-slate-200 flex items-center gap-4">
            <a href="{{ route('routes.edit', $route->id) }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Edit
            </a>
            <form method="POST" action="{{ route('routes.destroy', $route->id) }}" onsubmit="return confirm('Are you sure you want to delete this route?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 border border-red-500 text-red-600 rounded-md hover:bg-red-50 transition">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>
@endsection