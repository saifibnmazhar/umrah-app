@extends('layouts.app')
@section('title', 'Add District')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('districts.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Districts
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Add District</h1>

    <form method="POST" action="{{ route('districts.store') }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf

        @php
        $fields = ['name'];
        $fieldLabels = [
            'name' => 'District Name',
        ];
        $placeholders = [
            'name' => 'Enter district name',
        ];
        $divisions = ['Barishal', 'Chattogram', 'Dhaka', 'Khulna', 'Mymensingh', 'Rajshahi', 'Rangpur', 'Sylhet'];
        @endphp

        @foreach($fields as $field)
            <div>
                <label for="{{ $field }}" class="block text-sm font-medium text-slate-700 mb-1">
                    {{ $fieldLabels[$field] ?? ucfirst($field) }}
                </label>
                <input
                    type="text"
                    name="{{ $field }}"
                    id="{{ $field }}"
                    value="{{ old($field) }}"
                    placeholder="{{ $placeholders[$field] ?? '' }}"
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error($field) border-red-500 @enderror"
                >
                @error($field)
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        @endforeach

        <div>
            <label for="division" class="block text-sm font-medium text-slate-700 mb-1">Division</label>
            <select
                name="division"
                id="division"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border bg-white @error('division') border-red-500 @enderror"
            >
                <option value="">Select Division</option>
                @foreach($divisions as $division)
                    <option value="{{ $division }}" @selected(old('division') === $division)>{{ $division }}</option>
                @endforeach
            </select>
            @error('division')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Create District
            </button>
            <a href="{{ route('districts.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection