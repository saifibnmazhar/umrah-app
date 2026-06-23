@extends('layouts.app')
@section('title', 'Booking')
@section('content')
@php
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

$ticketAgents = \App\Models\TicketAgent::orderBy('name')->get();

$routesList = \App\Models\Route::with(['fromCity', 'toCity', 'returnCity', 'multiSegments.fromCity', 'multiSegments.toCity'])->get()->map(fn($r) => [
    'id' => $r->id,
    'display' => match($r->route_type?->value) {
        'multi_city' => $r->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', '),
        'round' => ($r->fromCity?->code ?? '?') . '-' . ($r->toCity?->code ?? '?') . '-' . ($r->returnCity?->code ?? '?'),
        default => ($r->fromCity?->code ?? '?') . '-' . ($r->toCity?->code ?? '?'),
    },
    'route_type' => $r->route_type?->value,
    'flight_type' => $r->flight_type?->value,
    'airline_id' => $r->airline_id,
])->values();

$airlinesList = \App\Models\Airline::with('travelClasses')->get()->map(fn($a) => [
    'id' => $a->id,
    'name' => $a->name,
    'class_ids' => $a->travelClasses->pluck('id'),
])->values();

$classesList = \App\Models\TravelClass::all()->map(fn($c) => [
    'id' => $c->id,
    'name' => $c->name,
])->values();

