@extends('layouts.app')

@section('title', 'Print Invoice')

@section('content')
<style>
    @page {
        size: landscape;
        margin: 0.3cm;
    }
    @media print {
        .no-print { display: none !important; }
        body { margin: 0; padding: 0; background: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .invoice-container { max-width: 100%; }
        .invoice-card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; page-break-inside: avoid; }
        .invoice-bg { background: white !important; }
        .text-\[10px\] { font-size: 10px !important; }
    }
    .invoice-container {
        max-width: 1200px;
        margin: 0 auto;
        font-size: 10px;
    }
</style>

<div class="invoice-container invoice-bg p-1" style="background: #f8fafc;" id="invoiceContent">

    {{-- Header --}}
    <div class="bg-white border border-slate-300 p-2 mb-3 invoice-card">
        <div class="flex justify-end mb-0">
            <span class="text-xs font-semibold text-slate-700">Invoice No: {{ $booking->invoice_id ?? '-' }}</span>
        </div>
        <div class="text-center">
            <h1 class="text-lg font-bold text-slate-800">BOOKING INVOICE UMH</h1>
            <p class="text-xs text-slate-500 mt-0">Phone: +966XXX-XXXXXXX</p>
            <p class="text-xs text-slate-500">{{ $booking->office->name ?? 'BMT-Dak' }}</p>
        </div>
    </div>

    @php
        $fpLocation = $booking->fingerprint_location;
        if ($fpLocation instanceof \BackedEnum) { $fpLocation = $fpLocation->value; }
    @endphp

    {{-- Customer Information & Invoice Information --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">

        {{-- Customer Information --}}
        <div class="bg-white border border-slate-500 invoice-card overflow-hidden">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Customer Information</div>
            <table class="w-full text-xs border-collapse">
                <tbody>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Booking Date :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $booking->booking_date ?? ($booking->created_at ? $booking->created_at->format('d M Y') : '-') }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Customer Name :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $booking->customer->name ?? '-' }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Iqama Number :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $booking->customer->iqama_no ?? '-' }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Phone Number :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $booking->customer->mobile_no ?? '-' }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Address (KSA) :</td>
                        <td class="px-2 py-1 border border-slate-300">-</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Passenger Number (BD) :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $booking->customer->iqama_no ?? '-' }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Customer Address :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $booking->customer->address ?? '-' }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Finger Location :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $fpLocation ?? '-' }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5">Finger Deadline :</td>
                        <td class="px-2 py-1">{{ $fingerprintDeadline ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Invoice Information --}}
        <div class="bg-white border border-slate-500 invoice-card overflow-hidden">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Invoice Information</div>
            <table class="w-full text-xs border-collapse">
                <tbody>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Invoice Date :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $invoiceDate ?? ($booking->created_at ? $booking->created_at->format('d M Y') : '-') }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Branch (Booking By) :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $booking->branch->name ?? '-' }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Branch (Operating By) :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $booking->office->name ?? '-' }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5 border border-slate-300">Sale Representative :</td>
                        <td class="px-2 py-1 border border-slate-300">{{ $booking->user->name ?? '-' }}</td>
                    </tr>
                    <tr class="even:bg-[#f1f5f9]">
                        <td class="px-2 py-1 font-semibold text-slate-700 w-2/5">Remarks :</td>
                        <td class="px-2 py-1">{{ $booking->remarks ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Passenger & Flight Details --}}
    <div class="bg-white border border-slate-300 invoice-card mb-3 overflow-hidden">
        <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Passenger & Flight Details</div>
        <div class="overflow-x-auto">
            <table class="w-full text-[10px] border-collapse whitespace-nowrap">
                <thead>
                    <tr class="bg-[#00A651] text-white">
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Pax No.</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Name of passengers</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Gender</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Passport number</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Package</th>
                        <th class="px-1 py-1 text-center font-semibold border border-[#00853e]">Duration (Days)</th>
                        <th class="px-1 py-1 text-right font-semibold border border-[#00853e]">Package Value (BDT)</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Trip</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Airline</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Route</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Est.Flight Date</th>
                        <th class="px-1 py-1 text-center font-semibold border border-[#00853e]">Baggage(KG)</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Cabin</th>
                        <th class="px-1 py-1 text-center font-semibold border border-[#00853e]">Meal</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Flight Type</th>
                        <th class="px-1 py-1 text-left font-semibold border border-[#00853e]">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($booking->passengers as $index => $passenger)
                    <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-[#f1f5f9]' }}">
                        <td class="px-1 py-0.5 border border-slate-300">{{ $index + 1 }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->first_name ?? '' }} {{ $passenger->last_name ?? '' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->gender ?? '-' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->passport_no ?? '-' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $booking->package?->package_name ?? 'Package' }}</td>
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $passenger->stay_duration ?? '-' }}</td>
                        <td class="px-1 py-0.5 text-right border border-slate-300">{{ number_format($passenger->package_value ?? 0, 2) }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->trip_display }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->ticketFare?->airline?->name ?? '-' }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->route_display }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->flight_date_display }}</td>
                        <td class="px-1 py-0.5 text-center border border-slate-300 whitespace-pre-line">{{ $passenger->baggage_display }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->ticketFare?->airlineClass?->travelClass?->name ?? '-' }}</td>
                        <td class="px-1 py-0.5 text-center border border-slate-300">{{ $passenger->meal_display }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $passenger->flight_type_display }}</td>
                        <td class="px-1 py-0.5 border border-slate-300">{{ $booking->remarks ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="16" class="px-3 py-2 text-center text-slate-500 border border-slate-300">No passenger data</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Package Calculation, Payment Summary, Important Note --}}
    <div class="grid grid-cols-1 md:grid-cols-[1fr_1fr_1.5fr] gap-3 mb-3">

        {{-- Package Calculation --}}
        <div class="bg-white border border-slate-300 invoice-card overflow-hidden">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Package Calculation</div>
            <div class="p-2 text-xs">
                <div class="flex justify-between py-1 border border-slate-300">
                    <span class="font-semibold text-slate-700">Sub Total:</span>
                    <span>{{ number_format($subTotal ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1 border border-slate-300">
                    <span class="font-semibold text-slate-700">Total Pax:</span>
                    <span>{{ $totalPackages ?? 0 }}</span>
                </div>
                <div class="flex justify-between py-1 border border-slate-300">
                    <span class="font-semibold text-slate-700">Fingerprint Charge:</span>
                    <span>{{ number_format($fingerprintCharge ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1 border border-slate-300">
                    <span class="font-semibold text-slate-700">Discount:</span>
                    <span>{{ number_format($discount ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1.5 mt-0">
                    <span class="font-bold text-slate-800">Grand Total (BDT):</span>
                    <span class="font-bold text-slate-800">{{ number_format($grandTotal ?? 0, 0) }}</span>
                </div>
            </div>
        </div>

        {{-- Payment Summary --}}
        <div class="bg-white border border-slate-300 invoice-card overflow-hidden">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Payment Summary</div>
            <div class="p-2 text-xs">
                <div class="flex justify-between py-1 border border-slate-300">
                    <span class="font-semibold text-slate-700">Total Amount:</span>
                    <span class="font-bold">{{ number_format($grandTotal ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1 border border-slate-300">
                    <span class="font-semibold text-slate-700">Previous Paid Amount:</span>
                    <span>{{ number_format(max(0, ($totalPaid ?? 0) - ($currentPaid ?? 0)), 0) }}</span>
                </div>
                <div class="flex justify-between py-1 border border-slate-300">
                    <span class="font-semibold text-slate-700">Current Paid Amount:</span>
                    <span>{{ number_format($currentPaid ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1 border border-slate-300">
                    <span class="font-semibold text-slate-700">Total Paid Amount:</span>
                    <span>{{ number_format($totalPaid ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1.5 mt-0">
                    <span class="font-bold text-red-700">Due Amount:</span>
                    <span class="font-bold text-red-700">{{ number_format($dueAmount ?? 0, 0) }}</span>
                </div>
            </div>
        </div>

        {{-- Important Note --}}
        <div class="bg-white border border-slate-300 invoice-card overflow-hidden">
            <div class="bg-[#00A651] text-white text-center px-3 py-1 font-bold text-xs uppercase tracking-wider">Important Note / Conditions</div>
            <div class="p-2 text-xs text-slate-400 italic">
                <p>N/A</p>
            </div>
        </div>
    </div>

    {{-- Signatures --}}
    <div class="flex justify-between mt-4 px-3 text-xs" style="page-break-inside: avoid;">
        <div class="text-center">
            <p class="font-semibold text-slate-700 mb-3">Representative Signature</p>
            <p class="border-t border-slate-400 pt-1.5 w-52">________________________</p>
        </div>
        <div class="text-center">
            <p class="font-semibold text-slate-700 mb-3">Customer Signature</p>
            <p class="border-t border-slate-400 pt-1.5 w-52">________________________</p>
        </div>
    </div>

    <p class="text-center text-[9px] text-slate-400 mt-4 border-t border-slate-200 pt-2">
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
