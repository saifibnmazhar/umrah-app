@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Invoices</h1>
        <a href="{{ route('invoices.create') }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Invoice
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

    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">ID</th>
                        <th class="px-3 py-2 text-left font-medium">Booking</th>
                        <th class="px-3 py-2 text-left font-medium">Branch</th>
                        <th class="px-3 py-2 text-left font-medium">Created By</th>
                        <th class="px-3 py-2 text-left font-medium">Date</th>
                        <th class="px-3 py-2 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-800 font-medium">#{{ $invoice->id }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $invoice->booking->id ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $invoice->branch->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $invoice->user->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $invoice->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <a href="{{ route('invoices.show', $invoice->id) }}" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded">View</a>
                                    <a href="{{ route('invoices.edit', $invoice->id) }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded">Edit</a>
                                    <form method="POST" action="{{ route('invoices.destroy', $invoice->id) }}" onsubmit="return confirm('Are you sure you want to delete this invoice?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">
                                No invoices yet. Click "Add Invoice" to create a new one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-center">
            {{ $invoices->links() }}
        </div>
    </div>
</div>
@endsection