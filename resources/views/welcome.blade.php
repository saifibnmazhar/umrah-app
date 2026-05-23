@extends('layouts.app')

@section('title', 'Welcome')

@php
    $canAccessVisa = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Visa Admin', 'Visa Staff'])->isNotEmpty();
    $canAccessTicket = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Ticket Admin', 'Ticket Staff'])->isNotEmpty();
    $canAccessAdmin = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty();
@endphp
@section('content')
<div class="max-w-3xl mx-auto py-12">
    <h1 class="text-3xl font-bold text-slate-800 mb-8">BM Umrah Booking - Laravel UI</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-slate-700 mb-4">Main Pages</h2>
            <ul class="space-y-2">
                <li><a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Dashboard</a></li>
                <li><a href="{{ route('bookings.index') }}" class="text-blue-600 hover:underline">Booking</a></li>
                @if($canAccessTicket)<li><a href="{{ route('fare.admin') }}" class="text-blue-600 hover:underline">Fare Admin</a></li>@endif
                @if($canAccessVisa)<li><a href="{{ route('visa.admin') }}" class="text-blue-600 hover:underline">Visa Admin</a></li>@endif
                <li><a href="{{ route('fingerprint.admin') }}" class="text-blue-600 hover:underline">Fingerprint Admin</a></li>
                <li><a href="{{ route('fingerprint.staff') }}" class="text-blue-600 hover:underline">Fingerprint Staff</a></li>
                @if($canAccessAdmin)<li><a href="{{ route('settings') }}" class="text-blue-600 hover:underline">Settings</a></li>@endif
            </ul>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-slate-700 mb-4">Reports</h2>
            <ul class="space-y-2">
                @if($canAccessTicket)<li><a href="{{ route('report.statement') }}" class="text-blue-600 hover:underline">Ticket Statement</a></li>@endif
                <li><a href="{{ route('report.profit-loss') }}" class="text-blue-600 hover:underline">Profit/Loss Report</a></li>
                @if($canAccessVisa)<li><a href="{{ route('report.visa') }}" class="text-blue-600 hover:underline">Visa Report</a></li>@endif
                @if($canAccessVisa)<li><a href="{{ route('report.visa-agent') }}" class="text-blue-600 hover:underline">Visa Agent Report</a></li>@endif
                @if($canAccessTicket)<li><a href="{{ route('report.ticket-agent') }}" class="text-blue-600 hover:underline">Ticket Agent Report</a></li>@endif
                <li><a href="{{ route('report.due') }}" class="text-blue-600 hover:underline">Due Report</a></li>
                <li><a href="{{ route('report.reissue-refund') }}" class="text-blue-600 hover:underline">Re-Issue & Refund Report</a></li>
                <li><a href="{{ route('report.user-sales') }}" class="text-blue-600 hover:underline">User-wise Sales Report</a></li>
                @if($canAccessTicket)<li><a href="{{ route('report.pending-ticket') }}" class="text-blue-600 hover:underline">Pending Outbound Ticket Report</a></li>@endif
                <li><a href="{{ route('report.payment-receiving') }}" class="text-blue-600 hover:underline">Payment Receiving Report</a></li>
                <li><a href="{{ route('report.fingerprint') }}" class="text-blue-600 hover:underline">Fingerprint Report</a></li>
            </ul>
        </div>
    </div>
</div>
@endsection