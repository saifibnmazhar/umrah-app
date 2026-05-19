@extends('layouts.app')

@section('title', 'Print Invoice')

@section('content')
<style>
    @page {
        size: landscape;
        margin: 0.5cm;
    }
    @media print {
        .no-print { display: none !important; }
        body { margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .invoice-container {
            width: 100%;
            max-width: 11in;
            margin: 0 auto;
            font-size: 10px;
        }
    }
    .invoice-container {
        width: 100%;
        max-width: 11in;
        margin: 0 auto;
        font-size: 10px;
    }
</style>

<div class="invoice-container bg-white p-4" id="invoiceContent">
    <!-- Header -->
    <div class="border-2 border-slate-800 mb-3">
        <div class="bg-yellow-300 px-3 py-2 border-b-2 border-slate-800">
            <h2 class="text-lg font-bold text-slate-800 text-center">BOOKING INVOICE UMH</h2>
        </div>
        <div class="flex">
            <div class="w-1/2 p-3 border-r-2 border-slate-800">
                <p class="font-semibold"><strong>Booking Date:</strong> {{ $booking->booking_date ?? $booking->created_at ?? '-' }}</p>
                <p class="font-semibold"><strong>Guardian Name:</strong> {{ $booking->customer->name ?? '-' }}</p>
                <p class="font-semibold"><strong>Iqama No:</strong> {{ $booking->customer->iqama_no ?? '-' }}</p>
                <p class="font-semibold"><strong>Phone Number:</strong> {{ $booking->customer->mobile_no ?? '-' }}</p>
                <p class="font-semibold"><strong>Address:</strong> {{ $booking->customer->address ?? '-' }}</p>
            </div>
            <div class="w-1/2 p-3">
                <p class="font-semibold"><strong>Invoice Date:</strong> {{ $invoiceDate ?? ($booking->created_at ?? '-') }}</p>
                <p class="font-semibold"><strong>Invoice Number:</strong> {{ $booking->invoice_no ?? '-' }}</p>
                <p class="font-semibold"><strong>Branch:</strong> {{ $booking->office->name ?? 'RUH' }}</p>
                <p class="font-semibold"><strong>Representative:</strong> {{ $representative ?? '-' }}</p>
                <p class="font-semibold"><strong>Offer:</strong> {{ $offer ?? 'NO' }}</p>
                <p class="font-semibold"><strong>Finger Location:</strong> {{ $booking->fingerprint_location ?? '-' }}</p>
                <p class="font-semibold"><strong>Finger Deadline:</strong> <span class="bg-red-600 text-white px-2 py-0.5">{{ $fingerprintDeadline ?? '-' }}</span></p>
            </div>
        </div>
    </div>

    <!-- Passenger & Package Details + Package Calculation -->
    <div class="flex mb-3">
        <!-- Passenger & Package Details Table -->
        <div class="w-[65%] border-2 border-slate-800 mr-2">
            <div class="bg-yellow-300 px-2 py-1 border-b-2 border-slate-800">
                <h3 class="font-bold text-slate-800">PASSENGER & PACKAGE DETAILS</h3>
            </div>
            <table class="w-full text-sm border-collapse">
                <thead class="bg-yellow-200">
                    <tr>
                        <th class="border border-slate-300 px-1 py-1 text-left text-xs">Pax No</th>
                        <th class="border border-slate-300 px-1 py-1 text-left text-xs">Name of Passengers</th>
                        <th class="border border-slate-300 px-1 py-1 text-left text-xs">Gender</th>
                        <th class="border border-slate-300 px-1 py-1 text-left text-xs">Passport Number</th>
                        <th class="border border-slate-300 px-1 py-1 text-left text-xs">Package</th>
                        <th class="border border-slate-300 px-1 py-1 text-left text-xs">Duration</th>
                        <th class="border border-slate-300 px-1 py-1 text-right text-xs">Package Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($booking->passengers as $index => $passenger)
                    <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                        <td class="border border-slate-300 px-1 py-1">{{ $index + 1 }}</td>
                        <td class="border border-slate-300 px-1 py-1">{{ $passenger->first_name ?? '' }} {{ $passenger->last_name ?? '' }}</td>
                        <td class="border border-slate-300 px-1 py-1">{{ $passenger->gender ?? '-' }}</td>
                        <td class="border border-slate-300 px-1 py-1">{{ $passenger->passport_no ?? '-' }}</td>
                        <td class="border border-slate-300 px-1 py-1">{{ $booking->package->name ?? 'Package' }}</td>
                        <td class="border border-slate-300 px-1 py-1">{{ $passenger->stay_duration ?? '-' }}</td>
                        <td class="border border-slate-300 px-1 py-1 text-right">{{ number_format($passenger->total ?? 0, 0) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="border border-slate-300 px-1 py-1 text-center">No passengers</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Package Calculation -->
        <div class="w-[35%] border-2 border-slate-800">
            <div class="bg-yellow-300 px-2 py-1 border-b-2 border-slate-800">
                <h3 class="font-bold text-slate-800">PACKAGE CALCULATION</h3>
            </div>
            <div class="p-2">
                <div class="flex justify-between py-1">
                    <span class="font-semibold">Sub Total:</span>
                    <span>{{ number_format($subTotal ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="font-semibold">Total Package:</span>
                    <span>{{ number_format($totalPackage ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="font-semibold">Finger Fee:</span>
                    <span>{{ number_format($fingerprintCost ?? 200, 0) }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="font-semibold">Additional Fee:</span>
                    <span>{{ number_format($additionalFee ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="font-semibold">Discount:</span>
                    <span>{{ number_format($booking->discount_value ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1 border-t-2 border-slate-800 mt-1 pt-1">
                    <span class="font-bold text-lg">GRAND TOTAL:</span>
                    <span class="font-bold text-lg">{{ number_format($grandTotal ?? 0, 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Flight Details Table -->
    <div class="border-2 border-slate-800 mb-3">
        <div class="bg-yellow-300 px-2 py-1 border-b-2 border-slate-800">
            <h3 class="font-bold text-slate-800">FLIGHT DETAILS</h3>
        </div>
        <table class="w-full text-sm border-collapse">
            <thead class="bg-yellow-200">
                <tr>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Pax</th>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Type</th>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Airlines</th>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Route</th>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Est. Flight Date</th>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Baggage</th>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Cabin</th>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Meal</th>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Flight Type</th>
                    <th class="border border-slate-300 px-1 py-1 text-xs">Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse($booking->passengers as $index => $passenger)
                <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                    <td class="border border-slate-300 px-1 py-1">{{ $index + 1 }}</td>
                    <td class="border border-slate-300 px-1 py-1">{{ $passenger->passenger_type ?? '-' }}</td>
                    <td class="border border-slate-300 px-1 py-1">{{ $passenger->airline ?? '-' }}</td>
                    <td class="border border-slate-300 px-1 py-1">{{ $passenger->route ?? '-' }}</td>
                    <td class="border border-slate-300 px-1 py-1">{{ $passenger->flight_date_from ?? '-' }}</td>
                    <td class="border border-slate-300 px-1 py-1">20kg</td>
                    <td class="border border-slate-300 px-1 py-1">{{ $passenger->travel_class ?? '-' }}</td>
                    <td class="border border-slate-300 px-1 py-1">Yes</td>
                    <td class="border border-slate-300 px-1 py-1">{{ $passenger->route_type ?? '-' }}</td>
                    <td class="border border-slate-300 px-1 py-1">{{ $booking->remarks ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="border border-slate-300 px-1 py-1 text-center">No flight details</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Group Umrah Features -->
    <div class="border-2 border-slate-800 mb-3">
        <div class="bg-yellow-300 px-2 py-1 border-b-2 border-slate-800">
            <h3 class="font-bold text-slate-800">GROUP UMRAH FEATURES</h3>
        </div>
        <table class="w-full text-sm border-collapse">
            <tbody>
                <tr class="bg-white">
                    <td class="border border-slate-300 px-2 py-1 font-semibold w-1/4">Accommodation</td>
                    <td class="border border-slate-300 px-2 py-1">N/A</td>
                    <td class="border border-slate-300 px-2 py-1 font-semibold w-1/4">Meal Facilities</td>
                    <td class="border border-slate-300 px-2 py-1">N/A</td>
                </tr>
                <tr class="bg-gray-50">
                    <td class="border border-slate-300 px-2 py-1 font-semibold">Transport</td>
                    <td class="border border-slate-300 px-2 py-1">N/A</td>
                    <td class="border border-slate-300 px-2 py-1 font-semibold">Site Visit</td>
                    <td class="border border-slate-300 px-2 py-1">N/A</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Payment Summary -->
    <div class="border-2 border-slate-800 mb-3">
        <div class="bg-yellow-300 px-2 py-1 border-b-2 border-slate-800">
            <h3 class="font-bold text-slate-800">PAYMENT SUMMARY</h3>
        </div>
        <div class="flex">
            <div class="w-1/2 p-2 border-r-2 border-slate-800">
                <div class="flex justify-between py-1">
                    <span class="font-semibold">Total Amount:</span>
                    <span class="font-bold">{{ number_format($grandTotal ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="font-semibold">Previous Paid Amount:</span>
                    <span>{{ number_format($totalPaid ?? 0, 0) }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="font-semibold">Current Paid Amount:</span>
                    <span>{{ number_format($currentPaid ?? 0, 0) }}</span>
                </div>
            </div>
            <div class="w-1/2 p-2">
                <div class="flex justify-between py-1 bg-red-100 px-2 -mx-2">
                    <span class="font-bold text-red-700">Due Amount:</span>
                    <span class="font-bold text-red-700">{{ number_format($dueAmount ?? 0, 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="border-2 border-slate-800">
        <div class="flex">
            <div class="w-1/2 p-2 border-r-2 border-slate-800">
                <p class="font-semibold">Offer: {{ $offer ?? 'N/A' }}</p>
            </div>
            <div class="w-1/2 p-2">
                <p class="font-semibold">Conditions: ________________________</p>
            </div>
        </div>
    </div>
</div>

<!-- Print Buttons -->
<div class="no-print flex justify-center gap-4 py-4" style="width: 7in; margin: 0 auto;">
    <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">Print</button>
    <button onclick="window.close()" class="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Close</button>
    <a href="{{ route('bookings.index') }}" class="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Back</a>
</div>
@endsection