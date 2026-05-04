@extends('layouts.app')
@section('title', 'Edit City Code')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('city-codes.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to City Codes
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit City Code</h1>

    <form method="POST" action="{{ route('city-codes.update', $cityCode->id) }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="city_name" class="block text-sm font-medium text-slate-700 mb-1">City Name</label>
            <input 
                type="text" 
                name="city_name" 
                id="city_name" 
                value="{{ old('city_name', $cityCode->city_name) }}" 
                placeholder="Enter city name"
                aria-describedby="city_name-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('city_name') border-red-500 @enderror"
            >
            @error('city_name')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="code" class="block text-sm font-medium text-slate-700 mb-1">Code</label>
            <input 
                type="text" 
                name="code" 
                id="code" 
                value="{{ old('code', $cityCode->code) }}" 
                placeholder="Enter city code (e.g., DAC, JED)"
                aria-describedby="code-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('code') border-red-500 @enderror"
            >
            @error('code')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="country" class="block text-sm font-medium text-slate-700 mb-1">Country</label>
            <input 
                type="text" 
                name="country" 
                id="country" 
                value="{{ old('country', $cityCode->country) }}" 
                placeholder="Enter country name"
                aria-describedby="country-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('country') border-red-500 @enderror"
            >
            @error('country')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Update City Code
            </button>
            <a href="{{ route('city-codes.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection