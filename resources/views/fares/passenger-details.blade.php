@extends('layouts.app')
@section('title', 'Fare Passenger Details')
@section('content')
<div class="max-w-3xl mx-auto container py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Fare Passenger Details</h1>
        <p class="text-slate-600">View passenger fare information</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">{{ $passenger_name ?? 'Mohammed Hassan' }}</h2>
            <p class="text-sm text-slate-500">Passport: {{ $passport_number ?? 'B98765432' }}</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Passenger Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Passenger Type</span>
                            <span class="text-sm font-medium text-slate-900">{{ $pax_type ?? 'Adult' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Date of Birth</span>
                            <span class="text-sm font-medium text-slate-900">{{ $date_of_birth ?? '1985-08-20' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Nationality</span>
                            <span class="text-sm font-medium text-slate-900">{{ $nationality ?? 'Bangladeshi' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Mobile</span>
                            <span class="text-sm font-medium text-slate-900">{{ $mobile ?? '+8801712345678' }}</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Fare Details</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Airline</span>
                            <span class="text-sm font-medium text-slate-900">{{ $airline ?? 'Egypt Air' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Route</span>
                            <span class="text-sm font-medium text-slate-900">{{ $route ?? 'DAC-CAI-JED' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Base Fare</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($base_fare ?? 45000) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Tax</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($tax ?? 8500) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">Fare Breakdown</h2>
        </div>
        <div class="p-6">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-sm text-slate-500 border-b">
                        <th class="pb-2">Description</th>
                        <th class="pb-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b">
                        <td class="py-3 text-sm text-slate-600">Base Fare</td>
                        <td class="py-3 text-sm text-slate-900 text-right">Rs. {{ number_format($base_fare ?? 45000) }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-3 text-sm text-slate-600">Airline Tax</td>
                        <td class="py-3 text-sm text-slate-900 text-right">Rs. {{ number_format($airline_tax ?? 5000) }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-3 text-sm text-slate-600">Visa Fee</td>
                        <td class="py-3 text-sm text-slate-900 text-right">Rs. {{ number_format($visa_fee ?? 3500) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 text-sm font-medium text-slate-700">Total</td>
                        <td class="py-3 text-sm font-bold text-slate-900 text-right">Rs. {{ number_format($total_fare ?? 53500) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('fares.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition">
            Back to Fares
        </a>
        <button class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
            Print Details
        </button>
    </div>
</div>
@endsection