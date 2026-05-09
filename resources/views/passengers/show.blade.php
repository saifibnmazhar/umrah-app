@extends('layouts.app')
@section('title', 'Passenger Details')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Passenger Details</h1>
        <a href="{{ route('bookings.index') }}" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition">Back to Bookings</a>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Passenger Name</h3>
                <p class="text-lg font-semibold text-slate-800">{{ $passenger->first_name }} {{ $passenger->last_name }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Passenger Type</h3>
                <p class="text-lg text-slate-800">{{ ucfirst($passenger->passenger_type) }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Passport Number</h3>
                <p class="text-lg text-slate-800">{{ $passenger->passport_no }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Passport Expiry</h3>
                <p class="text-lg text-slate-800">{{ $passenger->passport_expiry ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Date of Birth</h3>
                <p class="text-lg text-slate-800">{{ $passenger->date_of_birth }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Mobile Number</h3>
                <p class="text-lg text-slate-800">{{ $passenger->mobile_no ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Service Required</h3>
                <p class="text-lg text-slate-800">{{ $passenger->service_required }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Stay Duration</h3>
                <p class="text-lg text-slate-800">{{ $passenger->stay_duration }} days</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Ticket Status</h3>
                <p class="text-lg text-slate-800">{{ ucfirst($passenger->ticket_status ?? 'None') }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Visa Status</h3>
                <p class="text-lg text-slate-800">{{ ucfirst($passenger->visa_status ?? 'None') }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Flight Date From</h3>
                <p class="text-lg text-slate-800">{{ $passenger->flight_date_from ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Flight Date To</h3>
                <p class="text-lg text-slate-800">{{ $passenger->flight_date_to ?? '-' }}</p>
            </div>
            @if($passenger->address)
            <div class="md:col-span-2">
                <h3 class="text-sm font-medium text-slate-500 mb-1">Address</h3>
                <p class="text-lg text-slate-800">{{ $passenger->address }}</p>
            </div>
            @endif
        </div>
    </div>

    @if($passenger->booking)
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-slate-700 mb-4">Booking Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Booking ID</h3>
                <p class="text-lg text-slate-800">#{{ $passenger->booking->id }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Customer</h3>
                <p class="text-lg text-slate-800">{{ $passenger->booking->customer->name ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Package</h3>
                <p class="text-lg text-slate-800">{{ $passenger->booking->package->package_name ?? '-' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Fingerprint Location</h3>
                <p class="text-lg text-slate-800">{{ $passenger->booking->fingerprint_location ?? '-' }}</p>
            </div>
        </div>
    </div>
    @endif

    <div class="flex gap-3">
        <a href="{{ route('bookings.show', $passenger->booking_id) }}" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition">View Booking</a>
    </div>
</div>
@endsection