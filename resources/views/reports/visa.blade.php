@extends('layouts.app')
@section('title', 'Visa Report')
@section('content')
<div class="max-w-3xl mx-auto" x-data="{ date_from: '', date_to: '', status: '' }">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Visa Report</h1>

    <div class="bg-white p-4 rounded-lg shadow-sm border border-slate-200 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date From</label>
                <input type="date" x-model="date_from" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date To</label>
                <input type="date" x-model="date_to" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                <select x-model="status" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="flex items-end">
                <button class="w-full px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-700 transition">Search</button>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-slate-200 rounded-lg shadow-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Date</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Invoice</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Passenger</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Passport</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Visa Type</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <tr>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $date ?? '2024-01-01' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $invoice ?? 'INV-0001' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $passenger ?? 'John Doe' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $passport ?? 'AB1234567' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $visa_type ?? 'Umrah' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $status ?? 'Pending' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection