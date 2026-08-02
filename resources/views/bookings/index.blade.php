@extends('layouts.app')
@section('title', 'Booking')
@section('content')
@php
$costService = app(\App\Services\CostTrackingService::class);
$bookingCostCache = [];
$passengerTotalCostMap = ($passengers ?? collect())->getCollection()
    ->keyBy('id')
    ->map(function ($p) use ($costService, &$bookingCostCache) {
        $booking = $p->booking;
        if (! $booking) return 0;
        $bid = $booking->id;
        if (! isset($bookingCostCache[$bid])) {
            $bookingCostCache[$bid] = $costService->getPassengerCosts($booking)->keyBy('passenger_id');
        }
        $c = $bookingCostCache[$bid]->get($p->id);
        return (float) ($c['total_cost'] ?? 0);
    })
    ->all();

$passengersVisaData = ($passengers ?? collect())->map(function($p) {
    $rate = $p->booking?->currencyRate?->rate
        ?? app(\App\Services\CurrencyRateService::class)->getRateForDate($p->booking?->created_at)?->rate
        ?? 0;
    return [
        'id' => $p->id,
        'booking_id' => $p->booking_id,
        'rate' => $rate,
        'visa' => $p->visaSubmission ? [
            'id' => $p->visaSubmission->id,
            'agent_id' => $p->visaSubmission->visa_agent_id,
            'agent' => $p->visaSubmission?->visaAgent?->name ?? '',
            'visa_number' => $p->visaSubmission?->visa_number ?? '',
            'selling_price' => (float)($p->visaSubmission?->visaSellingPrice?->selling_price ?? 0),
            'agent_commission' => (float)($p->visaSubmission?->agent_commission ?? 0),
            'net_visa_cost' => (float)($p->visaSubmission?->net_visa_cost ?? 0),
            'additional_cost' => (float)($p->visaSubmission?->additional_cost ?? 0),
            'remarks' => $p->visaSubmission?->remarks ?? '',
            'final_cost' => (float)($p->visaSubmission?->final_cost ?? 0),
            'commission_agent_id' => $p->visaSubmission?->commission_agent_id,
            'commission_agent' => $p->visaSubmission?->commissionAgent?->name ?? '',
            'status' => $p->visaSubmission->status?->value ?? 'pending',
        ] : null,
    ];
})->values();

$packagesList = \App\Models\Package::select('id', 'package_name')
    ->get()
    ->unique('package_name')
    ->values();

$flightDateRanges = [];
$today = (int) now()->format('d');
$currentThird = $today <= 10 ? 1 : ($today <= 20 ? 2 : 3);

$months = [
    ['offset' => 0, 'startPart' => $currentThird],
    ['offset' => 1, 'startPart' => 1],
    ['offset' => 2, 'startPart' => 1],
    ['offset' => 3, 'startPart' => 1],
];

foreach ($months as $m) {
    if (count($flightDateRanges) >= 9) break;
    $month = now()->copy()->addMonths($m['offset'])->startOfMonth();
    $lastDay = (int) $month->copy()->endOfMonth()->format('d');
    $label = $month->format('M');

    $parts = [
        1 => ['start' => $month->format('Y-m-01'), 'end' => $month->format('Y-m-10'), 'label' => "{$label} 1–10"],
        2 => ['start' => $month->format('Y-m-11'), 'end' => $month->format('Y-m-20'), 'label' => "{$label} 11–20"],
        3 => ['start' => $month->format('Y-m-21'), 'end' => $month->copy()->endOfMonth()->format('Y-m-d'), 'label' => "{$label} 21–{$lastDay}"],
    ];

    for ($p = $m['startPart']; $p <= 3; $p++) {
        if (count($flightDateRanges) >= 9) break;
        $flightDateRanges[] = array_merge(['id' => count($flightDateRanges) + 1], $parts[$p]);
    }
}

$airlinesList = \App\Models\Airline::with('travelClasses')->get()->map(fn($a) => [
    'id' => $a->id,
    'name' => $a->name,
    'class_ids' => $a->travelClasses->pluck('id'),
])->values();

$classesList = \App\Models\TravelClass::all()->map(fn($c) => [
    'id' => $c->id,
    'name' => $c->name,
])->values();

$activeFares = \App\Models\TicketFare::where('is_active', true)->with([
    'route.fromCity', 'route.toCity', 'route.returnCity',
    'route.multiSegments.fromCity', 'route.multiSegments.toCity',
    'airline', 'airlineClass.class', 'groupTicket', 'baggageAllowances',
])->get();

$inactiveFareIds = \App\Models\Passenger::whereNotNull('ticket_fare_id')
    ->whereHas('ticketFare', fn($q) => $q->where('is_active', false))
    ->pluck('ticket_fare_id')
    ->unique();

$inactiveFares = \App\Models\TicketFare::whereIn('id', $inactiveFareIds)->with([
    'route.fromCity', 'route.toCity', 'route.returnCity',
    'route.multiSegments.fromCity', 'route.multiSegments.toCity',
    'airline', 'airlineClass.class', 'groupTicket', 'baggageAllowances',
])->get();

$ticketFaresList = $activeFares->merge($inactiveFares)->map(fn($fare) => [
    'id' => $fare->id,
    'route' => match($fare->route->route_type?->value) {
        'multi_city' => $fare->route->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', '),
        'round' => ($fare->route->fromCity?->code ?? '?') . '-' . ($fare->route->toCity?->code ?? '?') . '-' . ($fare->route->returnCity?->code ?? '?'),
        default => ($fare->route->fromCity?->code ?? '?') . '-' . ($fare->route->toCity?->code ?? '?'),
    },
    'airline' => $fare->airline->name,
    'airline_class' => $fare->airlineClass->class?->name ?? '',
    'ticket_type' => $fare->ticket_type?->value ?? 'regular',
    'route_id' => $fare->route_id,
    'airline_id' => $fare->airline_id,
    'airline_classes_id' => $fare->airline_classes_id,
    'route_type' => $fare->route->route_type?->value,
    'flight_type' => $fare->route->flight_type?->value,
    'group_ticket_id' => $fare->groupTicket?->id ?? null,
    'pnr' => $fare->groupTicket?->pnr ?? '',
    'inbound_date' => $fare->groupTicket?->inbound_date?->format('Y-m-d') ?? '',
    'outbound_date' => $fare->groupTicket?->outbound_date?->format('Y-m-d') ?? '',
    'ticket_qty' => $fare->groupTicket?->ticket_qty ?? null,
    'is_refundable' => $fare->groupTicket?->is_refundable ?? null,
    'is_exchangable' => $fare->groupTicket?->is_exchangable ?? null,
    'selling_fare' => (float)($fare->selling_fare ?? 0),
    'net_fare' => (float)($fare->net_fare ?? 0),
    'offer_price' => $fare->ticket_type?->value === 'offer' ? (float)($fare->offer_price ?? 0) : null,
    'child_fare_percentage' => (float)($fare->child_fare_percentage ?? 70),
    'infant_fare_percentage' => (float)($fare->infant_fare_percentage ?? 30),
    'is_active' => $fare->is_active,
    'baggage_allowances' => $fare->baggageAllowances->map(fn($b) => [
        'passenger_type' => $b->passenger_type,
        'travel_direction' => $b->travel_direction,
        'allowance' => $b->allowance,
    ])->values()->toArray(),
])->values();

