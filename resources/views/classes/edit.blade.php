@extends('layouts.app')
@section('title', 'Edit Class')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('classes.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
            ← Back to Classes
        </a>
    </div>

    <h1 class="text-2xl font-bold text-slate-800 mb-6">Edit Class</h1>

    <form method="POST" action="{{ route('classes.update', $class->id) }}" class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Class Name</label>
            <input 
                type="text" 
                name="name" 
                id="name" 
                value="{{ old('name', $class->name) }}" 
                placeholder="Enter class name"
                aria-describedby="name-error"
                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2 border @error('name') border-red-500 @enderror"
            >
            @error('name')
                <span class="text-sm text-red-600 mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
                Update Class
            </button>
            <a href="{{ route('classes.index') }}" class="text-slate-600 hover:text-slate-800 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection