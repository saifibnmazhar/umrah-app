@extends('layouts.app')
@section('title', 'Add Ticket Confirmation')
@section('content')
<div class="max-w-3xl mx-auto container py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Add Ticket Confirmation</h1>
        <p class="text-slate-600">Ticket has been added successfully</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-slate-700">Ticket Added Successfully</h2>
                    <p class="text-sm text-slate-500">The ticket has been issued</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Ticket Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Ticket Number</span>
                            <span class="text-sm font-medium text-slate-900">{{ $ticket_number ?? 'TK-20260045' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Passenger Name</span>
                            <span class="text-sm font-medium text-slate-900">{{ $passenger_name ?? 'Ahmed Ali' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Passport Number</span>
                            <span class="text-sm font-medium text-slate-900">{{ $passport_number ?? 'A12345678' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Mobile</span>
                            <span class="text-sm font-medium text-slate-900">{{ $mobile ?? '+8801700000000' }}</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Flight Details</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Airline</span>
                            <span class="text-sm font-medium text-slate-900">{{ $airline ?? 'Saudi Airlines' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Flight</span>
                            <span class="text-sm font-medium text-slate-900">{{ $flight ?? 'SV-802' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Route</span>
                            <span class="text-sm font-medium text-slate-900">{{ $route ?? 'DAC-JED' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Travel Date</span>
                            <span class="text-sm font-medium text-slate-900">{{ $travel_date ?? '2026-06-15' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-6 bg-slate-50 border-t border-slate-200">
            <div class="flex justify-between items-center">
                <span class="text-sm font-medium text-slate-700">Ticket Fare</span>
                <span class="text-lg font-bold text-slate-900">Rs. {{ number_format($ticket_fare ?? 65000) }}</span>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('tickets.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition">
            Back to Tickets
        </a>
        <a href="{{ route('tickets.print', $ticket_id ?? 45) }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
            Print Ticket
        </a>
    </div>
</div>
@endsection