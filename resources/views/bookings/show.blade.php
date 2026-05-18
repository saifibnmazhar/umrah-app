@extends('layouts.app')
@section('title', 'Booking Details')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Booking Details</h1>
        <a href="{{ route('bookings.index') }}" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition">Back to Bookings</a>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Booking ID</h3>
                <p class="text-lg font-semibold text-slate-800">{{ $booking->id }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Created Date</h3>
                <p class="text-lg text-slate-800">{{ $booking->created_at->format('Y-m-d H:i') }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Customer</h3>
                <p class="text-lg text-slate-800">{{ $booking->customer->name ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Mobile</h3>
                <p class="text-lg text-slate-800">{{ $booking->customer->mobile_no ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Fingerprint Location</h3>
                <p class="text-lg text-slate-800">{{ $booking->fingerprint_location ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Fingerprint Office</h3>
                <p class="text-lg text-slate-800">{{ $booking->fingerprint_office ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">District</h3>
                <p class="text-lg text-slate-800">{{ $booking->district->name ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Package</h3>
                <p class="text-lg text-slate-800">{{ $booking->package->package_name ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Passenger Count</h3>
                <p class="text-lg text-slate-800">{{ $booking->pax_qty }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Discount Type</h3>
                <p class="text-lg text-slate-800">{{ ucfirst($booking->discount_type->value ?? 'None') }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Discount Value</h3>
                <p class="text-lg text-slate-800">{{ $booking->discount_value ?? 0 }}</p>
            </div>
            <div class="md:col-span-2">
                <h3 class="text-sm font-medium text-slate-500 mb-1">Remarks</h3>
                <p class="text-lg text-slate-800">{{ $booking->remarks ?? '-' }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-semibold text-slate-700 mb-4">Passengers</h2>
        
        @if($booking->passengers->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">#</th>
                        <th class="px-3 py-2 text-left font-medium">Name</th>
                        <th class="px-3 py-2 text-left font-medium">Passport</th>
                        <th class="px-3 py-2 text-left font-medium">Type</th>
                        <th class="px-3 py-2 text-left font-medium">DOB</th>
                        <th class="px-3 py-2 text-left font-medium">Service</th>
                        <th class="px-3 py-2 text-left font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($booking->passengers as $index => $passenger)
                    <tr>
                        <td class="px-3 py-2 text-slate-700">{{ $index + 1 }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $passenger->first_name }} {{ $passenger->last_name }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $passenger->passport_no }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ ucfirst($passenger->passenger_type->value ?? 'N/A') }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $passenger->date_of_birth }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $passenger->service_required }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ ucfirst($passenger->ticket_status->value ?? 'None') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-slate-500">No passengers found.</p>
        @endif
    </div>

    <div class="flex gap-3 mt-6">
        <a href="{{ route('bookings.edit', $booking->id) }}" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition">Edit Booking</a>
        <form method="POST" action="{{ route('bookings.destroy', $booking->id) }}" onsubmit="return confirm('Are you sure you want to delete this booking?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Delete Booking</button>
        </form>
    </div>
</div>
@endsection