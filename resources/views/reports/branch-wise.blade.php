@extends('layouts.app')

@section('title', 'Branch Wise Report')

@section('content')
<div class="max-w-3xl mx-auto pt-6">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Branch Wise Report</h1>

    <form method="GET" action="{{ route('report.branch-wise') }}" class="flex flex-wrap items-end gap-4 mb-6 bg-white rounded-lg border border-slate-200 shadow-sm p-4">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Date From</label>
            <input type="date" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}"
                class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none"
                onchange="this.form.submit()">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Date To</label>
            <input type="date" name="date_to" value="{{ $dateTo->format('Y-m-d') }}"
                class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none"
                onchange="this.form.submit()">
        </div>
        @if(!$userBranchId)
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Branch</label>
            <select name="branch_id" class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none" onchange="this.form.submit()">
                <option value="">All Branches</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ ($selectedBranch ?? '') == $branch->id ? 'selected' : '' }}>
                    {{ $branch->name }}
                </option>
                @endforeach
            </select>
        </div>
        @endif
        <a href="{{ route('report.branch-wise') }}" class="px-4 py-2 bg-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-300 transition">Reset</a>
    </form>

    @php $dateLabel = $dateFrom->format('d M Y') . ' - ' . $dateTo->format('d M Y'); @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-sm font-semibold text-slate-600">Bookings</h3>
                <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
            <div class="flex justify-between items-center mb-2">
                <div class="text-left">
                    <div class="text-2xl font-bold text-slate-800">{{ $invoiceCount }}</div>
                    <div class="text-xs font-medium text-purple-600">Total Invoice</div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-slate-800">@currency($invoiceTotalAmount, 2)</div>
                    <div class="text-xs font-medium text-emerald-600">Total Amount</div>
                </div>
            </div>
            <div class="text-xs text-slate-500 mt-1">Sales ({{ $dateLabel }})</div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-600">Total Due</h3>
                <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-orange-600 mb-1">@currency($totalDue, 2) <span x-text="$store.currency.mode"></span></div>
            <div class="text-xs text-slate-500 mt-1">Receivable ({{ $dateLabel }})</div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-600">Total Due Collection</h3>
                <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-slate-800 mb-1">@currency($totalDueCollection, 2) <span x-text="$store.currency.mode"></span></div>
            <div class="text-xs text-slate-500 mt-1">Collection ({{ $dateLabel }})</div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-sm font-semibold text-slate-600">Fingerprint</h3>
                <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                    </svg>
                </div>
            </div>
            <div class="flex justify-between items-center mb-2">
                <div class="text-left">
                    <div class="text-2xl font-bold text-slate-800">{{ $fingerprintApproved }}</div>
                    <div class="text-xs font-medium text-blue-600">Approved</div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-slate-800">{{ $fingerprintDone }}</div>
                    <div class="text-xs font-medium text-emerald-600">Done</div>
                </div>
            </div>
            <div class="text-center pt-2 border-t border-slate-100">
                <div class="text-xl font-bold text-slate-800">{{ $fingerprintProcessing }}</div>
                <div class="text-xs font-medium text-amber-600">Processing</div>
            </div>
            <div class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">{{ $dateLabel }}</div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-sm font-semibold text-slate-600">Visa</h3>
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
            <div class="flex justify-between items-center mb-2">
                <div class="text-left">
                    <div class="text-2xl font-bold text-slate-800">{{ $visaSubmitted }}</div>
                    <div class="text-xs font-medium text-blue-600">Submitted</div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-slate-800">{{ $visaIssued }}</div>
                    <div class="text-xs font-medium text-emerald-600">Issued</div>
                </div>
            </div>
            <div class="text-center pt-2 border-t border-slate-100">
                <div class="text-xl font-bold text-slate-800">{{ $visaPending }}</div>
                <div class="text-xs font-medium text-amber-600">Pending</div>
            </div>
            <div class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">{{ $dateLabel }}</div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-600">Ticket</h3>
                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </div>
            </div>
            <div class="flex justify-between items-center mb-2">
                <div class="text-left">
                    <div class="text-2xl font-bold text-slate-800">{{ $inboundTicket ?? 0 }}</div>
                    <div class="text-xs font-medium text-emerald-600">Inbound Ticket</div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-slate-800">{{ $outboundTicket ?? 0 }}</div>
                    <div class="text-xs font-medium text-red-600">Outbound Ticket</div>
                </div>
            </div>
            <div class="text-center pt-2 border-t border-slate-100">
                <div class="text-xl font-bold text-slate-800">{{ $pendingTicket ?? 0 }}</div>
                <div class="text-xs font-medium text-amber-600">Pending Ticket</div>
            </div>
            <div class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">{{ $dateLabel }}</div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 rounded-full bg-pink-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-slate-800 mb-1">{{ $totalPassengers ?? 'N/A' }}</div>
            <div class="text-sm font-semibold text-slate-700">Total Passengers</div>
            <div class="text-xs text-slate-500 mt-1">{{ $dateLabel }}</div>
        </div>
    </div>
</div>
@endsection
