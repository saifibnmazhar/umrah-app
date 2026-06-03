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
        <p class="text-xs text-slate-500 mt-0">Phone: +966XXX-XXXXXXX</p>
        <p class="text-xs text-slate-500">{{ $booking->office->name ?? 'BMT-Dak' }}</p>
    </div>

    <div class="text-right mb-2">
        <span class="text-xs font-bold text-slate-800">Invoice No: {{ $booking->invoice_id ?? '-' }}</span>
    </div>

    @php
        $fpLocation = $booking->fingerprint_location;
        if ($fpLocation instanceof \BackedEnum) { $fpLocation = $fpLocation->value; }
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
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Passenger Number (BD) :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $booking->customer->iqama_no ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Customer Name :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $booking->customer->name ?? '-' }}</td>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Customer Address :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $booking->customer->address ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Iqama Number :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $booking->customer->iqama_no ?? '-' }}</td>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Finger Location :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $fpLocation ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Phone Number :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $booking->customer->mobile_no ?? '-' }}</td>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Finger Deadline :</td>
                        <td class="px-2 py-1 w-[20%]">{{ $fingerprintDeadline ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-[30%]">Address (KSA) :</td>
                        <td class="px-2 py-1 " colspan="3">-</td>
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
                        <td class="px-2 py-1">{{ $invoiceDate ?? ($booking->created_at ? $booking->created_at->format('d M Y') : '-') }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-2/5">Branch (Booking By) :</td>
                        <td class="px-2 py-1">{{ $booking->branch->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 w-2/5">Branch (Operating By) :</td>
                        <td class="px-2 py-1">{{ $booking->office->name ?? '-' }}</td>
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
                        <th class="px-1 py-1 text-right font-semibold border border-[#00853e]" rowspan="2">Package Value (BDT)</th>
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
                        <td class="px-1 py-0.5 text-right border border-slate-300">{{ number_format($passenger->package_value ?? 0, 2) }}</td>
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">In Bound</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">Out Bound</div>
                        </td>
                        @php
                            $_alCode = $passenger->ticketFare?->airline?->code ?? null;
                            $_rt = $passenger->ticketFare?->route?->route_type?->value;
                            $_airIn = 'N/A';
                            $_airOut = 'N/A';
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
                        @endphp
                        <td class="px-1 py-0 text-center border border-slate-300">
                            <div class="py-0.5 leading-tight">{{ $_airIn }}</div>
                            <div class="border-t border-slate-300"></div>
                            <div class="py-0.5 leading-tight">{{ $_airOut }}</div>
                        </td>
                        @php
                            $_routeType = $passenger->ticketFare?->route?->route_type?->value;
                            $_routeTop = 'N/A';
                            $_routeBottom = 'N/A';
                            $_isSplit = false;

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
                            $_cabinRt = $passenger->ticketFare?->route?->route_type?->value;
                            $_cabinTop = $_cabinVal;
                            $_cabinBottom = $_cabinVal;
                            $_cabinSplit = false;

                            if ($_cabinRt === 'oneway_inbound') {
                                $_cabinBottom = 'N/A';
                                $_cabinSplit = true;
                            } elseif ($_cabinRt === 'oneway_outbound') {
                                $_cabinTop = 'N/A';
                                $_cabinSplit = true;
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
                            $_mealRt = $passenger->ticketFare?->route?->route_type?->value;
                            $_mealTop = $_mealVal;
                            $_mealBottom = $_mealVal;
                            $_mealSplit = false;

                            if ($_mealRt === 'oneway_inbound') {
                                $_mealBottom = 'N/A';
                                $_mealSplit = true;
                            } elseif ($_mealRt === 'oneway_outbound') {
                                $_mealTop = 'N/A';
                                $_mealSplit = true;
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
                            $_ftRt = $passenger->ticketFare?->route?->route_type?->value;
                            $_ftType = $passenger->ticketFare?->route?->flight_type?->value;
                            $_transits = $passenger->ticketFare?->route?->transits ?? collect();
                            $_inMins = $_transits->filter(fn($t) => $t->route_direction?->value === 'inbound')->sum('transit_time');
                            $_outMins = $_transits->filter(fn($t) => $t->route_direction?->value === 'outbound')->sum('transit_time');

                            $_fmt = function($mins) {
                                if (!$mins || $mins <= 0) return 'Direct';
                                $h = intdiv($mins, 60);
                                $m = $mins % 60;
                                return sprintf('Transit: %02d hr %02d min', $h, $m);
                            };

                            $_ftTop = 'N/A';
                            $_ftBottom = 'N/A';
                            $_ftSplit = false;

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
                        <td class="px-2 py-1 text-right border border-slate-300">{{ number_format($subTotal ?? 0, 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Total Pax:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">{{ $totalPackages ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Fingerprint Charge:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">{{ number_format($fingerprintCharge ?? 0, 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Discount:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">{{ number_format($discount ?? 0, 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1.5 font-bold text-slate-800 border-4 border-double border-[#FFE699]">Grand Total (BDT):</td>
                        <td class="px-2 py-1.5 text-right font-bold text-slate-800 border-4 border-double border-[#FFE699]">{{ number_format($grandTotal ?? 0, 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Payment Summary --}}
        <div class="bg-white border border-slate-300 invoice-card overflow-hidden">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Payment Summary</div>
            <table class="w-full text-xs border-collapse">
                <tbody>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Total Amount:</td>
                        <td class="px-2 py-1 text-right font-bold border border-slate-300">{{ number_format($grandTotal ?? 0, 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Previous Paid Amount:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">{{ number_format(max(0, ($totalPaid ?? 0) - ($currentPaid ?? 0)), 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Current Paid Amount:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">{{ number_format($currentPaid ?? 0, 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 font-bold text-slate-800 border border-slate-300">Total Paid Amount:</td>
                        <td class="px-2 py-1 text-right border border-slate-300">{{ number_format($totalPaid ?? 0, 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1.5 font-bold text-red-700 border-4 border-double border-[#FFE699]">Due Amount:</td>
                        <td class="px-2 py-1.5 text-right font-bold text-red-700 border-4 border-double border-[#FFE699]">{{ number_format($dueAmount ?? 0, 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Important Note --}}
        <div class="bg-white border border-slate-300 invoice-card overflow-hidden">
            <div class="bg-[#F4B183] text-slate-800 text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Important Note / Conditions</div>
            <div class="p-2 text-xs text-slate-400 italic">
                <p>N/A</p>
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
