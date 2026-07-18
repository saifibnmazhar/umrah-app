@extends('layouts.app')
@section('title', 'Visa Agent Report')
@section('content')
<style>
select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 8px center;
    background-repeat: no-repeat;
    background-size: 16px 16px;
    padding-right: 32px;
}
@media print {
    @page { size: landscape; }
    body * { visibility: hidden; }
    body { background: white !important; }
    .print-area, .print-area * { visibility: visible; }
    .print-area { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print { display: none !important; }
    .print-heading { display: block !important; text-align: center; margin-bottom: 20px; }
    .print-heading h1 { font-size: 28px; font-weight: 900; color: #1e293b; margin: 0 0 4px; letter-spacing: -0.5px; }
    .print-heading .sub { font-size: 13px; color: #64748b; margin: 0 0 12px; }
    .print-heading hr { border: none; border-top: 2px solid #e2e8f0; margin: 0; }
    table { width: 100% !important; min-width: auto !important; table-layout: auto !important; font-size: 9px; }
    th, td { padding: 3px 5px !important; white-space: nowrap; }
    th:last-child, td:last-child { display: none; }
    th:nth-child(4), td:nth-child(4) { white-space: normal; }
    body.printing-modal .print-area { display: none !important; }
    body.printing-modal #detailModal { position: static !important; }
    body.printing-modal #detailModal .modal-overlay { display: none !important; }
    body.printing-modal #detailModal .modal-content { position: static !important; visibility: visible !important; max-height: none !important; box-shadow: none !important; border-radius: 0 !important; }
    body.printing-modal #detailModal .modal-content * { visibility: visible !important; }
    body.printing-modal #detailModal .modal-content .bg-slate-700 { background: #334155 !important; color: white !important; padding: 12px 16px !important; }
    body.printing-modal #detailModal .modal-content .p-6 { max-height: none !important; }
    body.printing-modal #detailModal .modal-content table { width: 100% !important; min-width: auto !important; table-layout: auto !important; font-size: 10px !important; }
    body.printing-modal #detailModal .modal-content th, body.printing-modal #detailModal .modal-content td { padding: 4px 8px !important; border: 1px solid #ccc !important; visibility: visible !important; }
    body.printing-modal #detailModal .modal-content .bg-gray-50.border-2 { break-inside: avoid; }
    body.printing-modal #detailModal .modal-content th:last-child,
    body.printing-modal #detailModal .modal-content td:last-child {
        display: table-cell !important;
    }
}
.print-heading { display: none; }
</style>

<div class="max-w-[1600px] mx-auto p-4" x-data="visaAgentReport({
    search: '{{ request('search') }}',
    date_from: '{{ request('date_from') }}',
    date_to: '{{ request('date_to') }}',
    visa_agent_id: '{{ request('visa_agent_id') }}',
})">
    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm no-print">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">SEARCH BOX</label>
                <input type="text" x-model="search" @keydown.enter="currentPage = 1; loadData()" placeholder="Search by Agent Name"
                       class="search-input w-80 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">Visa Agent</label>
                <select x-model="visa_agent_id" @change="currentPage = 1; loadData()" class="w-56 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300 bg-white">
                    <option value="">All Agents</option>
                    @foreach($visaAgents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">Date</label>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="date_from" @change="currentPage = 1; loadData()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="date_to" @change="currentPage = 1; loadData()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2 ml-auto no-print">
                <button @click="printReportViaPrint()" class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print
                </button>
            </div>
        </div>
    </div>

    <div class="print-area">
    <div class="print-heading">
        <h1>Visa Agent Report</h1>
        <p class="sub"><span x-text="date_from || '...'"></span> – <span x-text="date_to || '...'"></span></p>
        <hr>
    </div>
    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1400px] table-fixed">
                <thead>
                    <tr class="table-header">
                        <th class="w-56 px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Agent Name</th>
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Submitted</th>
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Total<br>Issued</th>
                        <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Price<br>(Max/Min/Avg)</th>
                        <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                        <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                        <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Balance</th>
                        <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Cancellation<br>Fee</th>
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && filteredData.length === 0">
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">No data found</td>
                        </tr>
                    </template>
                    <template x-for="agent in filteredData" :key="agent.id">
                        <tr class="table-row-agent">
                            <td class="px-2 py-3 text-sm text-left border-r border-gray-200 font-medium text-gray-800" x-text="agent.name"></td>
                            <td class="px-2 py-3 text-sm text-center border-r border-gray-200 font-medium" x-text="agent.totalSubmitted"></td>
                            <td class="px-2 py-3 text-sm text-center border-r border-gray-200 font-medium" x-text="agent.totalIssued"></td>
                            <td class="px-2 py-3 text-xs text-center border-r border-gray-200 whitespace-nowrap">
                                <template x-if="agent.price.max > 0">
                                    <span>
                                        <span class="text-red-600" x-text="$currency(agent.price.max, 2)"></span>
                                        <span class="text-gray-400"> / </span>
                                        <span class="text-green-600" x-text="$currency(agent.price.min, 2)"></span>
                                        <span class="text-gray-400"> / </span>
                                        <span class="text-blue-600" x-text="$currency(agent.price.avg, 2)"></span>
                                    </span>
                                </template>
                                <template x-if="!agent.price.max">
                                    <span class="text-gray-400">-</span>
                                </template>
                            </td>
                            <td class="px-2 py-3 text-sm text-right border-r border-gray-200 font-medium" x-text="$currency(agent.payable, 2)"></td>
                            <td class="px-2 py-3 text-sm text-right border-r border-gray-200"
                                :class="agent.paid > 0 ? 'text-green-700' : 'text-gray-600'"
                                x-text="$currency(agent.paid, 2)"></td>
                            <td class="px-2 py-3 text-sm text-right border-r border-gray-200 font-semibold"
                                                :class="agent.balance > 0 ? 'text-green-700' : (agent.balance < 0 ? 'text-red-600' : 'text-gray-600')"
                                x-text="$currency(Math.abs(agent.balance), 2)"></td>
                            <td class="px-2 py-3 text-sm text-right border-r border-gray-200"
                                :class="agent.cancellationFee > 0 ? 'text-red-600' : 'text-gray-600'"
                                x-text="agent.cancellationFee > 0 ? $currency(agent.cancellationFee, 2) : '-'"></td>
                            <td class="px-2 py-3 text-center whitespace-nowrap">
                                <button @click="openModal(agent.id)" class="view-btn text-white px-3 py-1 rounded text-xs font-medium transition-all">
                                    View
                                </button>
                                {{-- <button @click="openPaymentModal(agent.id)" class="ml-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-medium transition-all">
                                    Pay
                                </button> --}}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mt-0">
        <div class="flex flex-wrap gap-6">
            <div class="footer-box rounded-lg overflow-hidden min-w-[320px]">
                <div class="footer-box-header px-4 py-2">
                    <span class="text-sm font-bold text-gray-700">Visa Agent Report Summary</span>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Agents:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="summary.totalAgents"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Agents with Due:</span>
                            <span class="text-xs font-bold text-red-700" x-text="summary.agentsWithDue"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Payable:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="$currency(summary.totalPayable, 2)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Paid:</span>
                            <span class="text-xs font-bold text-green-700" x-text="$currency(summary.totalPaid, 2)"></span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 mt-1">
                            <span class="text-xs font-bold text-gray-700">Total Balance:</span>
                            <span class="text-xs font-bold" :class="summary.totalBalance > 0 ? 'text-green-700' : 'text-red-700'" x-text="$currency(Math.abs(summary.totalBalance), 2)"></span>
                        </div>
                    </div>
                </div>
            </div>


        </div>

        <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center no-print">
            <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
        </div>
    </div>
    </div>

    <div id="detailModal" x-show="detailModalOpen" x-cloak class="fixed inset-0 z-50">
        <div class="modal-overlay fixed inset-0 bg-transparent" @click="closeModal()"></div>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="modal-content relative bg-white rounded-lg shadow-2xl w-full max-w-4xl w-full max-w-6xl max-h-[90vh] overflow-hidden">
                <div class="bg-slate-700 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-white">Agent Details</h2>
                    <button @click="closeModal()" class="text-white hover:text-gray-300 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] scrollbar-thin">
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-2xl font-bold text-gray-800" x-text="modalAgent.name"></h3>
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-semibold text-gray-500">Date From</label>
                                <input type="date" x-model="modalDateFrom" @change="loadModalCombined()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                <label class="text-xs font-semibold text-gray-500">To</label>
                                <input type="date" x-model="modalDateTo" @change="loadModalCombined()" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Submitted</p>
                                <p class="text-xl font-bold text-blue-700 mt-1" x-text="modalAgent.totalSubmitted || 0"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Issued</p>
                                <p class="text-xl font-bold text-green-700 mt-1" x-text="modalAgent.totalIssued || 0"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Payable</p>
                                <p class="text-xl font-bold text-gray-800 mt-1" x-text="$currency(modalAgent.payable || 0, 2)"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Paid</p>
                                <p class="text-xl font-bold text-green-700 mt-1" x-text="$currency(modalAgent.paid || 0, 2)"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Balance</p>
                                <p class="text-xl font-bold mt-1" x-text="$currency(Math.abs(modalAgent.balance || 0), 2)"
                                   :class="(modalAgent.balance || 0) > 0 ? 'text-green-700' : ((modalAgent.balance || 0) < 0 ? 'text-red-600' : 'text-gray-600')"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Cancellation Fee</p>
                                <p class="text-xl font-bold text-red-700 mt-1" x-text="modalAgent.cancellationFee > 0 ? $currency(modalAgent.cancellationFee, 2) : '-'"></p>
                            </div>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Estimated Cost</p>
                                <p class="text-xl font-bold text-gray-800 mt-1" x-text="$currency(modalAgent.estimatedCost || 0, 2)"></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-b border-gray-200 mt-2">
                        <h3 class="px-4 py-2.5 font-bold text-sm text-gray-700 uppercase tracking-wide">Combined Report</h3>
                    </div>

                    <div class="mt-4">
                        <div class="border-2 border-gray-200 rounded-lg overflow-hidden">
                            <table class="w-full">
                                <thead>
                                    <tr class="table-header">
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Date</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Invoice ID</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Passenger Name</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Passport No</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Status</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Estimated Cost</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Balance</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right">Cancellation Fee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, idx) in modalAgent.combined" :key="idx">
                                        <tr class="border-b border-gray-100">
                                            <td class="px-3 py-3 text-sm text-left" x-text="row.date"></td>
                                            <td class="px-3 py-3 text-sm text-left font-medium" x-text="row.invoice_id || '-'"></td>
                                            <td class="px-3 py-3 text-sm text-left" x-text="row.passenger_name || '-'"></td>
                                            <td class="px-3 py-3 text-sm text-left" x-text="row.passport_no || '-'"></td>
                                            <td class="px-3 py-3 text-sm text-center">
                                                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold"
                                                    :class="{
                                                        'bg-blue-100 text-blue-800': row.status === 'Submitted',
                                                        'bg-green-100 text-green-800': row.status === 'Issued',
                                                        'bg-red-100 text-red-800': row.status === 'Cancelled',
                                                        'bg-gray-100 text-gray-600': row.status === 'Payment'
                                                    }"
                                                    x-text="row.status"></span>
                                            </td>
                                            <td class="px-3 py-3 text-sm text-right font-medium" x-text="row.estimated_cost > 0 ? $currency(row.estimated_cost, 2) : '-'"></td>
                                            <td class="px-3 py-3 text-sm text-right font-medium" x-text="row.payable > 0 ? $currency(row.payable, 2) : '-'"></td>
                                            <td class="px-3 py-3 text-sm text-right" :class="row.paid > 0 ? 'text-green-700' : 'text-gray-600'" x-text="row.paid > 0 ? $currency(row.paid, 2) : '-'"></td>
                                            <td class="px-3 py-3 text-sm text-right font-semibold"
                                                :class="row.balance > 0 ? 'text-green-700' : (row.balance < 0 ? 'text-red-600' : 'text-gray-600')"
                                                x-text="$currency(Math.abs(row.balance), 2)"></td>
                                            <td class="px-3 py-3 text-sm text-right" :class="row.cancellation_fee > 0 ? 'text-red-600' : 'text-gray-600'" x-text="row.cancellation_fee > 0 ? $currency(row.cancellation_fee, 2) : '-'"></td>
                                        </tr>
                                    </template>
                                    <template x-if="modalAgent.loading">
                                        <tr>
                                            <td colspan="10" class="px-4 py-4 text-center text-gray-500">Loading...</td>
                                        </tr>
                                    </template>
                                    <template x-if="!modalAgent.loading && (!modalAgent.combined || modalAgent.combined.length === 0)">
                                        <tr>
                                            <td colspan="10" class="px-4 py-4 text-center text-gray-500">No data found</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-between no-print">
                    <button @click="window.open(`/reports/visa-agent/${encodeURIComponent(modalAgent.id)}/print`, '_blank')" class="filter-btn px-6 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </button>
                    <button @click="closeModal()" class="filter-btn px-6 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="paymentModal" x-show="paymentModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="modal-overlay absolute inset-0 bg-black/50" @click="closePaymentModal()"></div>
        <div class="modal-content relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Payment Form</h3>
            <form @submit.prevent="handlePaymentSubmit">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Name</label>
                        <input type="text" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600" x-model="paymentAgentName">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Pay To *</label>
                        <select x-model="paymentPayTo" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="Visa Agent">Visa Agent</option>
                            <option value="Commission Agent">Commission Agent</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method *</label>
                        <select x-model="paymentMethod" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="Bank">Bank</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount (SAR) *</label>
                        <input type="number" x-model="paymentAmount" required min="0" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">Save</button>
                    <button type="button" @click="closePaymentModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function visaAgentReport(options = {}) {
    return {
        search: options.search || '',
        date_from: options.date_from || '',
        date_to: options.date_to || '',
        visa_agent_id: options.visa_agent_id || '',
        filteredData: [],
        loading: true,
        summary: {
            totalAgents: 0,
            agentsWithDue: 0,
            totalPayable: 0,
            totalPaid: 0,
            totalBalance: 0,
            totalBalanceLabel: '0 SAR',
        },
        detailModalOpen: false,
        paymentModalOpen: false,
        modalAgent: {},
        paymentAgentName: '',
        paymentPayTo: '',
        paymentMethod: '',
        paymentAmount: '',
        editingAgentId: null,
        modalDateFrom: '',
        modalDateTo: '',

        init() {
            this.loadData();
            window.addEventListener('afterprint', () => {
                document.title = 'Visa Agent Report';
                document.body.classList.remove('printing-modal');
            });
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.search) params.set('search', this.search);
                if (this.date_from) params.set('date_from', this.date_from);
                if (this.date_to) params.set('date_to', this.date_to);
                if (this.visa_agent_id) params.set('visa_agent_id', this.visa_agent_id);
                const response = await fetch(`/api/reports/visa-agent?${params}`);
                const result = await response.json();
                this.filteredData = result.data || [];
                if (result.summary) {
                    this.summary = result.summary;
                }
            } catch (error) {
                console.error('Failed to load visa agent report:', error);
                this.filteredData = [];
            } finally {
                this.loading = false;
            }
        },

        filterAgents() {
            this.loadData();
        },

        async openModal(agentId) {
            const agent = this.filteredData.find(a => a.id === agentId);
            if (!agent) return;
            this.modalDateFrom = '';
            this.modalDateTo = '';
            this.modalAgent = {
                ...agent,
                combined: [],
                loading: true,
            };
            this.detailModalOpen = true;
            document.body.style.overflow = 'hidden';

            await this.loadModalCombined();
        },

        async loadModalCombined() {
            const agentId = this.modalAgent.id;
            if (!agentId) return;
            this.modalAgent.combined = [];
            this.modalAgent.loading = true;

            try {
                const params = new URLSearchParams();
                if (this.modalDateFrom) params.set('date_from', this.modalDateFrom);
                if (this.modalDateTo) params.set('date_to', this.modalDateTo);
                const qs = params.toString() ? `?${params}` : '';
                const res = await fetch(`/api/reports/visa-agent/${agentId}/combined${qs}`);
                const result = await res.json();
                if (result.agent) {
                    const stats = result.stats || {};
                    this.modalAgent = {
                        ...this.modalAgent,
                        ...result.agent,
                        totalSubmitted: stats.totalSubmitted ?? 0,
                        totalIssued: stats.totalIssued ?? 0,
                        payable: stats.payable ?? 0,
                        paid: stats.paid ?? 0,
                        balance: stats.balance ?? 0,
                        cancellationFee: stats.cancellationFee ?? 0,
                        estimatedCost: stats.estimatedCost ?? 0,
                        loading: false,
                        combined: result.data || [],
                    };
                }
            } catch (error) {
                console.error('Failed to load agent details:', error);
                this.modalAgent.loading = false;
            }
        },

        printReportViaPrint() {
            document.title = '';
            window.print();
        },

        printModalViaPrint() {
            document.title = this.modalAgent.name || 'Agent Details';
            document.body.classList.add('printing-modal');
            window.print();
        },

        closeModal() {
            this.detailModalOpen = false;
            this.modalDateFrom = '';
            this.modalDateTo = '';
            document.body.style.overflow = 'auto';
        },

        openPaymentModal(agentId) {
            const agent = this.filteredData.find(a => a.id === agentId);
            if (!agent) return;
            this.editingAgentId = agent.id;
            this.paymentAgentName = agent.name;
            this.paymentPayTo = '';
            this.paymentMethod = '';
            this.paymentAmount = '';
            this.paymentModalOpen = true;
        },

        closePaymentModal() {
            this.editingAgentId = null;
            this.paymentModalOpen = false;
        },

        handlePaymentSubmit() {
            const amount = parseFloat(this.paymentAmount) || 0;
            this.closePaymentModal();
            if (window.showToast) {
                window.showToast('Payment saved successfully', 'success');
            }
        },
    };
}
</script>
@endpush
