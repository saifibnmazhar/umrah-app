@extends('layouts.app')
@section('title', 'Visa Passenger Details')
@section('content')
<div class="max-w-3xl mx-auto container py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Visa Passenger Details</h1>
        <p class="text-slate-600">View passenger visa information</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">{{ $passenger_name ?? 'Ahmed Ali' }}</h2>
            <p class="text-sm text-slate-500">Passport: {{ $passport_number ?? 'A12345678' }}</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Personal Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Date of Birth</span>
                            <span class="text-sm font-medium text-slate-900">{{ $date_of_birth ?? '1990-05-15' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Gender</span>
                            <span class="text-sm font-medium text-slate-900">{{ $gender ?? 'Male' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Nationality</span>
                            <span class="text-sm font-medium text-slate-900">{{ $nationality ?? 'Bangladeshi' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Mobile</span>
                            <span class="text-sm font-medium text-slate-900">{{ $mobile ?? '+966501234567' }}</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Visa Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Visa Number</span>
                            <span class="text-sm font-medium text-slate-900">{{ $visa_number ?? 'VISA-2026-0001' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Visa Type</span>
                            <span class="text-sm font-medium text-slate-900">{{ $visa_type ?? 'Umrah' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Submission Date</span>
                            <span class="text-sm font-medium text-slate-900">{{ $submission_date ?? '2026-04-15' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Status</span>
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                {{ $status ?? 'Approved' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-700">Visa Agent Information</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">Visa Agent</span>
                        <span class="text-sm font-medium text-slate-900">{{ $visa_agent ?? 'Ali Travel Agency' }}</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">Reference Number</span>
                        <span class="text-sm font-medium text-slate-900">{{ $reference_number ?? 'REF-2026-001' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('visas.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition">
            Back to Visas
        </a>
        <button class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
            Print Visa
        </button>
    </div>
</div>
@endsection