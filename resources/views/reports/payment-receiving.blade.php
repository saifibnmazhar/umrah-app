@extends('layouts.app')
@section('title', 'Payment Receiving Report')
@section('content')
<div class="max-w-[1600px] mx-auto">
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Reports</span>
        <span class="text-sm text-gray-400 mx-1">›</span>
        <span class="text-sm text-gray-700 font-semibold">Payment Receiving Report</span>
    </div>

    <form method="GET" action="{{ route('report.payment-receiving') }}">
    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">From:</label>
                    <input type="date" name="date_from" value="{{ request('date_from', now()->subDays(30)->format('Y-m-d')) }}" onchange="if(this.value) this.form.submit()" class="date-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">To:</label>
                    <input type="date" name="date_to" value="{{ request('date_to', now()->format('Y-m-d')) }}" onchange="if(this.value) this.form.submit()" class="date-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF
                </button>
                <button type="button" class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Excel
                </button>
            </div>
        </div>
    </div>
    </form>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] table-fixed">
                <thead>
                    <tr class="table-header">
                        <th class="w-32 px-4 py-3 text-sm font-bold text-gray-700 text-center border-r border-gray-300">Date</th>
                        <th class="w-40 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Cash Received</th>
                        <th class="w-40 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">Bank Received</th>
                        <th class="w-44 px-4 py-3 text-sm font-bold text-gray-700 text-right border-r border-gray-300">BD Office Collection</th>
                        <th class="w-44 px-4 py-3 text-sm font-bold text-gray-700 text-right">KSA Office Collection</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dailyPayments as $day)
                    <tr class="even:bg-[#fafafa] hover:bg-[#e8f4fc]">
                        <td class="px-4 py-3 text-sm text-center border-r border-gray-200 font-medium text-gray-700">{{ date('d-M-Y', strtotime($day['date'])) }}</td>
                        <td class="px-4 py-3 text-sm text-right border-r border-gray-200 {{ $day['cash'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['cash'], 2)</td>
                        <td class="px-4 py-3 text-sm text-right border-r border-gray-200 {{ $day['bank'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['bank'], 2)</td>
                        <td class="px-4 py-3 text-sm text-right border-r border-gray-200 {{ $day['bd_office'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['bd_office'], 2)</td>
                        <td class="px-4 py-3 text-sm text-right {{ $day['ksa_office'] > 0 ? 'text-green-800 font-semibold' : 'text-gray-400' }}">@currency($day['ksa_office'], 2)</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No records found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="rounded-lg overflow-hidden border border-green-500 bg-gradient-to-b from-green-50 to-green-100">
            <div class="bg-gradient-to-b from-green-500 to-green-600 px-4 py-2">
                <h3 class="text-white text-sm font-bold">Total Cash Received</h3>
            </div>
            <div class="px-4 py-4">
                <p class="text-2xl font-bold text-green-700">@currency($totalCashPayment, 2)</p>
            </div>
        </div>
        <div class="rounded-lg overflow-hidden border border-green-500 bg-gradient-to-b from-green-50 to-green-100">
            <div class="bg-gradient-to-b from-green-500 to-green-600 px-4 py-2">
                <h3 class="text-white text-sm font-bold">Total Bank Received</h3>
            </div>
            <div class="px-4 py-4">
                <p class="text-2xl font-bold text-green-700">@currency($totalBankPayment, 2)</p>
            </div>
        </div>
        <div class="rounded-lg overflow-hidden border border-green-500 bg-gradient-to-b from-green-50 to-green-100">
            <div class="bg-gradient-to-b from-green-500 to-green-600 px-4 py-2">
                <h3 class="text-white text-sm font-bold">Total BD Office Collection</h3>
            </div>
            <div class="px-4 py-4">
                <p class="text-2xl font-bold text-green-700">@currency($totalBdOfficeCollection, 2)</p>
            </div>
        </div>
        <div class="rounded-lg overflow-hidden border border-green-500 bg-gradient-to-b from-green-50 to-green-100">
            <div class="bg-gradient-to-b from-green-500 to-green-600 px-4 py-2">
                <h3 class="text-white text-sm font-bold">Total KSA Office Collection</h3>
            </div>
            <div class="px-4 py-4">
                <p class="text-2xl font-bold text-green-700">@currency($totalKsaOfficeCollection, 2)</p>
            </div>
        </div>
    </div>

    <div class="text-center text-sm text-gray-500">
        Last Updated: <span id="lastUpdated"></span>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var now = new Date();
        var options = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        var el = document.getElementById('lastUpdated');
        if (el) {
            el.textContent = now.toLocaleDateString('en-GB', options);
        }
    });
</script>
@endpush
