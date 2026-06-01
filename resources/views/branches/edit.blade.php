@extends('layouts.app')
@section('title', 'Edit Branch(KSA)')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('branches.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Branches(KSA)
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit Branch(KSA)</h1>

    <form method="POST" action="{{ route('branches.update', $branch->id) }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Branch(KSA) Name</label>
            <input 
                type="text" 
                name="name" 
                id="name" 
                value="{{ old('name', $branch->name) }}" 
                placeholder="Enter branch(KSA) name"
                aria-describedby="name-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('name') border-red-500 @enderror"
            >
            @error('name')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-slate-700 mb-1">Address</label>
            <input 
                type="text" 
                name="address" 
                id="address" 
                value="{{ old('address', $branch->address) }}" 
                placeholder="Enter address"
                aria-describedby="address-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('address') border-red-500 @enderror"
            >
            @error('address')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="contacts" class="block text-sm font-medium text-slate-700 mb-1">Contacts</label>
            <input 
                type="text" 
                name="contacts" 
                id="contacts" 
                value="{{ old('contacts', $branch->contacts) }}" 
                placeholder="Enter contacts"
                aria-describedby="contacts-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('contacts') border-red-500 @enderror"
            >
            @error('contacts')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Update Branch(KSA)
            </button>
            <a href="{{ route('branches.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection