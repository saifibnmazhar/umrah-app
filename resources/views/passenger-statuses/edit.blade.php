@extends('layouts.app')
@section('title', 'Edit Passenger Status')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('passenger-statuses.index') }}" class="text-slate-600 hover:text-slate-800 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Passenger Statuses
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit Passenger Status</h1>

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <form method="POST" action="{{ route('passenger-statuses.update', $passengerStatus->id) }}">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $passengerStatus->name) }}" class="w-full px-4 py-2 border border-slate-300 rounded-md focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('name') border-red-500 @enderror" maxlength="255" required>
                    @error('name')
                        <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-md focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @error('description') border-red-500 @enderror" maxlength="1000">{{ old('description', $passengerStatus->description) }}</textarea>
                    @error('description')
                        <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit" class="px-6 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition font-medium">
                    Update
                </button>
                <a href="{{ route('passenger-statuses.index') }}" class="px-6 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50 transition font-medium">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection