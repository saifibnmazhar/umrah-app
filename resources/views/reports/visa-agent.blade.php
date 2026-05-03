@extends('layouts.app')
@section('title', 'Visa Agent Report')
@section('content')
<div class="max-w-3xl mx-auto" x-data="{ date_from: '', date_to: '', agent: '' }">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Visa Agent Report</h1>

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
                <label class="block text-sm font-medium text-slate-700 mb-1">Agent</label>
                <select x-model="agent" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                    <option value="">All Agents</option>
                    <option value="agent1">Agent 1</option>
                    <option value="agent2">Agent 2</option>
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
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Agent</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Total Visa</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Approved</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Pending</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Rejected</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <tr>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $agent ?? 'Agent 1' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $total_visa ?? '10' }}</td>
                    <td class="px-3 py-2 text-sm text-green-600">{{ $approved ?? '8' }}</td>
                    <td class="px-3 py-2 text-sm text-yellow-600">{{ $pending ?? '1' }}</td>
                    <td class="px-3 py-2 text-sm text-red-600">{{ $rejected ?? '1' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection