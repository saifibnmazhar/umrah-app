@extends('layouts.app')
@section('title', 'Branch Due Details Report')
@section('content')
<div class="max-w-3xl mx-auto container py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Branch Due Details Report</h1>
        <p class="text-slate-600">Comprehensive due report by branch</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-slate-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Branch</label>
                    <select class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500">
                        <option value="">All Branches</option>
                        <option value="1">Dhaka Branch</option>
                        <option value="2">Chittagong Branch</option>
                        <option value="3">Sylhet Branch</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date From</label>
                    <input type="date" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date To</label>
                    <input type="date" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500">
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-slate-200 rounded-lg shadow-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase">Branch</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase">Invoice</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase">Passenger</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase">Package</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-600 uppercase">Total Cost</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-600 uppercase">Paid</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-600 uppercase">Due</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <tr>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $branch ?? 'Dhaka Branch' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $invoice ?? 'INV-0001' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $passenger ?? 'John Doe' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $package ?? 'Umrah Standard' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right">{{ number_format($total_cost ?? 70000) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 text-right">{{ number_format($paid ?? 50000) }}</td>
                    <td class="px-4 py-3 text-sm text-red-600 text-right">{{ number_format($due ?? 20000) }}</td>
                </tr>
            </tbody>
            <tfoot class="bg-slate-50">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-sm font-medium text-slate-700">Total</td>
                    <td class="px-4 py-3 text-sm font-medium text-slate-700 text-right">{{ number_format($total_cost ?? 70000) }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-slate-700 text-right">{{ number_format($paid ?? 50000) }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-red-600 text-right">{{ number_format($due ?? 20000) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection