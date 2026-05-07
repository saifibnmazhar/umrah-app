@extends('layouts.app')
@section('title', 'Package Details')
@section('content')
<div class="max-w-3xl mx-auto container py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Package Details</h1>
        <p class="text-slate-600">View package information</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">{{ $package->package_name }}</h2>
            <p class="text-sm text-slate-500">Package ID: {{ $package->id }}</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Package Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Ticket Fare</span>
                            <span class="text-sm font-medium text-slate-900">{{ $package->ticketFare->ticket_type->value ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Ticket Net Fare</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($package->ticketFare->net_fare ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Ticket Selling Fare</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($package->ticketFare->selling_fare ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Visa Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Visa Type</span>
                            <span class="text-sm font-medium text-slate-900">{{ $package->visaSellingPrice?->selling_price ? 'BDT ' . number_format($package->visaSellingPrice->selling_price, 0) : 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Visa Cost</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($package->visaSellingPrice->cost ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Visa Selling</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($package->visaSellingPrice->selling_price ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-6 border-t border-slate-200 bg-slate-50">
            <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Pricing</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex justify-between">
                    <span class="text-sm text-slate-600">Regular Price</span>
                    <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($package->regular_price, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-slate-600">Offer Price</span>
                    <span class="text-sm font-medium text-slate-900">
                        @if($package->offer_price)
                            Rs. {{ number_format($package->offer_price, 2) }}
                        @else
                            -
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-between">
        <a href="{{ route('packages.index') }}" class="px-4 py-2 bg-slate-600 text-white rounded-md hover:bg-slate-700 transition">
            Back to List
        </a>
        <div class="flex gap-3">
            <a href="{{ route('packages.edit', $package) }}" class="px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-800 transition">
                Edit
            </a>
            <form method="POST" action="{{ route('packages.destroy', $package) }}" onsubmit="return confirm('Are you sure you want to delete this package?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>
@endsection