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

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-slate-700 mb-2">Set Default Flight Date Gap</h2>
            <p class="text-sm text-slate-500">
                This value determines the minimum number of days between booking date and flight departure date.
            </p>
        </div>

        <form method="POST" action="{{ route('flight-date-gaps.store') }}">
            @csrf
            
            <div class="space-y-4">
                <div>
                    <label for="gap" class="block text-sm font-medium text-slate-700 mb-1">Gap Between Booking and Flight Date (Days) *</label>
                    <input type="number" id="gap" name="gap" 
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
                        value="{{ old('gap', 30) }}" 
                        min="1" required>
                    <p class="text-xs text-slate-500 mt-1">Minimum value is 1 day.</p>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit" class="px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>
@endsection