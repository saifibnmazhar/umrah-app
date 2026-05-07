@extends('layouts.app')
@section('title', 'Edit Airline Class')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('airline-classes.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Airline Classes
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit Airline Class</h1>

    <form method="POST" action="{{ route('airline-classes.update', $airlineClass->id) }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="airline_id" class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
            <select name="airline_id" id="airline_id" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('airline_id') border-red-500 @enderror">
                <option value="">Select Airline</option>
                @foreach($airlines as $airline)
                    <option value="{{ $airline->id }}" {{ (old('airline_id') ?? $airlineClass->airline_id) == $airline->id ? 'selected' : '' }}>
                        {{ $airline->name }}
                    </option>
                @endforeach
            </select>
            @error('airline_id')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="class_id" class="block text-sm font-medium text-slate-700 mb-1">Class</label>
            <select name="class_id" id="class_id" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('class_id') border-red-500 @enderror">
                <option value="">Select Class</option>
                @foreach($travelClasses as $class)
                    <option value="{{ $class->id }}" {{ (old('class_id') ?? $airlineClass->class_id) == $class->id ? 'selected' : '' }}>
                        {{ $class->name }}
                    </option>
                @endforeach
            </select>
            @error('class_id')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Update Airline Class
            </button>
            <a href="{{ route('airline-classes.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection