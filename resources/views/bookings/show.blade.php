@extends('layouts.app')
@section('title', 'Invoice Details')
@section('content')
<div class="max-w-5xl mx-auto">
    <div id="invoiceDetailsContent" class="space-y-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-start mb-6 pb-4 border-b border-slate-200">
                <div class="flex items-center gap-3">
                    <a href="{{ route('bookings.index') }}" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h2 class="text-xl font-semibold text-slate-800">Invoice Details</h2>
                        <p class="text-slate-500 text-sm mt-1">ID: {{ $booking->id }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm">
                        Print
                    </button>
                    <a href="{{ route('bookings.edit', $booking->id) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">
                        Edit
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <span class="text-slate-500 text-sm">Invoice No</span>
                    <p class="text-slate-800 font-medium">{{ $booking->invoice_id ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Booking Date</span>
                    <p class="text-slate-800 font-medium">{{ $booking->created_at->format('Y-m-d') }}</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Customer</span>
                    <p class="text-slate-800 font-medium">{{ $booking->customer->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Status</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ $booking->invoice && $booking->invoice->balance <= 0 ? 'Paid' : 'Due' }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 pt-4 border-t border-slate-200">
                <div>
                    <span class="text-slate-500 text-sm">Total Value</span>
                    <p class="text-xl font-bold text-slate-800">{{ number_format($booking->invoice?->total_amount ?? 0) }} SAR</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Total Paid</span>
                    <p class="text-xl font-bold text-green-600">{{ number_format($booking->invoice?->paid_amount ?? 0) }} SAR</p>
                </div>
                <div>
                    <span class="text-slate-500 text-sm">Due</span>
                    <p class="text-xl font-bold text-red-600">{{ number_format($booking->invoice?->balance ?? 0) }} SAR</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-slate-700">Passengers</h3>
                <span class="text-sm text-slate-500">{{ $booking->passengers->count() }} passenger(s)</span>
            </div>
            
            @if($booking->passengers->count() > 0)
            <div class="space-y-4">
                @foreach($booking->passengers as $index => $passenger)
                <div class="border border-slate-200 rounded-lg p-4 hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <span class="bg-slate-700 text-white text-xs font-medium px-2 py-1 rounded">P{{ str_pad($index + 1, 3, '0', STR_PAD_LEFT) }}</span>
                            <div>
                                <h4 class="font-semibold text-slate-800">{{ $passenger->first_name ?? '' }} {{ $passenger->last_name ?? '' }}</h4>
                                <p class="text-sm text-slate-500">{{ $passenger->passport_no ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            @if(($passenger->ticket_status?->value ?? '') === 'issued') bg-green-100 text-green-800
                            @elseif(($passenger->ticket_status?->value ?? '') === 'booked') bg-blue-100 text-blue-800
                            @else bg-yellow-100 text-yellow-800 @endif">
                            {{ ucfirst($passenger->ticket_status?->value ?? 'pending') }}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 pt-4 border-t border-slate-100">
                        <div>
                            <span class="text-slate-500 text-xs">Passenger Type</span>
                            <p class="text-sm text-slate-700">{{ ucfirst($passenger->passenger_type?->value ?? 'adult') }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500 text-xs">Service Required</span>
                            <p class="text-sm text-slate-700">{{ $passenger->service_required?->value ?? 'All' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500 text-xs">Route</span>
                            <p class="text-sm text-slate-700">{{ $passenger->route ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500 text-xs">Airline</span>
                            <p class="text-sm text-slate-700">{{ $passenger->airline ?? 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-3 pt-3 border-t border-slate-100">
                        <div>
                            <span class="text-slate-500 text-xs">Travel Class</span>
                            <p class="text-sm text-slate-700">{{ $passenger->travel_class ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500 text-xs">Flight Date</span>
                            <p class="text-sm text-slate-700">{{ $passenger->flight_date_from ? $passenger->flight_date_from->format('Y-m-d') : 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500 text-xs">Visa Status</span>
                            <p class="text-sm text-slate-700">{{ $passenger->visa_status?->value ?? 'None' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500 text-xs">Fingerprint</span>
                            <p class="text-sm text-slate-700">{{ ucfirst($booking->fingerprint_location?->value ?? 'Office') }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-slate-500 text-center py-4">No passengers found.</p>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-slate-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                    <h3 class="text-sm font-medium text-slate-600">Booking Information</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-slate-500 text-sm">Fingerprint Location</span>
                        <span class="text-slate-700 text-sm font-medium">{{ ucfirst($booking->fingerprint_location?->value ?? 'office') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 text-sm">Fingerprint Office</span>
                        <span class="text-slate-700 text-sm font-medium">{{ $booking->office->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 text-sm">District</span>
                        <span class="text-slate-700 text-sm font-medium">{{ $booking->district->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 text-sm">Package</span>
                        <span class="text-slate-700 text-sm font-medium">{{ $booking->package->package_name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 text-sm">Discount Type</span>
                        <span class="text-slate-700 text-sm font-medium">{{ $booking->discount_type?->value === 'percentage' ? 'Percentage' : 'Fixed' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 text-sm">Discount Value</span>
                        <span class="text-slate-700 text-sm font-medium">{{ $booking->discount_value ?? 0 }}</span>
                    </div>
                    @if($booking->remarks)
                    <div class="pt-2 border-t border-slate-200">
                        <span class="text-slate-500 text-sm">Remarks</span>
                        <p class="text-slate-700 text-sm mt-1">{{ $booking->remarks }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <div class="bg-slate-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-200">
                    <h3 class="text-sm font-medium text-slate-600">Payment History</h3>
                    <span class="text-xs text-slate-500">{{ $booking->payments->count() }} payment(s)</span>
                </div>
                @if($booking->payments->count() > 0)
                <div class="space-y-3 overflow-y-auto" style="max-height: 200px;">
                    @foreach($booking->payments as $payment)
                    <div class="bg-white rounded p-3 border border-slate-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-700 text-sm font-medium">{{ number_format($payment->amount) }} SAR</p>
                                <p class="text-slate-500 text-xs">{{ $payment->payment_method?->value ?? 'Cash' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-slate-500 text-xs">{{ $payment->created_at->format('Y-m-d') }}</p>
                                @if($payment->transaction_id)
                                <p class="text-slate-400 text-xs">TRX: {{ $payment->transaction_id }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-slate-400 text-sm">No payments recorded</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-slate-700">Passenger Financial Details</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">#</th>
                            <th class="px-3 py-2 text-left font-medium">Passenger</th>
                            <th class="px-3 py-2 text-left font-medium">Ticket Fare</th>
                            <th class="px-3 py-2 text-left font-medium">Package Value</th>
                            <th class="px-3 py-2 text-left font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($booking->passengers as $index => $passenger)
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $index + 1 }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger->first_name ?? '' }} {{ $passenger->last_name ?? '' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format($passenger->ticketFare?->fare ?? 0) }} SAR</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format($passenger->package_value ?? 0) }} SAR</td>
                            <td class="px-3 py-2 text-slate-800 font-medium">{{ number_format(($passenger->ticketFare?->fare ?? 0) + ($passenger->package_value ?? 0)) }} SAR</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-slate-500">No passengers found</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="4" class="px-3 py-2 text-right font-medium text-slate-600">Total:</td>
                            <td class="px-3 py-2 font-bold text-slate-800">{{ number_format($booking->invoice?->total_amount ?? 0) }} SAR</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex gap-3 mt-6">
            <a href="{{ route('bookings.edit', $booking->id) }}" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                Edit Booking
            </a>
            <form method="POST" action="{{ route('bookings.destroy', $booking->id) }}" onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                    Delete Booking
                </button>
            </form>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: white; }
    nav, .no-print, .max-w-5xl > div > div:last-child { display: none !important; }
    .bg-slate-100 { background: white; }
    .shadow-lg, .shadow-xl { box-shadow: none; }
    .bg-white { border: 1px solid #e2e8f0; }
    a[href]:after { content: none !important; }
}
</style>
@endsection