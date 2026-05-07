@extends('layouts.app')

@section('title', 'Invoice Details')

@section('content')
<div class="max-w-3xl mx-auto container py-8" x-data="{ showModal: false }">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Invoice Details</h1>
        <p class="text-gray-600">Invoice #{{ $invoice->id }}</p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Invoice Information</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Invoice ID</p>
                    <p class="font-medium text-gray-900">#{{ $invoice->id }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Booking</p>
                    <p class="font-medium text-gray-900">{{ $invoice->booking->id ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Branch</p>
                    <p class="font-medium text-gray-900">{{ $invoice->branch->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Created By</p>
                    <p class="font-medium text-gray-900">{{ $invoice->user->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Created Date</p>
                    <p class="font-medium text-gray-900">{{ $invoice->created_at->format('Y-m-d') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-between">
        <a href="{{ route('invoices.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
            Back to List
        </a>
        <div class="space-x-2">
            <a href="{{ route('invoices.edit', $invoice->id) }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Edit
            </a>
            <form method="POST" action="{{ route('invoices.destroy', $invoice->id) }}" onsubmit="return confirm('Are you sure you want to delete this invoice?')" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>
@endsection