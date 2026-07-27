@extends('layouts.app')
@section('title', 'Package Details')
@section('content')
<div class="max-w-3xl mx-auto pt-6">
    <div class="mb-4 flex gap-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-slate-600 hover:text-slate-800 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Dashboard
        </a>
        @if(auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty())
        <a href="{{ route('settings') }}" class="flex items-center gap-2 text-slate-600 hover:text-slate-800 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Settings
        </a>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-start justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">{{ $package->package_name }}</h2>
                @if($package->is_double_ticket)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-2 bg-blue-100 text-blue-700">Double Ticket</span>
                @else
                    @php
                        $ticketType = $package->ticketFare?->ticket_type?->value ?? 'regular';
                    @endphp
                    @if($ticketType === 'offer')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-2 bg-green-100 text-green-700">Offer</span>
                    @elseif($ticketType === 'group')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-2 bg-purple-100 text-purple-700">Group</span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-2 bg-slate-100 text-slate-700">Regular</span>
                    @endif
                @endif
            </div>
            @if(auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Branch Manager', 'Branch Staff', 'Auditor', 'Visa Admin', 'Visa Staff', 'Ticket Admin', 'Ticket Staff', 'Fingerprint Admin', 'Fingerprint Staff', 'Delivery Staff'])->isNotEmpty())
            <a href="{{ route('bookings.create', ['package_id' => $package->id]) }}" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Booking
            </a>
            @endif
        </div>

        <div class="border-t border-slate-200 pt-6">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Package Information</h3>

            @if($package->is_double_ticket)
                @php
                    $buildRouteDetails = function ($ticketFare) {
                        if (!$ticketFare) return '-';
                        $route = $ticketFare->route;
                        if (!$route) return '-';
                        if ($route->multiSegments && $route->multiSegments->count() > 0) {
                            $routeName = $route->multiSegments->map(
                                fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?')
                            )->implode(', ');
                        } else {
                            $fromCode = $route->fromCity?->code ?? '-';
                            $toCode = $route->toCity?->code ?? '-';
                            $routeName = $fromCode . ' → ' . $toCode;
                        }
                        $airlineName = $ticketFare->airline?->name ?? '-';
                        $className = $ticketFare->airlineClass?->class?->name ?? '-';
                        return $routeName . ' | ' . $airlineName . ' | ' . $className;
                    };
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-500 mb-1">Ticket Details (Inbound)</p>
                        <p class="text-slate-800 font-medium">{{ $buildRouteDetails($package->ticketFareInbound) }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-500 mb-1">Ticket Details (Outbound)</p>
                        <p class="text-slate-800 font-medium">{{ $buildRouteDetails($package->ticketFareOutbound) }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-500 mb-1">Available Tickets</p>
                        <p class="text-slate-800 font-medium">
                            @if($package->ticketFareInbound?->ticket_type?->value === 'group')
                                {{ $package->ticketFareInbound->groupTicket?->ticket_qty ?? 0 }} (Inbound)
                            @endif
                            @if($package->ticketFareOutbound?->ticket_type?->value === 'group')
                                @if($package->ticketFareInbound?->ticket_type?->value === 'group')
                                    /
                                @endif
                                {{ $package->ticketFareOutbound->groupTicket?->ticket_qty ?? 0 }} (Outbound)
                            @endif
                            @if($package->ticketFareInbound?->ticket_type?->value !== 'group' && $package->ticketFareOutbound?->ticket_type?->value !== 'group')
                                -
                            @endif
                        </p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-500 mb-1">Effective From</p>
                        <p class="text-slate-800 font-medium">
                            {{ $package->ticketFareInbound?->effective_from?->format('d M Y') ?? '-' }} (Inbound)<br>
                            {{ $package->ticketFareOutbound?->effective_from?->format('d M Y') ?? '-' }} (Outbound)
                        </p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-500 mb-1">Effective To</p>
                        <p class="text-slate-800 font-medium">
                            {{ $package->ticketFareInbound?->effective_to?->format('d M Y') ?? '-' }} (Inbound)<br>
                            {{ $package->ticketFareOutbound?->effective_to?->format('d M Y') ?? '-' }} (Outbound)
                        </p>
                    </div>
                </div>

                @php
                    $inboundTransits = $package->ticketFareInbound?->route?->transits;
                    $outboundTransits = $package->ticketFareOutbound?->route?->transits;
                    $inboundIsTransit = $package->ticketFareInbound?->route?->flight_type?->value === 'transit';
                    $outboundIsTransit = $package->ticketFareOutbound?->route?->flight_type?->value === 'transit';
                @endphp
                @if(($inboundIsTransit && $inboundTransits && $inboundTransits->count() > 0) || ($outboundIsTransit && $outboundTransits && $outboundTransits->count() > 0))
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        @if($inboundIsTransit && $inboundTransits && $inboundTransits->count() > 0)
                            @foreach($inboundTransits as $transit)
                                @php
                                    $cityName = $transit->transitCity?->city_name ?? '-';
                                    $minutes = $transit->transit_time ?? 0;
                                    $hours = intdiv($minutes, 60);
                                    $mins = $minutes % 60;
                                    $timeDisplay = $hours > 0 ? $hours . 'h ' . $mins . 'm' : $mins . 'm';
                                @endphp
                                <div class="bg-slate-50 rounded-lg p-4">
                                    <p class="text-sm text-slate-500 mb-1">Transit (Inbound)</p>
                                    <p class="text-slate-800 font-medium">{{ $cityName }} · {{ $timeDisplay }}</p>
                                </div>
                            @endforeach
                        @endif
                        @if($outboundIsTransit && $outboundTransits && $outboundTransits->count() > 0)
                            @foreach($outboundTransits as $transit)
                                @php
                                    $cityName = $transit->transitCity?->city_name ?? '-';
                                    $minutes = $transit->transit_time ?? 0;
                                    $hours = intdiv($minutes, 60);
                                    $mins = $minutes % 60;
                                    $timeDisplay = $hours > 0 ? $hours . 'h ' . $mins . 'm' : $mins . 'm';
                                @endphp
                                <div class="bg-slate-50 rounded-lg p-4">
                                    <p class="text-sm text-slate-500 mb-1">Transit (Outbound)</p>
                                    <p class="text-slate-800 font-medium">{{ $cityName }} · {{ $timeDisplay }}</p>
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endif
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-500 mb-1">Ticket Details</p>
                        @php
                            $route = $package->ticketFare?->route;
                            if ($route && $route->multiSegments && $route->multiSegments->count() > 0) {
                                $routeName = $route->multiSegments->map(
                                    fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?')
                                )->implode(', ');
                            } elseif ($route) {
                                $fromCode = $route->fromCity?->code ?? '-';
                                $toCode = $route->toCity?->code ?? '-';
                                if ($route->returnCity) {
                                    $returnCode = $route->returnCity?->code ?? '-';
                                    $routeName = $fromCode . '-' . $toCode . '-' . $returnCode;
                                } else {
                                    $routeName = $fromCode . ' → ' . $toCode;
                                }
                            } else {
                                $routeName = '-';
                            }
                            $airlineName = $package->ticketFare?->airline?->name ?? '-';
                            $className = $package->ticketFare?->airlineClass?->class?->name ?? '-';
                            $ticketDetails = $route ? ($routeName . ' | ' . $airlineName . ' | ' . $className) : '-';
                        @endphp
                        <p class="text-slate-800 font-medium">{{ $ticketDetails }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-500 mb-1">Available Tickets</p>
                        <p class="text-slate-800 font-medium">{{ $ticketType === 'group' ? ($package->ticketFare?->groupTicket?->ticket_qty ?? 0) . ' tickets' : '-' }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-500 mb-1">Effective From</p>
                        <p class="text-slate-800 font-medium">{{ $package->ticketFare?->effective_from?->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-500 mb-1">Effective To</p>
                        <p class="text-slate-800 font-medium">{{ $package->ticketFare?->effective_to?->format('d M Y') ?? '-' }}</p>
                    </div>
                </div>

                @php
                    $transits = $route?->transits;
                @endphp
                @if($route && $route->flight_type?->value === 'transit' && $transits && $transits->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        @foreach($transits as $transit)
                            @php
                                $cityName = $transit->transitCity?->city_name ?? '-';
                                $minutes = $transit->transit_time ?? 0;
                                $hours = intdiv($minutes, 60);
                                $mins = $minutes % 60;
                                $timeDisplay = $hours > 0 ? $hours . 'h ' . $mins . 'm' : $mins . 'm';
                                $direction = ucfirst($transit->route_direction?->value ?? 'Transit');
                            @endphp
                            <div class="bg-slate-50 rounded-lg p-4">
                                <p class="text-sm text-slate-500 mb-1">{{ $direction }} Transit</p>
                                <p class="text-slate-800 font-medium">{{ $cityName }} · {{ $timeDisplay }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

        <div class="border-t border-slate-200 pt-6 mt-6">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Pricing</h3>
            <div class="bg-slate-50 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500 mb-1">Regular Price</p>
                        <p class="text-2xl font-bold text-slate-800">@currency(($package->regular_price ?? 0) + ($package->service_charge ?? 0), 0)</p>
                    </div>
                    @if($package->offer_price > 0)
                        <div class="text-right">
                            <p class="text-sm text-slate-500 mb-1">Offer Price</p>
                            <p class="text-2xl font-bold text-emerald-600">@currency(($package->offer_price ?? 0) + ($package->service_charge ?? 0), 0)</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
