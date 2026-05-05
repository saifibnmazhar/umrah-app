@extends('layouts.app')
@section('title', 'Airline Classes')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Airline Classes</h1>
        <a href="{{ route('airline-classes.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
            Add New
        </a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs font-medium uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 text-left">Airline</th>
                        <th class="px-6 py-4 text-left">Class</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($airlineClasses as $airlineClass)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-slate-700 font-medium">{{ $airlineClass->airline->name }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ $airlineClass->class->name }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-4">
                                    <a href="{{ route('airline-classes.edit', $airlineClass->id) }}" class="text-slate-600 hover:text-slate-800 font-medium text-sm" aria-label="Edit {{ $airlineClass->airline->name }} - {{ $airlineClass->class->name }}">Edit</a>
                                    <form method="POST" action="{{ route('airline-classes.destroy', $airlineClass->id) }}" onsubmit="return confirm('Are you sure you want to delete this airline class?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-sm" aria-label="Delete {{ $airlineClass->airline->name }} - {{ $airlineClass->class->name }}">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-slate-500">
                                No airline classes found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex justify-center">
        {{ $airlineClasses->links() }}
    </div>
</div>
@endsection