@extends('layouts.app')

@section('title', 'Invoice Details')

@section('content')
<div class="max-w-3xl mx-auto container py-8" x-data="{ showModal: false }">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Invoice Details</h1>
        <p class="text-gray-600">View invoice information</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Invoice Information</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Invoice Number</p>
                    <p class="font-medium text-gray-900">{{ $invoiceNumber ?? 'INV-0001' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Invoice Date</p>
                    <p class="font-medium text-gray-900">{{ $invoiceDate ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Due Date</p>
                    <p class="font-medium text-gray-900">{{ $dueDate ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                        {{ $status ?? 'Pending' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Customer Information</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Customer Name</p>
                    <p class="font-medium text-gray-900">{{ $customerName ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Customer Email</p>
                    <p class="font-medium text-gray-900">{{ $customerEmail ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Phone Number</p>
                    <p class="font-medium text-gray-900">{{ $phoneNumber ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Address</p>
                    <p class="font-medium text-gray-900">{{ $address ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Invoice Items</h2>
        </div>
        <div class="p-6">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-sm text-gray-500 border-b">
                        <th class="pb-2">Item</th>
                        <th class="pb-2">Quantity</th>
                        <th class="pb-2">Unit Price</th>
                        <th class="pb-2">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b">
                        <td class="py-3">{{ $itemName ?? 'N/A' }}</td>
                        <td class="py-3">{{ $quantity ?? 1 }}</td>
                        <td class="py-3">Rs. {{ number_format($unitPrice ?? 0, 2) }}</td>
                        <td class="py-3">Rs. {{ number_format($itemTotal ?? 0, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="p-6">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">Subtotal</p>
                    <p class="text-lg font-bold">Rs. {{ number_format($subtotal ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Tax</p>
                    <p class="text-lg font-bold">Rs. {{ number_format($tax ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Amount</p>
                    <p class="text-lg font-bold">Rs. {{ number_format($totalAmount ?? 0, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-between">
        <a href="{{ route('invoices') }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
            Back to List
        </a>
        <div class="space-x-2">
            <button @click="showModal = true" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Print Invoice
            </button>
        </div>
    </div>
</div>
@endsection