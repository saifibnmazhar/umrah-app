@extends('layouts.app')

@section('title', 'Print Invoice')

@section('content')
<div class="max-w-3xl mx-auto container py-8" x-data="{ printing: false }">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Print Invoice</h1>
        <p class="text-gray-600">Print and download invoice</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-8">
            <div class="flex justify-between items-start mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">INVOICE</h2>
                    <p class="text-gray-600 mt-1">{{ $invoiceNumber ?? 'INV-0001' }}</p>
                </div>
                <div class="text-right">
                    <p class="font-semibold text-gray-900">{{ $companyName ?? 'Company Name' }}</p>
                    <p class="text-sm text-gray-600">{{ $companyAddress ?? 'Address' }}</p>
                    <p class="text-sm text-gray-600">{{ $companyPhone ?? 'Phone' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-8 mb-8">
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Bill To</h3>
                    <p class="font-medium text-gray-900">{{ $customerName ?? 'Customer Name' }}</p>
                    <p class="text-sm text-gray-600">{{ $customerEmail ?? 'email@example.com' }}</p>
                    <p class="text-sm text-gray-600">{{ $customerAddress ?? 'Customer Address (KSA)' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Invoice Date</p>
                    <p class="font-medium text-gray-900">{{ $invoiceDate ?? 'N/A' }}</p>
                    <p class="text-sm text-gray-500 mt-2">Due Date</p>
                    <p class="font-medium text-gray-900">{{ $dueDate ?? 'N/A' }}</p>
                </div>
            </div>

            <table class="w-full mb-8">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Description</th>
                        <th class="text-center py-3 text-sm font-semibold text-gray-700">Qty</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Unit Price</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b">
                        <td class="py-3">{{ $itemDescription ?? 'Item Description' }}</td>
                        <td class="py-3 text-center">{{ $quantity ?? 1 }}</td>
                        <td class="py-3 text-right">Rs. {{ number_format($unitPrice ?? 0, 2) }}</td>
                        <td class="py-3 text-right">Rs. {{ number_format($amount ?? 0, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="flex justify-end">
                <div class="w-64">
                    <div class="flex justify-between py-2">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-medium">Rs. {{ number_format($subtotal ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-gray-600">Tax</span>
                        <span class="font-medium">Rs. {{ number_format($tax ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-t-2 border-gray-300">
                        <span class="font-semibold text-gray-900">Total</span>
                        <span class="font-bold text-lg">Rs. {{ number_format($total ?? 0, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-between">
        <a href="{{ route('invoices.details', $invoiceId ?? 1) }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
            Back
        </a>
        <button @click="printing = true; window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Print Invoice
        </button>
    </div>
</div>
@endsection