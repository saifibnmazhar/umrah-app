@extends('layouts.app')
@section('title', 'Flight Date Gap')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Flight Date Gap</h1>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <div class="text-center py-8">
            <p class="text-slate-600 mb-4">No flight date gap configured.</p>
            <a href="{{ route('flight-date-gaps.create') }}" class="px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                Set Default Gap
            </a>
        </div>
    </div>
</div>
@endsection