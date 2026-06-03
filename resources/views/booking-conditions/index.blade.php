@extends('layouts.app')
@section('title', 'Booking Conditions')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Booking Conditions</h1>
        <a href="{{ route('booking-conditions.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">
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

    <style>[x-cloak]{display:none!important}</style>

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <colgroup>
                    <col class="w-16">
                    <col>
                    <col class="w-24">
                    <col class="w-40">
                </colgroup>
                <thead class="bg-slate-50 text-slate-600 text-xs font-medium uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Sort</th>
                        <th class="px-4 py-3 text-left">Title</th>
                        <th class="px-4 py-3 text-right">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                    @forelse($bookingConditions as $bookingCondition)
                        <tbody x-data="{ open: false }" class="divide-y divide-slate-200">
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-500">{{ $bookingCondition->sort_order ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-700 font-medium cursor-pointer select-none" @click="open = !open">
                                    {{ $bookingCondition->title }}
                                    <span class="text-xs text-slate-400 ml-1" x-text="open ? '▲' : '▼'"></span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('booking-conditions.toggle-active', $bookingCondition->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none {{ $bookingCondition->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $bookingCondition->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('booking-conditions.edit', $bookingCondition->id) }}" class="text-slate-600 hover:text-slate-800 font-medium" aria-label="Edit {{ $bookingCondition->title }}">Edit</a>
                                        <form method="POST" action="{{ route('booking-conditions.destroy', $bookingCondition->id) }}" onsubmit="return confirm('Are you sure you want to delete this booking condition?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium" aria-label="Delete {{ $bookingCondition->title }}">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="open" x-cloak>
                                <td colspan="4" class="px-4 py-3 text-slate-600 bg-slate-50/50 border-t-0">
                                    {{ $bookingCondition->description ?? 'No description provided.' }}
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-slate-500">
                                No booking conditions found.
                            </td>
                        </tr>
                    @endforelse
            </table>
        </div>
    </div>

    <div class="mt-4 flex justify-center">
        {{ $bookingConditions->appends(request()->query())->links() }}
    </div>
</div>
@endsection