$ticketFaresList = \App\Models\TicketFare::where('is_active', true)->with([
    'route.fromCity', 'route.toCity', 'route.returnCity',
    'route.multiSegments.fromCity', 'route.multiSegments.toCity',
    'airline', 'airlineClass.class', 'groupTicket',
])->get()->map(fn($fare) => [
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
    'ticket_qty' => $fare->groupTicket?->ticket_qty ?? null,
    'is_refundable' => $fare->groupTicket?->is_refundable ?? null,
    'is_exchangable' => $fare->groupTicket?->is_exchangable ?? null,
    'selling_fare' => (float)($fare->selling_fare ?? 0),
    'net_fare' => (float)($fare->net_fare ?? 0),
    'offer_price' => $fare->ticket_type?->value === 'offer' ? (float)($fare->offer_price ?? 0) : null,
    'child_fare_percentage' => (float)($fare->child_fare_percentage ?? 70),
    'infant_fare_percentage' => (float)($fare->infant_fare_percentage ?? 30),
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

    'ticket_status' => $p->ticket_status?->value ?? null,
    'due' => $p->booking?->invoice?->balance ?? 0,
    'required_flight_date' => $p->flight_date_from?->format('Y-m-d') ?? '',
    'actual_flight_date' => $p->actual_flight_date?->format('Y-m-d') ?? '',
    'fingerprint_location' => $p->booking?->fingerprint_location?->value ?? 'None',
    'fingerprint_status' => $p->fingerprintDetail?->status?->value ?? null,
    'status' => $p->passengerStatus?->name ?? 'None',
    'documents' => [],
    'passenger_data' => null,

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

    'latest_issued_ticket' => ($lit = $p->latestIssuedTicket) ? [
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
        'route' => $lit->ticketFare?->route ? ($lit->ticketFare->route->fromCity?->code . '-' . $lit->ticketFare->route->toCity?->code) : '',
        'route_type' => $lit->ticketFare?->route?->route_type?->value,
    ] : null,
])->values();
@endphp
<div class="w-full mx-auto" x-data="bookingIndexApp()">
    <div class="flex justify-between items-center mb-6">
        @php
            $canCreateBooking = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Branch Manager', 'Branch Staff', 'Auditor', 'Visa Admin', 'Visa Staff', 'Ticket Admin', 'Ticket Staff', 'Fingerprint Admin', 'Fingerprint Staff'])->isNotEmpty();
            $canViewFinancialColumns = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Auditor'])->isNotEmpty();
            $canViewVisaColumns = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Visa Admin', 'Visa Staff'])->isNotEmpty();
            $canViewTicketFareColumn = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Ticket Admin', 'Ticket Staff'])->isNotEmpty();
            $canEditInline = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty();
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
            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                <input type="text" x-model="searchTerm" x-ref="searchInput" class="w-full md:w-64 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition" placeholder="Search by Mobile or Invoice No...">
                @unless(auth()->user()->branch_id)
                <div class="flex items-center gap-4">
                    <select
                        x-model="selectedBranchId"
                        @change="onBranchChange"
                        class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                        <option value="">All Branches</option>
                        @foreach($bookingBranches as $branch)
                        <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 text-white font-semibold rounded-lg whitespace-nowrap shadow-sm" x-text="'Total Booking - ' + totalBookingCount">Total Booking - {{ $totalBookingCount }}</span>
                </div>
                @endunless
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
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Total</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Paid</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Due</th>@endif
                            @if($canViewActionColumn)<th class="px-3 py-2 text-left font-medium">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($bookings as $booking)
                        @php $bookingCurrencyRate = $booking->currencyRate?->rate ?? ($currencyRateService?->getRateForDate($booking->created_at)?->rate ?? 0); @endphp
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->invoice_id ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->customer->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->customer->mobile_no ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->passengers->count() }}</td>
                            <td class="px-3 py-2">
                                @if($canEditInline)
                                <select
                                    class="text-sm border border-slate-300 rounded px-2 py-1 bg-white focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
                                    data-original="{{ $booking->fingerprint_location?->value ?? 'office' }}"
                                    data-rate="{{ $bookingCurrencyRate }}"
                                    onchange="updateFingerprintLocation({{ $booking->id }}, this.value, this)">
                                    <option value="home" {{ ($booking->fingerprint_location?->value ?? '') === 'home' ? 'selected' : '' }}>Home</option>
                                    <option value="office" {{ ($booking->fingerprint_location?->value ?? '') === 'office' ? 'selected' : '' }}>Office</option>
                                </select>
                                @else
                                <span class="text-slate-700">{{ ucfirst($booking->fingerprint_location?->value ?? 'Office') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->bookingBranch->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->fingerprintBranch->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->district->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->package->package_name ?? 'N/A' }}</td>
                            @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">@currency($booking->invoice?->total_amount ?? 0, 2, $bookingCurrencyRate)</td>@endif
                            @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">@currency($booking->invoice?->paid_amount ?? 0, 2, $bookingCurrencyRate)</td>@endif
                            @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">@currency($booking->invoice?->balance ?? 0, 2, $bookingCurrencyRate)</td>@endif
                            @if($canViewActionColumn)
                            <td class="px-3 py-2">
                                <a href="{{ route('bookings.show', $booking->id) }}" class="text-slate-600 hover:text-slate-800">View</a>
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
                            <td colspan="{{ 10 + ($canViewFinancialColumns ? 3 : 0) + ($canViewActionColumn ? 1 : 0) }}" class="px-3 py-4 text-center text-slate-500">No bookings found</td>
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
                    <select x-model="selectedFingerprintStatus" @change="onFingerprintStatusChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                        <option value="">Fingerprint Status</option>
                        @foreach($fingerprintStatuses as $status)
                        <option value="{{ $status->value }}" {{ $selectedFingerprintStatus === $status->value ? 'selected' : '' }}>{{ ucfirst(str_replace('-', ' ', $status->value)) }}</option>
                        @endforeach
                    </select>
                    <select x-model="selectedVisaStatus" @change="onVisaStatusChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                        <option value="">Visa Status</option>
                        @foreach($visaStatuses as $status)
                        <option value="{{ $status->value }}" {{ $selectedVisaStatus === $status->value ? 'selected' : '' }}>{{ ucfirst(str_replace('-', ' ', $status->value)) }}</option>
                        @endforeach
                    </select>
                    <select x-model="selectedTicketStatus" @change="onTicketStatusChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                        <option value="">Ticket Status</option>
                        @foreach($ticketStatuses as $status)
                        <option value="{{ $status->value }}" {{ $selectedTicketStatus === $status->value ? 'selected' : '' }}>{{ ucfirst(str_replace('-', ' ', $status->value)) }}</option>
                        @endforeach
                    </select>
                    <select x-model="selectedVisaAgentId" @change="onVisaAgentChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                        <option value="">Visa Agent</option>
                        @foreach($visaAgents as $agent)
                        <option value="{{ $agent['id'] }}" {{ (string) $selectedVisaAgentId === (string) $agent['id'] ? 'selected' : '' }}>{{ $agent['name'] }}</option>
                        @endforeach
                    </select>
                    <input type="date" x-model="selectedBookingDateFrom" @change="onBookingDateFromChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <input type="date" x-model="selectedBookingDateTo" @change="onBookingDateToChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <select x-model="selectedFingerprintLocation" @change="onFingerprintLocationChange" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                        <option value="">Fingerprint Location</option>
                        @foreach($fingerprintLocations as $location)
                        <option value="{{ $location->value }}" {{ $selectedFingerprintLocation === $location->value ? 'selected' : '' }}>{{ ucfirst($location->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <span class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 text-white font-semibold rounded-lg whitespace-nowrap shadow-sm flex-shrink-0" x-text="'Total Passenger - ' + totalPassengerCount">Total Passenger - {{ $totalPassengerCount }}</span>
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
                            <th class="px-3 py-2 text-left font-medium">Package</th>
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Package Value</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Total Cost</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Markup (Profit)</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Due</th>@endif
                            @if($canViewVisaColumns)<th class="px-3 py-2 text-left font-medium">Visa</th>@endif
                            @if($canViewVisaColumns)<th class="px-3 py-2 text-left font-medium">Visa Agent</th>@endif
                            <th class="px-3 py-2 text-left font-medium">Visa Status</th>
                            @if($canViewTicketFareColumn)<th class="px-3 py-2 text-left font-medium">Ticket Fare</th>@endif
                            <th class="px-3 py-2 text-left font-medium">Ticket Status</th>
                            <th class="px-3 py-2 text-left font-medium">Fingerprint Status</th>
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
@php $lastBookingId = null; @endphp
@forelse($passengers as $passenger)
@php
$isFirstRow = ($lastBookingId !== $passenger->booking_id);
$lastBookingId = $passenger->booking_id;

$ticketFare = $passenger->ticketFare;
$baseFare = $ticketFare?->selling_fare ?? $ticketFare?->net_fare ?? 0;
$passengerTypeVal = $passenger->passenger_type?->value;
$fareAmount = match($passengerTypeVal) {
    'child' => $baseFare * ($ticketFare?->child_fare_percentage ?? 75) / 100,
    'infant' => $baseFare * ($ticketFare?->infant_fare_percentage ?? 10) / 100,
    default => $baseFare,
};

$route = $passenger->ticketFare?->route ?? $passenger->booking?->package?->ticketFare?->route;
$routeDisplay = '—';
if ($route) {
    $routeType = $route->route_type?->value;
    if ($routeType === 'multi_city') {
        $routeDisplay = $route->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
    } elseif ($routeType === 'round') {
        $routeDisplay = ($route->fromCity?->code ?? '?') . '-' . ($route->toCity?->code ?? '?') . '-' . ($route->returnCity?->code ?? '?');
    } else {
        $routeDisplay = ($route->fromCity?->code ?? '?') . ' → ' . ($route->toCity?->code ?? '?');
    }
}
@endphp
@php $passBookingRate = $passenger->booking?->currencyRate?->rate ?? ($currencyRateService?->getRateForDate($passenger->booking?->created_at)?->rate ?? 0); @endphp
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
            onchange="updatePassengerStatus({{ $passenger->id }}, this.value)">
            <option value="" {{ is_null($passenger->passenger_status_id) ? 'selected' : '' }}>None</option>
            @foreach($passengerStatuses as $status)
                <option value="{{ $status->id }}" {{ $passenger->passenger_status_id == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
            @endforeach
        </select>
        @else
        <span class="text-slate-700">{{ $passengerStatuses->firstWhere('id', $passenger->passenger_status_id)->name ?? 'None' }}</span>
        @endif
    </td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->passport_no ?? '—' }}</td>
    <td class="px-3 py-2 text-slate-600">{{ $routeDisplay }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->flight_date_from?->format('d M Y') . ' → ' . $passenger->flight_date_to?->format('d M Y') ?? '—' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ optional($passenger->actual_flight_date)->format('d M Y') ?: 'N/A' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->booking?->package?->package_name ?? '—' }}</td>
    @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">@if($passenger->package_value)@currency($passenger->package_value, 2, $passBookingRate)@else—@endif</td>@endif
    @if($canViewFinancialColumns)<td class="px-3 py-2"></td>@endif
    @if($canViewFinancialColumns)<td class="px-3 py-2"></td>@endif
    @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">@if($isFirstRow)@if($passenger->booking?->invoice?->balance)@currency($passenger->booking->invoice->balance, 2, $passBookingRate)@else—@endif @endif</td>@endif
    @if($canViewVisaColumns)
    <td class="px-3 py-2">
        <div class="flex items-center gap-1 flex-wrap">
            <template x-if="passengersVisaData[{{ $loop->index }}]?.visa">
                <span class="text-slate-800 font-medium text-xs mr-1" x-text="$currency(passengersVisaData[{{ $loop->index }}]?.visa?.selling_price, 2, passengersVisaData[{{ $loop->index }}]?.rate)"></span>
            </template>
            <template x-if="!passengersVisaData[{{ $loop->index }}]?.visa">
                <span class="text-slate-500 text-xs">N/A</span>
            </template>

            <template x-if="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'pending' && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'">
                <button @click="openVisaSubmitModal({{ $loop->index }})" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded font-medium transition">Submit</button>
            </template>
            <template x-if="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'submitted' && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'">
                <button @click="openVisaIssueModal({{ $loop->index }})" class="text-xs bg-green-100 hover:bg-green-200 text-green-600 px-2 py-1 rounded font-medium transition">Issue</button>
            </template>
            <template x-if="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'issued' && canEditVisa && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'">
                <button @click="openVisaEditModal({{ $loop->index }})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded font-medium transition">Edit</button>
            </template>
            <template x-if="passengersVisaData[{{ $loop->index }}]?.visa?.status === 'cancelled' && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'">
                <a href="{{ route('passengers.show', $passenger->id) }}" class="text-xs bg-orange-100 hover:bg-orange-200 text-orange-600 px-2 py-1 rounded font-medium transition">Re-Submit</a>
            </template>
            <template x-if="passengersTicketData[{{ $loop->index }}]?.fingerprint_status !== 'approved'">
                <span class="text-xs text-slate-400 italic">Fingerprint not approved</span>
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
                    'bg-red-100 text-red-700': passengersVisaData[{{ $loop->index }}]?.visa?.status === 'cancelled',
                    'bg-yellow-100 text-yellow-700': passengersVisaData[{{ $loop->index }}]?.visa?.status === 'pending'
                }"
                x-text="passengersVisaData[{{ $loop->index }}]?.visa?.status.charAt(0).toUpperCase() + passengersVisaData[{{ $loop->index }}]?.visa?.status.slice(1)">
            </span>
        </template>
        <template x-if="!passengersVisaData[{{ $loop->index }}]?.visa">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">N/A</span>
        </template>
    </td>
    @if($canViewTicketFareColumn)
    <td class="px-3 py-2 text-slate-700">
        <div class="flex items-center gap-1 flex-wrap">
            <span class="font-medium text-sm">@if($fareAmount > 0)@currency($fareAmount, 2, $passBookingRate)@else—@endif</span>
            <button x-show="(!passengersTicketData[{{ $loop->index }}]?.latest_issued_ticket || passengersTicketData[{{ $loop->index }}]?.latest_issued_ticket?.status === 'pending') && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'" @click="openTicketFareModal({{ $loop->index }})" class="text-xs bg-green-100 hover:bg-green-200 text-green-600 px-2 py-1 rounded font-medium transition">Issue</button>
            <button x-show="(passengersTicketData[{{ $loop->index }}]?.latest_issued_ticket?.status === 'issued' || passengersTicketData[{{ $loop->index }}]?.latest_issued_ticket?.status === 're-issued') && passengersTicketData[{{ $loop->index }}]?.fingerprint_status === 'approved'" @click="openTicketFareModal({{ $loop->index }})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded font-medium transition">Edit</button>
            <template x-if="passengersTicketData[{{ $loop->index }}]?.fingerprint_status !== 'approved'">
                <span class="text-xs text-slate-400 italic">Fingerprint not approved</span>
            </template>
        </div>
    </td>
    @endif
    <td class="px-3 py-2">
        <template x-if="passengersTicketData[{{ $loop->index }}]?.ticket_status">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="{
                    'bg-green-100 text-green-700': passengersTicketData[{{ $loop->index }}]?.ticket_status === 'issued',
                    'bg-purple-100 text-purple-700': passengersTicketData[{{ $loop->index }}]?.ticket_status === 're-issued',
                    'bg-red-100 text-red-700': passengersTicketData[{{ $loop->index }}]?.ticket_status === 'refunded',
                    'bg-slate-100 text-slate-600': ['issued','re-issued','refunded'].indexOf(passengersTicketData[{{ $loop->index }}]?.ticket_status) === -1
                }"
                x-text="passengersTicketData[{{ $loop->index }}]?.ticket_status.charAt(0).toUpperCase() + passengersTicketData[{{ $loop->index }}]?.ticket_status.slice(1)">
            </span>
        </template>
        <template x-if="!passengersTicketData[{{ $loop->index }}]?.ticket_status">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">—</span>
        </template>
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
            }
        @endphp
        @if($displayStatus)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                {{ $rawStatus === 'approved' ? 'bg-green-100 text-green-700' : ($rawStatus === 'processing' ? 'bg-blue-100 text-blue-700' : ($rawStatus === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600')) }}">
                {{ $displayStatus === 'Partially Approved' ? 'Partially Approved' : ucfirst($rawStatus) }}
            </span>
        @else
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">—</span>
        @endif
    </td>
    <td class="px-3 py-2">
        <a href="{{ route('passengers.show', $passenger->id) }}" class="text-slate-600 hover:text-slate-800">View</a>
    </td>
</tr>
@empty
<tr>
    <td colspan="{{ 16 + ($canViewFinancialColumns ? 4 : 0) + ($canViewVisaColumns ? 2 : 0) + ($canViewTicketFareColumn ? 1 : 0) }}" class="px-3 py-4 text-center text-slate-500">No passengers found</td>
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
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.agentCommission" min="0" @input="calculateVisaCost()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.netVisaCost" @input="calculateVisaCost()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.sellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
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
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (SAR)</label>
                        <input type="number" x-model="visaIssueForm.sellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Additional Cost (SAR)</label>
                        <input type="number" x-model="visaIssueForm.additionalCost" min="0" @input="calculateVisaIssueFinal()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
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
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (SAR)</label>
                        <input type="number" x-model="visaEditForm.sellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (SAR)</label>
                        <input type="number" x-model="visaEditForm.agentCommission" min="0" @input="calculateVisaEditFinal()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (SAR)</label>
                        <input type="number" x-model="visaEditForm.netVisaCost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Additional Cost (SAR)</label>
                        <input type="number" x-model="visaEditForm.additionalCost" min="0" @input="calculateVisaEditFinal()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                        <textarea x-model="visaEditForm.remarks" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter remarks" rows="2"></textarea>
                    </div>
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

    {{-- Ticket Fare Modal --}}
    <div x-show="isTicketFareModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeTicketFareModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeTicketFareModal()"></div>
        <div x-show="isTicketFareModalOpen" x-cloak class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4" id="ticketFareModalTitle" x-text="ticketFareModalTitle"></h3>
            <form @submit.prevent="handleTicketFareSubmit()">
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
                            <select x-model="ticketFareForm.route_type" @change="handleTicketFareRouteTypeChange(); handleRouteTypeOrFlightTypeChange()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
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
                                    <option :value="opt.value" x-text="opt.display"></option>
                                </template>
                            </select>
                        </div>
                        <div x-show="ticketFareForm.showInboundDate">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date *</label>
                            <input type="date" x-model="ticketFareForm.inbound_date" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div x-show="ticketFareForm.showOutboundDate">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date *</label>
                            <input type="date" x-model="ticketFareForm.outbound_date" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">PNR</label>
                            <input type="text" x-model="ticketFareForm.pnr" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter PNR">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Number *</label>
                            <input type="text" x-model="ticketFareForm.ticket_number" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter Ticket Number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Issue Date *</label>
                            <input type="date" x-model="ticketFareForm.date" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Agent *</label>
                            <select x-model="ticketFareForm.ticket_agent" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select Agent</option>
                                <template x-for="agent in ticketAgents" :key="agent.id">
                                    <option :value="agent.name" x-text="agent.name"></option>
                                </template>
                            </select>
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
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR)</label>
                            <input type="number" x-model="ticketFareForm.selling_fare" min="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (SAR)</label>
                            <input type="number" x-model="ticketFareForm.net_fare" min="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0">
                        </div>
                        <div x-show="ticketFareForm.ticket_type === 'offer'">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR)</label>
                            <input type="number" x-model="ticketFareForm.offer_price" min="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" value="0">
                        </div>
                    </div>
                </div>

                <div x-show="ticketFareForm.showBaggage" class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Baggage Info</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div x-show="ticketFareForm.showInboundDate">
                            <label class="block text-sm text-slate-600 mb-1">Inbound Baggage (KG)</label>
                            <input type="text" x-model="ticketFareForm.baggage_inbound" placeholder="e.g. 30" list="baggageSuggestInbound" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                            <datalist id="baggageSuggestInbound">
                                <option value="30"><option value="40"><option value="50"><option value="20"><option value="25">
                            </datalist>
                        </div>
                        <div x-show="ticketFareForm.showOutboundDate">
                            <label class="block text-sm text-slate-600 mb-1">Outbound Baggage (KG)</label>
                            <input type="text" x-model="ticketFareForm.baggage_outbound" placeholder="e.g. 50" list="baggageSuggestOutbound" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                            <datalist id="baggageSuggestOutbound">
                                <option value="50"><option value="40"><option value="30"><option value="25"><option value="20">
                            </datalist>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Ticket Options</h4>
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="ticketFareForm.non_refundable" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                            <span class="text-sm text-slate-700">Non-Refundable</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="ticketFareForm.non_exchangeable" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                            <span class="text-sm text-slate-700">Non-Exchangeable</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4" x-show="ticketFareForm.route_type === 'One Way-Inbound'">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="ticketFareForm.outbound_pending" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                        <span class="text-sm text-slate-700">Outbound Ticket Pending</span>
                    </label>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium" x-text="ticketFareModalTitle === 'Issue Ticket' ? 'Issue Ticket' : 'Save Changes'"></button>
                    <button type="button" @click="closeTicketFareModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    @include('partials.route-form-modal')
    @include('partials.airline-form-modal')
    @include('partials.class-form-modal')
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
        branchCounts: @json($branchCounts),
        allBookingCount: {{ $allBookingCount }},
        selectedFingerprintStatus: '{{ $selectedFingerprintStatus ?? '' }}',
        selectedVisaStatus: '{{ $selectedVisaStatus ?? '' }}',
        selectedTicketStatus: '{{ $selectedTicketStatus ?? '' }}',
        selectedVisaAgentId: '{{ $selectedVisaAgentId ?? '' }}',
        selectedBookingDateFrom: '{{ $selectedBookingDateFrom ?? '' }}',
        selectedBookingDateTo: '{{ $selectedBookingDateTo ?? '' }}',
        selectedFingerprintLocation: '{{ $selectedFingerprintLocation ?? '' }}',
        totalPassengerCount: {{ $totalPassengerCount }},

        init() {
            if (this.activeTab === 'passenger') {
                document.body.style.overflow = 'hidden';
            }
            this.$watch('activeTab', (newVal) => {
                document.body.style.overflow = newVal === 'passenger' ? 'hidden' : '';
            });

            this.$nextTick(() => {
                if (this.searchTerm && this.$refs.searchInput) {
                    this.$refs.searchInput.focus();
                    const len = this.searchTerm.length;
                    this.$refs.searchInput.setSelectionRange(len, len);
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
                    window.location.href = url.toString();
                }, 500);
            });
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

        passengersVisaData: @json($passengersVisaData),
        passengersTicketData: @json($passengersTicketData),

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
        editingVisaIndex: null,

        visaSubmitForm: {
            agentId: '',
            commissionAgentId: '',
            sellingPrice: 0,
            agentCommission: 0,
            netVisaCost: 0,
            finalCost: 0,
            commissionAgents: []
        },

        visaIssueForm: {
            agentName: '',
            visaNumber: '',
            sellingPrice: 0,
            additionalCost: 0,
            finalCost: 0,
            remarks: ''
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
            commissionAgents: []
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

            this.visaSubmitForm.sellingPrice = data?.visa?.selling_price || 0;
            this.visaSubmitForm.agentCommission = data?.visa?.agent_commission || 0;
            this.visaSubmitForm.netVisaCost = data?.visa?.net_visa_cost || 0;

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
            this.calculateVisaCost();
        },

        calculateVisaCost() {
            const commission = parseFloat(this.visaSubmitForm.agentCommission) || 0;
            const net = parseFloat(this.visaSubmitForm.netVisaCost) || 0;
            this.visaSubmitForm.finalCost = commission + net;
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

            const baseCost = (visa?.final_cost || 0) - (visa?.additional_cost || 0);
            this._visaIssueBaseCost = baseCost;
            this.visaIssueForm.agentName = visa?.agent || '';
            this.visaIssueForm.visaNumber = visa?.visa_number || '';
            this.visaIssueForm.sellingPrice = visa?.selling_price || 0;
            this.visaIssueForm.additionalCost = visa?.additional_cost || 0;
            this.visaIssueForm.finalCost = visa?.final_cost || 0;
            this.visaIssueForm.remarks = visa?.remarks || '';

            this.calculateVisaIssueFinal();
            this.visaIssueModalVisible = true;
        },

        closeVisaIssueModal() {
            this.editingVisaIndex = null;
            this.visaIssueModalVisible = false;
        },

        calculateVisaIssueFinal() {
            const additional = parseFloat(this.visaIssueForm.additionalCost) || 0;
            this.visaIssueForm.finalCost = (this._visaIssueBaseCost || 0) + additional;
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

            this.calculateVisaEditFinal();
            this.visaEditModalVisible = true;
        },

        closeVisaEditModal() {
            this.editingVisaIndex = null;
            this.visaEditModalVisible = false;
        },

        updateEditCommissionAgents(agentId) {
            this.visaEditForm.commissionAgents = this.getCommissionAgents(agentId);
            this.visaEditForm.commissionAgentId = '';
            this.visaEditForm.netVisaCost = this.getVisaAgentCost(agentId);
            this.calculateVisaEditFinal();
        },

        calculateVisaEditFinal() {
            const commission = parseFloat(this.visaEditForm.agentCommission) || 0;
            const net = parseFloat(this.visaEditForm.netVisaCost) || 0;
            const additional = parseFloat(this.visaEditForm.additionalCost) || 0;
            this.visaEditForm.finalCost = commission + net + additional;
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

        isTicketFareModalOpen: false,
        editingPassengerIndex: null,
        ticketFareModalTitle: 'Issue Ticket',

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
            date: (() => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); })(),
            ticket_agent: '',
            route: '',
            airline: '',
            travel_class: '',
            passenger_type: '',
            selling_fare: 0,
            net_fare: 0,
            offer_price: 0,
            baggage_inbound: '',
            baggage_outbound: '',
            non_refundable: false,
            non_exchangeable: false,
            outbound_pending: false,
            showInboundDate: false,
            showOutboundDate: false,
            showBaggage: false,
        },

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

        openTicketFareModal(rowIndex) {
            this.editingPassengerIndex = rowIndex;
            const row = this.passengersTicketData[rowIndex];
            if (!row) return;

            const lit = row.latest_issued_ticket;
            const isAlreadyIssued = lit && (lit.status === 'issued' || lit.status === 're-issued');
            this.ticketFareModalTitle = isAlreadyIssued ? 'Edit Ticket' : 'Issue Ticket';

            this.ticketFareForm.route = row.route || '';
            this.ticketFareForm.airline = row.airline || '';
            this.ticketFareForm.travel_class = row.travel_class || '';
            this.ticketFareForm.passenger_type = row.passenger_type || '';

            const today = (() => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); })();

            if (lit) {
                this.ticketFareForm.ticket_number = lit.ticket_number || '';
                this.ticketFareForm.pnr = lit.pnr || '';
                this.ticketFareForm.ticket_agent = lit.ticket_agent_name || '';
                this.ticketFareForm.date = lit.issued_date || today;
                this.ticketFareForm.inbound_date = lit.inbound_date || '';
                this.ticketFareForm.outbound_date = lit.outbound_date || '';
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
            } else if (row.ticket_fare) {
                this.ticketFareForm.ticket_type = row.ticket_fare.ticket_type || '';
                this.ticketFareForm.route_type = row.ticket_fare.route_type || '';
                this.ticketFareForm.flight_type = row.ticket_fare.flight_type || '';
                this.ticketFareForm.inbound_date = row.ticket_fare.inbound_date || '';
                this.ticketFareForm.outbound_date = row.ticket_fare.outbound_date || '';
                this.ticketFareForm.pnr = row.ticket_fare.pnr || '';
                this.ticketFareForm.ticket_number = row.ticket_fare.ticket_number || '';
                this.ticketFareForm.date = row.ticket_fare.date || today;
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
            }

            this._initLock = true;
            this.handleTicketFareRouteTypeChange();
            this.handleTicketTypeChange();

            if (!isAlreadyIssued && row.ticket_fare) {
                const opt = this.filteredTicketOptions.find(o => o.value == row.ticket_fare.ticket_fare_id);
                if (opt) {
                    this.ticketFareForm.ticket_option = opt.value;
                    this.handleTicketOptionChange();
                }

            }

            this._initLock = false;
            this.suggestBaggage();
            this.isTicketFareModalOpen = true;
        },

        calculateFareForPassengerType(baseFare, passengerType, childPct, infantPct) {
            if (passengerType === 'child') return Math.round((baseFare * (childPct || 70)) / 100);
            if (passengerType === 'infant') return Math.round((baseFare * (infantPct || 30)) / 100);
            return baseFare;
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
            this.ticketFareForm.offer_price = 0;
            this.ticketFareForm.baggage_inbound = '';
            this.ticketFareForm.baggage_outbound = '';
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
            const row = this.passengersTicketData[this.editingPassengerIndex];
            if (!row) return null;
            return row.latest_issued_ticket?.ticket_fare_id || row.ticket_fare_id || null;
        },

        mapIssuedTicketToForm(ticket) {
            const fare = ticket.ticket_fare || {};
            const route = fare.route || {};
            return {
                ticket_type: fare.ticket_type || 'regular',
                route_type: route.route_type || '',
                flight_type: route.flight_type || '',
                inbound_date: ticket.inbound_date || '',
                outbound_date: ticket.outbound_date || '',
                pnr: ticket.pnr || '',
                ticket_number: ticket.ticket_number || '',
                date: ticket.issued_date || '',
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

            const row = this.passengersTicketData[this.editingPassengerIndex];
            if (!row) return;

            const isEdit = row.latest_issued_ticket && (row.latest_issued_ticket.status === 'issued' || row.latest_issued_ticket.status === 're-issued');

            if (!isEdit && row.latest_issued_ticket && row.latest_issued_ticket.status !== 'pending') {
                this.showToast('This ticket cannot be issued again.', 'error');
                return;
            }

            const payload = {
                ticket_number: this.ticketFareForm.ticket_number || '',
                pnr: this.ticketFareForm.pnr || '',
                ticket_agent_id: this.getAgentIdByName(this.ticketFareForm.ticket_agent),
                ticket_fare_id: this.getSelectedFareId(),
                group_ticket_id: this.ticketFareForm.group_ticket_id || null,
                issued_date: this.ticketFareForm.date || '',
                inbound_date: this.ticketFareForm.inbound_date || null,
                outbound_date: this.ticketFareForm.outbound_date || null,
                selling_fare: parseFloat(this.ticketFareForm.selling_fare) || 0,
                net_fare: parseFloat(this.ticketFareForm.net_fare) || 0,
                offer_price: parseFloat(this.ticketFareForm.offer_price) || 0,
                is_refundable: !this.ticketFareForm.non_refundable,
                is_exchangeable: !this.ticketFareForm.non_exchangeable,
                baggage_inbound: this.ticketFareForm.baggage_inbound || '',
                baggage_outbound: this.ticketFareForm.baggage_outbound || '',
                outbound_pending: this.ticketFareForm.outbound_pending || false,
                issue_type: 'regular',
            };

            const url = isEdit
                ? `/bookings/${row.booking_id}/passengers/${row.id}/ticket-edit`
                : `/bookings/${row.booking_id}/passengers/${row.id}/ticket-issue`;

            fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    row.ticket_status = 'issued';
                    const t = data.issued_ticket;
                    row.latest_issued_ticket = {
                        id: t.id,
                        ticket_number: t.ticket_number,
                        pnr: t.pnr,
                        ticket_agent_id: t.ticket_agent_id,
                        ticket_agent_name: t.ticket_agent?.name || payload.ticket_agent || '',
                        ticket_fare_id: t.ticket_fare_id,
                        group_ticket_id: t.group_ticket_id,
                        issued_date: t.issued_date,
                        inbound_date: t.inbound_date,
                        outbound_date: t.outbound_date,
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
                    this.showToast(data.message || 'Ticket saved successfully.');
                    this.closeTicketFareModal();
                } else {
                    this.showToast(data.message || 'Failed to save ticket.', 'error');
                }
            })
            .catch(err => {
                console.error('Ticket submit error:', err);
                this.showToast('Failed to save ticket.', 'error');
            });
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
                return { display, value: f.id };
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
                this.ticketFareForm.pnr = fare.pnr || '';
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
                        this.ticketFareForm.offer_price = row.ticket_fare.offer_price;
                    }
                } else {
                    const pType = row?.passenger_type || 'adult';
                    this.ticketFareForm.selling_fare = this.calculateFareForPassengerType(fare.selling_fare, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                    this.ticketFareForm.net_fare = this.calculateFareForPassengerType(fare.net_fare, pType, fare.child_fare_percentage, fare.infant_fare_percentage);
                    if (fare.ticket_type === 'offer' && fare.offer_price) {
                        this.ticketFareForm.offer_price = fare.offer_price;
                    }
                }
            }
            this.suggestBaggage();
        },

        suggestBaggage() {
            const idx = this.editingPassengerIndex;
            if (idx === null) return;
            const row = this.passengersTicketData[idx];
            const allowances = row?.ticket_fare?.baggage_allowances;
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
                @if($canViewFinancialColumns)
                if (cells.length >= 12) {
                    const rate = parseFloat(selectEl.dataset.rate) || 0;
                    cells[9].textContent = Alpine.store('currency').format(data.invoice.total_amount, 2, rate);
                    cells[10].textContent = Alpine.store('currency').format(data.invoice.paid_amount, 2, rate);
                    cells[11].textContent = Alpine.store('currency').format(data.invoice.balance, 2, rate);
                }
                @endif
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
</script>
@endsection