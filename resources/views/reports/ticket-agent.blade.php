@extends('layouts.app')
@section('title', 'Ticket Agent Report')
@section('content')
<div class="max-w-[1600px] mx-auto p-4" x-data="ticketAgentReport()">
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Report</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Ticket Agent Report</span>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">SEARCH BOX</label>
                <input type="text" x-model="search" @keypress.enter="filterAgents()" placeholder="Search by Agent Name"
                       class="search-input w-80 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">Date</label>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="date_from" class="search-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="date_to" class="search-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="flex items-center gap-2">
                <button @click="filterAgents()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filter
                </button>
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

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px] table-fixed">
                <thead>
                    <tr class="table-header">
                        <th class="w-56 px-4 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Agent Name</th>
                        <th class="w-32 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Payable</th>
                        <th class="w-32 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Paid</th>
                        <th class="w-32 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Due</th>
                        <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Refunded Tickets</th>
                        <th class="w-28 px-4 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Re-issue Tickets</th>
                        <th class="w-36 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Total Refund Amount</th>
                        <th class="w-36 px-4 py-3 text-xs font-bold text-gray-700 text-right border-r border-gray-300">Total Re-issue Cost</th>
                        <th class="w-24 px-4 py-3 text-xs font-bold text-gray-700 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filteredAgents.length === 0">
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-sm text-center text-gray-500">No agents found matching your criteria.</td>
                        </tr>
                    </template>
                    <template x-for="agent in filteredAgents" :key="agent.id">
                        <tr class="table-row-agent">
                            <td class="px-4 py-3 text-sm text-left border-r border-gray-200 font-medium text-gray-800" x-text="agent.name"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-medium" x-text="formatCurrency(agent.payable)"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200"
                                :class="agent.paid > 0 ? 'text-green-700' : 'text-gray-600'"
                                x-text="formatCurrency(agent.paid)"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-semibold"
                                :class="agent.due > 0 ? 'text-red-600' : 'text-gray-600'"
                                x-text="formatCurrency(agent.due)"></td>
                            <td class="px-4 py-3 text-sm text-center border-r border-gray-200 font-medium" x-text="agent.refundedTickets"></td>
                            <td class="px-4 py-3 text-sm text-center border-r border-gray-200 font-medium" x-text="agent.reissueTickets"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-medium text-amber-700" x-text="formatCurrency(agent.totalRefundAmount)"></td>
                            <td class="px-4 py-3 text-sm text-right border-r border-gray-200 font-medium text-blue-700" x-text="formatCurrency(agent.totalReissueCost)"></td>
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
                            <span class="text-xs font-bold text-gray-800" x-text="formatCurrency(summary.totalPayable)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Paid:</span>
                            <span class="text-xs font-bold text-green-700" x-text="formatCurrency(summary.totalPaid)"></span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 mt-1">
                            <span class="text-xs font-bold text-gray-700">Total Due:</span>
                            <span class="text-xs font-bold text-red-700" x-text="formatCurrency(summary.totalDue)"></span>
                        </div>
                    </div>
                </div>
            </div>

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
                            <span class="text-xs font-bold text-amber-700" x-text="formatCurrency(summary.totalRefundAmount)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Re-issue Cost:</span>
                            <span class="text-xs font-bold text-blue-700" x-text="formatCurrency(summary.totalReissueCost)"></span>
                        </div>
                    </div>
                </div>
            </div>

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

    <div x-show="showModal" x-cloak class="fixed inset-0 z-50">
        <div class="modal-overlay fixed inset-0 bg-black bg-opacity-50" @click="closeModal()"></div>
        <div class="flex items-center justify-center min-h-screen px-4">
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
                                        <p class="text-xl font-bold text-gray-800 mt-1" x-text="formatCurrency(selectedAgent.payable)"></p>
                                    </div>
                                    <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Paid</p>
                                        <p class="text-xl font-bold text-green-700 mt-1" x-text="formatCurrency(selectedAgent.paid)"></p>
                                    </div>
                                    <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Due</p>
                                        <p class="text-xl font-bold mt-1"
                                           :class="selectedAgent.due > 0 ? 'text-red-600' : 'text-gray-600'"
                                           x-text="formatCurrency(selectedAgent.due)"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="flex border-b border-gray-200">
                                    <button @click="switchTab('payment')"
                                            :class="activeTab === 'payment' ? 'text-white bg-slate-700 border-slate-700' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-50 border-transparent'"
                                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">Payment</button>
                                    <button @click="switchTab('reissue')"
                                            :class="activeTab === 'reissue' ? 'text-white bg-slate-700 border-slate-700' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-50 border-transparent'"
                                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">Re-issue</button>
                                    <button @click="switchTab('refund')"
                                            :class="activeTab === 'refund' ? 'text-white bg-slate-700 border-slate-700' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-50 border-transparent'"
                                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">Refund</button>
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
                                                <th class="px-4 py-3 text-xs font-bold text-gray-700 text-right">Due</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="tx in selectedAgent.transactions" :key="tx.date">
                                                <tr class="modal-row border-b border-gray-100">
                                                    <td class="px-4 py-3 text-sm text-left" x-text="tx.date"></td>
                                                    <td class="px-4 py-3 text-sm text-right font-medium" x-text="formatCurrency(tx.payable)"></td>
                                                    <td class="px-4 py-3 text-sm text-right"
                                                       :class="tx.paid > 0 ? 'text-green-700' : 'text-gray-600'"
                                                       x-text="formatCurrency(tx.paid)"></td>
                                                    <td class="px-4 py-3 text-sm text-right font-semibold"
                                                       :class="(tx.payable - tx.paid) > 0 ? 'text-red-600' : 'text-gray-600'"
                                                       x-text="formatCurrency(tx.payable - tx.paid)"></td>
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
                                                    <td class="px-4 py-3 text-sm text-right font-medium text-blue-700" x-text="formatCurrency(tx.totalReissueCost)"></td>
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
                                                    <td class="px-4 py-3 text-sm text-right font-medium text-amber-700" x-text="formatCurrency(tx.totalRefundAmount)"></td>
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
        search: '',
        date_from: '',
        date_to: '',
        showModal: false,
        activeTab: 'payment',
        selectedAgent: null,
        lastUpdated: '',

        agents: [
            {
                id: 1,
                name: 'Al Rajhi Travel',
                payable: 85000,
                paid: 85000,
                due: 0,
                refundedTickets: 2,
                reissueTickets: 1,
                totalRefundAmount: 4500,
                totalReissueCost: 1200,
                transactions: [
                    { date: '01-Mar-2026', payable: 15000, paid: 15000 },
                    { date: '05-Mar-2026', payable: 22000, paid: 22000 },
                    { date: '12-Mar-2026', payable: 18000, paid: 18000 },
                    { date: '18-Mar-2026', payable: 15000, paid: 15000 },
                    { date: '25-Mar-2026', payable: 15000, paid: 15000 }
                ],
                reissueTransactions: [
                    { date: '15-Mar-2026', reissuedTicket: 1, totalReissueCost: 1200 }
                ],
                refundTransactions: [
                    { date: '08-Mar-2026', refundedTicket: 1, totalRefundAmount: 2500 },
                    { date: '22-Mar-2026', refundedTicket: 1, totalRefundAmount: 2000 }
                ]
            },
            {
                id: 2,
                name: 'FlyBurj Agent',
                payable: 62500,
                paid: 52000,
                due: 10500,
                refundedTickets: 3,
                reissueTickets: 2,
                totalRefundAmount: 8200,
                totalReissueCost: 3500,
                transactions: [
                    { date: '02-Mar-2026', payable: 12500, paid: 12500 },
                    { date: '08-Mar-2026', payable: 15000, paid: 15000 },
                    { date: '15-Mar-2026', payable: 17500, paid: 12500 },
                    { date: '22-Mar-2026', payable: 17500, paid: 12000 }
                ],
                reissueTransactions: [
                    { date: '10-Mar-2026', reissuedTicket: 1, totalReissueCost: 1800 },
                    { date: '25-Mar-2026', reissuedTicket: 1, totalReissueCost: 1700 }
                ],
                refundTransactions: [
                    { date: '05-Mar-2026', refundedTicket: 1, totalRefundAmount: 3200 },
                    { date: '18-Mar-2026', refundedTicket: 2, totalRefundAmount: 5000 }
                ]
            },
            {
                id: 3,
                name: 'Shahadat Visa Services',
                payable: 48000,
                paid: 48000,
                due: 0,
                refundedTickets: 1,
                reissueTickets: 0,
                totalRefundAmount: 2500,
                totalReissueCost: 0,
                transactions: [
                    { date: '03-Mar-2026', payable: 12000, paid: 12000 },
                    { date: '10-Mar-2026', payable: 14500, paid: 14500 },
                    { date: '17-Mar-2026', payable: 11500, paid: 11500 },
                    { date: '24-Mar-2026', payable: 10000, paid: 10000 }
                ],
                reissueTransactions: [],
                refundTransactions: [
                    { date: '12-Mar-2026', refundedTicket: 1, totalRefundAmount: 2500 }
                ]
            },
            {
                id: 4,
                name: 'Gulf Visa',
                payable: 97500,
                paid: 78000,
                due: 19500,
                refundedTickets: 4,
                reissueTickets: 3,
                totalRefundAmount: 12000,
                totalReissueCost: 6500,
                transactions: [
                    { date: '01-Mar-2026', payable: 25000, paid: 25000 },
                    { date: '06-Mar-2026', payable: 22000, paid: 22000 },
                    { date: '12-Mar-2026', payable: 21500, paid: 15000 },
                    { date: '20-Mar-2026', payable: 19000, paid: 11000 },
                    { date: '28-Mar-2026', payable: 10000, paid: 5000 }
                ],
                reissueTransactions: [
                    { date: '08-Mar-2026', reissuedTicket: 1, totalReissueCost: 2200 },
                    { date: '16-Mar-2026', reissuedTicket: 1, totalReissueCost: 2100 },
                    { date: '24-Mar-2026', reissuedTicket: 1, totalReissueCost: 2200 }
                ],
                refundTransactions: [
                    { date: '04-Mar-2026', refundedTicket: 1, totalRefundAmount: 3000 },
                    { date: '14-Mar-2026', refundedTicket: 2, totalRefundAmount: 6000 },
                    { date: '26-Mar-2026', refundedTicket: 1, totalRefundAmount: 3000 }
                ]
            },
            {
                id: 5,
                name: 'Dreamland Tours',
                payable: 36000,
                paid: 36000,
                due: 0,
                refundedTickets: 0,
                reissueTickets: 1,
                totalRefundAmount: 0,
                totalReissueCost: 1500,
                transactions: [
                    { date: '04-Mar-2026', payable: 12000, paid: 12000 },
                    { date: '11-Mar-2026', payable: 11000, paid: 11000 },
                    { date: '21-Mar-2026', payable: 13000, paid: 13000 }
                ],
                reissueTransactions: [
                    { date: '18-Mar-2026', reissuedTicket: 1, totalReissueCost: 1500 }
                ],
                refundTransactions: []
            },
            {
                id: 6,
                name: 'Saudi Umrah Express',
                payable: 71200,
                paid: 58000,
                due: 13200,
                refundedTickets: 2,
                reissueTickets: 2,
                totalRefundAmount: 5800,
                totalReissueCost: 4200,
                transactions: [
                    { date: '02-Mar-2026', payable: 17800, paid: 17800 },
                    { date: '09-Mar-2026', payable: 19200, paid: 15000 },
                    { date: '16-Mar-2026', payable: 17200, paid: 13000 },
                    { date: '25-Mar-2026', payable: 17000, paid: 12200 }
                ],
                reissueTransactions: [
                    { date: '06-Mar-2026', reissuedTicket: 1, totalReissueCost: 2200 },
                    { date: '20-Mar-2026', reissuedTicket: 1, totalReissueCost: 2000 }
                ],
                refundTransactions: [
                    { date: '12-Mar-2026', refundedTicket: 1, totalRefundAmount: 2800 },
                    { date: '28-Mar-2026', refundedTicket: 1, totalRefundAmount: 3000 }
                ]
            }
        ],

        filteredAgents: [],

        get summary() {
            const a = this.filteredAgents;
            return {
                totalAgents: a.length,
                agentsWithDue: a.filter(x => x.due > 0).length,
                totalPayable: a.reduce((s, x) => s + x.payable, 0),
                totalPaid: a.reduce((s, x) => s + x.paid, 0),
                totalDue: a.reduce((s, x) => s + x.due, 0),
                totalRefundedTickets: a.reduce((s, x) => s + x.refundedTickets, 0),
                totalReissueTickets: a.reduce((s, x) => s + x.reissueTickets, 0),
                totalRefundAmount: a.reduce((s, x) => s + x.totalRefundAmount, 0),
                totalReissueCost: a.reduce((s, x) => s + x.totalReissueCost, 0),
            };
        },

        init() {
            this.filteredAgents = [...this.agents];
            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            this.lastUpdated = `${pad(now.getDate())}-${months[now.getMonth()]}-${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())} ${now.getHours() >= 12 ? 'PM' : 'AM'}`;
        },

        formatCurrency(amount) {
            return Number(amount).toLocaleString('en-US') + ' SAR';
        },

        parseDate(dateStr) {
            const parts = dateStr.match(/(\d{2})-(\w{3})-(\d{4})/);
            if (!parts) return null;
            const months = { Jan: 0, Feb: 1, Mar: 2, Apr: 3, May: 4, Jun: 5, Jul: 6, Aug: 7, Sep: 8, Oct: 9, Nov: 10, Dec: 11 };
            return new Date(parts[3], months[parts[2]], parseInt(parts[1]));
        },

        filterAgents() {
            const searchTerm = this.search.toLowerCase().trim();
            const dateFrom = this.date_from ? new Date(this.date_from) : null;
            const dateTo = this.date_to ? new Date(this.date_to) : null;

            this.filteredAgents = this.agents.filter(agent => {
                if (searchTerm && !agent.name.toLowerCase().includes(searchTerm)) {
                    return false;
                }
                if (dateFrom || dateTo) {
                    return agent.transactions.some(tx => {
                        const txDate = this.parseDate(tx.date);
                        if (!txDate) return true;
                        if (dateFrom && txDate < dateFrom) return false;
                        if (dateTo && txDate > new Date(dateTo.getTime() + 86400000)) return false;
                        return true;
                    });
                }
                return true;
            });
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

        switchTab(tab) {
            this.activeTab = tab;
        }
    };
}
</script>
@endpush