$passengersTicketData = ($passengers ?? collect())->map(fn($p) => [
    'id' => $p->id,
    'booking_id' => $p->booking_id,
    'booking_date' => $p->booking?->created_at?->format('Y-m-d') ?? '',
    'invoice_no' => $p->booking?->invoice_id ?? '',
    'passenger_name' => trim($p->first_name . ' ' . $p->last_name),
    'passport' => $p->passport_no ?? '',
    'route' => $p->route_display ?? '',
    'airline' => $p->ticketFare?->airline?->name ?? $p->booking?->package?->ticketFare?->airline?->name ?? '',
    'travel_class' => $p->ticketFare?->airlineClass?->class?->name ?? $p->booking?->package?->ticketFare?->airlineClass?->class?->name ?? '',
    'passenger_type' => $p->passenger_type?->value ?? 'adult',
    'mobile_no' => $p->mobile_no ?? '',
    'guardian' => '',

    'is_ticket_held' => (bool)($p->is_ticket_held ?? false),
    'ticket_status' => $p->allIssuedTickets
        ->sortByDesc('id')
        ->first()?->status ?? null,
    'ticket_remarks' => $p->ticket_remarks ?? '',
    'due' => $p->booking?->invoice?->balance ?? 0,
    'required_flight_date' => $p->flight_date_from?->format('Y-m-d') ?? '',
    'actual_flight_date' => $p->actual_flight_date?->format('Y-m-d') ?? '',
    'fingerprint_location' => $p->booking?->fingerprint_location?->value ?? 'None',
    'fingerprint_status' => $p->fingerprintDetail?->status?->value ?? null,
    'status' => $p->status?->name ?? 'None',
    'is_cancelled' => $p->booking?->is_cancelled ?? false,
    'fingerprint_cost' => (function() use ($p) {
        $fpCost = $p->booking->fingerprint?->cost ?? 0;
        $paxCount = max($p->booking->passengers->count(), 1);
        return $fpCost > 0 ? round($fpCost / $paxCount, 6) : 0;
    })(),
    'documents' => [],
    'passenger_data' => null,
    'ticket_fare_inbound_id' => $p->ticket_fare_inbound_id,
    'ticket_fare_outbound_id' => $p->ticket_fare_outbound_id,
    'is_double_ticket' => !is_null($p->ticket_fare_inbound_id),
    'package_is_double_ticket' => $p->booking?->package?->is_double_ticket ?? false,

    'inbound_ticket_fare' => $p->ticketFareInbound ? [
        'id' => $p->ticketFareInbound->id,
        'ticket_type' => $p->ticketFareInbound->ticket_type?->value ?? 'regular',
        'flight_type' => $p->ticketFareInbound->route?->flight_type?->value,
        'route_display' => (function() use ($p) {
            $r = $p->ticketFareInbound->route;
            if (!$r) return '';
            if ($r->route_type?->value === 'multi_city') {
                return $r->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
            }
            $from = $r->fromCity?->code ?? '?';
            $to = $r->toCity?->code ?? '?';
            $return = $r->returnCity?->code ?? '';
            return ($r->route_type?->value === 'round' && $return) ? "{$from}-{$to}-{$return}" : "{$from}-{$to}";
        })(),
        'airline' => $p->ticketFareInbound->airline?->name ?? '',
        'travel_class' => $p->ticketFareInbound->airlineClass?->class?->name ?? '',
        'selling_fare' => (float)($p->ticketFareInbound->selling_fare ?? 0),
        'net_fare' => (float)($p->ticketFareInbound->net_fare ?? 0),
        'child_fare_percentage' => (float)($p->ticketFareInbound->child_fare_percentage ?? 70),
        'infant_fare_percentage' => (float)($p->ticketFareInbound->infant_fare_percentage ?? 30),
        'offer_price' => $p->ticketFareInbound->ticket_type?->value === 'offer' ? (float)($p->ticketFareInbound->offer_price ?? 0) : null,
        'with_offer' => (bool)($p->ticketFareInbound->ticket_type?->value === 'offer'),
        'baggage_inbound' => $p->ticketFareInbound->baggageAllowances
            ->filter(fn($ba) => $ba->travel_direction?->value === 'inbound' && $ba->passenger_type?->value === ($p->passenger_type?->value ?? 'adult'))
            ->first()?->allowance ?? '',
    ] : null,

    'outbound_ticket_fare' => $p->ticketFareOutbound ? [
        'id' => $p->ticketFareOutbound->id,
        'ticket_type' => $p->ticketFareOutbound->ticket_type?->value ?? 'regular',
        'flight_type' => $p->ticketFareOutbound->route?->flight_type?->value,
        'route_display' => (function() use ($p) {
            $r = $p->ticketFareOutbound->route;
            if (!$r) return '';
            if ($r->route_type?->value === 'multi_city') {
                return $r->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
            }
            $from = $r->fromCity?->code ?? '?';
            $to = $r->toCity?->code ?? '?';
            $return = $r->returnCity?->code ?? '';
            return ($r->route_type?->value === 'round' && $return) ? "{$from}-{$to}-{$return}" : "{$from}-{$to}";
        })(),
        'airline' => $p->ticketFareOutbound->airline?->name ?? '',
        'travel_class' => $p->ticketFareOutbound->airlineClass?->class?->name ?? '',
        'selling_fare' => (float)($p->ticketFareOutbound->selling_fare ?? 0),
        'net_fare' => (float)($p->ticketFareOutbound->net_fare ?? 0),
        'child_fare_percentage' => (float)($p->ticketFareOutbound->child_fare_percentage ?? 70),
        'infant_fare_percentage' => (float)($p->ticketFareOutbound->infant_fare_percentage ?? 30),
        'offer_price' => $p->ticketFareOutbound->ticket_type?->value === 'offer' ? (float)($p->ticketFareOutbound->offer_price ?? 0) : null,
        'with_offer' => (bool)($p->ticketFareOutbound->ticket_type?->value === 'offer'),
        'baggage_outbound' => $p->ticketFareOutbound->baggageAllowances
            ->filter(fn($ba) => $ba->travel_direction?->value === 'outbound' && $ba->passenger_type?->value === ($p->passenger_type?->value ?? 'adult'))
            ->first()?->allowance ?? '',
    ] : null,

    'ticket_fare' => $p->ticketFare ? [
        'ticket_type' => $p->ticketFare->ticket_type?->value ?? 'regular',
        'route_type' => match($p->ticketFare?->route?->route_type?->value) {
            'oneway_inbound' => 'One Way-Inbound',
            'oneway_outbound' => 'One Way-Outbound',
            'round' => 'Round',
            'multi_city' => 'Multi City',
            default => '',
        },
        'flight_type' => match($p->ticketFare?->route?->flight_type?->value) {
            'direct' => 'Direct',
            'transit' => 'Transit',
            default => '',
        },
        'group_ticket_id' => $p->ticketFare->groupTicket?->id ?? null,
        'inbound_date' => $p->ticketFare->groupTicket?->inbound_date ?? '',
        'outbound_date' => $p->ticketFare->groupTicket?->outbound_date ?? '',
        'pnr' => $p->ticketFare->groupTicket?->pnr ?? '',
        'ticket_number' => '',
        'date' => '',
        'ticket_agent' => '',
        'ticket_fare_id' => $p->ticketFare->id,
        'airline_id' => $p->ticketFare->airline_id,
        'airline_classes_id' => $p->ticketFare->airline_classes_id,
        'route_id' => $p->ticketFare->route_id,
        'selling_fare' => (float)($p->ticketFare->selling_fare ?? 0),
        'net_fare' => (float)($p->ticketFare->net_fare ?? 0),
        'child_fare_percentage' => (float)($p->ticketFare->child_fare_percentage ?? 70),
        'infant_fare_percentage' => (float)($p->ticketFare->infant_fare_percentage ?? 30),
        'offer_price' => $p->ticketFare->ticket_type?->value === 'offer' ? (float)($p->ticketFare->offer_price ?? 0) : null,
        'with_offer' => (bool)($p->ticketFare->ticket_type?->value === 'offer'),
        'refundable' => false,
        'non_refundable' => false,
        'non_exchangeable' => false,
        'baggage_inbound' => '',
        'baggage_outbound' => '',
        'outbound_pending' => false,
        'issue_type' => null,
        'issued_ticket_id' => null,
        'baggage_allowances' => $p->ticketFare?->baggageAllowances->map(fn($b) => [
            'passenger_type' => $b->passenger_type,
            'travel_direction' => $b->travel_direction,
            'allowance' => $b->allowance,
        ]) ?? [],
    ] : null,

    'latest_issued_ticket' => ($lit = $p->allIssuedTickets
        ->filter(fn($t) => is_null($t->issue_type) || $t->issue_type === 'regular')
        ->sortByDesc('id')
        ->first()) ? [
        'id' => $lit->id,
        'ticket_number' => $lit->ticket_number ?? '',
        'pnr' => $lit->pnr ?? '',
        'ticket_agent_id' => $lit->ticket_agent_id,
        'ticket_agent_name' => $lit->ticketAgent?->name ?? '',
        'ticket_fare_id' => $lit->ticket_fare_id,
        'group_ticket_id' => $lit->group_ticket_id,
        'issued_date' => $lit->issued_date?->format('Y-m-d') ?? '',
        'inbound_date' => $lit->inbound_date?->format('Y-m-d') ?? '',
        'outbound_date' => $lit->outbound_date?->format('Y-m-d') ?? '',
                    'selling_fare' => (float)($lit->selling_fare ?? 0),
                        'net_fare' => (float)($lit->net_fare ?? 0),
                        'offer_price' => (float)($lit->offer_price ?? 0),
        'is_refundable' => $lit->is_refundable ?? false,
        'is_exchangeable' => $lit->is_exchangeable ?? false,
        'baggage_inbound' => $lit->baggage_inbound ?? '',
        'baggage_outbound' => $lit->baggage_outbound ?? '',
        'outbound_pending' => $lit->outbound_pending ?? false,
        'issue_type' => $lit->issue_type,
        'status' => $lit->status,
        'airline' => $lit->ticketFare?->airline?->name ?? '',
        'travel_class' => $lit->ticketFare?->airlineClass?->class?->name ?? '',
        'route' => $lit->ticketFare?->route ? (function() use ($lit) {
            $r = $lit->ticketFare->route;
            $rt = $r->route_type?->value;
            if ($rt === 'multi_city') {
                return $r->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
            }
            $from = $r->fromCity?->code ?? '?';
            $to = $r->toCity?->code ?? '?';
            $return = $r->returnCity?->code ?? '';
            return ($rt === 'round' && $return) ? "{$from}-{$to}-{$return}" : "{$from}-{$to}";
        })() : '',
        'route_type' => $lit->ticketFare?->route?->route_type?->value,
    ] : null,

    'all_issued_tickets' => $p->allIssuedTickets->map(fn($t) => [
        'id' => $t->id,
        'ticket_number' => $t->ticket_number ?? '',
        'issued_date' => $t->issued_date?->format('Y-m-d') ?? '',
        'inbound_date' => $t->inbound_date?->format('Y-m-d') ?? '',
        'outbound_date' => $t->outbound_date?->format('Y-m-d') ?? '',
        'selling_fare' => (float)($t->selling_fare ?? 0),
        'net_fare' => (float)($t->net_fare ?? 0),
        'pnr' => $t->pnr ?? '',
        'status' => $t->status,
        'issue_type' => $t->issue_type,
        'is_refundable' => $t->is_refundable ?? false,
        'is_exchangeable' => $t->is_exchangeable ?? false,
        'baggage_inbound' => $t->baggage_inbound ?? '',
        'baggage_outbound' => $t->baggage_outbound ?? '',
        'airline' => $t->ticketFare?->airline?->name ?? '',
        'travel_class' => $t->ticketFare?->airlineClass?->class?->name ?? '',
        'route' => $t->ticketFare?->route ? (function() use ($t) {
            $r = $t->ticketFare->route;
            $rt = $r->route_type?->value;
            if ($rt === 'multi_city') {
                return $r->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
            }
            $from = $r->fromCity?->code ?? '?';
            $to = $r->toCity?->code ?? '?';
            $return = $r->returnCity?->code ?? '';
            return ($rt === 'round' && $return) ? "{$from}-{$to}-{$return}" : "{$from}-{$to}";
        })() : '',
        'route_type' => $t->ticketFare?->route?->route_type?->value,
        'ticket_agent_name' => $t->ticketAgent?->name ?? '',
        'issuer_name' => $t->issuer?->name ?? '',
    ])->values(),
    'pending_outbound_issued_ticket' => ($poit = $p->allIssuedTickets
        ->first(fn($t) => $t->issue_type === 'pending_outbound')) ? [
        'id' => $poit->id,
        'ticket_number' => $poit->ticket_number ?? '',
        'pnr' => $poit->pnr ?? '',
        'ticket_agent_id' => $poit->ticket_agent_id,
        'ticket_agent_name' => $poit->ticketAgent?->name ?? '',
        'ticket_fare_id' => $poit->ticket_fare_id,
        'ticket_type' => $poit->ticketFare?->ticket_type?->value ?? '',
        'flight_type' => $poit->ticketFare?->route?->flight_type?->value ?? '',
        'route_display' => $poit->ticketFare?->route ? (function() use ($poit) {
            $r = $poit->ticketFare->route;
            $rt = $r->route_type?->value;
            if ($rt === 'multi_city') {
                return $r->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
            }
            $from = $r->fromCity?->code ?? '?';
            $to = $r->toCity?->code ?? '?';
            $return = $r->returnCity?->code ?? '';
            return ($rt === 'round' && $return) ? "{$from}-{$to}-{$return}" : "{$from}-{$to}";
        })() : '',
        'airline' => $poit->ticketFare?->airline?->name ?? '',
        'travel_class' => $poit->ticketFare?->airlineClass?->class?->name ?? '',
        'issued_date' => $poit->issued_date?->format('Y-m-d') ?? '',
        'outbound_date' => $poit->outbound_date?->format('Y-m-d') ?? '',
        'selling_fare' => (float)($poit->selling_fare ?? 0),
        'net_fare' => (float)($poit->net_fare ?? 0),
        'offer_price' => (float)($poit->offer_price ?? 0),
        'is_refundable' => $poit->is_refundable ?? false,
        'is_exchangeable' => $poit->is_exchangeable ?? false,
        'baggage_outbound' => $poit->baggage_outbound ?? '',
        'status' => $poit->status,
    ] : null,
    'inbound_ticket_fare' => ($inFare = $p->ticketFareInbound) ? (function() use ($inFare) {
        $inRoute = $inFare->route;
        $inRouteDisplay = '—';
        if ($inRoute) {
            $inRouteType = $inRoute->route_type?->value;
            if ($inRouteType === 'multi_city') {
                $inRouteDisplay = $inRoute->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
            } elseif ($inRouteType === 'round') {
                $inRouteDisplay = ($inRoute->fromCity?->code ?? '?') . '-' . ($inRoute->toCity?->code ?? '?') . '-' . ($inRoute->returnCity?->code ?? '?');
            } else {
                $inRouteDisplay = ($inRoute->fromCity?->code ?? '?') . ' → ' . ($inRoute->toCity?->code ?? '?');
            }
        }
        return [
            'id' => $inFare->id,
            'route_display' => $inRouteDisplay,
            'airline' => $inFare->airline?->name ?? '',
            'travel_class' => $inFare->airlineClass?->class?->name ?? '',
            'ticket_type' => $inFare->ticket_type?->value ?? 'regular',
            'flight_type' => $inFare->route?->flight_type?->value ?? '',
            'selling_fare' => (float)($inFare->selling_fare ?? 0),
            'net_fare' => (float)($inFare->net_fare ?? 0),
            'offer_price' => $inFare->ticket_type?->value === 'offer' ? (float)($inFare->offer_price ?? 0) : null,
            'with_offer' => (bool)($inFare->ticket_type?->value === 'offer'),
            'child_fare_percentage' => (float)($inFare->child_fare_percentage ?? 70),
            'infant_fare_percentage' => (float)($inFare->infant_fare_percentage ?? 30),
            'baggage_inbound' => '',
            'baggage_outbound' => '',
        ];
    })() : null,
    'outbound_ticket_fare' => ($outFare = $p->ticketFareOutbound) ? (function() use ($outFare) {
        $outRoute = $outFare->route;
        $outRouteDisplay = '—';
        if ($outRoute) {
            $outRouteType = $outRoute->route_type?->value;
            if ($outRouteType === 'multi_city') {
                $outRouteDisplay = $outRoute->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
            } elseif ($outRouteType === 'round') {
                $outRouteDisplay = ($outRoute->fromCity?->code ?? '?') . '-' . ($outRoute->toCity?->code ?? '?') . '-' . ($outRoute->returnCity?->code ?? '?');
            } else {
                $outRouteDisplay = ($outRoute->fromCity?->code ?? '?') . ' → ' . ($outRoute->toCity?->code ?? '?');
            }
        }
        return [
            'id' => $outFare->id,
            'route_display' => $outRouteDisplay,
            'airline' => $outFare->airline?->name ?? '',
            'travel_class' => $outFare->airlineClass?->class?->name ?? '',
            'ticket_type' => $outFare->ticket_type?->value ?? 'regular',
            'flight_type' => $outFare->route?->flight_type?->value ?? '',
            'selling_fare' => (float)($outFare->selling_fare ?? 0),
            'net_fare' => (float)($outFare->net_fare ?? 0),
            'offer_price' => $outFare->ticket_type?->value === 'offer' ? (float)($outFare->offer_price ?? 0) : null,
            'with_offer' => (bool)($outFare->ticket_type?->value === 'offer'),
            'child_fare_percentage' => (float)($outFare->child_fare_percentage ?? 70),
            'infant_fare_percentage' => (float)($outFare->infant_fare_percentage ?? 30),
            'baggage_inbound' => '',
            'baggage_outbound' => '',
        ];
    })() : null,
    'total_cost' => $passengerTotalCostMap[$p->id] ?? 0,
])->values();
@endphp
<div class="w-full mx-auto" x-data="bookingIndexApp()">
    <div class="flex justify-between items-center mb-6">
        @php
            $canCreateBooking = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Branch Manager', 'Branch Staff', 'Auditor', 'Visa Admin', 'Visa Staff', 'Ticket Admin', 'Ticket Staff', 'Fingerprint Admin', 'Fingerprint Staff', 'Delivery Staff'])->isNotEmpty();
            $canViewFinancialColumns = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Auditor'])->isNotEmpty();
            $canViewVisaColumns = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Visa Admin', 'Visa Staff'])->isNotEmpty();
            $canViewTicketFareColumn = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Ticket Admin', 'Ticket Staff'])->isNotEmpty();
            $canViewTicketAgentColumn = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Ticket Admin', 'Ticket Staff'])->isNotEmpty();
            $canEditInline = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Delivery Staff'])->isNotEmpty();
            $canEditFingerprintLocation = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Fingerprint Admin', 'Delivery Staff'])->isNotEmpty();
            $canDeleteBooking = auth()->user()->roles->pluck('name')->intersect(['Super Admin'])->isNotEmpty();
            $canViewActionColumn = true;
            $canViewPassengerIndex = true;
        @endphp
        <h1 class="text-2xl font-bold text-slate-800">Booking</h1>
        @if($canCreateBooking)
        <a href="{{ route('bookings.create') }}" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Booking
        </a>
        @endif
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ session('error') }}
    </div>
    @endif

    <div class="flex gap-2 mb-4">
        <button @click="navigateToTab('booking')" :class="activeTab === 'booking' ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'" class="px-4 py-2 rounded-lg font-medium transition">Booking Index</button>
        @if($canViewPassengerIndex)<button @click="navigateToTab('passenger')" :class="activeTab === 'passenger' ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'" class="px-4 py-2 rounded-lg font-medium transition">Passenger Index</button>@endif
    </div>

    <div x-show="activeTab === 'booking'" x-cloak>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="mb-4 flex flex-wrap items-center gap-4">
                <input type="text" x-model="searchTerm" x-ref="searchInput" class="w-full md:w-64 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition" placeholder="Search by Mobile or Invoice No...">
                <input type="date" x-model="selectedBookingDateFrom" @change="onBookingDateFromChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                <input type="date" x-model="selectedBookingDateTo" @change="onBookingDateToChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                <select x-model="selectedFingerprintLocation" @change="onFingerprintLocationChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">Fingerprint Location</option>
                    @foreach($fingerprintLocations as $location)
                    <option value="{{ $location->value }}" {{ $selectedFingerprintLocation === $location->value ? 'selected' : '' }}>{{ ucfirst($location->value) }}</option>
                    @endforeach
                </select>
                <select x-model="selectedBookingStatus" @change="onBookingStatusChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">Booking Status</option>
                    <option value="active" {{ $selectedBookingStatus === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="cancellation_processing" {{ $selectedBookingStatus === 'cancellation_processing' ? 'selected' : '' }}>Cancellation Processing</option>
                    <option value="cancelled" {{ $selectedBookingStatus === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
                @unless(auth()->user()->branch_id)
                <select
                    x-model="selectedBranchId"
                    @change="onBranchChange"
                    class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">All Branches</option>
                    @foreach($bookingBranches as $branch)
                    <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @endunless
                <button @click="clearBookingFilters" class="px-3 py-2 border border-slate-300 rounded-lg hover:bg-slate-100 text-slate-600 transition text-sm">Clear</button>
                <span class="flex-1 min-w-0"></span>
                <span class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 text-white font-semibold rounded-lg whitespace-nowrap shadow-sm" x-text="'Total Booking - ' + totalBookingCount">Total Booking - {{ $totalBookingCount }}</span>
                <span class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 text-white font-semibold rounded-lg whitespace-nowrap shadow-sm" x-text="'Total Passenger - ' + totalBookingPassengerCount">Total Passenger - {{ $totalBookingPassengerCount }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                            <th class="px-3 py-2 text-left font-medium">Booking Date</th>
                            <th class="px-3 py-2 text-left font-medium">Customer</th>
                            <th class="px-3 py-2 text-left font-medium">Mobile</th>
                            <th class="px-3 py-2 text-left font-medium">Passengers</th>
                            <th class="px-3 py-2 text-left font-medium">Fingerprint Location</th>
                            <th class="px-3 py-2 text-left font-medium">Booking Branch</th>
                            <th class="px-3 py-2 text-left font-medium">Fingerprint Branch</th>
                            <th class="px-3 py-2 text-left font-medium">District</th>
                            <th class="px-3 py-2 text-left font-medium">Package</th>
                            <th class="px-3 py-2 text-left font-medium">Total</th>
                            <th class="px-3 py-2 text-left font-medium">Paid</th>
                            <th class="px-3 py-2 text-left font-medium">Due</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            @if($canViewActionColumn)<th class="px-3 py-2 text-left font-medium">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($bookings as $booking)
                        @php $bookingCurrencyRate = $booking->currencyRate?->rate ?? ($currencyRateService?->getRateForDate($booking->created_at)?->rate ?? ($currencyRateService?->getFirstRate()?->rate ?? 0)); @endphp
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->invoice_id ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->customer->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->customer->mobile_no ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->passengers->count() }}</td>
                            <td class="px-3 py-2">
                                @if($canEditFingerprintLocation && !(auth()->user()->hasRole('Fingerprint Admin') && ($booking->fingerprint_location?->value ?? 'office') === 'home'))
                                <select
                                    class="text-sm border border-slate-300 rounded px-2 py-1 bg-white focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
                                    data-original="{{ $booking->fingerprint_location?->value ?? 'office' }}"
                                    data-rate="{{ $bookingCurrencyRate }}"
                                    onchange="updateFingerprintLocation({{ $booking->id }}, this.value, this)">
                                    <option value="home" {{ ($booking->fingerprint_location?->value ?? 'office') === 'home' ? 'selected' : '' }}>Home</option>
                                    <option value="office" {{ ($booking->fingerprint_location?->value ?? 'office') === 'office' ? 'selected' : '' }}>Office</option>
                                </select>
                                @else
                                <span class="text-slate-700">{{ ucfirst($booking->fingerprint_location?->value ?? 'Office') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->bookingBranch->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->fingerprintBranch->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->district->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->package->package_name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">@currency($booking->invoice?->total_amount ?? 0, 2, $bookingCurrencyRate)</td>
                            <td class="px-3 py-2 text-slate-700">@currency($booking->invoice?->paid_amount ?? 0, 2, $bookingCurrencyRate)</td>
                            <td class="px-3 py-2 text-slate-700">@currency($booking->invoice?->balance ?? 0, 2, $bookingCurrencyRate)</td>
                            <td class="px-3 py-2">
                                @if($booking->is_cancelled)
                                    @php $cb = $booking->cancelledBooking; @endphp
                                    @if($cb && $cb->status->value === 'cancellation processing')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Cancellation Processing
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Cancelled
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @endif
                            </td>
                            @if($canViewActionColumn)
                            <td class="px-3 py-2">
                                <a href="{{ route('bookings.show', $booking->id) }}" class="text-slate-600 hover:text-slate-800">View</a>
                                @if(!$booking->is_cancelled && (auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Co Admin')))
                                <button @click="openCancelModal({{ $booking->id }})" class="text-orange-600 hover:text-orange-800 font-medium ml-3">
                                    Cancel
                                </button>
                                @endif
                                @if($canDeleteBooking)
                                <form method="POST" action="{{ route('bookings.destroy', $booking->id) }}" onsubmit="return confirm('Are you sure you want to delete this booking?')" class="inline ml-3">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                </form>
                                @endif
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ 14 + ($canViewActionColumn ? 1 : 0) }}" class="px-3 py-4 text-center text-slate-500">No bookings found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        </div>
    </div>

    @if($canViewPassengerIndex)
    <div x-show="activeTab === 'passenger'" x-cloak>
        <div class="bg-white rounded-xl shadow-lg p-6 flex flex-col" style="max-height: calc(95vh - 200px);">
            <div class="mb-4 flex items-center gap-4">
                <div class="flex flex-1 flex-wrap items-center gap-4 min-w-0">
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Search</label>
                        <input type="text" x-model="searchTerm" x-ref="passengerSearchInput" class="w-full md:w-48 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition" placeholder="Search Name, Mobile, Passport, Invoice, Ticket, PNR...">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Booking Date From</label>
                        <input type="date" x-model="selectedBookingDateFrom" @change="onBookingDateFromChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Booking Date To</label>
                        <input type="date" x-model="selectedBookingDateTo" @change="onBookingDateToChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                     </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Actual Flight From</label>
                        <input type="date" x-model="selectedActualFlightFrom" @change="onActualFlightFromChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Actual Flight To</label>
                        <input type="date" x-model="selectedActualFlightTo" @change="onActualFlightToChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Return Date From</label>
                        <input type="date" x-model="selectedReturnDateFrom" @change="onReturnDateFromChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Return Date To</label>
                        <input type="date" x-model="selectedReturnDateTo" @change="onReturnDateToChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Required Flight</label>
                        <select x-model="selectedFlightDateRange" @change="onFlightDateRangeChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($flightDateRanges as $range)
                            <option value="{{ $range['id'] }}">{{ $range['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Fingerprint Status</label>
                        <select x-model="selectedFingerprintStatus" @change="onFingerprintStatusChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($fingerprintStatuses as $status)
                            <option value="{{ $status->value }}" {{ $selectedFingerprintStatus === $status->value ? 'selected' : '' }}>{{ ucfirst(str_replace('-', ' ', $status->value)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Visa Status</label>
                        <select x-model="selectedVisaStatus" @change="onVisaStatusChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($visaStatuses as $status)
                            <option value="{{ $status->value }}" {{ $selectedVisaStatus === $status->value ? 'selected' : '' }}>{{ $status->value === 'cancelled' ? 'Resubmission Pending' : ucfirst(str_replace('-', ' ', $status->value)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Ticket Status</label>
                        <select x-model="selectedTicketStatus" @change="onTicketStatusChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($ticketStatuses as $status)
                            <option value="{{ $status['value'] }}" {{ $selectedTicketStatus === $status['value'] ? 'selected' : '' }}>{{ $status['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($canFilterByVisaAgent)
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Visa Agent</label>
                        <select x-model="selectedVisaAgentId" @change="onVisaAgentChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($visaAgents as $agent)
                            <option value="{{ $agent['id'] }}" {{ (string) $selectedVisaAgentId === (string) $agent['id'] ? 'selected' : '' }}>{{ $agent['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    @if($canFilterByTicketAgent)
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Ticket Agent</label>
                        <select x-model="selectedTicketAgentId" @change="onTicketAgentChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($ticketAgents as $agent)
                            <option value="{{ $agent->id }}" {{ (string) $selectedTicketAgentId === (string) $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Current Status</label>
                        <select x-model="selectedPassengerStatus" @change="onPassengerStatusChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($passengerStatuses as $status)
                            <option value="{{ $status->id }}" {{ (string) $selectedPassengerStatus === (string) $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Status Change</label>
                        <select x-model="selectedStatusChangeAction" @change="onStatusChangeActionChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($statusChangeOptions as $status)
                            <option value="{{ $status->id }}" {{ (string) $selectedStatusChangeAction === (string) $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col" x-show="selectedStatusChangeAction">
                        <label class="text-xs font-semibold text-slate-400 mb-1">From</label>
                        <input type="date" x-model="selectedStatusChangeFrom" @change="onStatusChangeDateChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    </div>
                    <div class="flex flex-col" x-show="selectedStatusChangeAction">
                        <label class="text-xs font-semibold text-slate-400 mb-1">To</label>
                        <input type="date" x-model="selectedStatusChangeTo" @change="onStatusChangeDateChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Route</label>
                        <select x-model="selectedRouteDisplay" @change="onRouteChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($routesList as $route)
                            <option value="{{ $route['display'] }}" {{ (string) $selectedRouteDisplay === $route['display'] ? 'selected' : '' }}>{{ $route['display'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Package</label>
                        <select x-model="selectedPackageId" @change="onPackageChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">Select Package</option>
                            @foreach($packagesList as $package)
                            <option value="{{ $package->id }}" {{ (string) $selectedPackageId === (string) $package->id ? 'selected' : '' }}>{{ $package->package_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @unless(auth()->user()->branch_id)
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Booking Branch</label>
                        <select x-model="selectedBranchId" @change="onBranchChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            @foreach($bookingBranches as $branch)
                            <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endunless
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">Payment Wise</label>
                        <select x-model="selectedPaymentWise" @change="onPaymentWiseChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                            <option value="">All</option>
                            <option value="clear">Payment Clear</option>
                            <option value="due">Payment Due</option>
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-semibold text-slate-400 mb-1">&nbsp;</label>
                        <button @click="clearPassengerFilters" class="px-3 py-2 border border-slate-300 rounded-lg hover:bg-slate-100 text-slate-600 transition text-sm">Clear</button>
                    </div>
                </div>
                <div class="flex flex-col gap-1 flex-shrink-0">
                    <span class="px-3 py-1 bg-slate-700 text-white text-xs font-semibold rounded whitespace-nowrap shadow-sm" x-text="'Total Passenger - ' + totalPassengerCount">Total Passenger - {{ $totalPassengerCount }}</span>
                    <span class="px-3 py-1 bg-slate-700 text-white text-xs font-semibold rounded whitespace-nowrap shadow-sm">Total Package - @currency($totalPackageValue, 2, null, $totalPackageBdt)</span>
                    <span class="px-3 py-1 bg-slate-700 text-white text-xs font-semibold rounded whitespace-nowrap shadow-sm">Total Due - @currency($totalDue, 2, null, $totalDueBdt)</span>
                </div>
            </div>
            <div class="overflow-auto flex-1 min-h-0">
                <table class="w-full min-w-[1800px] text-sm">
                    <thead class="bg-slate-50 text-slate-600 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Booking Date</th>
                            <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                            <th class="px-3 py-2 text-left font-medium">Customer</th>
                            <th class="px-3 py-2 text-left font-medium">PAX QTY</th>
                            <th class="px-3 py-2 text-left font-medium">Mobile</th>
                            <th class="px-3 py-2 text-left font-medium">Name</th>
                            <th class="px-3 py-2 text-left font-medium">Current status</th>
                            <th class="px-3 py-2 text-left font-medium">Passport No</th>
                            <th class="px-3 py-2 text-left font-medium">Route</th>
                            <th class="px-3 py-2 text-left font-medium">Required Flight Date</th>
                            <th class="px-3 py-2 text-left font-medium">Actual Flight Date</th>
                            <th class="px-3 py-2 text-left font-medium">Return Date</th>
                            <th class="px-3 py-2 text-left font-medium">Package</th>
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Package Value</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Total Cost</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Markup (Profit)</th>@endif
                            <th class="px-3 py-2 text-left font-medium">Due</th>
                            <th class="px-3 py-2 text-left font-medium">Stay Duration</th>
                            @if($canViewVisaColumns)<th class="px-3 py-2 text-left font-medium">Visa</th>@endif
                            @if($canViewVisaColumns)<th class="px-3 py-2 text-left font-medium">Visa Agent</th>@endif
                            <th class="px-3 py-2 text-left font-medium">Visa Status</th>
                            <th class="px-3 py-2 text-left font-medium">Passenger Type</th>
                            @if($canViewTicketFareColumn)<th class="px-3 py-2 text-left font-medium">Ticket Panel</th>@endif
                            {{-- @if($canViewTicketAgentColumn)<th class="px-3 py-2 text-left font-medium">Ticket Agent</th>@endif --}}
                            <th class="px-3 py-2 text-left font-medium">Ticket Status</th>
                            <th class="px-3 py-2 text-left font-medium">Ticket Remarks</th>
                            <th class="px-3 py-2 text-left font-medium">Fingerprint Status</th>
                            <th class="px-3 py-2 text-left font-medium">Remarks</th>
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
@php $lastBookingId = null; @endphp
@forelse($passengers as $passenger)
@php
$isFirstRow = ($lastBookingId !== $passenger->booking_id);
$lastBookingId = $passenger->booking_id;

$passengerTypeVal = $passenger->passenger_type?->value;

$calcFare = function($fare, $pType) {
    if (!$fare) return 0;
    $base = $fare->ticket_type?->value === 'offer'
        ? ($fare->offer_price ?? $fare->selling_fare ?? $fare->net_fare ?? 0)
        : ($fare->selling_fare ?? $fare->net_fare ?? 0);
    return match($pType) {
        'child' => $base * ($fare->child_fare_percentage) / 100,
        'infant' => $base * ($fare->infant_fare_percentage) / 100,
        default => $base,
    };
};

if ($passenger->ticket_fare_inbound_id && $passenger->ticket_fare_outbound_id) {
    $fareAmount = $calcFare($passenger->ticketFareInbound, $passengerTypeVal)
                + $calcFare($passenger->ticketFareOutbound, $passengerTypeVal);
} else {
    $fareAmount = $calcFare($passenger->ticketFare, $passengerTypeVal);
}

$route = $passenger->ticketFare?->route ?? $passenger->booking?->package?->ticketFare?->route;
$fmtRoute = function($route) {
    if (!$route) return '?';
    $rt = $route->route_type?->value;
    if ($rt === 'multi_city') {
        return $route->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
    }
    $from = $route->fromCity?->code ?? '?';
    $to = $route->toCity?->code ?? '?';
    if ($rt === 'round') {
        return $from . '-' . $to . '-' . ($route->returnCity?->code ?? '?');
    }
    return $from . ' → ' . $to;
};
$routeDisplay = '—';
if ($passenger->ticket_fare_inbound_id) {
    $inboundRoute = $fmtRoute($passenger->ticketFareInbound?->route);
    $outboundRoute = $fmtRoute($passenger->ticketFareOutbound?->route);
    $routeDisplay = $inboundRoute . "\n" . $outboundRoute;
} else {
    $route = $passenger->ticketFare?->route ?? $passenger->booking?->package?->ticketFare?->route;
    if ($route) {
        $routeDisplay = $fmtRoute($route);
    }
}
@endphp
@php $passBookingRate = $passenger->booking?->currencyRate?->rate ?? ($currencyRateService?->getRateForDate($passenger->booking?->created_at)?->rate ?? ($currencyRateService?->getFirstRate()?->rate ?? 0)); @endphp
<tr>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->booking?->created_at?->format('d M Y') ?? '—' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->booking?->invoice_id ?? '—' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->booking->customer->name ?? 'N/A' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $isFirstRow ? ($passenger->booking?->pax_qty ?? '—') : '' }}</td>
    <td class="px-3 py-2 text-slate-700">
        <div class="leading-tight">
            <span>{{ $passenger->booking?->customer?->mobile_no ?? '—' }}</span><br>
            <span>{{ $passenger->mobile_no ?? '—' }}</span>
        </div>
    </td>
    <td class="px-3 py-2 text-slate-700">{{ trim($passenger->first_name . ' ' . $passenger->last_name) ?: '—' }}</td>
    <td class="px-3 py-2">
        @if($canEditInline)
        <select
            class="text-sm border border-slate-300 rounded px-2 py-1 bg-white focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
            x-bind:value="getComputedStatusId({{ $loop->index }})"
            x-on:change="updatePassengerStatus({{ $passenger->id }}, $event.target.value)">
            <option value="">None</option>
            @foreach($passengerStatuses as $status)
                <option value="{{ $status->id }}" @if(in_array($status->name, ['Processing', 'Fingerprint Done', 'Visa Submitted', 'Visa Issued', 'Ticket Issued', 'Ticket Issued before Visa'])) disabled @endif>{{ $status->name }}</option>
            @endforeach
        </select>
        @else
        <span class="text-slate-700" x-text="getComputedStatusName({{ $loop->index }})">{{ $passenger->status?->name ?? $passenger->computed_status ?? 'None' }}</span>
        @endif
    </td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->passport_no ?? '—' }}</td>
    <td class="px-3 py-2 text-slate-600">{!! nl2br(e($routeDisplay)) !!}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->flight_date_from?->format('d M Y') . ' → ' . $passenger->flight_date_to?->format('d M Y') ?? '—' }}</td>
    @php
        $regularTicket = $passenger->allIssuedTickets
            ->first(fn($t) => in_array($t->issue_type, [null, 'regular'], true) && in_array($t->status, ['issued', 're-issued']));
        $actualFlightDate = 'N/A';
        if (($routeType ?? null) !== 'oneway_outbound' && $regularTicket) {
            $actualFlightDate = $regularTicket->inbound_date?->format('d M Y') ?? 'N/A';
        }
    @endphp
    <td class="px-3 py-2 text-slate-700">{{ $actualFlightDate }}</td>
    @php
        $returnDate = 'N/A';
        if (isset($routeType) && $routeType === 'oneway_inbound') {
            $pendingTicket = $passenger->allIssuedTickets
                ->first(fn($t) => $t->issue_type === 'pending_outbound');
            if ($pendingTicket && $pendingTicket->status === 'issued') {
                $returnDate = $pendingTicket->outbound_date?->format('d M Y') ?? 'N/A';
            }
        } elseif (isset($routeType) && $regularTicket) {
            $returnDate = $regularTicket->outbound_date?->format('d M Y') ?? 'N/A';
        }
    @endphp
    <td class="px-3 py-2 text-slate-700">{{ $returnDate }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->booking?->package?->package_name ?? '—' }}</td>
    @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">@if($passenger->package_value)@currency($passenger->package_value, 2, $passBookingRate)@else—@endif</td>@endif
    @if($canViewFinancialColumns)
    <td class="px-3 py-2 text-slate-700" x-text="(passengersTicketData[{{ $loop->index }}]?.total_cost || 0) > 0 ? $currency(passengersTicketData[{{ $loop->index }}].total_cost, 2, {{ $passBookingRate }}) : '—'"></td>
    @endif
    @if($canViewFinancialColumns)
    <td class="px-3 py-2 text-slate-700" x-text="({{ $passenger->package_value ?? 0 }} > 0 || (passengersTicketData[{{ $loop->index }}]?.total_cost || 0) > 0) ? $currency({{ $passenger->package_value ?? 0 }} - passengersTicketData[{{ $loop->index }}].total_cost, 2, {{ $passBookingRate }}) : '—'"></td>
    @endif
    <td class="px-3 py-2 text-slate-700">@if($isFirstRow)@if($passenger->booking?->invoice)<div class="font-medium">Total: @currency($passenger->booking->invoice->total_amount, 2, $passBookingRate)</div><div class="font-medium">Due: @currency($passenger->booking->invoice->balance, 2, $passBookingRate)</div>@else—@endif @endif</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->stay_duration ?? '—' }}</td>
    @if($canViewVisaColumns)
    <td class="px-3 py-2" x-init="$nextTick(() => console.log('P'+{{ $loop->index }}+': visa='+((passengersVisaData[{{ $loop->index }}]?.visa?.status)||'null')+' fp='+((passengersTicketData[{{ $loop->index }}]?.fingerprint_status)||'null')+' canc='+passengersTicketData[{{ $loop->index }}]?.is_cancelled))">
        <div class="flex items-center gap-1 flex-wrap">
            <template x-if="passengersVisaData[{{ $loop->index }}]?.visa">
                <span x-show="['submitted','issued'].includes(passengersVisaData[{{ $loop->index }}]?.visa?.status)" class="text-slate-800 font-medium text-xs mr-1" x-text="$currency(passengersVisaData[{{ $loop->index }}]?.visa?.net_visa_cost, 2, passengersVisaData[{{ $loop->index }}]?.rate)"></span>
            </template>
            <template x-if="passengersVisaData[{{ $loop->index }}]?.visa">
                <span x-show="!['submitted','issued'].includes(passengersVisaData[{{ $loop->index }}]?.visa?.status)" class="text-slate-800 font-medium text-xs mr-1" x-text="$currency(passengersVisaData[{{ $loop->index }}]?.visa?.selling_price, 2, passengersVisaData[{{ $loop->index }}]?.rate)"></span>
            </template>
            <template x-if="!passengersVisaData[{{ $loop->index }}]?.visa">
                <span class="text-slate-500 text-xs">N/A</span>
            </template>

            <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled">
                <button x-show="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'pending' && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'"
                        @click="openVisaSubmitModal({{ $loop->index }})"
                        class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded font-medium transition">Submit</button>
            </template>
            <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled">
                <button x-show="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'submitted' && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'"
                        @click="openVisaIssueModal({{ $loop->index }})"
                        class="text-xs bg-green-100 hover:bg-green-200 text-green-600 px-2 py-1 rounded font-medium transition">Issue</button>
            </template>
            <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled">
                <button x-show="(passengersVisaData[{{ $loop->index }}]?.visa?.status === 'submitted' || passengersVisaData[{{ $loop->index }}]?.visa?.status === 'issued') && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'"
                        @click="openVisaEditModal({{ $loop->index }})"
                        class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded font-medium transition">Edit</button>
            </template>
            <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled">
                <button x-show="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'submitted'"
                        @click="openVisaCancelModal({{ $loop->index }})"
                        class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded font-medium transition">Cancel</button>
            </template>
            <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled">
                <button x-show="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'cancelled' && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'"
                        @click="openVisaResubmitModal({{ $loop->index }})"
                        class="text-xs bg-orange-100 hover:bg-orange-200 text-orange-600 px-2 py-1 rounded font-medium transition">Re-Submit</button>
            </template>
            <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled && passengersTicketData[{{ $loop->index }}]?.fingerprint_status !== 'approved'">
                <span class="text-xs text-slate-400 italic">Fingerprint not approved</span>
            </template>
            <template x-if="passengersTicketData[{{ $loop->index }}]?.is_cancelled">
                <span class="text-xs text-slate-400 italic">Booking Cancelled</span>
            </template>
        </div>
    </td>
    <td class="px-3 py-2 text-slate-600">
        <template x-if="passengersVisaData[{{ $loop->index }}]?.visa">
            <span x-text="passengersVisaData[{{ $loop->index }}]?.visa?.agent || 'N/A'"></span>
        </template>
        <template x-if="!passengersVisaData[{{ $loop->index }}]?.visa">
            <span class="text-slate-500">N/A</span>
        </template>
    </td>
    @endif
    <td class="px-3 py-2">
        <template x-if="passengersVisaData[{{ $loop->index }}]?.visa">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="{
                    'bg-green-100 text-green-700': passengersVisaData[{{ $loop->index }}]?.visa?.status === 'issued',
                    'bg-blue-100 text-blue-700': passengersVisaData[{{ $loop->index }}]?.visa?.status === 'submitted',
                    'bg-yellow-100 text-yellow-700': passengersVisaData[{{ $loop->index }}]?.visa?.status === 'pending' || passengersVisaData[{{ $loop->index }}]?.visa?.status === 'cancelled'
                }"
                x-text="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'cancelled' ? 'Resubmission Pending' : (passengersVisaData[{{ $loop->index }}]?.visa?.status.charAt(0).toUpperCase() + passengersVisaData[{{ $loop->index }}]?.visa?.status.slice(1))">
            </span>
        </template>
        <template x-if="!passengersVisaData[{{ $loop->index }}]?.visa">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">N/A</span>
        </template>
    </td>
    <td class="px-3 py-2 text-slate-700">
        <span x-text="{
            'adult': 'ADT',
            'child': 'CHD',
            'infant': 'INF'
        }[passengersTicketData[{{ $loop->index }}]?.passenger_type] || '—'">—</span>
    </td>
    @if($canViewTicketFareColumn)
    <td class="px-3 py-2 text-slate-700">
        <div class="flex items-center gap-1 w-full">
            <span class="font-medium text-sm shrink-0">@if($fareAmount > 0)@currency($fareAmount, 2, $passBookingRate)@else—@endif</span>
            <div class="flex items-center gap-1 flex-1"
                 :class="(!hasRegularIssued({{ $loop->index }}) || rowHasPendingOutbound({{ $loop->index }})) ? 'justify-start' : 'justify-center'">
                <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled">
                    <button x-show="!hasRegularIssued({{ $loop->index }}) && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'" @click="openTicketFareModal({{ $loop->index }})" :disabled="passengersTicketData[{{ $loop->index }}]?.is_ticket_held" :class="passengersTicketData[{{ $loop->index }}]?.is_ticket_held ? 'opacity-40 cursor-not-allowed bg-green-100 text-green-600' : 'bg-green-100 hover:bg-green-200 text-green-600'" class="text-xs px-2 py-1 rounded font-medium transition">Issue</button>
                </template>
                <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled && rowHasPendingOutbound({{ $loop->index }}) && hasRegularIssued({{ $loop->index }}) && !regularTicketCoversOutbound({{ $loop->index }}) && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'">
                    <button @click="openOutboundTicketFareModal({{ $loop->index }})" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded font-medium transition">Issue-Out</button>
                </template>
                <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'">
                    <div class="flex items-center gap-1">
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="text-xs px-1.5 py-1 rounded font-medium transition bg-slate-100 hover:bg-slate-200 text-slate-500" title="More actions">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="10" cy="16" r="2"/></svg>
                            </button>
                            <div x-show="open" @click.outside="open = false" class="absolute right-0 top-full mt-1 z-50 bg-white border border-slate-200 rounded-lg shadow-lg flex items-center gap-1 px-2 py-1 whitespace-nowrap" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                                <button @click="toggleTicketHold({{ $loop->index }})" :disabled="isTogglingTicketHold[{{ $loop->index }}]" class="px-2 py-1 text-xs font-medium rounded hover:bg-slate-50 transition" :class="passengersTicketData[{{ $loop->index }}]?.is_ticket_held ? 'text-yellow-600' : 'text-orange-600'" x-text="passengersTicketData[{{ $loop->index }}]?.is_ticket_held ? 'Unhold' : 'Hold'"></button>
                                <button x-show="canShowIssueOutInMenu({{ $loop->index }})" @click="handleIssueOutFromMenu({{ $loop->index }})" class="px-2 py-1 text-xs font-medium text-blue-600 rounded hover:bg-slate-50 transition">Issue-Out</button>
                                <button x-show="hasRegularIssued({{ $loop->index }})" @click="openTicketFareModal({{ $loop->index }})" class="px-2 py-1 text-xs font-medium text-slate-600 rounded hover:bg-slate-50 transition">Edit</button>
                                <button x-show="rowHasIssuedOutbound({{ $loop->index }})" @click="openOutboundEditTicketFareModal({{ $loop->index }})" class="px-2 py-1 text-xs font-medium text-blue-600 rounded hover:bg-slate-50 transition">Edit-Out</button>
                                <template x-if="rowHasConfirmableTickets({{ $loop->index }})">
                                    <div>
                                        <template x-if="!showThreeButtonsMode({{ $loop->index }})">
                                            <button @click="confirmTickets({{ $loop->index }}, 'all')" class="px-2 py-1 text-xs font-medium text-indigo-600 rounded hover:bg-slate-50 transition">G-Confirm</button>
                                        </template>
                                        <template x-if="showThreeButtonsMode({{ $loop->index }})">
                                            <span>
                                                <button x-show="showGConfirmIn({{ $loop->index }})" @click="confirmTickets({{ $loop->index }}, 'in')" class="px-2 py-1 text-xs font-medium text-indigo-600 rounded hover:bg-slate-50 transition">G-Confirm In</button>
                                                <button x-show="showGConfirmOut({{ $loop->index }})" @click="confirmTickets({{ $loop->index }}, 'out')" class="px-2 py-1 text-xs font-medium text-indigo-600 rounded hover:bg-slate-50 transition">G-Confirm Out</button>
                                                <button x-show="showGConfirmBoth({{ $loop->index }})" @click="confirmTickets({{ $loop->index }}, 'both')" class="px-2 py-1 text-xs font-medium text-indigo-600 rounded hover:bg-slate-50 transition">G-Confirm Both</button>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <button
                            x-show="hasViewableTickets({{ $loop->index }})"
                            title="View Ticket Info"
                            @click="openTicketInfoModal({{ $loop->index }})"
                            class="text-xs px-1.5 py-1 rounded font-medium transition bg-slate-100 hover:bg-slate-200 text-slate-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </template>
                <template x-if="passengersTicketData[{{ $loop->index }}]?.is_cancelled">
                    <span class="text-xs text-slate-400 italic">Booking Cancelled</span>
                </template>
                <template x-if="!passengersTicketData[{{ $loop->index }}]?.is_cancelled && passengersTicketData[{{ $loop->index }}]?.fingerprint_status !== 'approved'">
                    <span class="text-xs text-slate-400 italic">Fingerprint not approved</span>
                </template>
            </div>
        </div>
    </td>
    @endif
    {{-- @if($canViewTicketAgentColumn)<td class="px-3 py-2 text-slate-700"><span x-text="passengersTicketData[{{ $loop->index }}]?.latest_issued_ticket?.ticket_agent_name || '—'">—</span></td>@endif --}}
    <td class="px-3 py-2">
        <template x-if="passengersTicketData[{{ $loop->index }}]?.latest_issued_ticket || passengersTicketData[{{ $loop->index }}]?.pending_outbound_issued_ticket || passengersTicketData[{{ $loop->index }}]?.is_ticket_held">
            <div>
                <div class="flex flex-wrap gap-1 mb-1">
                    <template x-for="status in getTicketStatuses({{ $loop->index }})" :key="status">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                            :class="statusColorClass(status)"
                            x-text="status">
                        </span>
                    </template>
                </div>
                <template x-for="ticket in passengersTicketData[{{ $loop->index }}]?.all_issued_tickets || []">
                    <template x-if="ticket.pnr && (ticket.status === 'issued' || ticket.status === 're-issued')">
                        <div class="text-xs leading-tight text-slate-500" x-text="ticket.pnr + (ticket.issue_type ? ' (' + ticket.issue_type + ')' : '')"></div>
                    </template>
                </template>
            </div>
        </template>
        <template x-if="!passengersTicketData[{{ $loop->index }}]?.latest_issued_ticket && !passengersTicketData[{{ $loop->index }}]?.pending_outbound_issued_ticket && !passengersTicketData[{{ $loop->index }}]?.is_ticket_held">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">—</span>
        </template>
    </td>
    <td class="px-3 py-2">
        <button @click="openRemarksModal({{ $loop->index }})"
                class="text-xs px-2 py-1 rounded font-medium bg-slate-100 hover:bg-slate-200 text-slate-600 transition">
            Remarks
        </button>
    </td>
    <td class="px-3 py-2">
        @php
            $detail = $passenger->fingerprintDetail;
            $rawStatus = $detail?->status?->value;
            $displayStatus = $rawStatus;
            if ($rawStatus === 'approved') {
                $allDetails = $detail->fingerprint?->fingerprintDetails;
                $allApproved = $allDetails && $allDetails->every(fn($d) => $d->status->value === 'approved');
                if (!$allApproved) {
                    $displayStatus = 'Partially Approved';
                }
                $approvedDate = $detail->approvedLog?->created_at ?? $detail->updated_at;
            } elseif ($rawStatus === 'done') {
                $displayStatus = 'Pending Pax Completion';
            }
        @endphp
        @if($displayStatus)
            <div class="flex flex-col items-center gap-0.5">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                    {{ $rawStatus === 'approved' ? 'bg-green-100 text-green-700' : ($rawStatus === 'done' ? 'bg-blue-100 text-blue-700' : ($rawStatus === 'processing' ? 'bg-blue-100 text-blue-700' : ($rawStatus === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600'))) }}">
                    {{ $displayStatus === 'Partially Approved' ? 'Partially Approved' : ($rawStatus === 'done' ? 'Pending Pax Completion' : ucfirst($rawStatus)) }}
                </span>
                @if($rawStatus === 'approved' && $approvedDate)
                    <span class="text-xs text-slate-500 leading-tight">{{ $approvedDate->format('d|m|y') }}</span>
                @endif
            </div>
        @else
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">—</span>
        @endif
    </td>
    <td class="px-3 py-2 text-slate-600 text-sm max-w-[250px] break-words whitespace-normal leading-snug">
        {{ $passenger->booking?->remarks ?? '—' }}
    </td>
    <td class="px-3 py-2">
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" class="text-xs px-1.5 py-1 rounded font-medium transition bg-slate-100 hover:bg-slate-200 text-slate-500" title="More actions">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="10" cy="16" r="2"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" class="absolute right-0 top-full mt-1 z-50 bg-white border border-slate-200 rounded-lg shadow-lg flex flex-col whitespace-nowrap" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <a href="{{ route('passengers.show', $passenger->id) }}?return_url={{ urlencode(request()->fullUrl()) }}" class="px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition">View Passenger</a>
                <button x-show="hasViewableTickets({{ $loop->index }})" @click="openTicketInfoModal({{ $loop->index }})" class="px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition text-left">View Tickets</button>
                @if($passenger->documents_count > 0)
                    <a href="{{ route('passengers.download-all-docs', $passenger->id) }}" class="px-3 py-1.5 text-xs font-medium text-green-600 hover:bg-slate-50 transition">Download</a>
                @else
                    <span class="px-3 py-1.5 text-xs font-medium text-slate-300 cursor-not-allowed">Download</span>
                @endif
                @if($passenger->booking->documents->isNotEmpty() || ($passenger->booking->customer && $passenger->booking->customer->documents->isNotEmpty()))
                    <a href="{{ route('bookings.download-all-docs', ['booking' => $passenger->booking_id, 'passenger_id' => $passenger->id]) }}" class="px-3 py-1.5 text-xs font-medium text-green-600 hover:bg-slate-50 transition">Download All</a>
                @else
                    <span class="px-3 py-1.5 text-xs font-medium text-slate-300 cursor-not-allowed">Download All</span>
                @endif
            </div>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="{{ 20 + ($canViewFinancialColumns ? 3 : 0) + ($canViewVisaColumns ? 2 : 0) + ($canViewTicketFareColumn ? 1 : 0) }}" class="px-3 py-4 text-center text-slate-500">No passengers found</td>
@endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex-shrink-0">
                {{ $passengers->links() }}
            </div>
        </div>
    </div>
    @endif

    {{-- Visa Submit Modal --}}
    <div x-show="visaSubmitModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeVisaSubmitModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeVisaSubmitModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Visa Submit</h3>
            <form @submit.prevent="handleVisaSubmit()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent *</label>
                        <select x-model="visaSubmitForm.agentId" required @change="updateSubmitCommissionAgents(visaSubmitForm.agentId)" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Agent</option>
                            <template x-for="agent in visaAgents" :key="agent.id">
                                <option :value="agent.id" x-text="agent.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Commission Agent</label>
                        <select x-model="visaSubmitForm.commissionAgentId" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Commission Agent</option>
                            <template x-for="agent in visaSubmitForm.commissionAgents" :key="agent.id">
                                <option :value="agent.id" x-text="agent.name"></option>
                            </template>
                        </select>
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (BDT)</label>
                            <input type="number" x-model="visaSubmitForm.agentCommissionBDT" min="0" step="0.000001" @input="convertAgentCommissionToSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.agentCommission" min="0" step="0.000001"
                               :readonly="$store.currency.mode === 'BDT'"
                               :class="$store.currency.mode === 'BDT' ? 'w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600' : 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none'"
                               @input="calculateVisaCost()" placeholder="0">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (BDT)</label>
                            <input type="number" x-model="visaSubmitForm.netVisaCostBDT" min="0" step="0.000001" @input="convertNetVisaCostToSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.netVisaCost" step="0.000001"
                               :readonly="$store.currency.mode === 'BDT'"
                               :class="$store.currency.mode === 'BDT' ? 'w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600' : 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none'"
                               @input="calculateVisaCost()" placeholder="0">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (BDT)</label>
                            <input type="number" x-model="visaSubmitForm.sellingPriceBDT" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.sellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Final Cost (BDT)</label>
                            <input type="number" x-model="visaSubmitForm.finalCostBDT" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Final Cost (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.finalCost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Submit</button>
                    <button type="button" @click="closeVisaSubmitModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Visa Issue Modal --}}
    <div x-show="visaIssueModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeVisaIssueModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeVisaIssueModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Visa Issue</h3>
            <form @submit.prevent="handleVisaIssue()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent</label>
                        <input type="text" x-model="visaIssueForm.agentName" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Number *</label>
                        <input type="text" x-model="visaIssueForm.visaNumber" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter visa number">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (BDT)</label>
                            <input type="number" x-model="visaIssueForm.sellingPriceBDT" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (SAR)</label>
                        <input type="number" x-model="visaIssueForm.sellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Additional Cost (BDT)</label>
                            <input type="number" x-model="visaIssueForm.additionalCostBDT" min="0" step="0.000001" @input="convertAdditionalCostToSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Additional Cost (SAR)</label>
                        <input type="number" x-model="visaIssueForm.additionalCost" step="0.000001"
                               :readonly="$store.currency.mode === 'BDT'"
                               :class="$store.currency.mode === 'BDT' ? 'w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600' : 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none'"
                               @input="calculateVisaIssueFinal()" placeholder="0">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Final Cost (BDT)</label>
                            <input type="number" x-model="visaIssueForm.finalCostBDT" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Final Cost (SAR)</label>
                        <input type="number" x-model="visaIssueForm.finalCost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                        <input type="text" x-model="visaIssueForm.remarks" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter remarks">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Issue</button>
                    <button type="button" @click="closeVisaIssueModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Visa Edit Modal --}}
    <div x-show="visaEditModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeVisaEditModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeVisaEditModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Edit Visa</h3>
            <form @submit.prevent="handleVisaEdit()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent *</label>
                        <select x-model="visaEditForm.agentId" required @change="updateEditCommissionAgents(visaEditForm.agentId)" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Agent</option>
                            <template x-for="agent in visaAgents" :key="agent.id">
                                <option :value="agent.id" x-text="agent.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Number</label>
                        <input type="text" x-model="visaEditForm.visaNumber" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter visa number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Commission Agent</label>
                        <select x-model="visaEditForm.commissionAgentId" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Commission Agent</option>
                            <template x-for="agent in visaEditForm.commissionAgents" :key="agent.id">
                                <option :value="agent.id" x-text="agent.name"></option>
                            </template>
                        </select>
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (BDT)</label>
                            <input type="number" x-model="visaEditForm.sellingPriceBDT" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (SAR)</label>
                        <input type="number" x-model="visaEditForm.sellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (BDT)</label>
                            <input type="number" x-model="visaEditForm.agentCommissionBDT" min="0" step="0.000001" @input="convertEditAgentCommissionToSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (SAR)</label>
                        <input type="number" x-model="visaEditForm.agentCommission" step="0.000001"
                               :readonly="$store.currency.mode === 'BDT'"
                               :class="$store.currency.mode === 'BDT' ? 'w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600' : 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none'"
                               @input="calculateVisaEditFinal()" placeholder="0">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (BDT)</label>
                            <input type="number" x-model="visaEditForm.netVisaCostBDT" min="0" step="0.000001" @input="convertEditNetVisaCostToSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (SAR)</label>
                        <input type="number" x-model="visaEditForm.netVisaCost" step="0.000001"
                               :readonly="$store.currency.mode === 'BDT'"
                               :class="$store.currency.mode === 'BDT' ? 'w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600' : 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none'"
                               @input="calculateVisaEditFinal()" placeholder="0">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Additional Cost (BDT)</label>
                            <input type="number" x-model="visaEditForm.additionalCostBDT" min="0" step="0.000001" @input="convertEditAdditionalCostToSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Additional Cost (SAR)</label>
                        <input type="number" x-model="visaEditForm.additionalCost" step="0.000001"
                               :readonly="$store.currency.mode === 'BDT'"
                               :class="$store.currency.mode === 'BDT' ? 'w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600' : 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none'"
                               @input="calculateVisaEditFinal()" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                        <textarea x-model="visaEditForm.remarks" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter remarks" rows="2"></textarea>
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Final Cost (BDT)</label>
                            <input type="number" x-model="visaEditForm.finalCostBDT" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Final Cost (SAR)</label>
                        <input type="number" x-model="visaEditForm.finalCost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <span :class="visaEditForm.statusLabel === 'Issued' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium" x-text="visaEditForm.statusLabel"></span>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                    <button type="button" @click="closeVisaEditModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Visa Re-Submit Modal --}}
    <div x-show="visaResubmitModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeVisaResubmitModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeVisaResubmitModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Visa Re-Submit</h3>
            <form @submit.prevent="handleVisaResubmit()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent *</label>
                        <select x-model="visaResubmitForm.visa_agent_id" required @change="updateResubmitCommissionAgents(visaResubmitForm.visa_agent_id)" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Agent</option>
                            <template x-for="agent in visaAgents" :key="agent.id">
                                <option :value="agent.id" x-text="agent.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Commission Agent</label>
                        <select x-model="visaResubmitForm.commission_agent_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Commission Agent</option>
                            <template x-for="ca in getCommissionAgents(visaResubmitForm.visa_agent_id)" :key="ca.id">
                                <option :value="ca.id" x-text="ca.name"></option>
                            </template>
                        </select>
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Visa Selling Price (BDT)</label>
                            <input type="text" :value="((passengersVisaData[editingVisaIndex]?.visa?.selling_price || 0) * ($store.currency.rate || 0)).toFixed(2)" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Selling Price (SAR)</label>
                        <input type="number" :value="passengersVisaData[editingVisaIndex]?.visa?.selling_price || 0" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (BDT)</label>
                            <input type="number" x-model="visaResubmitForm.agent_commission_bdt" min="0" step="0.000001" @input="convertResubmitAgentCommissionToSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (SAR)</label>
                        <input type="number" x-model="visaResubmitForm.agent_commission" min="0" step="0.000001"
                               :readonly="$store.currency.mode === 'BDT'"
                               :class="$store.currency.mode === 'BDT' ? 'w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600' : 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none'"
                               @input="calculateResubmitFinal()" placeholder="0">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (BDT)</label>
                            <input type="number" x-model="visaResubmitForm.net_visa_cost_bdt" min="0" step="0.000001" @input="convertResubmitNetVisaCostToSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (SAR)</label>
                        <input type="number" x-model="visaResubmitForm.net_visa_cost" step="0.000001"
                               :readonly="$store.currency.mode === 'BDT'"
                               :class="$store.currency.mode === 'BDT' ? 'w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600' : 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none'"
                               @input="calculateResubmitFinal()" placeholder="0">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Final Visa Cost (BDT)</label>
                            <input type="number" x-model="visaResubmitForm.final_cost_bdt" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Final Visa Cost (SAR)</label>
                        <input type="number" x-model="visaResubmitForm.final_cost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                    <button type="button" @click="closeVisaResubmitModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Visa Cancel Modal --}}
    <div x-show="visaCancelModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeVisaCancelModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeVisaCancelModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Cancel Visa</h3>
            <form @submit.prevent="handleVisaCancel()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Name</label>
                        <input type="text" :value="passengersVisaData[editingVisaIndex]?.visa?.agent || '-'" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Agent Cost (BDT)</label>
                            <input type="text" :value="((passengersVisaData[editingVisaIndex]?.visa?.net_visa_cost || 0) * ($store.currency.rate || 0)).toFixed(2)" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Cost (SAR)</label>
                        <input type="text" :value="(passengersVisaData[editingVisaIndex]?.visa?.net_visa_cost || 0).toFixed(2)" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <template x-if="$store.currency.mode === 'BDT'">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Cancellation Fee (BDT) *</label>
                            <input type="number" x-model="visaCancelForm.cancellation_fee_bdt" required min="0" step="0.000001" @input="convertCancelFeeToSar()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter cancellation fee">
                        </div>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Cancellation Fee (SAR) *</label>
                        <input type="number" x-model="visaCancelForm.cancellation_fee" required min="0" step="0.000001"
                               :readonly="$store.currency.mode === 'BDT'"
                               :class="$store.currency.mode === 'BDT' ? 'w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600' : 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none'"
                               placeholder="Enter cancellation fee">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                        <textarea x-model="visaCancelForm.remarks" rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Optional remarks"></textarea>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">Submit Cancellation</button>
                    <button type="button" @click="closeVisaCancelModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Close</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Ticket Info Modal --}}
    <div x-show="isTicketInfoModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="isTicketInfoModalOpen = false">
        <div class="fixed inset-0 bg-black/50" @click="isTicketInfoModalOpen = false"></div>
        <div x-show="isTicketInfoModalOpen" x-cloak class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold text-slate-800">Ticket Info</h3>
                <button type="button" @click="isTicketInfoModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <template x-if="ticketInfoPassengerIndex !== null">
                <div>
                    <div class="mb-3 p-3 bg-slate-50 rounded-lg">
                        <div class="text-sm font-medium text-slate-700">
                            <span x-text="passengersTicketData[ticketInfoPassengerIndex]?.passenger_name || ''"></span>
                            <span class="text-slate-400 mx-1">|</span>
                            <span x-text="passengersTicketData[ticketInfoPassengerIndex]?.passenger_type || ''"></span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                    <th class="px-3 py-2">#</th>
                                    <th class="px-3 py-2">Issue Date</th>
                                    <th class="px-3 py-2">Ticket No</th>
                                    <th class="px-3 py-2">PNR</th>
                                    <th class="px-3 py-2">Route</th>
                                    <th class="px-3 py-2">Airline</th>
                                    <th class="px-3 py-2">Class</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(ticket, idx) in viewableTickets(ticketInfoPassengerIndex)" :key="ticket.id">
                                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                                        <td class="px-3 py-2 text-slate-500" x-text="idx + 1"></td>
                                        <td class="px-3 py-2 text-slate-700" x-text="ticket.issued_date || '—'"></td>
                                        <td class="px-3 py-2 text-slate-700 font-mono" x-text="ticket.ticket_number || '—'"></td>
                                        <td class="px-3 py-2 text-slate-700 font-mono" x-text="ticket.pnr || '—'"></td>
                                        <td class="px-3 py-2 text-slate-700" x-text="ticket.route || '—'"></td>
                                        <td class="px-3 py-2 text-slate-700" x-text="ticket.airline || '—'"></td>
                                        <td class="px-3 py-2 text-slate-700" x-text="ticket.travel_class || '—'"></td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                                :class="{
                                                    'bg-green-100 text-green-700': ticket.status === 'issued',
                                                    'bg-purple-100 text-purple-700': ticket.status === 're-issued',
                                                    'bg-red-100 text-red-700': ticket.status === 'refunded',
                                                }"
                                                x-text="ticket.status.charAt(0).toUpperCase() + ticket.status.slice(1)">
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <template x-if="viewableTickets(ticketInfoPassengerIndex).length === 0">
                            <p class="text-center text-slate-400 py-6">No ticket information available.</p>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Ticket Fare Modal --}}
    <div x-show="isTicketFareModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeTicketFareModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeTicketFareModal()"></div>
        <div x-show="isTicketFareModalOpen" x-cloak class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4" id="ticketFareModalTitle" x-text="ticketFareModalTitle"></h3>
            <form novalidate @submit.prevent="handleTicketFareSubmit()">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type</label>
                    <select x-model="ticketFareForm.ticket_type" @change="handleTicketTypeChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select</option>
                        <option value="regular">Regular</option>
                        <option value="offer">Offer</option>
                        <option value="group">Group</option>
                    </select>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Ticket Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                            <select x-model="ticketFareForm.route_type" @change="handleTicketFareRouteTypeChange(); handleRouteTypeOrFlightTypeChange()" :disabled="ticketFareForm.isOutboundMode" :class="ticketFareForm.isOutboundMode ? 'bg-slate-100 cursor-not-allowed' : 'bg-white'" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                                <option value="">Select</option>
                                <option value="One Way-Inbound">One Way-Inbound</option>
                                <option value="One Way-Outbound">One Way-Outbound</option>
                                <option value="Round">Round</option>
                                <option value="Multi City">Multi City</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                            <select x-model="ticketFareForm.flight_type" @change="handleRouteTypeOrFlightTypeChange()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select</option>
                                <option value="Transit">Transit</option>
                                <option value="Direct">Direct</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket *</label>
                            <select x-model="ticketFareForm.ticket_option" @change="handleTicketOptionChange()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select Ticket</option>
                                <template x-for="opt in filteredTicketOptions" :key="opt.value">
                                    <option :value="opt.value" :disabled="opt.is_active === false" x-text="opt.display"></option>
                                </template>
                            </select>
                        </div>
                         <div x-show="ticketFareForm.showInboundDate">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date *</label>
                            <input type="text" x-model="ticketFareForm.inbound_date" placeholder="DD-MMM-YY"
                                   @input="ticketFareForm.errors.inbound_date = ''"
                                   :class="ticketFareForm.errors.inbound_date ? 'border-red-500' : ''"
                                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                            <p x-show="ticketFareForm.errors.inbound_date" x-text="ticketFareForm.errors.inbound_date" class="text-xs text-red-500 mt-1"></p>
                        </div>
                        <div x-show="ticketFareForm.showOutboundDate">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date *</label>
                            <input type="text" x-model="ticketFareForm.outbound_date" placeholder="DD-MMM-YY"
                                   @input="ticketFareForm.errors.outbound_date = ''"
                                   :class="ticketFareForm.errors.outbound_date ? 'border-red-500' : ''"
                                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                            <p x-show="ticketFareForm.errors.outbound_date" x-text="ticketFareForm.errors.outbound_date" class="text-xs text-red-500 mt-1"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">PNR *</label>
                            <input type="text" x-model="ticketFareForm.pnr"
                                   @input="ticketFareForm.errors.pnr = ''"
                                   :class="ticketFareForm.errors.pnr ? 'border-red-500' : ''"
                                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter PNR">
                            <p x-show="ticketFareForm.errors.pnr" x-text="ticketFareForm.errors.pnr" class="text-xs text-red-500 mt-1"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Number *</label>
                            <input type="text" x-model="ticketFareForm.ticket_number"
                                   @input="ticketFareForm.errors.ticket_number = ''"
                                   :class="ticketFareForm.errors.ticket_number ? 'border-red-500' : ''"
                                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter Ticket Number">
                            <p x-show="ticketFareForm.errors.ticket_number" x-text="ticketFareForm.errors.ticket_number" class="text-xs text-red-500 mt-1"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Issue Date *</label>
                            <input type="text" x-model="ticketFareForm.date" placeholder="DD-MMM-YY"
                                   @input="ticketFareForm.errors.date = ''"
                                   :class="ticketFareForm.errors.date ? 'border-red-500' : ''"
                                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                            <p x-show="ticketFareForm.errors.date" x-text="ticketFareForm.errors.date" class="text-xs text-red-500 mt-1"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Agent *</label>
                            <select x-model="ticketFareForm.ticket_agent"
                                    @change="ticketFareForm.errors.ticket_agent = ''"
                                    :class="ticketFareForm.errors.ticket_agent ? 'border-red-500' : ''"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select Agent</option>
                                <template x-for="agent in ticketAgents" :key="agent.id">
                                    <option :value="agent.name" x-text="agent.name"></option>
                                </template>
                            </select>
                            <p x-show="ticketFareForm.errors.ticket_agent" x-text="ticketFareForm.errors.ticket_agent" class="text-xs text-red-500 mt-1"></p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <button type="button" @click="newTicketFareForm.visible = !newTicketFareForm.visible" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        <span x-text="newTicketFareForm.visible ? '- Hide' : '+ Add New Ticket'"></span>
                    </button>
                </div>

                <div x-show="newTicketFareForm.visible" x-cloak class="mb-4 p-4 border border-blue-200 rounded-lg bg-blue-50/30">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3">New Ticket Fare</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Route Type</label>
                            <select x-model="newTicketFareForm.route_type" @change="handleNewTicketRouteTypeChange()" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-slate-400">
                                <option value="">Select</option>
                                <option value="oneway_inbound">One Way-Inbound</option>
                                <option value="oneway_outbound">One Way-Outbound</option>
                                <option value="round">Round</option>
                                <option value="multi_city">Multi City</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Flight Type</label>
                            <select x-model="newTicketFareForm.flight_type" @change="handleNewTicketFlightTypeChange()" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-slate-400">
                                <option value="">Select</option>
                                <option value="direct">Direct</option>
                                <option value="transit">Transit</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Airline</label>
                            <select x-model="newTicketFareForm.airline_id" @change="onNewAirlineSelectChange($event)" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-slate-400">
                                <option value="">Select</option>
                                <template x-for="a in airlinesList" :key="a.id">
                                    <option :value="a.id" x-text="a.name"></option>
                                </template>
                                <option value="__add_new__">+ Add New</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Class</label>
                            <select x-model="newTicketFareForm.airline_classes_id" @change="onNewClassSelectChange($event)" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-slate-400">
                                <option value="">Select</option>
                                <template x-for="ac in filteredNewClasses" :key="ac.id">
                                    <option :value="ac.id" x-text="ac.class_name"></option>
                                </template>
                                <option value="__add_new__">+ Add New</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-slate-600 mb-1">Route</label>
                            <select x-model="newTicketFareForm.route_id" @change="onNewRouteSelectChange($event)" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-slate-400">
                                <option value="">Select</option>
                                <template x-for="r in filteredNewRoutes" :key="r.id">
                                    <option :value="r.id" x-text="r.display" :data-route-type="r.route_type" :data-flight-type="r.flight_type" :data-airline-id="r.airline_id"></option>
                                </template>
                                <option value="__add_new__">+ Add New</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Ticket Type</label>
                            <select x-model="newTicketFareForm.ticket_type" @change="handleNewTicketTypeChange()" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-slate-400">
                                <option value="regular">Regular</option>
                                <option value="offer">Offer</option>
                                <option value="group">Group</option>
                            </select>
                        </div>
                        <div x-show="newTicketFareForm.ticket_type === 'offer'">
                            <label class="block text-xs text-slate-600 mb-1">Offer Price</label>
                            <input type="number" x-model="newTicketFareForm.offer_price" min="0" step="0.01" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Effective From</label>
                            <input type="date" x-model="newTicketFareForm.effective_from" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Effective To</label>
                            <input type="date" x-model="newTicketFareForm.effective_to" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Net Fare (SAR)</label>
                            <input type="number" x-model="newTicketFareForm.net_fare" min="0" step="0.01" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Selling Fare (SAR)</label>
                            <input type="number" x-model="newTicketFareForm.selling_fare" min="0" step="0.01" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Child Fare %</label>
                            <input type="number" x-model="newTicketFareForm.child_fare_percentage" min="0" max="100" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Infant Fare %</label>
                            <input type="number" x-model="newTicketFareForm.infant_fare_percentage" min="0" max="100" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded">
                        </div>
                    </div>
                    <div x-show="newTicketFareForm.route_type && (newTicketFareForm.route_type === 'round' || newTicketFareForm.route_type === 'oneway_inbound')">
                        <label class="block text-xs text-slate-600 mb-1">Inbound Baggage</label>
                        <div class="grid grid-cols-3 gap-2 mb-2">
                            <div><label class="block text-xs text-slate-500">Adult</label><input type="number" x-model="newTicketFareForm.inbound_adult" min="0" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                            <div><label class="block text-xs text-slate-500">Child</label><input type="number" x-model="newTicketFareForm.inbound_child" min="0" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                            <div><label class="block text-xs text-slate-500">Infant</label><input type="number" x-model="newTicketFareForm.inbound_infant" min="0" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                        </div>
                    </div>
                    <div x-show="newTicketFareForm.route_type && (newTicketFareForm.route_type === 'round' || newTicketFareForm.route_type === 'oneway_outbound')">
                        <label class="block text-xs text-slate-600 mb-1">Outbound Baggage</label>
                        <div class="grid grid-cols-3 gap-2 mb-2">
                            <div><label class="block text-xs text-slate-500">Adult</label><input type="number" x-model="newTicketFareForm.outbound_adult" min="0" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                            <div><label class="block text-xs text-slate-500">Child</label><input type="number" x-model="newTicketFareForm.outbound_child" min="0" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                            <div><label class="block text-xs text-slate-500">Infant</label><input type="number" x-model="newTicketFareForm.outbound_infant" min="0" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                        </div>
                    </div>
                    <div x-show="newTicketFareForm.ticket_type === 'group'" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                        <div><label class="block text-xs text-slate-600 mb-1">PNR</label><input type="text" x-model="newTicketFareForm.pnr" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                        <div><label class="block text-xs text-slate-600 mb-1">Qty</label><input type="number" x-model="newTicketFareForm.ticket_qty" min="1" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                        <div><label class="block text-xs text-slate-600 mb-1">Inbound Date</label><input type="date" x-model="newTicketFareForm.inbound_date" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                        <div><label class="block text-xs text-slate-600 mb-1">Outbound Date</label><input type="date" x-model="newTicketFareForm.outbound_date" class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded"></div>
                    </div>
                    <div class="flex items-center gap-4 mb-3">
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="newTicketFareForm.is_non_refundable" class="w-3 h-3"> Non-Refundable</label>
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="newTicketFareForm.is_non_exchangable" class="w-3 h-3"> Non-Exchangeable</label>
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="newTicketFareForm.with_meal" class="w-3 h-3"> With Meal</label>
                    </div>
                    <button type="button" @click="saveNewTicketFare()" :disabled="newTicketFareForm.saving" class="text-xs px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                        <span x-text="newTicketFareForm.saving ? 'Saving...' : 'Create Ticket Fare'"></span>
                    </button>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Travel Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route</label>
                            <input type="text" x-model="ticketFareForm.route" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
                            <input type="text" x-model="ticketFareForm.airline" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Class</label>
                            <input type="text" x-model="ticketFareForm.travel_class" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Passenger Type</label>
                            <input type="text" x-model="ticketFareForm.passenger_type" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Fare Calculation</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div x-show="$store.currency.mode === 'SAR' || $store.currency.mode === undefined">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR) *</label>
                                <input type="number" x-model="ticketFareForm.selling_fare" min="0" step="0.000001"
                                       @input="handleTicketFareSarInput('selling_fare'); ticketFareForm.errors.selling_fare = ''"
                                       :class="ticketFareForm.errors.selling_fare ? 'border-red-500' : ''"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                                <p x-show="ticketFareForm.errors.selling_fare" x-text="ticketFareForm.errors.selling_fare" class="text-xs text-red-500 mt-1"></p>
                            </div>
                            <div x-show="$store.currency.mode === 'BDT'">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (BDT) *</label>
                                <input type="number" x-model="ticketFareForm.selling_fare_bdt" min="0" step="0.000001"
                                       @input="handleTicketFareBdtInput('selling_fare'); ticketFareForm.errors.selling_fare = ''"
                                       :class="ticketFareForm.errors.selling_fare ? 'border-red-500' : ''"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                                <input type="number" x-model="ticketFareForm.selling_fare" min="0" step="0.000001" readonly class="w-full mt-1 px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm">
                                <p x-show="ticketFareForm.errors.selling_fare" x-text="ticketFareForm.errors.selling_fare" class="text-xs text-red-500 mt-1"></p>
                            </div>
                        </div>
                        <div>
                            <div x-show="$store.currency.mode === 'SAR' || $store.currency.mode === undefined">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (SAR) *</label>
                                <input type="number" x-model="ticketFareForm.net_fare" min="0" step="0.000001"
                                       @input="handleTicketFareSarInput('net_fare'); ticketFareForm.errors.net_fare = ''"
                                       :class="ticketFareForm.errors.net_fare ? 'border-red-500' : ''"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                                <p x-show="ticketFareForm.errors.net_fare" x-text="ticketFareForm.errors.net_fare" class="text-xs text-red-500 mt-1"></p>
                            </div>
                            <div x-show="$store.currency.mode === 'BDT'">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (BDT) *</label>
                                <input type="number" x-model="ticketFareForm.net_fare_bdt" min="0" step="0.000001"
                                       @input="handleTicketFareBdtInput('net_fare'); ticketFareForm.errors.net_fare = ''"
                                       :class="ticketFareForm.errors.net_fare ? 'border-red-500' : ''"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                                <input type="number" x-model="ticketFareForm.net_fare" min="0" step="0.000001" readonly class="w-full mt-1 px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm">
                                <p x-show="ticketFareForm.errors.net_fare" x-text="ticketFareForm.errors.net_fare" class="text-xs text-red-500 mt-1"></p>
                            </div>
                        </div>
                        <div x-show="ticketFareForm.ticket_type === 'offer'">
                            <div x-show="$store.currency.mode === 'SAR' || $store.currency.mode === undefined">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR) *</label>
                                <input type="number" x-model="ticketFareForm.offer_price" min="0" step="0.000001"
                                       @input="handleTicketFareSarInput('offer_price'); ticketFareForm.errors.offer_price = ''"
                                       :class="ticketFareForm.errors.offer_price ? 'border-red-500' : ''"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                                <p x-show="ticketFareForm.errors.offer_price" x-text="ticketFareForm.errors.offer_price" class="text-xs text-red-500 mt-1"></p>
                            </div>
                            <div x-show="$store.currency.mode === 'BDT'">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (BDT) *</label>
                                <input type="number" x-model="ticketFareForm.offer_price_bdt" min="0" step="0.000001"
                                       @input="handleTicketFareBdtInput('offer_price'); ticketFareForm.errors.offer_price = ''"
                                       :class="ticketFareForm.errors.offer_price ? 'border-red-500' : ''"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                                <input type="number" x-model="ticketFareForm.offer_price" min="0" step="0.000001" readonly class="w-full mt-1 px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 text-sm">
                                <p x-show="ticketFareForm.errors.offer_price" x-text="ticketFareForm.errors.offer_price" class="text-xs text-red-500 mt-1"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="ticketFareForm.showBaggage" class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Baggage Info</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div x-show="ticketFareForm.showInboundDate">
                            <label class="block text-sm text-slate-600 mb-1">Inbound Baggage (KG)</label>
                            <input type="text" x-model="ticketFareForm.baggage_inbound" placeholder="e.g. 30" list="baggageSuggestInbound" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500">
                            <datalist id="baggageSuggestInbound">
                                <option value="30"><option value="40"><option value="50"><option value="20"><option value="25">
                            </datalist>
                        </div>
                        <div x-show="ticketFareForm.showOutboundDate">
                            <label class="block text-sm text-slate-600 mb-1">Outbound Baggage (KG)</label>
                            <input type="text" x-model="ticketFareForm.baggage_outbound" placeholder="e.g. 50" list="baggageSuggestOutbound" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500">
                            <datalist id="baggageSuggestOutbound">
                                <option value="50"><option value="40"><option value="30"><option value="25"><option value="20">
                            </datalist>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Ticket Options</h4>
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 cursor-not-allowed">
                            <input type="checkbox" x-model="ticketFareForm.non_refundable" disabled class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                            <span class="text-sm text-slate-500">Non-Refundable</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-not-allowed">
                            <input type="checkbox" x-model="ticketFareForm.non_exchangeable" disabled class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                            <span class="text-sm text-slate-500">Non-Exchangeable</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4" x-show="ticketFareForm.route_type === 'One Way-Inbound' && !ticketFareForm.isOutboundMode">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="ticketFareForm.outbound_pending" :disabled="ticketFareForm.double_ticket_active" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                        <span class="text-sm text-slate-700">Outbound Ticket Pending</span>
                    </label>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" :disabled="isSubmitting" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium" :class="isSubmitting ? 'opacity-50 cursor-not-allowed' : ''" x-text="ticketFareModalTitle === 'Issue Ticket' ? 'Issue Ticket' : 'Save Changes'"></button>
                    <button type="button" @click="closeTicketFareModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    @include('partials.route-form-modal')
    @include('partials.airline-form-modal')
    @include('partials.class-form-modal')

    {{-- Cancellation Initiation Modal --}}
    <div x-show="cancelModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeCancelModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeCancelModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Cancel Booking</h3>

            <div class="text-sm text-slate-500 mb-4">
                <span x-text="'Invoice ID: #' + cancelBookingId"></span>
            </div>

            {{-- Financial Summary --}}
            <div class="grid grid-cols-3 gap-3 mb-4 p-3 bg-slate-50 rounded-lg text-sm">
                <div>
                    <span class="text-slate-500">Total Paid</span>
                    <p class="font-bold text-green-600" x-text="$currency(cancelTotalPaid, 2)"></p>
                </div>
                <div>
                    <span class="text-slate-500">Total Cost</span>
                    <p class="font-bold text-slate-800" x-text="$currency(cancelCosts.total_cost, 2)"></p>
                </div>
                <div>
                    <span class="text-slate-500">Balance</span>
                    <p class="font-bold" x-text="$currency(cancelTotalPaid - cancelCosts.total_cost, 2)"></p>
                </div>
            </div>

            {{-- Cost Breakdown --}}
            <div class="mb-4">
                <h4 class="text-sm font-medium text-slate-600 mb-2">Costs Incurred</h4>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Fingerprint:</span>
                        <span class="font-medium" x-text="$currency(cancelCosts.fingerprint_cost, 2)"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Visa:</span>
                        <span class="font-medium" x-text="$currency(cancelCosts.visa_cost, 2)"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Ticket:</span>
                        <span class="font-medium" x-text="$currency(cancelCosts.ticket_cost, 2)"></span>
                    </div>
                    <div class="flex justify-between pt-1 border-t border-slate-200 font-semibold">
                        <span class="text-slate-700">Total Cost:</span>
                        <span x-text="$currency(cancelCosts.total_cost, 2)"></span>
                    </div>
                </div>
            </div>

            {{-- Branch & Service Charge --}}
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cancellation Branch *</label>
                    <select x-model="cancelBranchId" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                        <option value="">Select Branch</option>
                        <template x-for="branch in cancelBranches" :key="branch.id">
                            <option :value="branch.id" x-text="branch.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <div x-show="$store.currency.mode === 'BDT'" class="mb-3">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge (BDT)</label>
                        <input type="number" x-model="cancelServiceChargeBdt" min="0" step="0.01"
                            @input="cancelServiceCharge = parseFloat(((parseFloat(cancelServiceChargeBdt) || 0) / ($store.currency.rate || 1)).toFixed(6))"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none"
                            placeholder="Enter amount in BDT">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge (SAR)</label>
                        <input type="number" x-model="cancelServiceCharge" step="0.000001" min="0"
                            :readonly="$store.currency.mode === 'BDT'"
                            :class="{'bg-slate-100 cursor-not-allowed': $store.currency.mode === 'BDT'}"
                            @input="if ($store.currency.mode === 'BDT' && $store.currency.rate > 0) { cancelServiceChargeBdt = Math.round((parseFloat($event.target.value) || 0) * $store.currency.rate * 100) / 100; }"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none"
                            placeholder="Enter amount in SAR">
                    </div>
                </div>
            </div>

            {{-- Refund Amount --}}
            <div class="mb-6 p-3 bg-blue-50 rounded-lg">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-slate-700">Refund Amount:</span>
                    <span class="text-lg font-bold text-blue-700" x-text="$currency(computedRefundAmount, 2)"></span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Refund = Total Paid &minus; Total Cost &minus; Service Charge</p>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3">
                <button type="button" @click="handleCancelSubmit()" :disabled="cancelLoading || !cancelBranchId"
                        class="flex-1 px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition font-medium disabled:bg-slate-300 disabled:cursor-not-allowed">
                    <span x-text="cancelLoading ? 'Processing...' : 'Submit Cancellation'"></span>
                </button>
                <button type="button" @click="closeCancelModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    {{-- Ticket Remarks Modal --}}
    <div x-show="remarksModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeRemarksModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeRemarksModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Ticket Remarks</h3>

            <template x-if="!remarksEditMode">
                <div>
                    <template x-if="remarksContent">
                        <p class="text-slate-700 text-sm whitespace-pre-wrap" x-text="remarksContent"></p>
                    </template>
                    <template x-if="!remarksContent">
                        <p class="text-slate-400 text-sm italic">No Remarks Entered</p>
                    </template>
                    <div class="flex gap-3 mt-6">
                        <button @click="remarksEditMode = true" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Edit</button>
                        <button @click="closeRemarksModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Close</button>
                    </div>
                </div>
            </template>

            <template x-if="remarksEditMode">
                <form @submit.prevent="updateRemarks()">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                            <textarea x-model="remarksForm.text"
                                      class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
                                      rows="4"></textarea>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Update</button>
                        <button type="button" @click="closeRemarksModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>

<style>
.modal-overlay { transition: opacity 0.2s ease; }
.modal-content { transition: transform 0.2s ease, opacity 0.2s ease; }
</style>

<script>
function bookingIndexApp() {
    return {
        activeTab: '{{ $tab ?? 'booking' }}',
        searchTerm: new URL(window.location).searchParams.get('search') || '',
        searchTimeout: null,
        selectedBranchId: '{{ $selectedBranchId }}',
        totalBookingCount: {{ $totalBookingCount }},
        totalBookingPassengerCount: {{ $totalBookingPassengerCount }},
        branchCounts: @json($branchCounts),
        allBookingCount: {{ $allBookingCount }},
        selectedFingerprintStatus: '{{ $selectedFingerprintStatus ?? '' }}',
        selectedVisaStatus: '{{ $selectedVisaStatus ?? '' }}',
        selectedTicketStatus: '{{ $selectedTicketStatus ?? '' }}',
        remarksModalVisible: false,
        remarksEditMode: false,
        editingRemarksIndex: null,
        remarksContent: '',
        remarksForm: { text: '' },
        selectedVisaAgentId: '{{ $selectedVisaAgentId ?? '' }}',
        selectedBookingDateFrom: '{{ $selectedBookingDateFrom ?? '' }}',
        selectedBookingDateTo: '{{ $selectedBookingDateTo ?? '' }}',
        selectedFingerprintLocation: '{{ $selectedFingerprintLocation ?? '' }}',
        selectedBookingStatus: '{{ $selectedBookingStatus ?? '' }}',
        selectedPassengerStatus: '{{ $selectedPassengerStatus ?? '' }}',
        selectedRouteDisplay: '{{ $selectedRouteDisplay ?? '' }}',
        selectedPackageId: '{{ $selectedPackageId ?? '' }}',
        selectedTicketAgentId: '{{ $selectedTicketAgentId ?? '' }}',
        selectedActualFlightFrom: '{{ $selectedActualFlightFrom ?? '' }}',
        selectedActualFlightTo: '{{ $selectedActualFlightTo ?? '' }}',
        selectedReturnDateFrom: '{{ $selectedReturnDateFrom ?? '' }}',
        selectedReturnDateTo: '{{ $selectedReturnDateTo ?? '' }}',
        selectedStatusChangeAction: '{{ $selectedStatusChangeAction ?? '' }}',
        selectedStatusChangeFrom: '{{ $selectedStatusChangeFrom ?? '' }}',
        selectedStatusChangeTo: '{{ $selectedStatusChangeTo ?? '' }}',
        selectedFlightDateRange: '',
        selectedPaymentWise: '{{ $selectedPaymentWise ?? '' }}',
        flightDateRanges: @json($flightDateRanges),
        totalPassengerCount: {{ $totalPassengerCount }},

        init() {
            const raw = sessionStorage.getItem('searchInputBuffer');
            if (raw) {
                sessionStorage.removeItem('searchInputBuffer');
                try {
                    const { value: buffered, time } = JSON.parse(raw);
                    const urlSearch = new URL(window.location).searchParams.get('search') || '';
                    if (buffered !== urlSearch && Date.now() - time < 3000) {
                        this.searchTerm = buffered;
                    }
                } catch (e) {}
            }

            if (this.activeTab === 'passenger') {
                document.body.style.overflow = 'hidden';
            }
            this.$watch('activeTab', (newVal) => {
                document.body.style.overflow = newVal === 'passenger' ? 'hidden' : '';
            });

            window.addEventListener('beforeunload', () => {
                const ref = this.activeTab === 'passenger' ? 'passengerSearchInput' : 'searchInput';
                const input = this.$refs[ref];
                if (input) {
                    sessionStorage.setItem('searchInputBuffer', JSON.stringify({
                        value: input.value,
                        time: Date.now()
                    }));
                }
            });

            window.addEventListener('currency-toggled', () => {
                const r = window.__currencyRate || 0;
                if (r > 0) {
                    const f = this.ticketFareForm;
                    this._converting = true;
                    f.selling_fare_bdt = Math.round(parseFloat(f.selling_fare || 0) * r);
                    f.net_fare_bdt = Math.round(parseFloat(f.net_fare || 0) * r);
                    f.offer_price_bdt = Math.round(parseFloat(f.offer_price || 0) * r);
                    this._converting = false;
                }
                if (this.cancelModalVisible && r > 0) {
                    this.cancelServiceChargeBdt = this.cancelServiceCharge
                        ? Math.round(parseFloat(this.cancelServiceCharge) * r * 100) / 100
                        : '';
                }
            });

            this.$nextTick(() => {
                const ref = this.activeTab === 'passenger' ? 'passengerSearchInput' : 'searchInput';
                if (this.$refs[ref]) {
                    if (!this.$refs[ref].value && this.searchTerm) {
                        this.$refs[ref].value = this.searchTerm;
                    }
                    this.$refs[ref].focus();
                    if (this.$refs[ref].value) {
                        const len = this.$refs[ref].value.length;
                        this.$refs[ref].setSelectionRange(len, len);
                    }
                }
            });

            this.$watch('searchTerm', (val) => {
                if (this.searchTimeout) clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    const url = new URL(window.location);
                    if (val) {
                        url.searchParams.set('search', val);
                    } else {
                        url.searchParams.delete('search');
                    }
                    url.searchParams.delete('page');

                    const input = this.$refs[this.activeTab === 'passenger' ? 'passengerSearchInput' : 'searchInput'];
                    if (input) {
                        sessionStorage.setItem('searchInputBuffer', JSON.stringify({
                            value: input.value,
                            time: Date.now()
                        }));
                    }

                    window.location.href = url.toString();
                }, 1500);
            });

            const url = new URL(window.location);
            const fFrom = url.searchParams.get('flight_date_from');
            const fTo = url.searchParams.get('flight_date_to');
            if (fFrom && fTo && this.flightDateRanges) {
                const match = this.flightDateRanges.find(r => r.start === fFrom && r.end === fTo);
                if (match) this.selectedFlightDateRange = match.id;
            }
        },

        navigateToTab(tab) {
            if (this.activeTab === tab) return;
            const url = new URL(window.location);
            url.searchParams.delete('page');
            if (tab === 'booking') {
                url.searchParams.delete('tab');
            } else {
                url.searchParams.set('tab', tab);
            }
            window.location.href = url.toString();
        },

        onBranchChange() {
            this.totalBookingCount = this.selectedBranchId
                ? (this.branchCounts[this.selectedBranchId] || 0)
                : this.allBookingCount;
            const url = new URL(window.location.href);
            if (this.selectedBranchId) {
                url.searchParams.set('booking_branch_id', this.selectedBranchId);
            } else {
                url.searchParams.delete('booking_branch_id');
            }
            window.location.href = url.toString();
        },

        onFingerprintStatusChange() {
            const url = new URL(window.location.href);
            if (this.selectedFingerprintStatus) {
                url.searchParams.set('fingerprint_status', this.selectedFingerprintStatus);
            } else {
                url.searchParams.delete('fingerprint_status');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onVisaStatusChange() {
            const url = new URL(window.location.href);
            if (this.selectedVisaStatus) {
                url.searchParams.set('visa_status', this.selectedVisaStatus);
            } else {
                url.searchParams.delete('visa_status');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onTicketStatusChange() {
            const url = new URL(window.location.href);
            if (this.selectedTicketStatus) {
                url.searchParams.set('ticket_status', this.selectedTicketStatus);
            } else {
                url.searchParams.delete('ticket_status');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onVisaAgentChange() {
            const url = new URL(window.location.href);
            if (this.selectedVisaAgentId) {
                url.searchParams.set('visa_agent_id', this.selectedVisaAgentId);
            } else {
                url.searchParams.delete('visa_agent_id');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onBookingDateFromChange() {
            const url = new URL(window.location.href);
            if (this.selectedBookingDateFrom) {
                url.searchParams.set('booking_date_from', this.selectedBookingDateFrom);
            } else {
                url.searchParams.delete('booking_date_from');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onBookingDateToChange() {
            const url = new URL(window.location.href);
            if (this.selectedBookingDateTo) {
                url.searchParams.set('booking_date_to', this.selectedBookingDateTo);
            } else {
                url.searchParams.delete('booking_date_to');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onFingerprintLocationChange() {
            const url = new URL(window.location.href);
            if (this.selectedFingerprintLocation) {
                url.searchParams.set('fingerprint_location', this.selectedFingerprintLocation);
            } else {
                url.searchParams.delete('fingerprint_location');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onBookingStatusChange() {
            const url = new URL(window.location.href);
            if (this.selectedBookingStatus) {
                url.searchParams.set('booking_status', this.selectedBookingStatus);
            } else {
                url.searchParams.delete('booking_status');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        clearBookingFilters() {
            const url = new URL(window.location);
            ['search', 'booking_date_from', 'booking_date_to',
             'fingerprint_location', 'booking_status', 'booking_branch_id', 'page'
            ].forEach(p => url.searchParams.delete(p));
            window.location.href = url.toString();
        },

        onPassengerStatusChange() {
            const url = new URL(window.location.href);
            if (this.selectedPassengerStatus) {
                url.searchParams.set('passenger_status', this.selectedPassengerStatus);
            } else {
                url.searchParams.delete('passenger_status');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onRouteChange() {
            const url = new URL(window.location.href);
            if (this.selectedRouteDisplay) {
                url.searchParams.set('route_display', this.selectedRouteDisplay);
            } else {
                url.searchParams.delete('route_display');
            }
            url.searchParams.delete('route_id');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onTicketAgentChange() {
            const url = new URL(window.location.href);
            if (this.selectedTicketAgentId) {
                url.searchParams.set('ticket_agent_id', this.selectedTicketAgentId);
            } else {
                url.searchParams.delete('ticket_agent_id');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onActualFlightFromChange() {
            const url = new URL(window.location.href);
            if (this.selectedActualFlightFrom) {
                url.searchParams.set('actual_flight_from', this.selectedActualFlightFrom);
            } else {
                url.searchParams.delete('actual_flight_from');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onActualFlightToChange() {
            const url = new URL(window.location.href);
            if (this.selectedActualFlightTo) {
                url.searchParams.set('actual_flight_to', this.selectedActualFlightTo);
            } else {
                url.searchParams.delete('actual_flight_to');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onReturnDateFromChange() {
            const url = new URL(window.location.href);
            if (this.selectedReturnDateFrom) {
                url.searchParams.set('return_date_from', this.selectedReturnDateFrom);
            } else {
                url.searchParams.delete('return_date_from');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onReturnDateToChange() {
            const url = new URL(window.location.href);
            if (this.selectedReturnDateTo) {
                url.searchParams.set('return_date_to', this.selectedReturnDateTo);
            } else {
                url.searchParams.delete('return_date_to');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onFlightDateRangeChange() {
            const url = new URL(window.location.href);
            if (this.selectedFlightDateRange) {
                const range = this.flightDateRanges.find(r => r.id == this.selectedFlightDateRange);
                if (range) {
                    url.searchParams.set('flight_date_from', range.start);
                    url.searchParams.set('flight_date_to', range.end);
                }
            } else {
                url.searchParams.delete('flight_date_from');
                url.searchParams.delete('flight_date_to');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onPackageChange() {
            const url = new URL(window.location.href);
            if (this.selectedPackageId) {
                url.searchParams.set('package_id', this.selectedPackageId);
            } else {
                url.searchParams.delete('package_id');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onStatusChangeActionChange() {
            const url = new URL(window.location.href);
            if (this.selectedStatusChangeAction) {
                url.searchParams.set('status_change_action', this.selectedStatusChangeAction);
            } else {
                url.searchParams.delete('status_change_action');
                url.searchParams.delete('status_change_from');
                url.searchParams.delete('status_change_to');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onStatusChangeDateChange() {
            const url = new URL(window.location.href);
            if (this.selectedStatusChangeFrom) {
                url.searchParams.set('status_change_from', this.selectedStatusChangeFrom);
            } else {
                url.searchParams.delete('status_change_from');
            }
            if (this.selectedStatusChangeTo) {
                url.searchParams.set('status_change_to', this.selectedStatusChangeTo);
            } else {
                url.searchParams.delete('status_change_to');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        onPaymentWiseChange() {
            const url = new URL(window.location.href);
            if (this.selectedPaymentWise) {
                url.searchParams.set('payment_wise', this.selectedPaymentWise);
            } else {
                url.searchParams.delete('payment_wise');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },

        clearPassengerFilters() {
            const url = new URL(window.location);
            ['fingerprint_status', 'visa_status', 'ticket_status',
             'visa_agent_id', 'ticket_agent_id', 'passenger_status', 'route_display', 'package_id',
             'booking_branch_id', 'booking_date_from', 'booking_date_to',
             'actual_flight_from', 'actual_flight_to',
             'return_date_from', 'return_date_to',
             'flight_date_from', 'flight_date_to',
             'status_change_action', 'status_change_from', 'status_change_to',
             'payment_wise',
             'search', 'page'
            ].forEach(p => url.searchParams.delete(p));
            url.searchParams.set('tab', 'passenger');
            window.location.href = url.toString();
        },

        passengersVisaData: @json($passengersVisaData),
        passengersTicketData: @json($passengersTicketData),
        isTogglingTicketHold: [],
        passengerStatusMap: @json($passengerStatuses->pluck('id', 'name')),

        ticketAgents: @json($ticketAgents),

        routesList: @json($routesList),
        airlinesList: @json($airlinesList),
        classesList: @json($classesList),
        ticketFaresList: @json($ticketFaresList),

        visaAgents: @json($visaAgents ?? []),
        canEditVisa: {{ $canEditVisa ? 'true' : 'false' }},

        visaSubmitModalVisible: false,
        visaIssueModalVisible: false,
        visaEditModalVisible: false,
        visaResubmitModalVisible: false,
        editingVisaIndex: null,

        visaSubmitForm: {
            agentId: '',
            commissionAgentId: '',
            sellingPrice: 0,
            agentCommission: 0,
            netVisaCost: 0,
            finalCost: 0,
            commissionAgents: [],
            agentCommissionBDT: 0,
            netVisaCostBDT: 0,
            sellingPriceBDT: 0,
            finalCostBDT: 0,
        },

        visaIssueForm: {
            agentName: '',
            visaNumber: '',
            sellingPrice: 0,
            additionalCost: 0,
            finalCost: 0,
            remarks: '',
            sellingPriceBDT: 0,
            additionalCostBDT: 0,
            finalCostBDT: 0,
        },

        visaEditForm: {
            agentId: '',
            visaNumber: '',
            commissionAgentId: '',
            sellingPrice: 0,
            agentCommission: 0,
            netVisaCost: 0,
            additionalCost: 0,
            remarks: '',
            finalCost: 0,
            statusLabel: '',
            commissionAgents: [],
            sellingPriceBDT: 0,
            agentCommissionBDT: 0,
            netVisaCostBDT: 0,
            additionalCostBDT: 0,
            finalCostBDT: 0,
        },

        visaResubmitForm: {
            visa_agent_id: '',
            commission_agent_id: '',
            agent_commission: 0,
            net_visa_cost: 0,
            final_cost: 0,
            agent_commission_bdt: 0,
            net_visa_cost_bdt: 0,
            final_cost_bdt: 0,
        },

        visaCancelModalVisible: false,
        visaCancelForm: {
            cancellation_fee: 0,
            cancellation_fee_bdt: 0,
            remarks: '',
        },

        getComputedStatusId(index) {
            const row = this.passengersTicketData[index];
            if (!row) return '';

            if (row.status && row.status !== 'None') {
                return this.passengerStatusMap[row.status] ?? '';
            }

            const visa = this.passengersVisaData[index]?.visa;

            const fpStatus = row.fingerprint_status;
            const visaStatus = visa?.status;
            const ticketStatus = row.ticket_status;
            const issuedTicketStatus = row.latest_issued_ticket?.status;

            const isFingerprintApproved = fpStatus === 'approved';
            const isVisaSubmitted = visaStatus === 'submitted';
            const isVisaIssued = visaStatus === 'issued';
            const isVisaCancelled = visaStatus === 'cancelled';
            const isTicketIssued = ['issued', 're-issued'].includes(ticketStatus)
                || ['issued', 're-issued'].includes(issuedTicketStatus);

            let statusName = null;
            if (isTicketIssued && isVisaIssued) statusName = 'Ticket Issued';
            else if (isVisaCancelled) statusName = 'Processing';
            else if (isTicketIssued && !isVisaIssued) statusName = 'Ticket Issued before Visa';
            else if (isVisaIssued) statusName = 'Visa Issued';
            else if (isVisaSubmitted) statusName = 'Visa Submitted';
            else if (isFingerprintApproved) statusName = 'Fingerprint Done';

            return statusName ? (this.passengerStatusMap[statusName] ?? '') : '';
        },

        getComputedStatusName(index) {
            const row = this.passengersTicketData[index];
            if (!row) return 'None';

            if (row.status && row.status !== 'None') {
                return row.status;
            }

            const visa = this.passengersVisaData[index]?.visa;

            const fpStatus = row.fingerprint_status;
            const visaStatus = visa?.status;
            const ticketStatus = row.ticket_status;
            const issuedTicketStatus = row.latest_issued_ticket?.status;

            const isFingerprintApproved = fpStatus === 'approved';
            const isVisaSubmitted = visaStatus === 'submitted';
            const isVisaIssued = visaStatus === 'issued';
            const isVisaCancelled = visaStatus === 'cancelled';
            const isTicketIssued = ['issued', 're-issued'].includes(ticketStatus)
                || ['issued', 're-issued'].includes(issuedTicketStatus);

            if (isTicketIssued && isVisaIssued) return 'Ticket Issued';
            if (isVisaCancelled) return 'Processing';
            if (isTicketIssued && !isVisaIssued) return 'Ticket Issued before Visa';
            if (isVisaIssued) return 'Visa Issued';
            if (isVisaSubmitted) return 'Visa Submitted';
            if (isFingerprintApproved) return 'Fingerprint Done';
            return 'None';
        },

        getCommissionAgents(agentId) {
            const agent = this.visaAgents.find(a => a.id == agentId);
            return agent?.commission_agents || [];
        },

        getVisaAgentCost(agentId) {
            const agent = this.visaAgents.find(a => a.id == agentId);
            return agent?.cost || 0;
        },

        openVisaSubmitModal(index) {
            this.editingVisaIndex = index;
            const data = this.passengersVisaData[index];
            const rate = data?.rate || 0;

            this.visaSubmitForm.sellingPrice = data?.visa?.selling_price || 0;
            this.visaSubmitForm.agentCommission = data?.visa?.agent_commission || 0;
            this.visaSubmitForm.netVisaCost = data?.visa?.net_visa_cost || 0;

            this.visaSubmitForm.agentCommissionBDT = rate > 0 ? this.visaSubmitForm.agentCommission * rate : 0;
            this.visaSubmitForm.netVisaCostBDT = rate > 0 ? this.visaSubmitForm.netVisaCost * rate : 0;
            this.visaSubmitForm.sellingPriceBDT = rate > 0 ? this.visaSubmitForm.sellingPrice * rate : 0;

            this.visaSubmitForm.agentId = '';
            this.visaSubmitForm.commissionAgentId = '';
            this.visaSubmitForm.commissionAgents = [];

            this.calculateVisaCost();
            this.visaSubmitModalVisible = true;
        },

        closeVisaSubmitModal() {
            this.editingVisaIndex = null;
            this.visaSubmitModalVisible = false;
        },

        updateSubmitCommissionAgents(agentId) {
            this.visaSubmitForm.commissionAgents = this.getCommissionAgents(agentId);
            this.visaSubmitForm.commissionAgentId = '';
            this.visaSubmitForm.netVisaCost = this.getVisaAgentCost(agentId);
            const rate = this.getCurrentRate();
            this.visaSubmitForm.netVisaCostBDT = rate > 0 ? this.visaSubmitForm.netVisaCost * rate : 0;
            this.calculateVisaCost();
        },

        getCurrentRate() {
            const data = this.passengersVisaData[this.editingVisaIndex];
            return data?.rate || 0;
        },

        convertAgentCommissionToSar() {
            const rate = this.getCurrentRate();
            const bdt = parseFloat(this.visaSubmitForm.agentCommissionBDT) || 0;
            this.visaSubmitForm.agentCommission = rate > 0 ? parseFloat((bdt / rate).toFixed(6)) : 0;
            this.calculateVisaCost();
        },

        convertNetVisaCostToSar() {
            const rate = this.getCurrentRate();
            const bdt = parseFloat(this.visaSubmitForm.netVisaCostBDT) || 0;
            this.visaSubmitForm.netVisaCost = rate > 0 ? parseFloat((bdt / rate).toFixed(6)) : 0;
            this.calculateVisaCost();
        },

        calculateVisaCost() {
            const commission = parseFloat(this.visaSubmitForm.agentCommission) || 0;
            const net = parseFloat(this.visaSubmitForm.netVisaCost) || 0;
            this.visaSubmitForm.finalCost = commission + net;
            const rate = this.getCurrentRate();
            this.visaSubmitForm.finalCostBDT = rate > 0 ? this.visaSubmitForm.finalCost * rate : 0;
        },

        convertResubmitAgentCommissionToSar() {
            const rate = this.getCurrentRate();
            const bdt = parseFloat(this.visaResubmitForm.agent_commission_bdt) || 0;
            this.visaResubmitForm.agent_commission = rate > 0 ? parseFloat((bdt / rate).toFixed(6)) : 0;
            this.calculateResubmitFinal();
        },

        convertResubmitNetVisaCostToSar() {
            const rate = this.getCurrentRate();
            const bdt = parseFloat(this.visaResubmitForm.net_visa_cost_bdt) || 0;
            this.visaResubmitForm.net_visa_cost = rate > 0 ? parseFloat((bdt / rate).toFixed(6)) : 0;
            this.calculateResubmitFinal();
        },

        calculateResubmitFinal() {
            const commission = parseFloat(this.visaResubmitForm.agent_commission) || 0;
            const net = parseFloat(this.visaResubmitForm.net_visa_cost) || 0;
            this.visaResubmitForm.final_cost = commission + net;
            const rate = this.getCurrentRate();
            this.visaResubmitForm.final_cost_bdt = rate > 0 ? this.visaResubmitForm.final_cost * rate : 0;
        },

        convertCancelFeeToSar() {
            const rate = this.getCurrentRate();
            const bdt = parseFloat(this.visaCancelForm.cancellation_fee_bdt) || 0;
            this.visaCancelForm.cancellation_fee = rate > 0 ? parseFloat((bdt / rate).toFixed(6)) : 0;
        },

        handleVisaSubmit() {
            if (this.editingVisaIndex === null) return;
            const data = this.passengersVisaData[this.editingVisaIndex];
            if (!data) return;

            const payload = {
                passenger_id: data.id,
                booking_id: data.booking_id,
                visa_agent_id: this.visaSubmitForm.agentId || null,
                commission_agent_id: this.visaSubmitForm.commissionAgentId || null,
                agent_commission: this.visaSubmitForm.agentCommission,
                net_visa_cost: this.visaSubmitForm.netVisaCost,
                final_cost: this.visaSubmitForm.finalCost,
            };

            fetch('/bookings/' + payload.booking_id + '/passengers/' + payload.passenger_id + '/visa-submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const sub = res.visa_submission;
                    this.$nextTick(() => {
                        if (data.visa) {
                            data.visa.agent_id = sub.visa_agent_id;
                            data.visa.agent = sub.visa_agent?.name || '';
                            data.visa.commission_agent_id = sub.commission_agent_id;
                            data.visa.commission_agent = sub.commission_agent?.name || '';
                            data.visa.agent_commission = sub.agent_commission;
                            data.visa.net_visa_cost = sub.net_visa_cost;
                            data.visa.final_cost = sub.final_cost;
                            data.visa.status = 'submitted';
                        } else {
                            data.visa = {
                                id: sub.id,
                                agent_id: sub.visa_agent_id,
                                agent: sub.visa_agent?.name || '',
                                visa_number: sub.visa_number || '',
                                selling_price: sub.visa_selling_price?.selling_price || this.visaSubmitForm.sellingPrice,
                                agent_commission: sub.agent_commission,
                                net_visa_cost: sub.net_visa_cost,
                                additional_cost: sub.additional_cost || 0,
                                remarks: sub.remarks || '',
                                final_cost: sub.final_cost,
                                commission_agent_id: sub.commission_agent_id,
                                commission_agent: sub.commission_agent?.name || '',
                                status: 'submitted'
                            };
                        }
                    });
                    this.showToast('Visa submitted successfully');
                    this.closeVisaSubmitModal();
                } else {
                    alert(res.message || 'Failed to submit visa');
                }
            })
            .catch(err => {
                console.error('Visa submit error:', err);
                alert('Failed to submit visa');
            });
        },

        openVisaIssueModal(index) {
            this.editingVisaIndex = index;
            const data = this.passengersVisaData[index];
            const visa = data?.visa;
            const rate = data?.rate || 0;

            const baseCost = (visa?.final_cost || 0) - (visa?.additional_cost || 0);
            this._visaIssueBaseCost = baseCost;
            this.visaIssueForm.agentName = visa?.agent || '';
            this.visaIssueForm.visaNumber = visa?.visa_number || '';
            this.visaIssueForm.sellingPrice = visa?.selling_price || 0;
            this.visaIssueForm.additionalCost = visa?.additional_cost || 0;
            this.visaIssueForm.finalCost = visa?.final_cost || 0;
            this.visaIssueForm.remarks = visa?.remarks || '';

            this.visaIssueForm.sellingPriceBDT = rate > 0 ? this.visaIssueForm.sellingPrice * rate : 0;
            this.visaIssueForm.additionalCostBDT = rate > 0 ? this.visaIssueForm.additionalCost * rate : 0;

            this.calculateVisaIssueFinal();
            this.visaIssueModalVisible = true;
        },

        closeVisaIssueModal() {
            this.editingVisaIndex = null;
            this.visaIssueModalVisible = false;
        },

        convertAdditionalCostToSar() {
            const rate = this.getCurrentRate();
            const bdt = parseFloat(this.visaIssueForm.additionalCostBDT) || 0;
            this.visaIssueForm.additionalCost = rate > 0 ? parseFloat((bdt / rate).toFixed(6)) : 0;
            this.calculateVisaIssueFinal();
        },

        calculateVisaIssueFinal() {
            const additional = parseFloat(this.visaIssueForm.additionalCost) || 0;
            this.visaIssueForm.finalCost = (this._visaIssueBaseCost || 0) + additional;
            const rate = this.getCurrentRate();
            this.visaIssueForm.finalCostBDT = rate > 0 ? this.visaIssueForm.finalCost * rate : 0;
        },

        handleVisaIssue() {
            if (this.editingVisaIndex === null) return;
            const data = this.passengersVisaData[this.editingVisaIndex];
            if (!data?.visa) return;

            const payload = {
                passenger_id: data.id,
                booking_id: data.booking_id,
                visa_number: this.visaIssueForm.visaNumber,
                additional_cost: this.visaIssueForm.additionalCost,
                final_cost: this.visaIssueForm.finalCost,
                remarks: this.visaIssueForm.remarks,
            };

            fetch('/bookings/' + payload.booking_id + '/passengers/' + payload.passenger_id + '/visa-issue', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const sub = res.visa_submission;
                    data.visa.visa_number = sub.visa_number;
                    data.visa.additional_cost = sub.additional_cost;
                    data.visa.final_cost = sub.final_cost;
                    data.visa.remarks = sub.remarks || '';
                    data.visa.status = 'issued';
                    this.showToast('Visa issued successfully');
                    this.closeVisaIssueModal();
                } else {
                    alert(res.message || 'Failed to issue visa');
                }
            })
            .catch(err => {
                console.error('Visa issue error:', err);
                alert('Failed to issue visa');
            });
        },

        openVisaEditModal(index) {
            this.editingVisaIndex = index;
            const data = this.passengersVisaData[index];
            const visa = data?.visa;
            if (!visa) return;
            const rate = data?.rate || 0;

            this.visaEditForm.agentId = visa.agent_id || '';
            this.visaEditForm.visaNumber = visa.visa_number || '';

            this.visaEditForm.commissionAgents = visa.agent_id ? this.getCommissionAgents(visa.agent_id) : [];
            this.$nextTick(() => {
                this.visaEditForm.commissionAgentId = visa.commission_agent_id || '';
            });

            this.visaEditForm.sellingPrice = visa.selling_price || 0;
            this.visaEditForm.agentCommission = visa.agent_commission || 0;
            this.visaEditForm.netVisaCost = visa.net_visa_cost || 0;
            this.visaEditForm.additionalCost = visa.additional_cost || 0;
            this.visaEditForm.remarks = visa.remarks || '';
            this.visaEditForm.statusLabel = visa.status === 'issued' ? 'Issued' : 'Pending';

            this.visaEditForm.sellingPriceBDT = rate > 0 ? this.visaEditForm.sellingPrice * rate : 0;
            this.visaEditForm.agentCommissionBDT = rate > 0 ? this.visaEditForm.agentCommission * rate : 0;
            this.visaEditForm.netVisaCostBDT = rate > 0 ? this.visaEditForm.netVisaCost * rate : 0;
            this.visaEditForm.additionalCostBDT = rate > 0 ? this.visaEditForm.additionalCost * rate : 0;

            this.calculateVisaEditFinal();
            this.visaEditModalVisible = true;
        },

        closeVisaEditModal() {
            this.editingVisaIndex = null;
            this.visaEditModalVisible = false;
        },

        openVisaResubmitModal(index) {
            this.editingVisaIndex = index;
            this.visaResubmitForm.visa_agent_id = '';
            this.visaResubmitForm.commission_agent_id = '';
            this.visaResubmitForm.agent_commission = 0;
            this.visaResubmitForm.net_visa_cost = 0;
            this.visaResubmitForm.agent_commission_bdt = 0;
            this.visaResubmitForm.net_visa_cost_bdt = 0;
            this.calculateResubmitFinal();
            this.visaResubmitModalVisible = true;
        },

        closeVisaResubmitModal() {
            this.editingVisaIndex = null;
            this.visaResubmitModalVisible = false;
        },

        updateResubmitCommissionAgents(agentId) {
            this.visaResubmitForm.commission_agent_id = '';
            const agent = this.visaAgents.find(a => a.id == agentId);
            if (agent) {
                this.visaResubmitForm.net_visa_cost = agent.cost || 0;
                const rate = this.getCurrentRate();
                this.visaResubmitForm.net_visa_cost_bdt = rate > 0 ? this.visaResubmitForm.net_visa_cost * rate : 0;
            }
            this.calculateResubmitFinal();
        },

        handleVisaResubmit() {
            if (this.editingVisaIndex === null) return;
            const data = this.passengersVisaData[this.editingVisaIndex];
            if (!data) return;

            const payload = {
                visa_agent_id: this.visaResubmitForm.visa_agent_id || null,
                commission_agent_id: this.visaResubmitForm.commission_agent_id || null,
                agent_commission: this.visaResubmitForm.agent_commission,
                net_visa_cost: this.visaResubmitForm.net_visa_cost,
            };

            fetch('/bookings/' + data.booking_id + '/passengers/' + data.id + '/visa-resubmit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const sub = res.visa_submission;
                    this.$nextTick(() => {
                        if (data.visa) {
                            data.visa.agent_id = sub.visa_agent_id;
                            data.visa.agent = sub.visa_agent?.name || '';
                            data.visa.commission_agent_id = sub.commission_agent_id;
                            data.visa.commission_agent = sub.commission_agent?.name || '';
                            data.visa.agent_commission = sub.agent_commission;
                            data.visa.net_visa_cost = sub.net_visa_cost;
                            data.visa.final_cost = sub.final_cost;
                            data.visa.status = 'submitted';
                        }
                    });
                    this.closeVisaResubmitModal();
                    this.showToast('Visa re-submitted successfully');
                } else {
                    alert(res.message || 'Re-submit failed');
                }
            })
            .catch(err => {
                console.error('Visa re-submit error:', err);
                alert('Failed to re-submit visa');
            });
        },

        openVisaCancelModal(index) {
            this.editingVisaIndex = index;
            this.visaCancelForm.cancellation_fee = 0;
            this.visaCancelForm.cancellation_fee_bdt = 0;
            this.visaCancelForm.remarks = '';
            this.visaCancelModalVisible = true;
        },

        closeVisaCancelModal() {
            this.editingVisaIndex = null;
            this.visaCancelModalVisible = false;
        },

        handleVisaCancel() {
            if (this.editingVisaIndex === null) return;
            const data = this.passengersVisaData[this.editingVisaIndex];
            if (!data) return;

            fetch('/bookings/' + data.booking_id + '/passengers/' + data.id + '/visa-cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    cancellation_fee: this.visaCancelForm.cancellation_fee,
                    remarks: this.visaCancelForm.remarks,
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    this.$nextTick(() => {
                        if (data.visa) {
                            data.visa.status = 'cancelled';
                            data.visa.agent = '';
                            data.visa.agent_id = null;
                        }
                    });
                    this.closeVisaCancelModal();
                    this.showToast('Visa cancelled successfully');
                } else {
                    alert(res.message || 'Cancellation failed');
                }
            })
            .catch(err => {
                console.error('Visa cancel error:', err);
                alert('Failed to cancel visa');
            });
        },

        updateEditCommissionAgents(agentId) {
            this.visaEditForm.commissionAgents = this.getCommissionAgents(agentId);
            this.visaEditForm.commissionAgentId = '';
            this.visaEditForm.netVisaCost = this.getVisaAgentCost(agentId);
            const rate = this.getCurrentRate();
            this.visaEditForm.netVisaCostBDT = rate > 0 ? this.visaEditForm.netVisaCost * rate : 0;
            this.calculateVisaEditFinal();
        },

        convertEditAgentCommissionToSar() {
            const rate = this.getCurrentRate();
            const bdt = parseFloat(this.visaEditForm.agentCommissionBDT) || 0;
            this.visaEditForm.agentCommission = rate > 0 ? parseFloat((bdt / rate).toFixed(6)) : 0;
            this.calculateVisaEditFinal();
        },

        convertEditAdditionalCostToSar() {
            const rate = this.getCurrentRate();
            const bdt = parseFloat(this.visaEditForm.additionalCostBDT) || 0;
            this.visaEditForm.additionalCost = rate > 0 ? parseFloat((bdt / rate).toFixed(6)) : 0;
            this.calculateVisaEditFinal();
        },

        convertEditNetVisaCostToSar() {
            const rate = this.getCurrentRate();
            const bdt = parseFloat(this.visaEditForm.netVisaCostBDT) || 0;
            this.visaEditForm.netVisaCost = rate > 0 ? parseFloat((bdt / rate).toFixed(6)) : 0;
            this.calculateVisaEditFinal();
        },

        calculateVisaEditFinal() {
            const commission = parseFloat(this.visaEditForm.agentCommission) || 0;
            const net = parseFloat(this.visaEditForm.netVisaCost) || 0;
            const additional = parseFloat(this.visaEditForm.additionalCost) || 0;
            this.visaEditForm.finalCost = commission + net + additional;
            const rate = this.getCurrentRate();
            this.visaEditForm.finalCostBDT = rate > 0 ? this.visaEditForm.finalCost * rate : 0;
        },

        handleVisaEdit() {
            if (this.editingVisaIndex === null) return;
            const data = this.passengersVisaData[this.editingVisaIndex];
            if (!data?.visa) return;

            const payload = {
                passenger_id: data.id,
                booking_id: data.booking_id,
                visa_agent_id: this.visaEditForm.agentId || null,
                visa_number: this.visaEditForm.visaNumber,
                commission_agent_id: this.visaEditForm.commissionAgentId || null,
                agent_commission: this.visaEditForm.agentCommission,
                net_visa_cost: this.visaEditForm.netVisaCost,
                additional_cost: this.visaEditForm.additionalCost,
                final_cost: this.visaEditForm.finalCost,
                remarks: this.visaEditForm.remarks,
            };

            fetch('/bookings/' + payload.booking_id + '/passengers/' + payload.passenger_id + '/visa-edit', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const sub = res.visa_submission;
                    data.visa.agent_id = sub.visa_agent_id;
                    data.visa.agent = sub.visa_agent?.name || '';
                    data.visa.visa_number = sub.visa_number || '';
                    data.visa.commission_agent_id = sub.commission_agent_id;
                    data.visa.commission_agent = sub.commission_agent?.name || '';
                    data.visa.agent_commission = sub.agent_commission;
                    data.visa.net_visa_cost = sub.net_visa_cost;
                    data.visa.additional_cost = sub.additional_cost;
                    data.visa.remarks = sub.remarks || '';
                    data.visa.final_cost = sub.final_cost;
                    this.showToast('Visa updated successfully');
                    this.closeVisaEditModal();
                } else {
                    alert(res.message || 'Failed to update visa');
                }
            })
            .catch(err => {
                console.error('Visa edit error:', err);
                alert('Failed to update visa');
            });
        },

        isSubmitting: false,
        isTicketFareModalOpen: false,
        editingPassengerIndex: null,
        ticketFareModalTitle: 'Issue Ticket',
        isTicketInfoModalOpen: false,
        ticketInfoPassengerIndex: null,

        ticketFareForm: {
            ticket_type: '',
            group_ticket_id: '',
            route_type: '',
            flight_type: '',
            ticket_option: '',
            inbound_date: '',
            outbound_date: '',
            pnr: '',
            ticket_number: '',
            date: (() => { const d = new Date(); const ms = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; return d.getDate() + '-' + ms[d.getMonth()] + '-' + String(d.getFullYear()).slice(-2); })(),
            ticket_agent: '',
            route: '',
            airline: '',
            travel_class: '',
            passenger_type: '',
            selling_fare: 0,
            selling_fare_bdt: '',
            net_fare: 0,
            net_fare_bdt: '',
            offer_price: 0,
            offer_price_bdt: '',
            baggage_inbound: '',
            baggage_outbound: '',
            non_refundable: false,
            non_exchangeable: false,
            outbound_pending: false,
            isOutboundMode: false,
            issued_ticket_id: null,
            clear_double_ticket: false,
            double_ticket_active: false,
            showInboundDate: false,
            showOutboundDate: false,
            showBaggage: false,
            errors: {
                pnr: '',
                ticket_number: '',
                date: '',
                ticket_agent: '',
                selling_fare: '',
                net_fare: '',
                offer_price: '',
                inbound_date: '',
                outbound_date: '',
            },
        },

        _converting: false,
        _initLock: false,

        newTicketFareForm: {
            visible: false,
            saving: false,
            route_type: '',
            flight_type: '',
            airline_id: '',
            airline_classes_id: '',
            route_id: '',
            ticket_type: 'regular',
            effective_from: '',
            effective_to: '',
            net_fare: '',
            selling_fare: '',
            offer_price: '',
            child_fare_percentage: 70,
            infant_fare_percentage: 30,
            with_meal: false,
            pnr: '',
            ticket_qty: 1,
            inbound_date: '',
            outbound_date: '',
            is_non_refundable: false,
            is_non_exchangable: false,
            inbound_adult: '',
            inbound_child: '',
            inbound_infant: '',
            outbound_adult: '',
            outbound_child: '',
            outbound_infant: '',
        },

        toggleTicketHold(index) {
            const row = this.passengersTicketData[index];
            if (!row) return;

            this.isTogglingTicketHold[index] = true;

            fetch(`/passengers/${row.id}/toggle-ticket-hold`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.passengersTicketData[index].is_ticket_held = data.is_ticket_held;
                }
            })
            .finally(() => {
                this.isTogglingTicketHold[index] = false;
            });
        },

        rowHasPendingOutbound(index) {
            const row = this.passengersTicketData[index];
            if (!row || row.is_cancelled) return false;
            return (row.all_issued_tickets || []).some(t => t.issue_type === 'pending_outbound' && ['pending', 'awaiting-group'].includes(t.status));
        },

        rowHasIssuedOutbound(index) {
            const row = this.passengersTicketData[index];
            if (!row || row.is_cancelled) return false;
            return (row.all_issued_tickets || []).some(t => t.issue_type === 'pending_outbound' && (t.status === 'issued' || t.status === 're-issued'));
        },

        regularTicketCoversOutbound(index) {
            const row = this.passengersTicketData[index];
            if (!row || row.is_cancelled) return false;
            const lit = row.latest_issued_ticket;
            if (!lit || lit.issue_type !== 'regular') return false;
            if (!['issued', 're-issued'].includes(lit.status)) return false;
            return ['round', 'multi_city'].includes(lit.route_type);
        },

        canShowIssueOutInMenu(index) {
            const row = this.passengersTicketData[index];
            if (!row || row.is_cancelled) return false;
            if (this.regularTicketCoversOutbound(index)) return false;
            if (!row.package_is_double_ticket && !row.is_double_ticket) return false;
            if (!row.outbound_ticket_fare) return false;
            const hasIssuedOutbound = (row.all_issued_tickets || []).some(
                t => t.issue_type === 'pending_outbound' && ['issued', 're-issued'].includes(t.status)
            );
            if (hasIssuedOutbound) return false;
            if (this.rowHasPendingOutbound(index) && this.hasRegularIssued(index) && row.fingerprint_status === 'approved') {
                return false;
            }
            return true;
        },

        hasRegularIssued(index) {
            const row = this.passengersTicketData[index];
            if (!row) return false;
            const regular = (row.all_issued_tickets || []).find(
                t => !t.issue_type || t.issue_type === 'regular'
            );
            return regular && ['issued', 're-issued'].includes(regular.status);
        },

        rowHasConfirmableTickets(index) {
            const row = this.passengersTicketData[index];
            if (!row || row.is_cancelled) return false;
            const hasConfirmable = (row.all_issued_tickets || []).some(t => ['pending', 'refunded'].includes(t.status));
            if (hasConfirmable) return true;
            if (row.package_is_double_ticket) {
                return !(row.all_issued_tickets || []).some(t => t.issue_type === 'pending_outbound');
            }
            return false;
        },

        getTicketStatuses(index) {
            const row = this.passengersTicketData[index];
            if (!row || row.is_cancelled) return [];

            const R = row.latest_issued_ticket;
            const PO = row.pending_outbound_issued_ticket;
            const statuses = [];

            if (!R) {
                const s = [];
                if (row.is_ticket_held) s.push('Hold');
                if (PO) {
                    if (PO.status === 'pending') s.push('Pending');
                    if (PO.status === 'issued') s.push('Outbound Issued');
                    if (PO.status === 'awaiting-group') s.push('Awaiting Group Outbound');
                    if (PO.status === 're-issued') s.push('Re-Issued');
                    if (PO.status === 'refunded') s.push('Refunded');
                }
                return s;
            }

            const OP = R.outbound_pending ?? false;
            const rt = (R.route_type || '').toLowerCase();
            const hasInb = !!R.inbound_date;
            const hasOut = !!R.outbound_date;

            const isInboundRoute = rt === 'oneway_inbound' || (!hasOut && rt !== 'oneway_outbound' && rt !== 'round' && rt !== 'multi_city');
            const isOutboundRoute = rt === 'oneway_outbound' || (!hasInb && rt !== 'oneway_inbound' && rt !== 'round' && rt !== 'multi_city');
            const isRoundOrMulti = rt === 'round' || rt === 'multi_city' || (hasInb && hasOut && rt !== 'oneway_inbound' && rt !== 'oneway_outbound');

            if (row.is_ticket_held) statuses.push('Hold');

            if (OP) {
                if (R.status === 'pending' && PO?.status === 'pending')
                    statuses.push('Pending');
                if (R.status === 'issued' && PO?.status === 'pending')
                    statuses.push('Inbound Issued');
                if (R.status === 'pending' && PO?.status === 'issued')
                    statuses.push('Outbound Issued');
                if (R.status === 'issued' && PO?.status === 'issued')
                    statuses.push('Both Issued');
                if (R.status === 'awaiting-group' && PO?.status !== 'awaiting-group')
                    statuses.push('Awaiting Group Inbound');
                if (R.status !== 'awaiting-group' && PO?.status === 'awaiting-group')
                    statuses.push('Awaiting Group Outbound');
                if (R.status === 'awaiting-group' && PO?.status === 'awaiting-group')
                    statuses.push('Awaiting Group Both');
                if (R.status === 're-issued' || PO?.status === 're-issued')
                    statuses.push('Partial Re-Issued');
                if (R.status === 're-issued' && PO?.status === 're-issued')
                    statuses.push('Re-Issued');
                if (R.status === 'refunded' || PO?.status === 'refunded')
                    statuses.push('Partial Refunded');
                if (R.status === 'refunded' && PO?.status === 'refunded')
                    statuses.push('Refunded');
            } else {
                if (R.status === 'pending')
                    statuses.push('Pending');
                if (R.status === 'awaiting-group')
                    statuses.push(row.package_is_double_ticket ? 'Awaiting Group Inbound' : 'Awaiting Group');
                if (isInboundRoute && R.status === 'issued')
                    statuses.push('Inbound Issued');
                if (isOutboundRoute && R.status === 'issued')
                    statuses.push('Outbound Issued');
                if (isRoundOrMulti && R.status === 'issued')
                    statuses.push('Both Issued');
                if (R.status === 're-issued')
                    statuses.push('Re-Issued');
                if (R.status === 'refunded')
                    statuses.push('Refunded');
            }

            if (statuses.includes('Re-Issued') && statuses.includes('Partial Re-Issued'))
                statuses.splice(statuses.indexOf('Partial Re-Issued'), 1);
            if (statuses.includes('Refunded') && statuses.includes('Partial Refunded'))
                statuses.splice(statuses.indexOf('Partial Refunded'), 1);

            return statuses;
        },

        statusColorClass(status) {
            const map = {
                'Pending': 'bg-slate-100 text-slate-700',
                'Inbound Issued': 'bg-blue-100 text-blue-700',
                'Outbound Issued': 'bg-cyan-100 text-cyan-800',
                'Both Issued': 'bg-green-100 text-green-700',
                'Awaiting Group': 'bg-yellow-100 text-yellow-700',
                'Awaiting Group Inbound': 'bg-amber-100 text-amber-700',
                'Awaiting Group Outbound': 'bg-orange-100 text-orange-700',
                'Awaiting Group Both': 'bg-yellow-100 text-yellow-700',
                'Hold': 'bg-purple-100 text-purple-700',
                'Partial Re-Issued': 'bg-indigo-100 text-indigo-700',
                'Re-Issued': 'bg-violet-100 text-violet-700',
                'Partial Refunded': 'bg-rose-100 text-rose-700',
                'Refunded': 'bg-red-100 text-red-700',
            };
            return map[status] || 'bg-slate-100 text-slate-600';
        },

        showThreeButtonsMode(index) {
            const row = this.passengersTicketData[index];
            if (!row) return false;
            const hasRegular = (row.all_issued_tickets || []).some(t => !t.issue_type || t.issue_type === 'regular');
            const hasPendingOutbound = (row.all_issued_tickets || []).some(t => t.issue_type === 'pending_outbound');
            return row.package_is_double_ticket || (hasRegular && hasPendingOutbound);
        },

        hasConfirmableRegular(index) {
            const row = this.passengersTicketData[index];
            if (!row) return false;
            const regular = (row.all_issued_tickets || []).find(t => !t.issue_type || t.issue_type === 'regular');
            return regular && ['pending', 'refunded'].includes(regular.status);
        },

        hasConfirmableOutbound(index) {
            const row = this.passengersTicketData[index];
            if (!row) return false;
            const outbound = (row.all_issued_tickets || []).find(t => t.issue_type === 'pending_outbound');
            return outbound && ['pending', 'refunded'].includes(outbound.status);
        },

        hasNoOutboundTicket(index) {
            const row = this.passengersTicketData[index];
            if (!row) return false;
            return !(row.all_issued_tickets || []).some(t => t.issue_type === 'pending_outbound');
        },

        isRegularTicketIssuedRoundOrMultiCity(index) {
            const row = this.passengersTicketData[index];
            if (!row) return false;
            const regular = (row.all_issued_tickets || []).find(
                t => !t.issue_type || t.issue_type === 'regular'
            );
            return regular
                && regular.status === 'issued'
                && (regular.route_type === 'round' || regular.route_type === 'multi_city');
        },

        showGConfirmIn(index) {
            const row = this.passengersTicketData[index];
            if (!row) return false;
            return this.hasConfirmableRegular(index);
        },

        showGConfirmOut(index) {
            const row = this.passengersTicketData[index];
            if (!row) return false;
            return (this.hasConfirmableOutbound(index) || (this.hasNoOutboundTicket(index) && row.package_is_double_ticket))
                && !this.isRegularTicketIssuedRoundOrMultiCity(index);
        },

        showGConfirmBoth(index) {
            const row = this.passengersTicketData[index];
            if (!row) return false;
            return ((this.hasConfirmableRegular(index) && this.hasConfirmableOutbound(index))
                || (this.hasNoOutboundTicket(index) && row.package_is_double_ticket && this.hasConfirmableRegular(index)))
                && !this.isRegularTicketIssuedRoundOrMultiCity(index);
        },

        confirmTickets(index, action) {
            const row = this.passengersTicketData[index];
            if (!row) return;

            fetch(`/passengers/${row.id}/confirm-group`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify({ action, booking_id: row.booking_id }),
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    row.ticket_status = 'awaiting-group';
                    (row.all_issued_tickets || []).forEach(t => {
                        if (data.updated_ids.includes(t.id)) {
                            t.status = 'awaiting-group';
                        }
                    });
                    if (row.latest_issued_ticket && data.updated_ids.includes(row.latest_issued_ticket.id)) {
                        row.latest_issued_ticket.status = 'awaiting-group';
                    }
                    if (data.created_ticket) {
                        const ct = data.created_ticket;
                        if (!row.all_issued_tickets) row.all_issued_tickets = [];
                        row.all_issued_tickets.push({
                            id: ct.id, net_fare: ct.net_fare ?? 0,
                            status: ct.status, pnr: ct.pnr ?? '',
                            issue_type: 'pending_outbound'
                        });
                    }
                    this.showToast(data.message || 'Tickets confirmed successfully.');
                } else {
                    this.showToast(data.message || 'Failed to confirm tickets.', 'error');
                }
            })
            .catch(err => {
                console.error('Confirm group error:', err);
                this.showToast('Failed to confirm tickets.', 'error');
            });
        },
        
        openRemarksModal(index) {
            const row = this.passengersTicketData[index];
            if (!row) return;
            this.editingRemarksIndex = index;
            this.remarksContent = row.ticket_remarks || '';
            this.remarksForm.text = this.remarksContent;
            this.remarksEditMode = false;
            this.remarksModalVisible = true;
        },

        closeRemarksModal() {
            this.editingRemarksIndex = null;
            this.remarksModalVisible = false;
            this.remarksEditMode = false;
            this.remarksContent = '';
            this.remarksForm.text = '';
        },

        updateRemarks() {
            const row = this.passengersTicketData[this.editingRemarksIndex];
            if (!row) return;

            fetch(`/passengers/${row.id}/ticket-remarks`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify({ ticket_remarks: this.remarksForm.text }),
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    row.ticket_remarks = this.remarksForm.text;
                    this.remarksContent = this.remarksForm.text;
                    this.remarksEditMode = false;
                    this.showToast('Remarks updated successfully.');
                } else {
                    this.showToast(data.message || 'Failed to update remarks.', 'error');
                }
            })
            .catch(err => {
                console.error('Update remarks error:', err);
                this.showToast('Failed to update remarks.', 'error');
            });
        },

        handleIssueOutFromMenu(rowIndex) {
            const row = this.passengersTicketData[rowIndex];
            if (!row) return;

            const existingPending = (row.all_issued_tickets || []).find(
                t => t.issue_type === 'pending_outbound' && ['pending', 'awaiting-group'].includes(t.status)
            );

            if (existingPending) {
                this.openOutboundTicketFareModal(rowIndex);
                return;
            }

            fetch(`/passengers/${row.id}/create-outbound-pending`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const t = data.ticket;
                    if (!row.all_issued_tickets) row.all_issued_tickets = [];
                    const exists = row.all_issued_tickets.some(et => et.id === t.id);
                    if (!exists) {
                        row.all_issued_tickets.push({
                            id: t.id, ticket_number: t.ticket_number || '',
                            issued_date: t.issued_date || '', status: t.status,
                            pnr: t.pnr || '', issue_type: 'pending_outbound',
                            selling_fare: t.selling_fare ?? 0, net_fare: t.net_fare ?? 0,
                            is_refundable: t.is_refundable ?? false,
                            is_exchangeable: t.is_exchangeable ?? false,
                            baggage_inbound: '', baggage_outbound: t.baggage_outbound || '',
                            airline: '', travel_class: '',
                            route: '', route_type: '',
                            ticket_agent_name: t.ticket_agent_name || '',
                            issuer_name: '',
                        });
                    }
                    this.openOutboundTicketFareModal(rowIndex);
                } else {
                    this.showToast(data.message || 'Failed to create pending outbound ticket.', 'error');
                }
            })
            .catch(err => {
                console.error('Create pending outbound error:', err);
                this.showToast('Failed to create pending outbound ticket.', 'error');
            });
        },

        openOutboundTicketFareModal(rowIndex) {
            this.editingPassengerIndex = rowIndex;
            const row = this.passengersTicketData[rowIndex];
            if (!row) return;

            this.ticketFareModalTitle = 'Issue Outbound Ticket';
            this.ticketFareForm.isOutboundMode = true;

            const pendingOutbound = (row.all_issued_tickets || []).find(t => t.issue_type === 'pending_outbound' && ['pending', 'awaiting-group'].includes(t.status));
            this.ticketFareForm.issued_ticket_id = pendingOutbound?.id || null;

            const today = (() => { const d = new Date(); const ms = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; return d.getDate() + '-' + ms[d.getMonth()] + '-' + String(d.getFullYear()).slice(-2); })();
            this.ticketFareForm.ticket_type = '';
            this.ticketFareForm.route_type = 'One Way-Outbound';
            this.ticketFareForm.flight_type = '';
            this.ticketFareForm.inbound_date = '';
            this.ticketFareForm.outbound_date = '';
            this.ticketFareForm.pnr = '';
            this.ticketFareForm.ticket_number = '';
            this.ticketFareForm.date = today;
            this.ticketFareForm.ticket_agent = '';
            this.ticketFareForm.route = '';
            this.ticketFareForm.airline = '';
            this.ticketFareForm.travel_class = '';
            this.ticketFareForm.selling_fare = 0;
            this.ticketFareForm.net_fare = 0;
            this.ticketFareForm.offer_price = 0;
            this.ticketFareForm.baggage_inbound = '';
            this.ticketFareForm.baggage_outbound = '';
            this.ticketFareForm.outbound_pending = false;
            this.ticketFareForm.clear_double_ticket = false;
            this.ticketFareForm.double_ticket_active = false;
            this.ticketFareForm.errors = { inbound_date: '', outbound_date: '', date: '' };

            this.ticketFareForm.passenger_type = row.passenger_type || '';

            if (row.outbound_ticket_fare) {
                const fare = row.outbound_ticket_fare;
                this.ticketFareForm.ticket_type = fare.ticket_type || '';
                this.ticketFareForm.flight_type = fare.flight_type === 'direct' ? 'Direct' : 'Transit';
                this.ticketFareForm.ticket_option = fare.id || '';
                this.ticketFareForm.route = fare.route_display || '';
                this.ticketFareForm.airline = fare.airline || '';
                this.ticketFareForm.travel_class = fare.travel_class || '';
                const pType = row.passenger_type || 'adult';
                this.ticketFareForm.selling_fare = this.calculateFareForPassengerType(fare.selling_fare, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                this.ticketFareForm.net_fare = this.calculateFareForPassengerType(fare.net_fare, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                if (fare.with_offer && fare.offer_price) {
                    this.ticketFareForm.offer_price = this.calculateFareForPassengerType(fare.offer_price, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                }
            }

            this.handleTicketOptionChange();
            this.handleTicketFareRouteTypeChange();
            this.isTicketFareModalOpen = true;
        },

        openOutboundEditTicketFareModal(rowIndex) {
            this.editingPassengerIndex = rowIndex;
            const row = this.passengersTicketData[rowIndex];
            if (!row) return;

            this.ticketFareModalTitle = 'Edit Outbound Ticket';
            this.ticketFareForm.isOutboundMode = true;

            const poit = row.pending_outbound_issued_ticket;
            if (!poit) return;

            this.ticketFareForm.issued_ticket_id = poit.id;

            const today = (() => { const d = new Date(); const ms = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; return d.getDate() + '-' + ms[d.getMonth()] + '-' + String(d.getFullYear()).slice(-2); })();
            this.ticketFareForm.route_type = 'One Way-Outbound';
            this.ticketFareForm.inbound_date = '';
            this.ticketFareForm.outbound_date = poit.outbound_date ? this.formatToDDMMMYY(poit.outbound_date) : '';
            this.ticketFareForm.pnr = poit.pnr || '';
            this.ticketFareForm.ticket_number = poit.ticket_number || '';
            this.ticketFareForm.date = poit.issued_date ? this.formatToDDMMMYY(poit.issued_date) : today;
            this.ticketFareForm.ticket_agent = poit.ticket_agent_name || '';
            this.ticketFareForm.selling_fare = poit.selling_fare || 0;
            this.ticketFareForm.net_fare = poit.net_fare || 0;
            this.ticketFareForm.offer_price = poit.offer_price || 0;
            this.ticketFareForm.baggage_inbound = '';
            this.ticketFareForm.baggage_outbound = poit.baggage_outbound || '';
            this.ticketFareForm.outbound_pending = false;
            this.ticketFareForm.clear_double_ticket = false;
            this.ticketFareForm.double_ticket_active = false;
            this.ticketFareForm.errors = { inbound_date: '', outbound_date: '', date: '' };

            this.ticketFareForm.passenger_type = row.passenger_type || '';

            const fareFromPoit = poit.ticket_fare_id && poit.ticket_type;
            if (fareFromPoit) {
                this.ticketFareForm.ticket_type = poit.ticket_type || '';
                this.ticketFareForm.flight_type = poit.flight_type === 'direct' ? 'Direct' : 'Transit';
                this.ticketFareForm.ticket_option = poit.ticket_fare_id || '';
                this.ticketFareForm.route = poit.route_display || '';
                this.ticketFareForm.airline = poit.airline || '';
                this.ticketFareForm.travel_class = poit.travel_class || '';
            } else if (row.outbound_ticket_fare) {
                const fare = row.outbound_ticket_fare;
                this.ticketFareForm.ticket_type = fare.ticket_type || '';
                this.ticketFareForm.flight_type = fare.flight_type === 'direct' ? 'Direct' : 'Transit';
                this.ticketFareForm.ticket_option = fare.id || '';
                this.ticketFareForm.route = fare.route_display || '';
                this.ticketFareForm.airline = fare.airline || '';
                this.ticketFareForm.travel_class = fare.travel_class || '';
                if (!this.ticketFareForm.selling_fare) {
                    const pType = row.passenger_type || 'adult';
                    this.ticketFareForm.selling_fare = this.calculateFareForPassengerType(fare.selling_fare, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                    this.ticketFareForm.net_fare = this.calculateFareForPassengerType(fare.net_fare, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                }
                if (!this.ticketFareForm.offer_price && fare.with_offer && fare.offer_price) {
                    const pType = row.passenger_type || 'adult';
                    this.ticketFareForm.offer_price = this.calculateFareForPassengerType(fare.offer_price, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                }
                if (!this.ticketFareForm.baggage_outbound) {
                    this.ticketFareForm.baggage_outbound = fare.baggage_outbound || '';
                }
            }

            this.handleTicketOptionChange();
            this.handleTicketFareRouteTypeChange();
            this.isTicketFareModalOpen = true;
        },

        openTicketFareModal(rowIndex) {
            this.editingPassengerIndex = rowIndex;
            const row = this.passengersTicketData[rowIndex];
            if (!row) return;

            const lit = row.latest_issued_ticket;
            const isAlreadyIssued = lit && (lit.status === 'issued' || lit.status === 're-issued');
            this.ticketFareModalTitle = isAlreadyIssued ? 'Edit Ticket' : 'Issue Ticket';

            this.ticketFareForm.isOutboundMode = false;
            this.ticketFareForm.issued_ticket_id = null;

            this.ticketFareForm.route = row.route || '';
            this.ticketFareForm.airline = row.airline || '';
            this.ticketFareForm.travel_class = row.travel_class || '';
            this.ticketFareForm.passenger_type = row.passenger_type || '';

            this.ticketFareForm.errors = { inbound_date: '', outbound_date: '', date: '' };

            const today = (() => { const d = new Date(); const ms = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; return d.getDate() + '-' + ms[d.getMonth()] + '-' + String(d.getFullYear()).slice(-2); })();

            if (lit) {
                this.ticketFareForm.ticket_number = lit.ticket_number || '';
                this.ticketFareForm.pnr = lit.pnr || '';
                this.ticketFareForm.ticket_agent = lit.ticket_agent_name || '';
                this.ticketFareForm.date = this.formatToDDMMMYY(lit.issued_date) || today;
                this.ticketFareForm.inbound_date = this.formatToDDMMMYY(lit.inbound_date) || '';
                this.ticketFareForm.outbound_date = this.formatToDDMMMYY(lit.outbound_date) || '';
                this.ticketFareForm.selling_fare = lit.selling_fare || 0;
                this.ticketFareForm.net_fare = lit.net_fare || 0;
                this.ticketFareForm.offer_price = lit.offer_price || 0;
                this.ticketFareForm.non_refundable = !lit.is_refundable;
                this.ticketFareForm.non_exchangeable = !lit.is_exchangeable;
                this.ticketFareForm.baggage_inbound = lit.baggage_inbound || '';
                this.ticketFareForm.baggage_outbound = lit.baggage_outbound || '';
                this.ticketFareForm.outbound_pending = lit.outbound_pending || false;
                this.ticketFareForm.ticket_type = row.ticket_fare?.ticket_type || '';
                this.ticketFareForm.route_type = lit.route_type ? (
                    lit.route_type === 'oneway_inbound' ? 'One Way-Inbound' :
                    lit.route_type === 'oneway_outbound' ? 'One Way-Outbound' :
                    lit.route_type === 'round' ? 'Round' :
                    lit.route_type === 'multi_city' ? 'Multi City' : ''
                ) : (row.ticket_fare?.route_type || '');
                this.ticketFareForm.flight_type = row.ticket_fare?.flight_type || '';
                this.ticketFareForm.route_id = row.ticket_fare?.route_id || '';
                this.ticketFareForm.airline_id = row.ticket_fare?.airline_id || '';
                const r1 = window.__currencyRate || 0;
                if (r1 > 0) {
                    this.ticketFareForm.selling_fare_bdt = Math.round(parseFloat(this.ticketFareForm.selling_fare) * r1);
                    this.ticketFareForm.net_fare_bdt = Math.round(parseFloat(this.ticketFareForm.net_fare) * r1);
                    this.ticketFareForm.offer_price_bdt = Math.round(parseFloat(this.ticketFareForm.offer_price) * r1);
                }
            } else if (row.ticket_fare) {
                this.ticketFareForm.ticket_type = row.ticket_fare.ticket_type || '';
                this.ticketFareForm.route_type = row.ticket_fare.route_type || '';
                this.ticketFareForm.flight_type = row.ticket_fare.flight_type || '';
                this.ticketFareForm.inbound_date = this.formatToDDMMMYY(row.ticket_fare.inbound_date) || '';
                this.ticketFareForm.outbound_date = this.formatToDDMMMYY(row.ticket_fare.outbound_date) || '';
                this.ticketFareForm.pnr = row.ticket_fare.pnr || '';
                this.ticketFareForm.ticket_number = row.ticket_fare.ticket_number || '';
                this.ticketFareForm.date = this.formatToDDMMMYY(row.ticket_fare.date) || today;
                this.ticketFareForm.ticket_agent = row.ticket_fare.ticket_agent || '';
                this.ticketFareForm.selling_fare = row.ticket_fare.selling_fare || 0;
                this.ticketFareForm.net_fare = row.ticket_fare.net_fare || 0;
                this.ticketFareForm.offer_price = row.ticket_fare.offer_price || 0;
                this.ticketFareForm.non_refundable = row.ticket_fare.non_refundable || false;
                this.ticketFareForm.non_exchangeable = row.ticket_fare.non_exchangeable || false;
                this.ticketFareForm.baggage_inbound = row.ticket_fare.baggage_inbound || '';
                this.ticketFareForm.baggage_outbound = row.ticket_fare.baggage_outbound || '';
                this.ticketFareForm.outbound_pending = row.ticket_fare.outbound_pending || false;
                this.ticketFareForm.route_id = row.ticket_fare.route_id || '';
                this.ticketFareForm.airline_id = row.ticket_fare.airline_id || '';
                const r2 = window.__currencyRate || 0;
                if (r2 > 0) {
                    this.ticketFareForm.selling_fare_bdt = Math.round(parseFloat(this.ticketFareForm.selling_fare) * r2);
                    this.ticketFareForm.net_fare_bdt = Math.round(parseFloat(this.ticketFareForm.net_fare) * r2);
                    this.ticketFareForm.offer_price_bdt = Math.round(parseFloat(this.ticketFareForm.offer_price) * r2);
                }
            } else {
                this.ticketFareForm.ticket_type = '';
                this.ticketFareForm.route_type = '';
                this.ticketFareForm.flight_type = '';
                this.ticketFareForm.inbound_date = '';
                this.ticketFareForm.outbound_date = '';
                this.ticketFareForm.pnr = '';
                this.ticketFareForm.ticket_number = '';
                this.ticketFareForm.date = today;
                this.ticketFareForm.ticket_agent = '';
                this.ticketFareForm.selling_fare = 0;
                this.ticketFareForm.net_fare = 0;
                this.ticketFareForm.non_refundable = false;
                this.ticketFareForm.non_exchangeable = false;
                this.ticketFareForm.baggage_inbound = '';
                this.ticketFareForm.baggage_outbound = '';
                this.ticketFareForm.outbound_pending = false;
                this.ticketFareForm.selling_fare_bdt = '';
                this.ticketFareForm.net_fare_bdt = '';
                this.ticketFareForm.offer_price_bdt = '';
            }

            this._initLock = true;
            this.handleTicketFareRouteTypeChange();
            this.handleTicketTypeChange();

            const fareId = isAlreadyIssued
                ? row.latest_issued_ticket?.ticket_fare_id
                : row.ticket_fare?.ticket_fare_id;
            if (fareId) {
                const opt = this.filteredTicketOptions.find(o => o.value == fareId);
                if (opt) {
                    this.ticketFareForm.ticket_option = opt.value;
                    this.handleTicketOptionChange();
                }
            }

            if (row.is_double_ticket && row.ticket_fare_inbound_id) {
                if (isAlreadyIssued && this.ticketFareForm.outbound_pending) {
                    this.ticketFareForm.double_ticket_active = true;
                } else if (!isAlreadyIssued) {
                    this.ticketFareForm.double_ticket_active = true;
                    this.ticketFareForm.clear_double_ticket = false;
                    this.ticketFareForm.route_type = 'One Way-Inbound';
                    this.handleTicketFareRouteTypeChange();
                    this.ticketFareForm.outbound_pending = true;
                    this.ticketFareForm.route = row.inbound_ticket_fare?.route_display || '';
                    this.ticketFareForm.airline = row.inbound_ticket_fare?.airline || '';
                    this.ticketFareForm.travel_class = row.inbound_ticket_fare?.travel_class || '';
            if (row.inbound_ticket_fare) {
                const fare = row.inbound_ticket_fare;
                this.ticketFareForm.ticket_type = fare.ticket_type;
                this.ticketFareForm.flight_type = fare.flight_type === 'direct' ? 'Direct' : 'Transit';
                this.ticketFareForm.ticket_option = fare.id;
                this.handleTicketOptionChange();
            }
                }
            }

            this._initLock = false;
            this.suggestBaggage();
            this.isTicketFareModalOpen = true;
        },

        openTicketInfoModal(rowIndex) {
            this.ticketInfoPassengerIndex = rowIndex;
            this.isTicketInfoModalOpen = true;
        },

        viewableTickets(rowIndex) {
            const row = this.passengersTicketData[rowIndex];
            if (!row) return [];
            return (row.all_issued_tickets || []).filter(t => ['issued', 're-issued', 'refunded'].includes(t.status));
        },

        hasViewableTickets(rowIndex) {
            return this.viewableTickets(rowIndex).length > 0;
        },

        calculateFareForPassengerType(baseFare, passengerType, childPct, infantPct) {
            if (passengerType === 'child') return Math.round((baseFare * (childPct || 70)) / 100);
            if (passengerType === 'infant') return Math.round((baseFare * (infantPct || 30)) / 100);
            return baseFare;
        },

        handleTicketFareSarInput(field) {
            if (this._converting) return;
            this._converting = true;
            const rate = window.__currencyRate || 0;
            if (rate > 0) {
                const sar = parseFloat(this.ticketFareForm[field]) || 0;
                this.ticketFareForm[field + '_bdt'] = Math.round(sar * rate);
            }
            this._converting = false;
        },

        handleTicketFareBdtInput(field) {
            if (this._converting) return;
            this._converting = true;
            const rate = window.__currencyRate || 0;
            if (rate > 0) {
                const bdt = parseFloat(this.ticketFareForm[field + '_bdt']) || 0;
                this.ticketFareForm[field] = (Math.round(bdt / rate * 1e6) / 1e6).toFixed(6);
            }
            this._converting = false;
        },

        formatToDDMMMYY(dateStr) {
            if (!dateStr) return '';
            const parts = dateStr.split('-');
            if (parts.length !== 3) return dateStr;
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const d = parseInt(parts[2]), m = parseInt(parts[1]), y = parts[0];
            if (isNaN(d) || isNaN(m) || m < 1 || m > 12) return dateStr;
            return d + '-' + months[m - 1] + '-' + y.slice(-2);
        },

        parseDDMMMYY(input) {
            if (!input) return '';
            if (/^\d{4}-\d{2}-\d{2}$/.test(input)) return input;
            const parts = input.split('-');
            if (parts.length !== 3) return null;
            const months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
            const d = parseInt(parts[0]), mmm = parts[1].toLowerCase().slice(0, 3), yy = parts[2];
            const mi = months.indexOf(mmm);
            if (isNaN(d) || mi === -1 || !/^\d{2}$/.test(yy)) return null;
            const year = 2000 + parseInt(yy), month = mi + 1;
            if (d < 1 || d > new Date(year, month, 0).getDate()) return null;
            return year + '-' + String(month).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        },

        validateTicketFareDates() {
            const f = this.ticketFareForm;
            const errors = { inbound_date: '', outbound_date: '', date: '' };
            let valid = true;

            if (!f.date || !this.parseDDMMMYY(f.date)) {
                errors.date = 'Issue date must be in DD-MMM-YY format';
                valid = false;
            }
            if (f.showInboundDate && (!f.inbound_date || !this.parseDDMMMYY(f.inbound_date))) {
                errors.inbound_date = 'Inbound date must be in DD-MMM-YY format';
                valid = false;
            }
            if (f.showOutboundDate && (!f.outbound_date || !this.parseDDMMMYY(f.outbound_date))) {
                errors.outbound_date = 'Outbound date must be in DD-MMM-YY format';
                valid = false;
            }

            f.errors = errors;
            return valid;
        },

        closeTicketFareModal() {
            this.isTicketFareModalOpen = false;
            this.editingPassengerIndex = null;
        },

        handleTicketFareRouteTypeChange() {
            const routeType = this.ticketFareForm.route_type;
            const f = this.ticketFareForm;
            f.showBaggage = false;
            f.showInboundDate = false;
            f.showOutboundDate = false;

            switch (routeType) {
                case 'One Way-Inbound':
                    f.showBaggage = true;
                    f.showInboundDate = true;
                    break;
                case 'One Way-Outbound':
                    f.showBaggage = true;
                    f.showOutboundDate = true;
                    break;
                case 'Round':
                case 'Multi City':
                    f.showBaggage = true;
                    f.showInboundDate = true;
                    f.showOutboundDate = true;
                    break;
            }

            if (f.double_ticket_active && routeType !== 'One Way-Inbound') {
                f.outbound_pending = false;
                f.double_ticket_active = false;
                f.clear_double_ticket = true;
            }
        },

        handleTicketTypeChange() {
            if (this._initLock) return;
            this.ticketFareForm.ticket_option = '';
            this.ticketFareForm.route = '';
            this.ticketFareForm.airline = '';
            this.ticketFareForm.travel_class = '';
            this.ticketFareForm.route_id = '';
            this.ticketFareForm.airline_id = '';
                this.ticketFareForm.selling_fare = 0;
                this.ticketFareForm.net_fare = 0;
                this.ticketFareForm.offer_price = 0;
            this.ticketFareForm.selling_fare_bdt = '';
            this.ticketFareForm.net_fare_bdt = '';
            this.ticketFareForm.offer_price_bdt = '';
            this.ticketFareForm.baggage_inbound = '';
            this.ticketFareForm.baggage_outbound = '';
            this.ticketFareForm.inbound_date = '';
            this.ticketFareForm.outbound_date = '';
            this.ticketFareForm.pnr = '';
            this.ticketFareForm.ticket_number = '';
            this.ticketFareForm.ticket_agent = '';
            this.ticketFareForm.date = (() => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); })();
            this.ticketFareForm.group_ticket_id = '';
            this.suggestBaggage();
        },

        getAgentIdByName(name) {
            const agent = this.ticketAgents.find(a => a.name === name);
            return agent ? agent.id : null;
        },

        getSelectedFareId() {
            if (this.ticketFareForm.ticket_option) {
                return this.ticketFareForm.ticket_option;
            }
            const row = this.passengersTicketData[this.editingPassengerIndex];
            if (!row) return null;
            return row.latest_issued_ticket?.ticket_fare_id || row.ticket_fare?.ticket_fare_id || null;
        },

        mapIssuedTicketToForm(ticket) {
            const fare = ticket.ticket_fare || {};
            const route = fare.route || {};
            return {
                ticket_type: fare.ticket_type || 'regular',
                route_type: route.route_type || '',
                flight_type: route.flight_type || '',
                inbound_date: this.formatToDDMMMYY(ticket.inbound_date) || '',
                outbound_date: this.formatToDDMMMYY(ticket.outbound_date) || '',
                pnr: ticket.pnr || '',
                ticket_number: ticket.ticket_number || '',
                date: this.formatToDDMMMYY(ticket.issued_date) || '',
                ticket_agent: ticket.ticket_agent?.name || '',
                selling_fare: ticket.selling_fare || 0,
                net_fare: ticket.net_fare || 0,
                offer_price: ticket.offer_price || 0,
                non_refundable: !ticket.is_refundable,
                non_exchangeable: !ticket.is_exchangeable,
                baggage_inbound: ticket.baggage_inbound || '',
                baggage_outbound: ticket.baggage_outbound || '',
                outbound_pending: ticket.outbound_pending || false,
            };
        },

        handleTicketFareSubmit() {
            if (this.editingPassengerIndex === null) return;
            if (this.isSubmitting) return;

            const f = this.ticketFareForm;
            f.errors = { pnr: '', ticket_number: '', date: '', ticket_agent: '', selling_fare: '', net_fare: '', offer_price: '', inbound_date: '', outbound_date: '' };

            if (!f.pnr || !f.pnr.trim()) f.errors.pnr = 'PNR is required';
            if (!f.ticket_number || !f.ticket_number.trim()) f.errors.ticket_number = 'Ticket number is required';
            if (!f.date || !f.date.trim()) f.errors.date = 'Issue date is required';
            if (!f.ticket_agent) f.errors.ticket_agent = 'Please select a ticket agent';
            if (!f.selling_fare || parseFloat(f.selling_fare) <= 0) f.errors.selling_fare = 'Selling fare must be greater than 0';
            if (!f.net_fare || parseFloat(f.net_fare) <= 0) f.errors.net_fare = 'Net fare must be greater than 0';
            if (f.ticket_type === 'offer' && (!f.offer_price || parseFloat(f.offer_price) <= 0)) f.errors.offer_price = 'Offer price must be greater than 0';
            if (f.showInboundDate && (!f.inbound_date || !f.inbound_date.trim())) f.errors.inbound_date = 'Inbound date is required';
            if (f.showOutboundDate && (!f.outbound_date || !f.outbound_date.trim())) f.errors.outbound_date = 'Outbound date is required';

            const firstError = Object.values(f.errors).find(e => e);
            if (firstError) {
                this.showToast(firstError, 'error');
                return;
            }

            if (!this.validateTicketFareDates()) {
                const firstError2 = Object.values(f.errors).find(e => e);
                this.showToast(firstError2, 'error');
                return;
            }

            const row = this.passengersTicketData[this.editingPassengerIndex];
            if (!row) return;

            let isEdit, issuedTicketId;

            if (this.ticketFareForm.isOutboundMode) {
                issuedTicketId = this.ticketFareForm.issued_ticket_id;
                const targetTicket = (row.all_issued_tickets || []).find(t => t.id === issuedTicketId);
                isEdit = targetTicket && (targetTicket.status === 'issued' || targetTicket.status === 're-issued');
            } else {
                isEdit = row.latest_issued_ticket && (row.latest_issued_ticket.status === 'issued' || row.latest_issued_ticket.status === 're-issued');

                if (!isEdit && row.latest_issued_ticket && !['pending', 'awaiting-group'].includes(row.latest_issued_ticket.status)) {
                    this.showToast('This ticket cannot be issued again.', 'error');
                    return;
                }

                const pendingTicket = (row.all_issued_tickets || []).find(t => ['pending', 'awaiting-group'].includes(t.status));
                issuedTicketId = isEdit ? row.latest_issued_ticket?.id : pendingTicket?.id;
            }

            if (!issuedTicketId) {
                this.showToast('No pending ticket found for this passenger.', 'error');
                return;
            }

            const payload = {
                issued_ticket_id: issuedTicketId,
                ticket_number: this.ticketFareForm.ticket_number || '',
                pnr: this.ticketFareForm.pnr || '',
                ticket_agent_id: this.getAgentIdByName(this.ticketFareForm.ticket_agent),
                ticket_fare_id: this.getSelectedFareId(),
                group_ticket_id: this.ticketFareForm.group_ticket_id || null,
                issued_date: this.parseDDMMMYY(this.ticketFareForm.date) || '',
                inbound_date: this.parseDDMMMYY(this.ticketFareForm.inbound_date) || null,
                outbound_date: this.parseDDMMMYY(this.ticketFareForm.outbound_date) || null,
                selling_fare: parseFloat(this.ticketFareForm.selling_fare) || 0,
                net_fare: parseFloat(this.ticketFareForm.net_fare) || 0,
                offer_price: parseFloat(this.ticketFareForm.offer_price) || 0,
                is_refundable: !this.ticketFareForm.non_refundable,
                is_exchangeable: !this.ticketFareForm.non_exchangeable,
                baggage_inbound: this.ticketFareForm.baggage_inbound || '',
                baggage_outbound: this.ticketFareForm.baggage_outbound || '',
                outbound_pending: this.ticketFareForm.outbound_pending || false,
                clear_double_ticket: this.ticketFareForm.clear_double_ticket || false,
                ticket_fare_inbound_id: this.ticketFareForm.double_ticket_active ? row.ticket_fare_inbound_id : null,
                ticket_fare_outbound_id: this.ticketFareForm.double_ticket_active ? row.ticket_fare_outbound_id : null,
            };

            this.isSubmitting = true;

            const url = isEdit
                ? `/bookings/${row.booking_id}/passengers/${row.id}/ticket-edit`
                : `/bookings/${row.booking_id}/passengers/${row.id}/ticket-issue`;

            fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (!this.ticketFareForm.isOutboundMode) {
                        row.ticket_status = 'issued';
                    }
                    const t = data.issued_ticket;
                    row.latest_issued_ticket = {
                        id: t.id,
                        ticket_number: t.ticket_number,
                        pnr: t.pnr,
                        ticket_agent_id: t.ticket_agent_id,
                        ticket_agent_name: t.ticket_agent?.name || payload.ticket_agent || '',
                        ticket_fare_id: t.ticket_fare_id,
                        group_ticket_id: t.group_ticket_id,
                        issued_date: this.formatToDDMMMYY(t.issued_date),
                        inbound_date: this.formatToDDMMMYY(t.inbound_date),
                        outbound_date: this.formatToDDMMMYY(t.outbound_date),
                        selling_fare: t.selling_fare,
                        net_fare: t.net_fare,
                        is_refundable: t.is_refundable,
                        is_exchangeable: t.is_exchangeable,
                        baggage_inbound: t.baggage_inbound,
                        baggage_outbound: t.baggage_outbound,
                        outbound_pending: t.outbound_pending,
                        issue_type: t.issue_type,
                        status: t.status,
                        airline: t.ticket_fare?.airline?.name || '',
                        travel_class: t.ticket_fare?.airlineClass?.class?.name || '',
                        route_type: t.ticket_fare?.route?.route_type,
                    };
                    row.ticket_fare = this.mapIssuedTicketToForm(t);
                    if (t.status === 'issued' || t.status === 're-issued') {
                        if (!row.all_issued_tickets) row.all_issued_tickets = [];
                        const existingIdx = row.all_issued_tickets.findIndex(et => et.id === t.id);
                        const routeStr = t.ticket_fare?.route
                            ? [t.ticket_fare.route.from_city?.code, t.ticket_fare.route.to_city?.code].filter(Boolean).join('-')
                            : '';
                        const ticketObj = {
                            id: t.id, ticket_number: t.ticket_number || '',
                            issued_date: t.issued_date || '', status: t.status,
                            pnr: t.pnr || '', issue_type: t.issue_type,
                            selling_fare: t.selling_fare ?? 0, net_fare: t.net_fare ?? 0,
                            is_refundable: t.is_refundable ?? false,
                            is_exchangeable: t.is_exchangeable ?? false,
                            baggage_inbound: t.baggage_inbound || '',
                            baggage_outbound: t.baggage_outbound || '',
                            airline: t.ticket_fare?.airline?.name || '',
                            travel_class: t.ticket_fare?.airlineClass?.class?.name || '',
                            route: routeStr,
                            route_type: t.ticket_fare?.route?.route_type || '',
                            ticket_agent_name: t.ticket_agent?.name || '',
                            issuer_name: t.issuer?.name || '',
                        };
                        if (existingIdx !== -1) {
                            row.all_issued_tickets[existingIdx] = ticketObj;
                        } else {
                            row.all_issued_tickets.push(ticketObj);
                        }
                    }
                    if (data.pending_outbound_ticket) {
                        const po = data.pending_outbound_ticket;
                        row.pending_outbound_issued_ticket = {
                            id: po.id,
                            ticket_fare_id: po.ticket_fare_id,
                            status: po.status,
                            selling_fare: po.selling_fare ?? 0,
                            net_fare: po.net_fare ?? 0,
                            offer_price: po.offer_price ?? 0,
                            pnr: po.pnr ?? '',
                            ticket_number: po.ticket_number ?? '',
                            ticket_agent_id: po.ticket_agent_id,
                            ticket_agent_name: '',
                            issued_date: po.issued_date ?? '',
                            outbound_date: po.outbound_date ?? '',
                            is_refundable: po.is_refundable ?? false,
                            is_exchangeable: po.is_exchangeable ?? false,
                            baggage_outbound: po.baggage_outbound ?? '',
                            ticket_type: po.ticket_fare?.ticket_type?.value || po.ticket_fare?.ticket_type || '',
                            flight_type: po.ticket_fare?.route?.flight_type?.value || po.ticket_fare?.route?.flight_type || '',
                            route_display: (() => {
                                const r = po.ticket_fare?.route;
                                if (!r) return '';
                                const rt = r.route_type?.value || r.route_type;
                                if (rt === 'multi_city') {
                                    return (r.multi_segments || []).map(s => (s.from_city?.code || s.fromCity?.code || '?') + '-' + (s.to_city?.code || s.toCity?.code || '?')).join(', ');
                                }
                                const from = r.from_city?.code || r.fromCity?.code || '?';
                                const to = r.to_city?.code || r.toCity?.code || '?';
                                const ret = r.return_city?.code || r.returnCity?.code || '';
                                return (rt === 'round' && ret) ? from + '-' + to + '-' + ret : from + '-' + to;
                            })(),
                            airline: po.ticket_fare?.airline?.name || '',
                            travel_class: po.ticket_fare?.airlineClass?.class?.name || '',
                        };
                        if (!row.all_issued_tickets) row.all_issued_tickets = [];
                        const exists = row.all_issued_tickets.some(t => t.id === po.id);
                        if (!exists) {
                            row.all_issued_tickets.push({
                                id: po.id, ticket_number: po.ticket_number || '',
                                issued_date: po.issued_date || '', status: po.status,
                                pnr: po.pnr || '', issue_type: 'pending_outbound',
                                selling_fare: po.selling_fare ?? 0, net_fare: po.net_fare ?? 0,
                                is_refundable: po.is_refundable ?? false,
                                is_exchangeable: po.is_exchangeable ?? false,
                                baggage_inbound: '', baggage_outbound: po.baggage_outbound || '',
                                airline: '', travel_class: '',
                                route: '', route_type: '',
                                ticket_agent_name: po.ticket_agent_name || '',
                                issuer_name: '',
                            });
                        }
                    } else if (t.issue_type === 'pending_outbound') {
                        row.pending_outbound_issued_ticket = {
                            id: t.id,
                            ticket_fare_id: t.ticket_fare_id,
                            status: t.status,
                            selling_fare: t.selling_fare ?? 0,
                            net_fare: t.net_fare ?? 0,
                            offer_price: t.offer_price ?? 0,
                            pnr: t.pnr || '',
                            ticket_number: t.ticket_number || '',
                            ticket_agent_id: t.ticket_agent_id,
                            ticket_agent_name: t.ticket_agent?.name || payload.ticket_agent || '',
                            issued_date: t.issued_date ? this.formatToDDMMMYY(t.issued_date) : '',
                            outbound_date: t.outbound_date ? this.formatToDDMMMYY(t.outbound_date) : '',
                            is_refundable: t.is_refundable ?? false,
                            is_exchangeable: t.is_exchangeable ?? false,
                            baggage_outbound: t.baggage_outbound || '',
                            ticket_type: t.ticket_fare?.ticket_type?.value || t.ticket_fare?.ticket_type || '',
                            flight_type: t.ticket_fare?.route?.flight_type?.value || t.ticket_fare?.route?.flight_type || '',
                            route_display: (() => {
                                const r = t.ticket_fare?.route;
                                if (!r) return '';
                                const rt = r.route_type?.value || r.route_type;
                                if (rt === 'multi_city') {
                                    return (r.multi_segments || []).map(s => (s.from_city?.code || s.fromCity?.code || '?') + '-' + (s.to_city?.code || s.toCity?.code || '?')).join(', ');
                                }
                                const from = r.from_city?.code || r.fromCity?.code || '?';
                                const to = r.to_city?.code || r.toCity?.code || '?';
                                const ret = r.return_city?.code || r.returnCity?.code || '';
                                return (rt === 'round' && ret) ? from + '-' + to + '-' + ret : from + '-' + to;
                            })(),
                            airline: t.ticket_fare?.airline?.name || '',
                            travel_class: t.ticket_fare?.airlineClass?.class?.name || '',
                        };
                    }
                    if (!this.ticketFareForm.isOutboundMode && !data.pending_outbound_ticket) {
                        row.all_issued_tickets = (row.all_issued_tickets || []).filter(t => !(t.issue_type === 'pending_outbound' && ['pending', 'awaiting-group'].includes(t.status)));
                        row.pending_outbound_issued_ticket = null;
                    }
                    this.showToast(data.message || 'Ticket saved successfully.');
                    this.closeTicketFareModal();
                } else {
                    this.showToast(data.message || 'Failed to save ticket.', 'error');
                }
            })
            .catch(err => {
                console.error('Ticket submit error:', err);
                this.showToast('Failed to save ticket.', 'error');
            })
            .finally(() => { this.isSubmitting = false; });
        },

        handleRouteTypeOrFlightTypeChange() {
            this.ticketFareForm.ticket_option = '';
            this.ticketFareForm.route = '';
            this.ticketFareForm.airline = '';
            this.ticketFareForm.travel_class = '';
        },

        handleAirlineChange() {
            const current = this.ticketFareForm.travel_class;
            const available = this.filteredClasses.map(c => c.name);
            if (!available.includes(current)) {
                this.ticketFareForm.travel_class = '';
            }
        },

        get filteredRoutes() {
            const rt = this.ticketFareForm.route_type;
            const ft = this.ticketFareForm.flight_type;
            if (!rt || !ft) return this.routesList;
            const rtMap = {'One Way-Inbound':'oneway_inbound','One Way-Outbound':'oneway_outbound','Round':'round','Multi City':'multi_city'};
            const ftMap = {'Transit':'transit','Direct':'direct'};
            return this.routesList.filter(r => r.route_type === (rtMap[rt]||rt) && r.flight_type === (ftMap[ft]||ft));
        },

        get filteredAirlines() {
            const rt = this.ticketFareForm.route_type;
            const ft = this.ticketFareForm.flight_type;
            if (!rt || !ft) return this.airlinesList;
            const rtMap = {'One Way-Inbound':'oneway_inbound','One Way-Outbound':'oneway_outbound','Round':'round','Multi City':'multi_city'};
            const ftMap = {'Transit':'transit','Direct':'direct'};
            const ids = this.routesList.filter(r => r.route_type === (rtMap[rt]||rt) && r.flight_type === (ftMap[ft]||ft)).map(r => r.airline_id);
            return this.airlinesList.filter(a => ids.includes(a.id));
        },

        get filteredClasses() {
            const name = this.ticketFareForm.airline;
            if (!name) return this.classesList;
            const airline = this.airlinesList.find(a => a.name === name);
            if (!airline) return this.classesList;
            return this.classesList.filter(c => airline.class_ids.includes(c.id));
        },

        get filteredTicketOptions() {
            const tt = this.ticketFareForm.ticket_type;
            const rt = this.ticketFareForm.route_type;
            const ft = this.ticketFareForm.flight_type;
            const rtMap = {'One Way-Inbound':'oneway_inbound','One Way-Outbound':'oneway_outbound','Round':'round','Multi City':'multi_city'};
            const ftMap = {'Transit':'transit','Direct':'direct'};
            let fares = this.ticketFaresList;
            if (tt) {
                fares = fares.filter(f => f.ticket_type === tt);
            }
            if (rt && ft) {
                fares = fares.filter(f => f.route_type === (rtMap[rt]||rt) && f.flight_type === (ftMap[ft]||ft));
            }
            return fares.map(f => {
                let display = f.route + ' | ' + f.airline + ' | ' + f.airline_class + ' | ' + f.ticket_type;
                if (f.ticket_type === 'group' && f.pnr && f.ticket_qty) {
                    display += ' | ' + f.pnr + ' | ' + f.ticket_qty;
                }
                return { display, value: f.id, is_active: f.is_active };
            });
        },

        handleTicketOptionChange() {
            const val = this.ticketFareForm.ticket_option;
            if (!val) {
                this.ticketFareForm.route = '';
                this.ticketFareForm.airline = '';
                this.ticketFareForm.travel_class = '';
                this.ticketFareForm.route_id = '';
                this.ticketFareForm.airline_id = '';
                this.ticketFareForm.selling_fare = 0;
                this.ticketFareForm.net_fare = 0;
                this.ticketFareForm.baggage_inbound = '';
                this.ticketFareForm.baggage_outbound = '';
                this.ticketFareForm.inbound_date = '';
                this.ticketFareForm.outbound_date = '';
                this.ticketFareForm.selling_fare_bdt = '';
                this.ticketFareForm.net_fare_bdt = '';
                this.ticketFareForm.offer_price_bdt = '';
                return;
            }
            const fare = this.ticketFaresList.find(f => f.id == val);
            if (fare) {
                this.ticketFareForm.route = fare.route || '';
                this.ticketFareForm.airline = fare.airline || '';
                this.ticketFareForm.travel_class = fare.airline_class || '';
                this.ticketFareForm.route_id = fare.route_id;
                this.ticketFareForm.airline_id = fare.airline_id;

                this.ticketFareForm.group_ticket_id = fare.group_ticket_id || null;
                if (fare.pnr) {
                    this.ticketFareForm.pnr = fare.pnr;
                }
                if (fare.inbound_date) {
                    this.ticketFareForm.inbound_date = this.formatToDDMMMYY(fare.inbound_date) || '';
                }
                if (fare.outbound_date) {
                    this.ticketFareForm.outbound_date = this.formatToDDMMMYY(fare.outbound_date) || '';
                }
                if (fare.ticket_type === 'group' && fare.is_refundable !== null) {
                    this.ticketFareForm.non_refundable = !fare.is_refundable;
                    this.ticketFareForm.non_exchangeable = !fare.is_exchangable;
                }

                const row = this.passengersTicketData[this.editingPassengerIndex];
                if (row?.ticket_fare && fare.id === row.ticket_fare.ticket_fare_id) {
                    const pType = row.passenger_type || 'adult';
                    this.ticketFareForm.selling_fare = this.calculateFareForPassengerType(row.ticket_fare.selling_fare, pType, row.ticket_fare.child_fare_percentage, row.ticket_fare.infant_fare_percentage);
                    this.ticketFareForm.net_fare = this.calculateFareForPassengerType(row.ticket_fare.net_fare, pType, row.ticket_fare.child_fare_percentage, row.ticket_fare.infant_fare_percentage);
                    if (row.ticket_fare.with_offer && row.ticket_fare.offer_price) {
                        this.ticketFareForm.offer_price = this.calculateFareForPassengerType(row.ticket_fare.offer_price, pType, row.ticket_fare.child_fare_percentage, row.ticket_fare.infant_fare_percentage);
                    }
                    const r3 = window.__currencyRate || 0;
                    if (r3 > 0) {
                        this.ticketFareForm.selling_fare_bdt = Math.round(parseFloat(this.ticketFareForm.selling_fare) * r3);
                        this.ticketFareForm.net_fare_bdt = Math.round(parseFloat(this.ticketFareForm.net_fare) * r3);
                        this.ticketFareForm.offer_price_bdt = Math.round(parseFloat(this.ticketFareForm.offer_price) * r3);
                    }
                } else {
                    const pType = row?.passenger_type || 'adult';
                    this.ticketFareForm.selling_fare = this.calculateFareForPassengerType(fare.selling_fare, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                    this.ticketFareForm.net_fare = this.calculateFareForPassengerType(fare.net_fare, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                    if (fare.ticket_type === 'offer' && fare.offer_price) {
                        this.ticketFareForm.offer_price = this.calculateFareForPassengerType(fare.offer_price, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                    }
                    const r4 = window.__currencyRate || 0;
                    if (r4 > 0) {
                        this.ticketFareForm.selling_fare_bdt = Math.round(parseFloat(this.ticketFareForm.selling_fare) * r4);
                        this.ticketFareForm.net_fare_bdt = Math.round(parseFloat(this.ticketFareForm.net_fare) * r4);
                        this.ticketFareForm.offer_price_bdt = Math.round(parseFloat(this.ticketFareForm.offer_price) * r4);
                    }
                }
            }
            this.suggestBaggage(fare?.baggage_allowances);
        },

        suggestBaggage(allowancesOverride = null) {
            const idx = this.editingPassengerIndex;
            if (idx === null) return;
            const row = this.passengersTicketData[idx];
            const allowances = allowancesOverride || row?.ticket_fare?.baggage_allowances;
            if (!allowances?.length) return;
            const pType = this.ticketFareForm.passenger_type || 'adult';
            const inbound = allowances.find(b => b.passenger_type === pType && b.travel_direction === 'inbound');
            const outbound = allowances.find(b => b.passenger_type === pType && b.travel_direction === 'outbound');
            if (inbound) this.ticketFareForm.baggage_inbound = inbound.allowance;
            if (outbound) this.ticketFareForm.baggage_outbound = outbound.allowance;
        },

        get filteredNewRoutes() {
            const rt = this.newTicketFareForm.route_type;
            const ft = this.newTicketFareForm.flight_type;
            if (!rt || !ft) return this.routesList;
            return this.routesList.filter(r => r.route_type === rt && r.flight_type === ft);
        },

        get filteredNewClasses() {
            const id = this.newTicketFareForm.airline_id;
            if (!id) return [];
            const airline = this.airlinesList.find(a => a.id == id);
            if (!airline) return [];
            return this.classesList.filter(c => airline.class_ids.includes(c.id));
        },

        handleNewTicketRouteTypeChange() {
            this.newTicketFareForm.flight_type = '';
            this.newTicketFareForm.route_id = '';
        },

        handleNewTicketFlightTypeChange() {
            this.newTicketFareForm.route_id = '';
        },

        handleNewTicketTypeChange() {},

        buildRouteDisplay(route) {
            if (!route) return '';
            const rt = route.route_type?.value || route.route_type;
            if (rt === 'multi_city') {
                const seg = route.multi_segments?.[0] || route.multiSegments?.[0];
                if (seg) {
                    const fromCode = seg.from_city?.code || seg.fromCity?.code || '?';
                    const toCode = seg.to_city?.code || seg.toCity?.code || '?';
                    return fromCode + '-' + toCode;
                }
                return 'Multi City';
            }
            const fromCode = route.from_city?.code || route.fromCity?.code || '?';
            const toCode = route.to_city?.code || route.toCity?.code || '?';
            const returnCode = route.return_city?.code || route.returnCity?.code || '';
            return rt === 'round' && returnCode
                ? fromCode + '-' + toCode + '-' + returnCode
                : fromCode + '-' + toCode;
        },

        onNewRouteSelectChange(event) {
            if (event.target.value === '__add_new__') {
                event.target.value = '';
                this.openRouteModal();
            }
        },

        onNewAirlineSelectChange(event) {
            if (event.target.value === '__add_new__') {
                event.target.value = '';
                this.openAirlineModal();
            }
        },

        onNewClassSelectChange(event) {
            if (event.target.value === '__add_new__') {
                event.target.value = '';
                this.openClassModal();
            }
        },

        saveNewTicketFare() {
            this.newTicketFareForm.saving = true;
            const f = this.newTicketFareForm;
            const payload = {
                route_type: f.route_type,
                flight_type: f.flight_type,
                airline_id: f.airline_id,
                airline_classes_id: f.airline_classes_id,
                route_id: f.route_id,
                ticket_type: f.ticket_type,
                net_fare: parseFloat(f.net_fare) || 0,
                selling_fare: parseFloat(f.selling_fare) || 0,
                offer_price: f.offer_price ? parseFloat(f.offer_price) : null,
                child_fare_percentage: parseFloat(f.child_fare_percentage) || 70,
                infant_fare_percentage: parseFloat(f.infant_fare_percentage) || 30,
                with_meal: f.with_meal,
                pnr: f.pnr || null,
                ticket_qty: f.ticket_qty || null,
                inbound_date: f.inbound_date || null,
                outbound_date: f.outbound_date || null,
                is_non_refundable: f.is_non_refundable,
                is_non_exchangable: f.is_non_exchangable,
                inbound_adult: f.inbound_adult ? parseFloat(f.inbound_adult) : null,
                inbound_child: f.inbound_child ? parseFloat(f.inbound_child) : null,
                inbound_infant: f.inbound_infant ? parseFloat(f.inbound_infant) : null,
                outbound_adult: f.outbound_adult ? parseFloat(f.outbound_adult) : null,
                outbound_child: f.outbound_child ? parseFloat(f.outbound_child) : null,
                outbound_infant: f.outbound_infant ? parseFloat(f.outbound_infant) : null,
            };

            fetch('/api/ticket-fares/quick-create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(data => {
                this.newTicketFareForm.saving = false;
                if (data.success) {
                    const fare = data.ticket_fare;
                    if (fare.route_id && fare.airline_id && fare.airline_classes_id) {
                        const routeDisplay = this.buildRouteDisplay(fare.route);
                        const airlineName = fare.airline?.name || '';
                        const className = fare.airlineClass?.class?.name || '';
                        const ticketType = fare.ticket_type?.value || fare.ticket_type || 'regular';
                        const pnr = this.newTicketFareForm.pnr || '';
                        const ticketQty = this.newTicketFareForm.ticket_qty || null;
                        this.ticketFaresList.push({
                            id: fare.id,
                            route: routeDisplay,
                            airline: airlineName,
                            airline_class: className,
                            ticket_type: ticketType,
                            route_id: fare.route_id,
                            airline_id: fare.airline_id,
                            airline_classes_id: fare.airline_classes_id,
                            route_type: fare.route?.route_type?.value || fare.route?.route_type || '',
                            flight_type: fare.route?.flight_type?.value || fare.route?.flight_type || '',
                            group_ticket_id: fare.group_ticket?.id ?? null,
                            pnr: pnr,
                            ticket_qty: ticketQty,
                            is_refundable: !this.newTicketFareForm.is_non_refundable,
                            is_exchangable: !this.newTicketFareForm.is_non_exchangable,
                        });
                        this.ticketFareForm.ticket_option = fare.id;
                        this.handleTicketOptionChange();
                    }
                    this.showToast('Ticket fare created successfully.');
                    this.newTicketFareForm.visible = false;
                } else {
                    this.showToast(data.message || 'Failed to create ticket fare.', 'error');
                }
            })
            .catch(err => {
                this.newTicketFareForm.saving = false;
                console.error('Quick create error:', err);
                this.showToast('Failed to create ticket fare.', 'error');
            });
        },

        showRouteModal: false,
        editRouteMode: false,
        routeSaving: false,
        routeErrors: {},
        route: {
            id: null, airline_id: '', route_type: '', flight_type: '',
            from_city_id: '', to_city_id: '', return_city_id: '',
            additional_gap: '', transits: [
                {transit_city_id: '', transit_hours: '', transit_minutes: ''},
                {transit_city_id: '', transit_hours: '', transit_minutes: ''},
            ],
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

        openRouteModal() { this.showRouteModal = true; this.editRouteMode = false; },
        closeRouteModal() { this.showRouteModal = false; this.routeErrors = {}; },
        toggleRouteFields() {},
        toggleTransitFields() {},

        onCitySelectChange(selectKey, event) {
            if (event.target.value === '__add_new__') {
                event.target.value = '';
                this.activeSelect = selectKey;
                this.openCityModal();
            }
        },
        openCityModal() { this.cityModalOpen = true; },
        closeCityModal() { this.cityModalOpen = false; this.cityErrors = {}; this.activeSelect = null; },
        saveCity() { /* handled by route save */ },
        appendCityToAllSelects() {},

        openAirlineModal() { this.airlineModalOpen = true; this.airlineData = { name: '', code: '' }; this.airlineErrors = {}; },
        closeAirlineModal() { this.airlineModalOpen = false; this.airlineErrors = {}; },

        saveAirline() {
            this.airlineSaving = true;
            fetch('/airlines', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify(this.airlineData),
            })
            .then(res => res.json())
            .then(data => {
                this.airlineSaving = false;
                if (data.id) {
                    this.airlinesList.push({ id: data.id, name: data.name, class_ids: [] });
                    this.newTicketFareForm.airline_id = data.id;
                    this.closeAirlineModal();
                    this.showToast('Airline created successfully.');
                } else {
                    this.airlineErrors = data.errors || {};
                    this.showToast('Failed to create airline.', 'error');
                }
            })
            .catch(() => { this.airlineSaving = false; this.showToast('Failed to create airline.', 'error'); });
        },

        openClassModal() { this.classModalOpen = true; this.classData = { airline_id: '', class_id: '' }; this.classErrors = {}; },
        closeClassModal() { this.classModalOpen = false; this.classErrors = {}; },

        saveClass() {
            this.classSaving = true;
            this.classData.airline_id = this.newTicketFareForm.airline_id;
            fetch('/airline-classes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify(this.classData),
            })
            .then(res => res.json())
            .then(data => {
                this.classSaving = false;
                if (data.id) {
                    const travelClass = this.classesList.find(c => c.id == data.class_id);
                    const display = travelClass ? travelClass.name : data.class_id;
                    this.newTicketFareForm.airline_classes_id = data.id;
                    const airline = this.airlinesList.find(a => a.id == data.airline_id);
                    if (airline && travelClass && !airline.class_ids.includes(travelClass.id)) {
                        airline.class_ids.push(travelClass.id);
                    }
                    this.closeClassModal();
                    this.showToast('Class created successfully.');
                } else {
                    this.classErrors = data.errors || {};
                    this.showToast('Failed to create class.', 'error');
                }
            })
            .catch(() => { this.classSaving = false; this.showToast('Failed to create class.', 'error'); });
        },

        saveRoute() {
            this.routeSaving = true;
            const formData = new FormData();
            Object.entries(this.route).forEach(([k, v]) => {
                if (k === 'transits') {
                    v.forEach((t, i) => {
                        Object.entries(t).forEach(([tk, tv]) => formData.append(`transits[${i}][${tk}]`, tv));
                    });
                } else {
                    formData.append(k, v);
                }
            });
            fetch('/routes', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: formData,
            })
            .then(res => res.json())
            .then(data => {
                this.routeSaving = false;
                if (data.id) {
                    this.newTicketFareForm.route_id = data.id;
                    this.closeRouteModal();
                    this.showToast('Route created successfully.');
                } else {
                    this.routeErrors = data.errors || {};
                    this.showToast('Failed to create route.', 'error');
                }
            })
            .catch(() => { this.routeSaving = false; this.showToast('Failed to create route.', 'error'); });
        },

        // ── Cancellation State ──
        cancelModalVisible: false,
        cancelBookingId: null,
        cancelBranches: @json($bookingBranches),
        cancelBranchId: '',
        cancelServiceCharge: null,
        cancelServiceChargeBdt: '',
        cancelTotalPaid: 0,
        cancelCosts: { fingerprint_cost: 0, visa_cost: 0, ticket_cost: 0, total_cost: 0 },
        cancelLoading: false,

        async openCancelModal(bookingId) {
            this.cancelBookingId = bookingId;
            this.cancelModalVisible = true;
            this.cancelServiceCharge = null;
            this.cancelServiceChargeBdt = '';
            try {
                const res = await fetch(`/bookings/${bookingId}/cancellation/initiate`);
                const data = await res.json();
                this.cancelTotalPaid = data.total_paid;
                this.cancelCosts = data.costs;
                if (data.booking_branch_id) this.cancelBranchId = data.booking_branch_id;
            } catch (e) {
                alert('Failed to load cancellation data');
                this.closeCancelModal();
            }
        },

        closeCancelModal() {
            this.cancelModalVisible = false;
            this.cancelBookingId = null;
        },

        get computedRefundAmount() {
            const paid = this.cancelTotalPaid;
            const cost = this.cancelCosts.total_cost;
            const charge = parseFloat(this.cancelServiceCharge) || 0;
            return (paid - cost - charge).toFixed(2);
        },

        async handleCancelSubmit() {
            this.cancelLoading = true;
            try {
                const res = await fetch(`/bookings/${this.cancelBookingId}/cancellation/initiate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        cancellation_branch_id: this.cancelBranchId,
                        service_charge_deduction: this.cancelServiceCharge === '' ? null : this.cancelServiceCharge,
                    }),
                });
                const data = await res.json();
                if (data.success) window.location.reload();
                else alert(data.message || 'Failed to initiate cancellation');
            } catch (e) {
                alert('Failed to initiate cancellation');
            } finally {
                this.cancelLoading = false;
            }
        },

        showToast(message) {
            const container = document.getElementById('toastContainer') || (() => {
                const el = document.createElement('div');
                el.id = 'toastContainer';
                el.className = 'fixed top-4 right-4 z-50 space-y-2';
                document.body.appendChild(el);
                return el;
            })();

            const toast = document.createElement('div');
            toast.className = 'toast px-4 py-3 rounded-lg shadow-lg text-white font-medium bg-slate-700 translate-x-full opacity-0';
            toast.textContent = message;
            container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            });

            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    }
}

function updatePassengerStatus(passengerId, statusId) {
    fetch(`/passengers/${passengerId}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ passenger_status_id: statusId || null })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Status updated successfully');
        } else {
            alert('Failed to update status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update status');
    });
}

function updateFingerprintLocation(bookingId, location, select) {
    const selectEl = select || event.target;
    const originalValue = selectEl.dataset.original;

    fetch(`/bookings/${bookingId}/fingerprint-location`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ fingerprint_location: location })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            selectEl.dataset.original = location;
            if (data.invoice) {
                const row = selectEl.closest('tr');
                const cells = row.querySelectorAll('td');
                if (cells.length >= 12) {
                    const rate = parseFloat(selectEl.dataset.rate) || 0;
                    cells[9].textContent = Alpine.store('currency').format(data.invoice.total_amount, 2, rate);
                    cells[10].textContent = Alpine.store('currency').format(data.invoice.paid_amount, 2, rate);
                    cells[11].textContent = Alpine.store('currency').format(data.invoice.balance, 2, rate);
                }
            }
        } else {
            alert('Failed to update fingerprint location');
            selectEl.value = originalValue;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update fingerprint location');
        selectEl.value = originalValue;
    });
}

window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        location.reload();
    }
});
</script>
@endsection