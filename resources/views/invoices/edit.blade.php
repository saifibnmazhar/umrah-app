@extends('layouts.app')
@section('title', 'Edit Invoice')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('invoices.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Invoices
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit Invoice</h1>

    <form method="POST" action="{{ route('invoices.update', $invoice->id) }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="booking_id" class="block text-sm font-medium text-slate-700 mb-1">Booking</label>
            <select 
                name="booking_id" 
                id="booking_id" 
                required
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('booking_id') border-red-500 @enderror"
            >
                <option value="">Select Booking</option>
                @foreach($bookings as $booking)
                    <option value="{{ $booking->id }}" {{ old('booking_id', $invoice->booking_id) == $booking->id ? 'selected' : '' }}>
                        #{{ $booking->id }} - {{ $booking->customer->name ?? 'N/A' }}
                    </option>
                @endforeach
            </select>
            @error('booking_id')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="branch_id" class="block text-sm font-medium text-slate-700 mb-1">Branch</label>
            <select 
                name="branch_id" 
                id="branch_id" 
                required
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('branch_id') border-red-500 @enderror"
            >
                <option value="">Select Branch</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ old('branch_id', $invoice->branch_id) == $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            @error('branch_id')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Update Invoice
            </button>
            <a href="{{ route('invoices.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection