@extends('layouts.app')
@section('title', 'Add Airline City')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('airline-cities.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Airline Cities
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Add Airline City</h1>

    <form method="POST" action="{{ route('airline-cities.store') }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf

        <div>
            <label for="airline_id" class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
            <select name="airline_id" id="airline_id" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('airline_id') border-red-500 @enderror">
                <option value="">Select Airline</option>
                @foreach($airlines as $airline)
                    <option value="{{ $airline->id }}" {{ old('airline_id') == $airline->id ? 'selected' : '' }}>
                        {{ $airline->name }}
                    </option>
                @endforeach
            </select>
            @error('airline_id')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="city_code_id" class="block text-sm font-medium text-slate-700 mb-1">City</label>
            <select name="city_code_id" id="city_code_id" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('city_code_id') border-red-500 @enderror">
                <option value="">Select City</option>
                @foreach($cityCodes as $cityCode)
                    <option value="{{ $cityCode->id }}" {{ old('city_code_id') == $cityCode->id ? 'selected' : '' }}>
                        {{ $cityCode->city_name }} ({{ $cityCode->code }})
                    </option>
                @endforeach
            </select>
            @error('city_code_id')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Create Airline City
            </button>
            <a href="{{ route('airline-cities.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection