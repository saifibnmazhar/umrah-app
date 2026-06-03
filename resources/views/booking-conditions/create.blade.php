@extends('layouts.app')
@section('title', 'Add Booking Condition')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('booking-conditions.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Booking Conditions
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Add Booking Condition</h1>

    <form method="POST" action="{{ route('booking-conditions.store') }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Title</label>
            <input 
                type="text" 
                name="title" 
                id="title" 
                value="{{ old('title') }}" 
                placeholder="Enter condition title"
                aria-describedby="title-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('title') border-red-500 @enderror"
            >
            @error('title')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea 
                name="description" 
                id="description" 
                rows="4"
                placeholder="Enter condition description"
                aria-describedby="description-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('description') border-red-500 @enderror"
            >{{ old('description') }}</textarea>
            @error('description')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="sort_order" class="block text-sm font-medium text-slate-700 mb-1">Sort Order</label>
            <input 
                type="number" 
                name="sort_order" 
                id="sort_order" 
                value="{{ old('sort_order') }}" 
                placeholder="0"
                min="0"
                aria-describedby="sort_order-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('sort_order') border-red-500 @enderror"
            >
            @error('sort_order')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <input 
                type="checkbox" 
                name="is_active" 
                id="is_active" 
                value="1"
                {{ old('is_active', true) ? 'checked' : '' }}
                class="rounded border-slate-300 text-slate-800 focus:ring-slate-500"
            >
            <label for="is_active" class="text-sm font-medium text-slate-700">Active</label>
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Create Booking Condition
            </button>
            <a href="{{ route('booking-conditions.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
