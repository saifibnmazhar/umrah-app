@extends('layouts.app')
@section('title', 'Ticket Agent Report')
@section('content')
<div class="max-w-[1600px] mx-auto p-4" x-data="ticketAgentReport()">
    <div class="sticky top-0 z-30 bg-white py-2 mb-3">
        <span class="text-sm text-gray-500 font-medium">Report</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Ticket Agent Report</span>
    </div>

    <div class="sticky top-[40px] z-20 bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            {{-- Search box commented out per requirement
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">SEARCH BOX</label>
                <input type="text" x-model="search" @keypress.enter="filterAgents()" placeholder="Search by Agent Name"
                       class="search-input w-80 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            --}}

            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">Date</label>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="date_from" @change="loadData()" class="search-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="date_to" @change="loadData()" class="search-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">Ticket Agent</label>
                <select x-model="agent_id" @change="loadData()" class="w-56 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Agents</option>
                    @foreach($agents as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <button class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                    </svg>
                    PDF
                </button>
                <button class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4 text-green-700" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                    </svg>
                    Excel
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin flex flex-col" style="max-height: calc(100vh - 280px);">
        <div class="overflow-auto flex-1 min-h-0">
            <table class="w-full min-w-[1200px] table-fixed">
                <thead class="sticky top-0 z-10">
                    <tr class="table-header">
                        <th class="w-56 px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Agent Name</th>
                        <th class="w-32 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                        <th class="w-32 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                        <th class="w-32 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Balance</th>
                        {{-- <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Refunded Tickets</th>
                        <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Re-issue Tickets</th>
                        <th class="w-36 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Total Refund Amount</th>
                        <th class="w-36 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Total Re-issue Cost</th> --}}
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-sm text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && filteredAgents.length === 0">
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-sm text-center text-gray-500">No agents found matching your criteria.</td>
                        </tr>
                    </template>
                    <template x-for="agent in paginatedAgents" :key="agent.id">
                        <tr class="table-row-agent">
                            <td class="px-4 py-3 text-sm text-left border-r border-gray-200 font-medium text-gray-800" x-text="agent.name"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-medium" x-text="$currency(agent.payable, 2)"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200"
                                :class="agent.paid > 0 ? 'text-green-700' : 'text-gray-600'"
                                x-text="$currency(agent.paid, 2)"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-semibold"
                                :class="agent.due > 0 ? 'text-green-700' : (agent.due < 0 ? 'text-red-600' : 'text-gray-600')"
                                x-text="(agent.due > 0 ? '+' : '') + $currency(agent.due, 2)"></td>
                            {{-- <td class="px-4 py-3 text-sm text-center border-r border-gray-200 font-medium" x-text="agent.refundedTickets"></td>
                            <td class="px-4 py-3 text-sm text-center border-r border-gray-200 font-medium" x-text="agent.reissueTickets"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-medium text-amber-700" x-text="$currency(agent.totalRefundAmount, 2)"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-medium text-blue-700" x-text="$currency(agent.totalReissueCost, 2)"></td> --}}
                            <td class="px-4 py-3 text-center">
                                <button @click="openModal(agent)" class="view-btn text-white px-4 py-1.5 rounded text-xs font-medium transition-all">
                                    View
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <nav x-show="agentTotalPages > 1" class="flex justify-end" aria-label="Pagination Navigation">
        <span class="inline-flex items-center gap-2">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage <= 1"
                    :class="currentPage <= 1 ? 'px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5' : 'px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100'">
                Prev
            </button>
            <span class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md leading-5">
                <span x-text="currentPage"></span>/<span x-text="agentTotalPages"></span>
            </span>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage >= agentTotalPages"
                    :class="currentPage >= agentTotalPages ? 'px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5' : 'px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100'">
                Next
            </button>
        </span>
    </nav>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex flex-wrap gap-6">
            <div class="footer-box rounded-lg overflow-hidden min-w-[240px]">
                <div class="footer-box-header px-4 py-2">
                    <span class="text-sm font-bold text-gray-700">Agent Stats</span>
                </div>
                <div class="p-4">
                    <div class="flex flex-col gap-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Agents:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="summary.totalAgents"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Agents with Due:</span>
                            <span class="text-xs font-bold text-red-700" x-text="summary.agentsWithDue"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-box rounded-lg overflow-hidden min-w-[240px]">
                <div class="footer-box-header px-4 py-2">
                    <span class="text-sm font-bold text-gray-700">Payment Summary</span>
                </div>
                <div class="p-4">
                    <div class="flex flex-col gap-2">
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
                            <span class="text-xs font-bold" :class="summary.totalDue > 0 ? 'text-green-700' : (summary.totalDue < 0 ? 'text-red-700' : 'text-gray-700')" x-text="(summary.totalDue > 0 ? '+' : '') + $currency(summary.totalDue, 2)"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{--
            <div class="footer-box rounded-lg overflow-hidden min-w-[260px]">
                <div class="footer-box-header px-4 py-2">
                    <span class="text-sm font-bold text-gray-700">Ticket Summary</span>
                </div>
                <div class="p-4">
                    <div class="flex flex-col gap-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Refunded:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="summary.totalRefundedTickets"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Re-issue:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="summary.totalReissueTickets"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Refund Amount:</span>
                            <span class="text-xs font-bold text-amber-700" x-text="$currency(summary.totalRefundAmount, 2)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Re-issue Cost:</span>
                            <span class="text-xs font-bold text-blue-700" x-text="$currency(summary.totalReissueCost, 2)"></span>
                        </div>
                    </div>
                </div>
            </div>
            --}}

            <div class="flex flex-col gap-4">
                <div class="footer-box rounded-lg overflow-hidden">
                    <div class="footer-box-header px-4 py-2">
                        <span class="text-sm font-bold text-gray-700">Export Options</span>
                    </div>
                    <div class="p-4 flex gap-3">
                        <button class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                            </svg>
                            PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center">
            <span class="text-xs text-gray-400">Last Updated: <span x-text="lastUpdated"></span></span>
            <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
        </div>
    </div>

    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.5);" @click="closeModal()"></div>
        <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
            <div class="modal-content relative bg-white rounded-lg shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden">
                <div class="bg-slate-700 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-white">Agent Details</h2>
                    <button @click="closeModal()" class="text-white hover:text-gray-300 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] scrollbar-thin">
                    <template x-if="selectedAgent">
                        <div>
                            <div class="mb-6">
                                <h3 class="text-2xl font-bold text-gray-800 mb-4" x-text="selectedAgent.name"></h3>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Payable</p>
                                        <p class="text-xl font-bold text-gray-800 mt-1" x-text="$currency(selectedAgent.payable, 2)"></p>
                                    </div>
                                    <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Paid</p>
                                        <p class="text-xl font-bold text-green-700 mt-1" x-text="$currency(selectedAgent.paid, 2)"></p>
                                    </div>
                                    <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Balance</p>
                                        <p class="text-xl font-bold mt-1"
                                           :class="selectedAgent.due > 0 ? 'text-green-700' : (selectedAgent.due < 0 ? 'text-red-600' : 'text-gray-600')"
                                           x-text="(selectedAgent.due > 0 ? '+' : '') + $currency(selectedAgent.due, 2)"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="flex border-b border-gray-200">
                                    <button @click="switchTab('payment')"
                                            :class="activeTab === 'payment' ? 'text-white bg-slate-700 border-slate-700' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-50 border-transparent'"
                                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">Payment</button>
                                    {{-- <button @click="switchTab('reissue')"
                                            :class="activeTab === 'reissue' ? 'text-white bg-slate-700 border-slate-700' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-50 border-transparent'"
                                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">Re-issue</button>
                                    <button @click="switchTab('refund')"
                                            :class="activeTab === 'refund' ? 'text-white bg-slate-700 border-slate-700' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-50 border-transparent'"
                                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">Refund</button> --}}
                                </div>
                            </div>

                            <div x-show="activeTab === 'payment'">
                                <div class="border-2 border-gray-200 rounded-lg overflow-hidden">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="table-header">
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Date</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="tx in selectedAgent.transactions" :key="tx.date">
                                                <tr class="modal-row border-b border-gray-100">
                                                    <td class="px-4 py-3 text-sm text-left" x-text="tx.date"></td>
                                                    <td class="px-4 py-3 text-sm text-right font-medium" x-text="$currency(tx.payable, 2)"></td>
                                                    <td class="px-4 py-3 text-sm text-right"
                                                       :class="tx.paid > 0 ? 'text-green-700' : 'text-gray-600'"
                                                       x-text="$currency(tx.paid, 2)"></td>
                                                    <td class="px-4 py-3 text-sm text-right font-semibold"
                                                       :class="(tx.paid - tx.payable) > 0 ? 'text-green-700' : (tx.paid - tx.payable) < 0 ? 'text-red-600' : 'text-gray-600'"
                                                       x-text="((tx.paid - tx.payable) > 0 ? '+' : '') + $currency(tx.paid - tx.payable, 2)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div x-show="activeTab === 'reissue'" x-cloak>
                                <div class="border-2 border-gray-200 rounded-lg overflow-hidden">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="table-header">
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Date</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Re-issued Ticket</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right">Total Re-issue Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-if="selectedAgent.reissueTransactions.length === 0">
                                                <tr>
                                                    <td colspan="3" class="px-4 py-8 text-sm text-center text-gray-500">No re-issue transactions found</td>
                                                </tr>
                                            </template>
                                            <template x-for="tx in selectedAgent.reissueTransactions" :key="tx.date">
                                                <tr class="modal-row border-b border-gray-100">
                                                    <td class="px-4 py-3 text-sm text-left" x-text="tx.date"></td>
                                                    <td class="px-4 py-3 text-sm text-center font-medium" x-text="tx.reissuedTicket"></td>
                                                    <td class="px-4 py-3 text-sm text-right font-medium text-blue-700" x-text="$currency(tx.totalReissueCost, 2)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div x-show="activeTab === 'refund'" x-cloak>
                                <div class="border-2 border-gray-200 rounded-lg overflow-hidden">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="table-header">
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Date</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Refunded Ticket</th>
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right">Total Refund Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-if="selectedAgent.refundTransactions.length === 0">
                                                <tr>
                                                    <td colspan="3" class="px-4 py-8 text-sm text-center text-gray-500">No refund transactions found</td>
                                                </tr>
                                            </template>
                                            <template x-for="tx in selectedAgent.refundTransactions" :key="tx.date">
                                                <tr class="modal-row border-b border-gray-100">
                                                    <td class="px-4 py-3 text-sm text-left" x-text="tx.date"></td>
                                                    <td class="px-4 py-3 text-sm text-center font-medium" x-text="tx.refundedTicket"></td>
                                                    <td class="px-4 py-3 text-sm text-right font-medium text-amber-700" x-text="$currency(tx.totalRefundAmount, 2)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end">
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
</div>
@endsection

@push('scripts')
<script>
function ticketAgentReport() {
    return {
        date_from: '',
        date_to: '',
        agent_id: '',
        showModal: false,
        activeTab: 'payment',
        selectedAgent: null,
        lastUpdated: '',
        agents: [],
        filteredAgents: [],
        loading: false,
        currentPage: 1,
        perPage: 25,

        get summary() {
            const a = this.filteredAgents;
            return {
                totalAgents: a.length,
                agentsWithDue: a.filter(x => x.due < 0).length,
                totalPayable: a.reduce((s, x) => s + x.payable, 0),
                totalPaid: a.reduce((s, x) => s + x.paid, 0),
                totalDue: a.reduce((s, x) => s + x.due, 0),
                totalRefundedTickets: a.reduce((s, x) => s + x.refundedTickets, 0),
                totalReissueTickets: a.reduce((s, x) => s + x.reissueTickets, 0),
                totalRefundAmount: a.reduce((s, x) => s + x.totalRefundAmount, 0),
                totalReissueCost: a.reduce((s, x) => s + x.totalReissueCost, 0),
            };
        },

        get agentTotalPages() {
            return Math.max(1, Math.ceil(this.filteredAgents.length / this.perPage));
        },

        get paginatedAgents() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredAgents.slice(start, start + this.perPage);
        },

        init() {
            this.loadData();
            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            this.lastUpdated = `${pad(now.getDate())}-${months[now.getMonth()]}-${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())} ${now.getHours() >= 12 ? 'PM' : 'AM'}`;
        },

        async loadData() {
            this.loading = true;
            this.currentPage = 1;
            const params = new URLSearchParams();
            if (this.date_from) params.set('date_from', this.date_from);
            if (this.date_to) params.set('date_to', this.date_to);
            if (this.agent_id) params.set('agent_id', this.agent_id);

            try {
                const response = await fetch(`/api/reports/ticket-agent?${params}`);
                const result = await response.json();
                this.agents = result.data || [];
                this.filteredAgents = result.data || [];
            } catch (error) {
                console.error('Failed to load ticket agent report:', error);
                this.agents = [];
                this.filteredAgents = [];
            } finally {
                this.loading = false;
            }
        },

        openModal(agent) {
            this.selectedAgent = agent;
            this.activeTab = 'payment';
            this.showModal = true;
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            this.showModal = false;
            this.selectedAgent = null;
            document.body.style.overflow = 'auto';
        },

        goToPage(page) {
            if (page < 1 || page > this.agentTotalPages) return;
            this.currentPage = page;
        },

        switchTab(tab) {
            this.activeTab = tab;
        }
    };
}
</script>
@endpush
