@extends('layouts.app')
@section('title', 'Passenger Statuses')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Passenger Statuses</h1>
        <a href="{{ route('passenger-statuses.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
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
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Description</th>
                        <th class="px-4 py-3 text-center">Passengers</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($passengerStatuses as $status)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700 font-medium">{{ $status->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $status->description ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700">
                                    {{ $status->passengers->count() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('passenger-statuses.edit', $status->id) }}" class="text-slate-600 hover:text-slate-800 font-medium">Edit</a>
                                    <form method="POST" action="{{ route('passenger-statuses.destroy', $status->id) }}" onsubmit="return confirm('Are you sure you want to delete this passenger status?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-slate-500">
                                No passenger statuses found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-200">
            {{ $passengerStatuses->links() }}
        </div>
    </div>
</div>
@endsection