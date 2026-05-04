@extends('layouts.app')
@section('title', 'Edit District')
@section('content')
<div class="max-w-2xl mx-auto">
    <a href="{{ route('districts.index') }}" class="text-slate-600 hover:text-slate-800 text-sm mb-4 inline-block">
        ← Back to Districts
    </a>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit District</h1>

    <form method="POST" action="{{ route('districts.update', $district->id) }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        @php
        $fields = ['name', 'division'];
        $fieldLabels = [
            'name' => 'District Name',
            'division' => 'Division',
        ];
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
                    value="{{ old($field, $district->$field) }}"
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error($field) border-red-500 @enderror"
                >
                @error($field)
                    <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>
        @endforeach

        <div class="pt-2">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Update District
            </button>
        </div>
    </form>
</div>
@endsection