@extends('layouts.app')
@section('title', 'Add Bank')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('banks.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Banks
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Add Bank</h1>

    <form method="POST" action="{{ route('banks.store') }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Bank Name</label>
            <input 
                type="text" 
                name="name" 
                id="name" 
                value="{{ old('name') }}" 
                placeholder="Enter bank name"
                aria-describedby="name-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('name') border-red-500 @enderror"
            >
            @error('name')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <input 
                type="text" 
                name="description" 
                id="description" 
                value="{{ old('description') }}" 
                placeholder="Enter description (optional)"
                aria-describedby="description-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('description') border-red-500 @enderror"
            >
            @error('description')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="currency" class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
            <select
                name="currency"
                id="currency"
                aria-describedby="currency-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border bg-white @error('currency') border-red-500 @enderror"
            >
                <option value="">Select Currency</option>
                <option value="SAR" @selected(old('currency') == 'SAR')>SAR</option>
                <option value="BDT" @selected(old('currency') == 'BDT')>BDT</option>
            </select>
            @error('currency')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="location" class="block text-sm font-medium text-slate-700 mb-1">Location</label>
            <select
                name="location"
                id="location"
                aria-describedby="location-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border bg-white @error('location') border-red-500 @enderror"
            >
                <option value="">Select Location</option>
                <option value="KSA" @selected(old('location') == 'KSA')>KSA</option>
                <option value="BD" @selected(old('location') == 'BD')>Bangladesh</option>
            </select>
            @error('location')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Create Bank
            </button>
            <a href="{{ route('banks.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection