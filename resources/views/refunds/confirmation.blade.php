@extends('layouts.app')
@section('title', 'Refund Confirmation')
@section('content')
<div class="max-w-3xl mx-auto container py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Refund Confirmation</h1>
        <p class="text-slate-600">Confirm refund request</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-slate-700">Refund Request Submitted</h2>
                    <p class="text-sm text-slate-500">Your refund request is being processed</p>
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
                            <span class="text-sm font-medium text-slate-900">{{ $ticket_number ?? 'TK-20260001' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Passenger Name</span>
                            <span class="text-sm font-medium text-slate-900">{{ $passenger_name ?? 'John Doe' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Route</span>
                            <span class="text-sm font-medium text-slate-900">{{ $route ?? 'DAC-JED' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Travel Date</span>
                            <span class="text-sm font-medium text-slate-900">{{ $travel_date ?? '2026-05-15' }}</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase mb-3">Refund Details</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Ticket Fare</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($ticket_fare ?? 65000) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Cancellation Charge</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($cancellation_charge ?? 5000) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Service Charge</span>
                            <span class="text-sm font-medium text-slate-900">Rs. {{ number_format($service_charge ?? 1000) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600">Refund Method</span>
                            <span class="text-sm font-medium text-slate-900">{{ $refund_method ?? 'Bank Transfer' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-6 bg-slate-50 border-t border-slate-200">
            <div class="flex justify-between items-center">
                <span class="text-sm font-medium text-slate-700">Refundable Amount</span>
                <span class="text-lg font-bold text-emerald-600">Rs. {{ number_format($refundable_amount ?? 59000) }}</span>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('tickets.index') }}" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition">
            Back to Tickets
        </a>
        <a href="{{ route('refunds.history') }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
            View History
        </a>
    </div>
</div>
@endsection