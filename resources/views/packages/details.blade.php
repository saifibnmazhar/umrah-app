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
            <h2 class="text-lg font-semibold text-slate-700">{{ $package_name ?? 'Umrah Standard Package' }}</h2>
            <p class="text-sm text-slate-500">Package Code: {{ $package_code ?? 'UMP-001' }}</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Package Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Package Type</span>
                            <span class="text-sm font-medium text-slate-900">{{ $package_type ?? 'Umrah' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Duration</span>
                            <span class="text-sm font-medium text-slate-900">{{ $duration ?? '10 Days' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Hotel Category</span>
                            <span class="text-sm font-medium text-slate-900">{{ $hotel_category ?? '5 Star' }}</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Pricing</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Adult Fare</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($adult_fare ?? 65000) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Child Fare</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($child_fare ?? 45000) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Infant Fare</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($infant_fare ?? 15000) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">Package Itinerary</h2>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 bg-slate-800 text-white rounded-full flex items-center justify-center text-sm font-medium flex-shrink-0">1</div>
                    <div>
                        <h4 class="text-sm font-medium text-slate-900">{{ $day1_title ?? 'Arrival in Jeddah' }}</h4>
                        <p class="text-sm text-slate-600">{{ $day1_desc ?? 'Arrive at Jeddah Airport, transfer to hotel' }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 bg-slate-800 text-white rounded-full flex items-center justify-center text-sm font-medium flex-shrink-0">2</div>
                    <div>
                        <h4 class="text-sm font-medium text-slate-900">{{ $day2_title ?? 'Umrah Performed' }}</h4>
                        <p class="text-sm text-slate-600">{{ $day2_desc ?? 'Perform Umrah at Mecca' }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 bg-slate-800 text-white rounded-full flex items-center justify-center text-sm font-medium flex-shrink-0">3</div>
                    <div>
                        <h4 class="text-sm font-medium text-slate-900">{{ $day3_title ?? 'Madina Visit' }}</h4>
                        <p class="text-sm text-slate-600">{{ $day3_desc ?? 'Visit Masjid al-Nabawi in Madina' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end">
        <a href="{{ route('packages.index') }}" class="px-4 py-2 bg-slate-600 text-white rounded-md hover:bg-slate-700 transition">
            Back to List
        </a>
    </div>
</div>
@endsection