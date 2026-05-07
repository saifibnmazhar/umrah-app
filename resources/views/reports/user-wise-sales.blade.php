@extends('layouts.app')
@section('title', 'User-wise Sales Report')
@section('content')
<div class="max-w-3xl mx-auto" x-data="{ date_from: '', date_to: '', user: '' }">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">User-wise Sales Report</h1>

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
                <label class="block text-sm font-medium text-slate-700 mb-1">User</label>
                <select x-model="user" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent">
                    <option value="">All Users</option>
                    <option value="user1">User 1</option>
                    <option value="user2">User 2</option>
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
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">User</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Total Booking</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Total PAX</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Total Sale</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Total Cost</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase">Profit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <tr>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $user ?? 'User 1' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $total_booking ?? '20' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $total_pax ?? '25' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $total_sale ?? '1000000' }}</td>
                    <td class="px-3 py-2 text-sm text-slate-700">{{ $total_cost ?? '900000' }}</td>
                    <td class="px-3 py-2 text-sm text-green-600">{{ $profit ?? '100000' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection