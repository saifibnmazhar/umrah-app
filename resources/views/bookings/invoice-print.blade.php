@extends('layouts.app')

@section('title', 'Print Invoice')

@section('navigation')
@endsection

@section('content')
<style>
    @page {
        size: landscape;
        margin: 0.1cm;
    }
    @media print {
        .no-print { display: none !important; }
        body { margin: 0; padding: 0 !important; background: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .invoice-container { max-width: 100%; }
        .invoice-card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; page-break-inside: avoid; }
        .invoice-bg { background: white !important; }
        .text-\[11px\] { font-size: 9px !important; }
        .overflow-x-auto { overflow: visible !important; }
        table { page-break-inside: auto; white-space: normal !important; }
        tr { page-break-inside: avoid; }
        table th, table td { padding: 1px 2px !important; }
    }
    .invoice-container {
        max-width: 1200px;
        margin: 0 auto;
        font-size: 11px;
    }
</style>

<div class="invoice-container invoice-bg p-1" style="background: #f8fafc;" id="invoiceContent">

    {{-- Header --}}
    <div class="text-center mb-2">
        <h1 class="text-lg font-bold text-slate-800">BOOKING INVOICE UMH</h1>
        <p class="text-xs text-slate-500 mt-0">Phone: {{ $booking->bookingBranch->contacts ?? '+966XXX-XXXXXXX' }}</p>
        <p class="text-xs text-slate-500">{{ $booking->bookingBranch->name ?? 'BMT-Dak' }}</p>
    </div>

    <div class="text-right mb-2">
        <span class="text-xs font-bold text-slate-800">Invoice No: {{ $booking->invoice_id ?? '-' }}</span>
    </div>

    @php
        $fpLocationValue = $booking->fingerprint_location;
        if ($fpLocationValue instanceof \BackedEnum) { $fpLocationValue = $fpLocationValue->value; }
        if ($fpLocationValue === 'home') {
            $fpLocation = 'Home';
        } elseif ($fpLocationValue === 'office') {
            $fpLocation = $booking->fingerprintBranch?->address ?? '-';
        } else {
            $fpLocation = '-';
        }
        $fingerprintDeadline = $booking->fingerprint?->deadline?->format('d M Y');
        $passengerMobileBd = $booking->passengers->first()?->mobile_no ?? '-';
        $passengerAddress = $booking->passengers->first()?->address ?? '-';
        $addressKsa = $booking->customer->address ?? '-';
    @endphp

    {{-- Customer Information & Invoice Information --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-0 mb-2">

        {{-- Customer Information --}}
        <div class="bg-white border border-slate-500 invoice-card overflow-hidden">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Customer Information</div>
            <table class="w-full text-xs border-collapse">
                <tbody>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Booking Date :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $booking->booking_date ?? ($booking->created_at ? $booking->created_at->format('d M Y') : '-') }}</td>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Passenger Mobile (BD) :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $passengerMobileBd }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Customer Name :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $booking->customer->name ?? '-' }}</td>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Passenger Address :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $passengerAddress }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Iqama Number :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $booking->customer->iqama_no ?? '-' }}</td>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Fingerprint Deadline :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $fingerprintDeadline ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Customer Mobile :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $booking->customer->mobile_no ?? '-' }}</td>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Fingerprint Location :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $fpLocation ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Address (KSA) :</td>
                        <td class="px-2 py-1 " colspan="3">{{ $addressKsa }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Invoice Information --}}
        <div class="bg-white border border-slate-500 invoice-card overflow-hidden ml-10">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Invoice Information</div>
            <table class="w-full text-xs border-collapse">
                <tbody>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-2/5">Invoice Date :</td>
                        <td class="px-2 py-1">{{ $invoiceDate ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-2/5">Booking Date :</td>
                        <td class="px-2 py-1">{{ $booking->created_at ? $booking->created_at->format('d M Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-2/5">Booking Branch :</td>
                        <td class="px-2 py-1">{{ $booking->bookingBranch->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-2/5">Fingerprint Branch :</td>
                        <td class="px-2 py-1">{{ $booking->fingerprintBranch->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-2/5">Sale Representative :</td>
                        <td class="px-2 py-1">{{ $booking->user->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-2/5">Remarks :</td>
                        <td class="px-2 py-1">{{ $booking->remarks ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Passenger & Flight Details --}}
    <div class="bg-white border border-slate-300 invoice-card mb-2 overflow-hidden">
        <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Passenger & Flight Details</div>
        <div class="overflow-x-auto">
            <table class="w-full text-[11px] border-collapse whitespace-nowrap">
                <thead>
                    <tr class="bg-[#FFE699]">
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Pax No.</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Name of passengers</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Gender</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Passport number</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Package</th>
                        <th class="px-1 py-1 text-center font-semibold border border-[#00853e]" rowspan="2">Duration (Days)</th>
                        <th class="px-1 py-1 text-right font-semibold border border-[#00853e]" rowspan="2">Package Value</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Trip</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Airline</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Route</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Est.Flight Date</th>
                        <th class="px-1 py-1 text-center font-semibold border border-[#00853e]" colspan="2">Baggage</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Cabin</th>
                        <th class="px-1 py-1 text-center font-semibold border border-[#00853e]" rowspan="2">Meal</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Flight Type</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]" rowspan="2">Remarks</th>
                    </tr>
                    <tr class="bg-[#FFE699]">
                        <th class="px-1 py-1 text-center font-semibold border border-[#00853e]">In</th>
                        <th class="px-1 py-1 text-center font-semibold border border-[#00853e]">Out</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($booking->passengers as $index => $passenger)
                    <tr>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $index + 1 }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->first_name ?? '' }} {{ $passenger->last_name ?? '' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->gender ?? '-' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->passport_no ?? '-' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $booking->package?->package_name ?? 'Package' }}</td>
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $passenger->stay_duration ?? '-' }}</td>
                        <td class="px-1 py-0.5 text-right border border-slate-300">@currency($passenger->package_value ?? 0, 2, $rate)</td>
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">In Bound</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">Out Bound</div>
                        </td>
                        @php
                            $_airIn = 'N/A';
                            $_airOut = 'N/A';
                            if ($passenger->ticket_fare_inbound_id) {
                                $_airIn = $passenger->ticketFareInbound?->airline?->code ?? 'N/A';
                                $_airOut = $passenger->ticketFareOutbound?->airline?->code ?? 'N/A';
                            } else {
                                $_alCode = $passenger->ticketFare?->airline?->code ?? null;
                                $_rt = $passenger->ticketFare?->route?->route_type?->value;
                                if ($_alCode) {
                                    if ($_rt === 'oneway_inbound') {
                                        $_airIn = $_alCode;
                                    } elseif ($_rt === 'oneway_outbound') {
                                        $_airOut = $_alCode;
                                    } else {
                                        $_airIn = $_alCode;
                                        $_airOut = $_alCode;
                                    }
                                }
                            }
                        @endphp
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $_airIn }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $_airOut }}</div>
                        </td>
                        @php
                            $_routeTop = 'N/A';
                            $_routeBottom = 'N/A';
                            $_isSplit = false;
                            $_fmtRt = function($r) {
                                if (!$r) return 'N/A';
                                return ($r->fromCity?->code ?? '-') . '-' . ($r->toCity?->code ?? '-');
                            };

                            if ($passenger->ticket_fare_inbound_id) {
                                $_routeTop = $_fmtRt($passenger->ticketFareInbound?->route);
                                $_routeBottom = $_fmtRt($passenger->ticketFareOutbound?->route);
                                $_isSplit = true;
                            } else {
                                $_routeType = $passenger->ticketFare?->route?->route_type?->value;
                                if ($_routeType === 'oneway_inbound') {
                                    $_routeTop = $passenger->route_display;
                                    $_isSplit = true;
                                } elseif ($_routeType === 'oneway_outbound') {
                                    $_routeBottom = $passenger->route_display;
                                    $_isSplit = true;
                                } elseif ($_routeType === 'multi_city') {
                                    $_segments = $passenger->ticketFare?->route?->multiSegments ?? collect();
                                    $_inSegment = $_segments->first(fn($s) => $s->segment_direction?->value === 'inbound');
                                    $_outSegment = $_segments->first(fn($s) => $s->segment_direction?->value === 'outbound');
                                    if ($_inSegment && $_inSegment->fromCity && $_inSegment->toCity) {
                                        $_routeTop = $_inSegment->fromCity->code . ' → ' . $_inSegment->toCity->code;
                                    }
                                    if ($_outSegment && $_outSegment->fromCity && $_outSegment->toCity) {
                                        $_routeBottom = $_outSegment->fromCity->code . ' → ' . $_outSegment->toCity->code;
                                    }
                                    $_isSplit = true;
                                }
                            }
                        @endphp
                        @if($_isSplit)
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $_routeTop }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $_routeBottom }}</div>
                        </td>
                        @else
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $passenger->route_display }}</td>
                        @endif
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $passenger->flight_date_display }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">After {{ $passenger->stay_duration ?? '-' }} Days</div>
                        </td>
                        @php
                            $_bd = $passenger->baggage_display;
                            $_in = 'N/A';
                            $_out = 'N/A';
                            if ($_bd !== '-' && $_bd !== '') {
                                foreach (explode("\n", $_bd) as $_line) {
                                    if (str_starts_with($_line, 'In:')) {
                                        $_raw = trim(substr($_line, 3));
                                        $_in = preg_replace('/[^0-9]/', '', $_raw) ?: 'N/A';
                                    } elseif (str_starts_with($_line, 'Out:')) {
                                        $_raw = trim(substr($_line, 4));
                                        $_out = preg_replace('/[^0-9]/', '', $_raw) ?: 'N/A';
                                    }
                                }
                            }
                        @endphp
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $_in }}</td>
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $_out }}</td>
                        @php
                            $_cabinVal = $passenger->ticketFare?->airlineClass?->travelClass?->name ?? '-';
                            $_cabinTop = $_cabinVal;
                            $_cabinBottom = $_cabinVal;
                            $_cabinSplit = false;

                            if ($passenger->ticket_fare_inbound_id) {
                                $_cabinTop = $passenger->ticketFareInbound?->airlineClass?->travelClass?->name ?? 'N/A';
                                $_cabinBottom = $passenger->ticketFareOutbound?->airlineClass?->travelClass?->name ?? 'N/A';
                                $_cabinSplit = true;
                            } else {
                                $_cabinRt = $passenger->ticketFare?->route?->route_type?->value;
                                if ($_cabinRt === 'oneway_inbound') {
                                    $_cabinBottom = 'N/A';
                                    $_cabinSplit = true;
                                } elseif ($_cabinRt === 'oneway_outbound') {
                                    $_cabinTop = 'N/A';
                                    $_cabinSplit = true;
                                }
                            }
                        @endphp
                        @if($_cabinSplit)
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $_cabinTop }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $_cabinBottom }}</div>
                        </td>
                        @else
                        <td class="px-1 py-0.5 border border-slate-300">{{ $_cabinVal }}</td>
                        @endif
                        @php
                            $_mealVal = $passenger->meal_display;
                            $_mealTop = $_mealVal;
                            $_mealBottom = $_mealVal;
                            $_mealSplit = false;

                            if ($passenger->ticket_fare_inbound_id) {
                                foreach (explode("\n", $_mealVal) as $_line) {
                                    if (str_starts_with($_line, 'In:')) $_mealTop = trim(substr($_line, 3)) ?: 'N/A';
                                    elseif (str_starts_with($_line, 'Out:')) $_mealBottom = trim(substr($_line, 4)) ?: 'N/A';
                                }
                                $_mealSplit = true;
                            } else {
                                $_mealRt = $passenger->ticketFare?->route?->route_type?->value;
                                if ($_mealRt === 'oneway_inbound') {
                                    $_mealBottom = 'N/A';
                                    $_mealSplit = true;
                                } elseif ($_mealRt === 'oneway_outbound') {
                                    $_mealTop = 'N/A';
                                    $_mealSplit = true;
                                }
                            }
                        @endphp
                        @if($_mealSplit)
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $_mealTop }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $_mealBottom }}</div>
                        </td>
                        @else
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $_mealVal }}</td>
                        @endif
                        @php
                            $_fmt = function($mins) {
                                if (!$mins || $mins <= 0) return 'Direct';
                                $h = intdiv($mins, 60);
                                $m = $mins % 60;
                                return sprintf('Transit: %02d hr %02d min', $h, $m);
                            };

                            $_ftTop = 'N/A';
                            $_ftBottom = 'N/A';
                            $_ftSplit = false;

                            if ($passenger->ticket_fare_inbound_id) {
                                $inRoute = $passenger->ticketFareInbound?->route;
                                $outRoute = $passenger->ticketFareOutbound?->route;
                                if ($inRoute) {
                                    $mins = $inRoute->transits->sum('transit_time');
                                    $_ftTop = $inRoute->flight_type?->value === 'transit' ? $_fmt($mins) : 'Direct';
                                }
                                if ($outRoute) {
                                    $mins = $outRoute->transits->sum('transit_time');
                                    $_ftBottom = $outRoute->flight_type?->value === 'transit' ? $_fmt($mins) : 'Direct';
                                }
                                $_ftSplit = true;
                            } else {
                                $_ftRt = $passenger->ticketFare?->route?->route_type?->value;
                                $_ftType = $passenger->ticketFare?->route?->flight_type?->value;
                                $_transits = $passenger->ticketFare?->route?->transits ?? collect();
                                $_inMins = $_transits->filter(fn($t) => $t->route_direction?->value === 'inbound')->sum('transit_time');
                                $_outMins = $_transits->filter(fn($t) => $t->route_direction?->value === 'outbound')->sum('transit_time');

                                if ($_ftRt === 'oneway_inbound') {
                                    $_ftTop = $_ftType === 'transit' ? $_fmt($_inMins) : 'Direct';
                                    $_ftSplit = true;
                                } elseif ($_ftRt === 'oneway_outbound') {
                                    $_ftBottom = $_ftType === 'transit' ? $_fmt($_outMins) : 'Direct';
                                    $_ftSplit = true;
                                } elseif (in_array($_ftRt, ['round', 'multi_city'])) {
                                    if ($_ftType === 'transit') {
                                        $_ftTop = $_fmt($_inMins);
                                        $_ftBottom = $_fmt($_outMins);
                                        $_ftSplit = true;
                                    } else {
                                        $_ftTop = 'Direct';
                                    }
                                }
                            }
                        @endphp
                        @if($_ftSplit)
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $_ftTop }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $_ftBottom }}</div>
                        </td>
                        @else
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $_ftTop }}</td>
                        @endif
                        <td class="px-1 py-0.5 border border-slate-300">{{ $booking->remarks ?? '-' }}</td>
                    </tr>
                    @foreach($passenger->allIssuedTickets->where('issue_type', 'additional')->sortBy('id') as $addTicket)
                    @php
                        $addTf = $addTicket->ticketFare;
                        $addRoute = $addTf?->route;
                        $addRouteType = $addRoute?->route_type?->value ?? '';
                        $addInDate = $addTicket->inbound_date;
                        $addOutDate = $addTicket->outbound_date;
                        $addDuration = ($addInDate && $addOutDate) ? $addInDate->diffInDays($addOutDate) : null;
                        $addIsSplit = in_array($addRouteType, ['round', 'multi_city']);
                    @endphp
                    <tr class="bg-slate-50">
                        <td class="px-1 py-0.5 border border-slate-300"></td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $addTicket->passenger->first_name ?? '' }} {{ $addTicket->passenger->last_name ?? '' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $addTicket->passenger->gender ?? '-' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $addTicket->passenger->passport_no ?? '-' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">N/A</td>
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $addDuration !== null ? $addDuration : 'N/A' }}</td>
                        <td class="px-1 py-0.5 text-right border border-slate-300">N/A</td>
                        <td class="px-1 py-0 text-center border border-slate-300">
                            @if($addIsSplit)
                            <div class="py-0.5 leading-tight">In Bound</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">Out Bound</div>
                            @elseif($addRouteType === 'oneway_inbound')
                            <div class="py-0.5 leading-tight">In Bound</div>
                            @elseif($addRouteType === 'oneway_outbound')
                            <div class="py-0.5 leading-tight">Out Bound</div>
                            @else
                            <div class="py-0.5 leading-tight">-</div>
                            @endif
                        </td>
                        @php
                            $addAirIn = 'N/A';
                            $addAirOut = 'N/A';
                            if ($addIsSplit) {
                                $addAirIn = $addTf?->airline?->code ?? 'N/A';
                                $addAirOut = $addTf?->airline?->code ?? 'N/A';
                            } elseif ($addRouteType === 'oneway_inbound') {
                                $addAirIn = $addTf?->airline?->code ?? 'N/A';
                            } elseif ($addRouteType === 'oneway_outbound') {
                                $addAirOut = $addTf?->airline?->code ?? 'N/A';
                            } else {
                                $addAirIn = $addTf?->airline?->code ?? 'N/A';
                                $addAirOut = $addTf?->airline?->code ?? 'N/A';
                            }
                        @endphp
                        <td class="px-1 py-0 text-center border border-slate-300">
                            @if($addIsSplit)
                            <div class="py-0.5 leading-tight">{{ $addAirIn }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $addAirOut }}</div>
                            @else
                            <div class="py-0.5 leading-tight">{{ $addRouteType === 'oneway_outbound' ? $addAirOut : $addAirIn }}</div>
                            @endif
                        </td>
                        @php
                            $addFmtRt = function($r) {
                                if (!$r) return 'N/A';
                                return ($r->fromCity?->code ?? '-') . '-' . ($r->toCity?->code ?? '-');
                            };
                            $addRouteTop = 'N/A';
                            $addRouteBottom = 'N/A';
                            if ($addIsSplit) {
                                if ($addRouteType === 'multi_city' && $addRoute?->multiSegments) {
                                    $addInSeg = $addRoute->multiSegments->first(fn($s) => $s->segment_direction?->value === 'inbound');
                                    $addOutSeg = $addRoute->multiSegments->first(fn($s) => $s->segment_direction?->value === 'outbound');
                                    if ($addInSeg && $addInSeg->fromCity && $addInSeg->toCity) {
                                        $addRouteTop = $addInSeg->fromCity->code . ' → ' . $addInSeg->toCity->code;
                                    }
                                    if ($addOutSeg && $addOutSeg->fromCity && $addOutSeg->toCity) {
                                        $addRouteBottom = $addOutSeg->fromCity->code . ' → ' . $addOutSeg->toCity->code;
                                    }
                                } else {
                                    $addRouteTop = $addFmtRt($addRoute);
                                    $addRouteBottom = $addFmtRt($addRoute);
                                }
                            } elseif ($addRouteType === 'oneway_inbound') {
                                $addRouteTop = $addFmtRt($addRoute);
                            } elseif ($addRouteType === 'oneway_outbound') {
                                $addRouteBottom = $addFmtRt($addRoute);
                            } else {
                                $addRouteTop = $addFmtRt($addRoute);
                            }
                        @endphp
                        @if($addIsSplit)
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $addRouteTop }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $addRouteBottom }}</div>
                        </td>
                        @else
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $addRouteType === 'oneway_outbound' ? $addRouteBottom : $addRouteTop }}</td>
                        @endif
                        @if($addIsSplit && $addInDate && $addOutDate)
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $addInDate->format('d M Y') }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">After {{ $addDuration }} Days</div>
                        </td>
                        @else
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ ($addRouteType === 'oneway_outbound' && $addOutDate) ? $addOutDate->format('d M Y') : ($addInDate ? $addInDate->format('d M Y') : '-') }}</td>
                        @endif
                        @php
                            $addBdIn = 'N/A';
                            $addBdOut = 'N/A';
                            $addPassengerType = $addTicket->passenger?->passenger_type?->value;
                            if ($addTf && $addPassengerType) {
                                $addAllowances = $addTf->baggageAllowances ?? collect();
                                $addInBag = $addAllowances->filter(fn($a) => $a->travel_direction?->value === 'inbound' && ($a->passenger_type?->value ?? $a->passenger_type) === $addPassengerType)->first()?->allowance;
                                $addOutBag = $addAllowances->filter(fn($a) => $a->travel_direction?->value === 'outbound' && ($a->passenger_type?->value ?? $a->passenger_type) === $addPassengerType)->first()?->allowance;
                                if ($addInBag !== null) $addBdIn = preg_replace('/[^0-9]/', '', $addInBag) ?: 'N/A';
                                if ($addOutBag !== null) $addBdOut = preg_replace('/[^0-9]/', '', $addOutBag) ?: 'N/A';
                            }
                        @endphp
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $addRouteType === 'oneway_outbound' ? $addBdOut : $addBdIn }}</td>
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $addRouteType === 'oneway_inbound' ? $addBdIn : $addBdOut }}</td>
                        @php
                            $addCabinVal = $addTf?->airlineClass?->travelClass?->name ?? '-';
                            $addCabinTop = $addCabinVal;
                            $addCabinBottom = $addCabinVal;
                            $addCabinSplit = false;
                            if ($addIsSplit) {
                                $addCabinTop = $addCabinVal;
                                $addCabinBottom = $addCabinVal;
                                $addCabinSplit = true;
                            } elseif ($addRouteType === 'oneway_inbound') {
                                $addCabinBottom = 'N/A';
                                $addCabinSplit = true;
                            } elseif ($addRouteType === 'oneway_outbound') {
                                $addCabinTop = 'N/A';
                                $addCabinSplit = true;
                            }
                        @endphp
                        @if($addCabinSplit)
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $addCabinTop }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $addCabinBottom }}</div>
                        </td>
                        @else
                        <td class="px-1 py-0.5 border border-slate-300">{{ $addCabinVal }}</td>
                        @endif
                        @php
                            $addMealVal = $addTf?->with_meal === true ? 'Yes' : 'No';
                            $addMealTop = $addMealVal;
                            $addMealBottom = $addMealVal;
                            $addMealSplit = false;
                            if ($addIsSplit) {
                                $addMealSplit = true;
                            } elseif ($addRouteType === 'oneway_inbound') {
                                $addMealBottom = 'N/A';
                                $addMealSplit = true;
                            } elseif ($addRouteType === 'oneway_outbound') {
                                $addMealTop = 'N/A';
                                $addMealSplit = true;
                            }
                        @endphp
                        @if($addMealSplit)
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $addMealTop }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $addMealBottom }}</div>
                        </td>
                        @else
                        <td class="px-1 py-0.5 border border-slate-300">{{ $addMealVal }}</td>
                        @endif
                        @php
                            $addFmt = function($mins) {
                                if (!$mins || $mins <= 0) return 'Direct';
                                $h = intdiv($mins, 60);
                                $m = $mins % 60;
                                return sprintf('Transit: %02d hr %02d min', $h, $m);
                            };
                            $addFtTop = 'N/A';
                            $addFtBottom = 'N/A';
                            $addFtSplit = false;
                            if ($addRoute) {
                                $addTransits = $addRoute->transits ?? collect();
                                $addInMins = $addTransits->filter(fn($t) => $t->route_direction?->value === 'inbound')->sum('transit_time');
                                $addOutMins = $addTransits->filter(fn($t) => $t->route_direction?->value === 'outbound')->sum('transit_time');
                                $addFtType = $addRoute->flight_type?->value;
                                if ($addIsSplit) {
                                    $addFtTop = $addFtType === 'transit' ? $addFmt($addInMins) : 'Direct';
                                    $addFtBottom = $addFtType === 'transit' ? $addFmt($addOutMins) : 'Direct';
                                    $addFtSplit = true;
                                } elseif ($addRouteType === 'oneway_inbound') {
                                    $addFtTop = $addFtType === 'transit' ? $addFmt($addInMins) : 'Direct';
                                    $addFtSplit = true;
                                } elseif ($addRouteType === 'oneway_outbound') {
                                    $addFtBottom = $addFtType === 'transit' ? $addFmt($addOutMins) : 'Direct';
                                    $addFtSplit = true;
                                }
                            }
                        @endphp
                        @if($addFtSplit)
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $addFtTop }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $addFtBottom }}</div>
                        </td>
                        @else
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $addFtTop }}</td>
                        @endif
                        <td class="px-1 py-0.5 border border-slate-300">{{ $booking->remarks ?? '-' }}</td>
                    </tr>
                    @endforeach
                    @empty
                    <tr>
                        <td colspan="17" class="px-3 py-2 text-center text-slate-500 border border-slate-300">No passenger data</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Package Calculation, Payment Summary, Important Note --}}
    <div class="grid grid-cols-1 md:grid-cols-[1fr_1fr_1.5fr] gap-3 mb-2">

        {{-- Package Calculation --}}
        <div class="bg-white border border-slate-300 invoice-card overflow-hidden">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Package Calculation</div>
            <table class="w-full text-xs border-collapse">
                <tbody>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Sub Total:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">@currency($subTotal, 2, $rate)</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Total Pax:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">{{ $totalPackages ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Fingerprint Charge:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">@currency($fingerprintCharge, 2, $rate)</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Discount:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">@currency($discount, 2, $rate)</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1.5 font-bold text-slate-800 border border-slate-600">Grand Total:</td>
                        <td class="px-2 py-1.5 text-right font-bold text-slate-800 border border-slate-600">@currency($grandTotal, 2, $rate)</td>
                    </tr>
                </tbody>
            </table>
            @if($useBdt)
                <p class="text-[10px] text-slate-400 px-2 pb-1 mt-1">Exchange Rate: 1 SAR = {{ number_format($displayRate, 4) }} BDT</p>
            @endif
        </div>

        {{-- Payment Summary --}}
        <div class="bg-white border border-slate-300 invoice-card overflow-hidden">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Payment Summary</div>
            <table class="w-full text-xs border-collapse">
                <tbody>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Total Amount:</td>
                        <td class="px-2 py-1 text-right font-bold border border-slate-300">@currency($grandTotal, 2, $rate)</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Previous Paid Amount:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">@currency($totalPaid - $currentPaid, 2, $rate)</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Current Paid Amount:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">@currency($currentPaid, 2, $rate)</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Total Paid Amount:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">@currency($totalPaid, 2, $rate)</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1.5 font-bold text-red-700 border border-red-400">Due Amount:</td>
                        <td class="px-2 py-1.5 text-right font-bold text-red-700 border border-red-400">@currency($dueAmount, 2, $rate)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Important Note --}}
        <div class="bg-white border border-slate-300 invoice-card overflow-hidden">
            <div class="bg-[#F4B183] text-slate-800 text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Important Note / Conditions</div>
            <div class="p-2 text-xs text-slate-600">
                <ul class="list-disc list-inside space-y-1">
                    @forelse($conditions as $condition)
                        <li>
                            <span class="font-semibold text-slate-700">{{ $condition->title }}</span>
                            @if($condition->description)
                                : {{ $condition->description }}
                            @endif
                        </li>
                    @empty
                        <li class="text-slate-400 italic">N/A</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Signatures --}}
    <div class="flex justify-between mt-3 px-3 text-xs" style="page-break-inside: avoid;">
        <div class="text-center w-52">
            <div class="h-20"></div>
            <div class="border-t-2 border-black"></div>
            <div class="h-0.5"></div>
            <div class="border-t-2 border-black"></div>
            <p class="font-bold text-slate-800 mt-1">Representative Signature</p>
        </div>
        <div class="text-center w-52">
            <div class="h-20"></div>
            <div class="border-t-2 border-black"></div>
            <div class="h-0.5"></div>
            <div class="border-t-2 border-black"></div>
            <p class="font-bold text-slate-800 mt-1">Customer Signature</p>
        </div>
    </div>

    <p class="text-center text-[10px] text-slate-400 mt-3 border-t border-slate-200 pt-2">
        This is a computer-generated invoice. No signature is required.
    </p>
</div>

{{-- Print Buttons --}}
<div class="no-print flex justify-center gap-4 py-4">
    <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">Print</button>
    <button onclick="window.close()" class="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-sm">Close</button>
    <a href="{{ route('bookings.index') }}" class="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-sm">Back</a>
</div>
@endsection
