@extends('layouts.app')
@section('title', 'Ticket Fares')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Ticket Fares</h1>
        <a href="{{ route('ticket-fares.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
            Add New
        </a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    <livewire:ticket-fare.ticket-fare-index-table />
@endsection