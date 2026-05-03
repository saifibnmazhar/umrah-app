@extends('layouts.app')

@section('title', 'Branch Due Details')

@section('content')
<div class="max-w-3xl mx-auto container py-8" x-data="{ showModal: false }">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Branch Due Details</h1>
        <p class="text-gray-600">View branch due information</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Branch Information</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Branch Name</p>
                    <p class="font-medium text-gray-900">{{ $branchName ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Branch Code</p>
                    <p class="font-medium text-gray-900">{{ $branchCode ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Contact Number</p>
                    <p class="font-medium text-gray-900">{{ $contactNumber ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="font-medium text-gray-900">{{ $email ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Due Details</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Total Due Amount</p>
                    <p class="font-medium text-gray-900">Rs. {{ number_format($totalDue ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Paid Amount</p>
                    <p class="font-medium text-gray-900">Rs. {{ number_format($paidAmount ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Pending Amount</p>
                    <p class="font-medium text-gray-900">Rs. {{ number_format($pendingAmount ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Due Date</p>
                    <p class="font-medium text-gray-900">{{ $dueDate ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Transaction History</h2>
        </div>
        <div class="p-6">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-sm text-gray-500 border-b">
                        <th class="pb-2">Date</th>
                        <th class="pb-2">Description</th>
                        <th class="pb-2">Amount</th>
                        <th class="pb-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b">
                        <td class="py-3">{{ $transactionDate ?? 'N/A' }}</td>
                        <td class="py-3">{{ $transactionDescription ?? 'N/A' }}</td>
                        <td class="py-3">Rs. {{ number_format($transactionAmount ?? 0, 2) }}</td>
                        <td class="py-3">
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                {{ $transactionStatus ?? 'Completed' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex justify-end">
        <a href="{{ route('branch-due') }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
            Back to List
        </a>
    </div>
</div>
@endsection