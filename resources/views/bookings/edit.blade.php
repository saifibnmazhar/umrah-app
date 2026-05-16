@extends('layouts.app')
@section('title', 'Edit Booking')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Edit Booking</h1>
        <a href="{{ route('bookings.show', $booking->id) }}" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition">Back to Details</a>
    </div>

    <form method="POST" action="{{ route('bookings.update', $booking->id) }}" class="bg-white p-6 rounded-xl shadow-lg">
        @csrf
        @method('PUT')
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Customer</label>
                <p class="px-4 py-3 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    {{ $booking->customer->name ?? '-' }}
                </p>
                <input type="hidden" name="customer_id" value="{{ $booking->customer_id }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Fingerprint Location</label>
                <select name="fingerprint_location" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                    <option value="Office" {{ $booking->fingerprint_location === 'Office' ? 'selected' : '' }}>Office</option>
                    <option value="Home" {{ $booking->fingerprint_location === 'Home' ? 'selected' : '' }}>Home</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Fingerprint Office</label>
                <select name="fingerprint_office" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                    <option value="">Select Office</option>
                    <option value="BMT-Dhaka" {{ $booking->fingerprint_office === 'BMT-Dhaka' ? 'selected' : '' }}>BMT-Dhaka</option>
                    <option value="BMT-Chattogram" {{ $booking->fingerprint_office === 'BMT-Chattogram' ? 'selected' : '' }}>BMT-Chattogram</option>
                    <option value="BMT-Sylhet" {{ $booking->fingerprint_office === 'BMT-Sylhet' ? 'selected' : '' }}>BMT-Sylhet</option>
                    <option value="BMT-Rangpur" {{ $booking->fingerprint_office === 'BMT-Rangpur' ? 'selected' : '' }}>BMT-Rangpur</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">District</label>
                <select name="district_id" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                    <option value="">Select District</option>
                    @foreach(\App\Models\District::orderBy('name')->get() as $district)
                    <option value="{{ $district->id }}" {{ $booking->district_id == $district->id ? 'selected' : '' }}>{{ $district->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="fingerprint_charge_id" value="{{ $booking->fingerprint_charge_id }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Package</label>
                <select name="package_id" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                    <option value="">Select Package</option>
                    @foreach(\App\Models\Package::orderBy('package_name')->get() as $package)
                    <option value="{{ $package->id }}" {{ $booking->package_id == $package->id ? 'selected' : '' }}>{{ $package->package_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Discount Type</label>
                <select name="discount_type" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white">
                    <option value="fixed" {{ $booking->discount_type === 'fixed' ? 'selected' : '' }}>Fixed</option>
                    <option value="percentage" {{ $booking->discount_type === 'percentage' ? 'selected' : '' }}>Percentage</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Discount Value</label>
                <input type="number" name="discount_value" value="{{ $booking->discount_value }}" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-2">Remarks</label>
                <textarea name="remarks" rows="3" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition">{{ $booking->remarks }}</textarea>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Update Booking</button>
            <a href="{{ route('bookings.show', $booking->id) }}" class="px-6 py-3 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</a>
        </div>
    </form>
</div>
@endsection