@extends('layouts.app')
@section('title', 'Add Airline')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('airlines.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Airlines
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Add Airline</h1>

    <form method="POST" action="{{ route('airlines.store') }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Airline Name</label>
            <input 
                type="text" 
                name="name" 
                id="name" 
                value="{{ old('name') }}" 
                placeholder="Enter airline name"
                aria-describedby="name-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('name') border-red-500 @enderror"
            >
            @error('name')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="code" class="block text-sm font-medium text-slate-700 mb-1">Airline Code</label>
            <input 
                type="text" 
                name="code" 
                id="code" 
                value="{{ old('code') }}" 
                placeholder="Enter airline code (e.g., SV, QR)"
                aria-describedby="code-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('code') border-red-500 @enderror"
            >
            @error('code')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Create Airline
            </button>
            <a href="{{ route('airlines.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection