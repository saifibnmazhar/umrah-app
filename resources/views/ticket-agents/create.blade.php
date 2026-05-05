@extends('layouts.app')
@section('title', 'Add Ticket Agent')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('ticket-agents.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Ticket Agents
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Add Ticket Agent</h1>

    <form method="POST" action="{{ route('ticket-agents.store') }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('name') border-red-500 @enderror">
            @error('name')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-slate-700 mb-1">Address <span class="text-slate-400 text-xs">(optional)</span></label>
            <textarea name="address" id="address" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('address') border-red-500 @enderror">{{ old('address') }}</textarea>
            @error('address')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="contacts" class="block text-sm font-medium text-slate-700 mb-1">Contacts <span class="text-slate-400 text-xs">(optional)</span></label>
            <input type="text" name="contacts" id="contacts" value="{{ old('contacts') }}" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent @error('contacts') border-red-500 @enderror">
            @error('contacts')
                <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Create Ticket Agent
            </button>
            <a href="{{ route('ticket-agents.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection