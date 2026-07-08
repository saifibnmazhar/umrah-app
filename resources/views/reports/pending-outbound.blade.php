@extends('layouts.app')
@section('title', 'Pending Outbound Ticket Report')
@section('content')
<style>
.search-input {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
    border: 1px solid #d4d4d4;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);
}
.filter-btn {
    background: linear-gradient(to bottom, #fff 0%, #e9ecef 100%);
    border: 1px solid #d4d4d4;
    box-shadow: 0 1px 0 rgba(255,255,255,0.5);
}
.filter-btn:hover {
    background: linear-gradient(to bottom, #f0f0f0 0%, #e2e6ea 100%);
}
.date-input {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
    border: 1px solid #d4d4d4;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);
}
.table-header {
    background: linear-gradient(to bottom, #f3f3f3 0%, #e8e8e8 100%);
    border: 1px solid #d4d4d4;
}
.table-row-ticket {
    background-color: #ffffff;
    border: 1px solid #d4d4d4;
}
.table-row-ticket:nth-child(even) {
    background-color: #fafafa;
}
.table-row-ticket:hover {
    background-color: #e8f4fc !important;
}
.footer-box {
    background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
    border: 2px solid #d4d4d4;
}
.footer-box-header {
    background: linear-gradient(to bottom, #f3f3f3 0%, #e8e8e8 100%);
    border-bottom: 1px solid #d4d4d4;
}
.export-btn {
    background: linear-gradient(to bottom, #fff 0%, #e9ecef 100%);
    border: 1px solid #d4d4d4;
    box-shadow: 0 1px 0 rgba(255,255,255,0.5);
}
.export-btn:hover {
    background: linear-gradient(to bottom, #f0f0f0 0%, #dee2e6 100%);
}
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0.5);
    cursor: pointer;
}
.scrollbar-thin::-webkit-scrollbar { height: 8px; width: 8px; }
.scrollbar-thin::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
.scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
}
.status-not-arrived {
    background-color: #f3f4f6; color: #374151; border: 1px solid #d1d5db;
}
.status-pending {
    background-color: #fef3c7; color: #92400e; border: 1px solid #f59e0b;
}
.status-issued {
    background-color: #dcfce7; color: #166534; border: 1px solid #22c55e;
}
.status-refunded {
    background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444;
}
select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 8px center;
    background-repeat: no-repeat;
    background-size: 16px 16px;
    padding-right: 32px;
}
.btn-action {
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-left: 4px;
}
.btn-issue {
    background: linear-gradient(to bottom, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: 1px solid #1d4ed8;
}
.btn-issue:hover {
    background: linear-gradient(to bottom, #2563eb 0%, #1d4ed8 100%);
}
.btn-edit {
    background: linear-gradient(to bottom, #8b5cf6 0%, #7c3aed 100%);
    color: white;
    border: 1px solid #6d28d9;
}
.btn-edit:hover {
    background: linear-gradient(to bottom, #7c3aed 0%, #6d28d9 100%);
}
.modal-overlay { transition: opacity 0.2s ease; }
.modal-content { transition: transform 0.2s ease, opacity 0.2s ease; }
</style>

<div class="max-w-[1600px] mx-auto p-4" x-data='pendingOutboundReport({
    ticketAgents: @json($ticketAgents),
    ticketFaresList: @json($ticketFaresList),
    filters: @json($filters)
})'>
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Report</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Pending Outbound Ticket</span>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">SEARCH BOX</label>
                <input type="text" x-model="filters.search" @keydown.enter="currentPage = 1; loadData()" placeholder="Search by Invoice No, Customer Name, PAX Name, Mobile, Passport"
                       class="search-input w-96 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Booking Date</span>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="filters.booking_date_from" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="filters.booking_date_to" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Expected Flight</span>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="filters.flight_date_from" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="filters.flight_date_to" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Pending Tkt Status</span>
                <select x-model="filters.status" class="search-input px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="issued">Issued</option>
                </select>
            </div>
            <div class="flex items-center gap-2 ml-auto">
                <button @click="currentPage = 1; loadData()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filter
                </button>
                <button @click="resetFilters()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1400px] table-fixed">
                <thead>
                    <tr class="table-header">
                        <th class="w-28 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Booking Date</th>
                        <th class="w-28 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Invoice</th>
                        <th class="w-40 px-2 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Customer Name</th>
                        <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Mobile</th>
                        <th class="w-40 px-2 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Passenger Name</th>
                        <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Passport</th>
                        <th class="w-36 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Pending Tkt Status</th>
                        <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Visa Expiry Date</th>
                        <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Expected Flight Date</th>
                        <th class="w-32 px-2 py-3 text-xs font-bold text-gray-700 text-center">Actual Flight Date</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500">No pending outbound ticket records found</td>
                        </tr>
                    </template>
                    <template x-for="(row, rowIndex) in data" :key="row.id || rowIndex">
                        <tr class="table-row-ticket">
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium" x-text="row.booking_date"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium" x-text="row.invoice"></td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200" x-text="row.customer_name"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200">
                                <div x-text="'C: ' + (row.customer_mobile || '-')" class="text-[11px]"></div>
                                <div x-text="'P: ' + (row.mobile || '-')" class="text-[11px] font-semibold text-gray-700"></div>
                            </td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200" x-text="row.passenger_name"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row.passport"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200">
                                <template x-if="row.status === 'pending'">
                                    <span>
                                        <span class="status-badge status-pending">Pending</span>
                                        <button @click="openIssueModal(row)" class="btn-action btn-issue">Issue</button>
                                    </span>
                                </template>
                                <template x-if="row.status === 'issued' || row.status === 're-issued'">
                                    <span>
                                        <span class="status-badge status-issued">Issued</span>
                                        <button @click="openEditModal(row)" class="btn-action btn-edit">Edit</button>
                                    </span>
                                </template>
                                <template x-if="row.status === 'refunded'">
                                    <span class="status-badge status-refunded">Refunded</span>
                                </template>
                            </td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row.visa_expiry_date"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row.expected_flight_date"></td>
                            <td class="px-2 py-2 text-xs text-center" x-text="row.actual_flight_date"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="lastPage > 1" class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-600">
                Showing <span class="font-medium" x-text="((currentPage - 1) * perPage + 1)"></span>
                to <span class="font-medium" x-text="Math.min(currentPage * perPage, totalRecords)"></span>
                of <span class="font-medium" x-text="totalRecords"></span> results
            </div>
            <nav class="flex items-center gap-1">
                <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors">Previous</button>
                <template x-for="page in paginationPages" :key="page">
                    <button @click="changePage(page)"
                            :class="page === currentPage ? 'bg-slate-700 text-white border-slate-700' : 'text-slate-600 hover:bg-slate-100 border-slate-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-lg border bg-white transition-colors" x-text="page"></button>
                </template>
                <button @click="changePage(currentPage + 1)" :disabled="currentPage === lastPage"
                        :class="currentPage === lastPage ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors">Next</button>
            </nav>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mt-0">
        <div class="flex flex-wrap gap-6">
            <div class="footer-box rounded-lg overflow-hidden min-w-[320px]">
                <div class="footer-box-header px-4 py-2"><span class="text-sm font-bold text-gray-700">Pending Outbound Ticket Summary</span></div>
                <div class="p-4">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-2">
                        <div class="flex justify-between"><span class="text-xs text-gray-600">Total Records:</span><span class="text-xs font-bold text-gray-800" x-text="summary.total_records"></span></div>
                        <div class="flex justify-between"><span class="text-xs text-gray-600">Total Invoices:</span><span class="text-xs font-bold text-gray-800" x-text="summary.total_invoices"></span></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 mt-1"><span class="text-xs text-gray-600">Pending:</span><span class="text-xs font-bold text-yellow-700" x-text="summary.pending"></span></div>
                        <div class="flex justify-between"><span class="text-xs text-gray-600">Issued:</span><span class="text-xs font-bold text-green-700" x-text="summary.issued"></span></div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-4">
                <div class="footer-box rounded-lg overflow-hidden">
                    <div class="footer-box-header px-4 py-2"><span class="text-sm font-bold text-gray-700">Export Options</span></div>
                    <div class="p-4 flex gap-3">
                        <button class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all">
                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
                            PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center">
            <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
        </div>
    </div>

    <div x-show="modalOpen" class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-8 overflow-y-auto">
        <div class="modal-backdrop fixed inset-0 bg-black/50" @click="closeModal()"></div>
        <div class="modal-content relative z-10 bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden">
            <div class="bg-slate-700 text-white px-6 py-4 flex justify-between items-center">
                <h2 class="text-lg font-bold" x-text="isEdit ? 'Edit Ticket' : 'Issue Ticket'"></h2>
                <button @click="closeModal()" class="text-white hover:text-gray-200 p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 max-h-[80vh] overflow-y-auto">
                <p class="text-sm text-slate-600 mb-4">Invoice: <span class="font-semibold text-slate-800" x-text="form.invoice || ''"></span></p>
                <form @submit.prevent="handleSubmit">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type</label>
                        <select x-model="form.ticket_type" @change="handleTicketTypeChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select</option>
                            <option value="regular">Regular</option>
                            <option value="offer">Offer</option>
                            <option value="group">Group</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ticket</label>
                        <select x-model="form.ticket_option" @change="handleTicketOptionChange()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Ticket</option>
                            <template x-for="opt in filteredTicketOptions" :key="opt.value">
                                <option :value="opt.value" x-text="opt.display"></option>
                            </template>
                        </select>
                    </div>
                    <div class="mb-4">
                        <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Ticket Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                                <select disabled class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-600">
                                    <option selected>One Way-Outbound</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                                <select x-model="form.flight_type" @change="handleTicketTypeChange()" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                                    <option value="">Select</option>
                                    <option value="Direct">Direct</option>
                                    <option value="Transit">Transit</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date *</label>
                                <input type="text" x-model="form.outbound_date" placeholder="DD-MMM-YY" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">PNR</label>
                                <input type="text" x-model="form.pnr" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none" placeholder="Enter PNR">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Number *</label>
                                <input type="text" x-model="form.ticket_number" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none" placeholder="Enter Ticket Number">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Issue Date *</label>
                                <input type="text" x-model="form.issued_date" placeholder="DD-MMM-YY" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Agent *</label>
                                <select x-model="form.ticket_agent_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                                    <option value="">Select Agent</option>
                                    <template x-for="agent in ticketAgents" :key="agent.id">
                                        <option :value="agent.id" x-text="agent.name"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Travel Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Route</label>
                                <input type="text" x-model="form.route_display" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
                                <input type="text" x-model="form.airline" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Class</label>
                                <input type="text" x-model="form.travel_class" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Passenger Type</label>
                                <input type="text" x-model="form.passenger_type" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Fare Calculation</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR)</label>
                                <input type="number" x-model="form.selling_fare" min="0" step="0.000001" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (SAR)</label>
                                <input type="number" x-model="form.net_fare" min="0" step="0.000001" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Baggage Info</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Outbound Baggage (KG)</label>
                                <input type="text" x-model="form.baggage_outbound" placeholder="e.g. 50" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Ticket Options</h4>
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" x-model="form.non_refundable" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                                <span class="text-sm text-slate-700">Non-Refundable</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" x-model="form.non_exchangeable" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                                <span class="text-sm text-slate-700">Non-Exchangeable</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium" x-text="isEdit ? 'Save Changes' : 'Issue Ticket'"></button>
                        <button type="button" @click="closeModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function pendingOutboundReport(options = {}) {
    return {
        data: [],
        loading: true,
        currentPage: 1,
        lastPage: 1,
        totalRecords: 0,
        perPage: 25,
        ticketAgents: options.ticketAgents || [],
        ticketFaresList: options.ticketFaresList || [],
        filters: options.filters || {
            search: '',
            booking_date_from: '',
            booking_date_to: '',
            flight_date_from: '',
            flight_date_to: '',
            status: 'all',
        },
        summary: {
            total_records: 0,
            total_invoices: 0,
            pending: 0,
            issued: 0,
        },
        modalOpen: false,
        isEdit: false,
        editingRow: null,
        form: {
            invoice: '',
            booking_id: null,
            passenger_id: null,
            issued_ticket_id: null,
            ticket_type: '',
            ticket_option: '',
            flight_type: '',
            outbound_date: '',
            pnr: '',
            ticket_number: '',
            issued_date: '',
            ticket_agent_id: '',
            ticket_fare_id: null,
            route_type: '',
            route_id: '',
            airline_id: '',
            route_display: '',
            airline: '',
            travel_class: '',
            passenger_type: '',
            selling_fare: 0,
            net_fare: 0,
            baggage_outbound: '',
            non_refundable: false,
            non_exchangeable: false,
        },

        get paginationPages() {
            const pages = [];
            const start = Math.max(1, this.currentPage - 2);
            const end = Math.min(this.lastPage, this.currentPage + 2);
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },

        get filteredTicketOptions() {
            const tt = this.form.ticket_type;
            const rt = 'One Way-Outbound';
            const ft = this.form.flight_type;
            const rtMap = {'One Way-Inbound':'oneway_inbound','One Way-Outbound':'oneway_outbound','Round':'round','Multi City':'multi_city'};
            const ftMap = {'Transit':'transit','Direct':'direct'};
            let fares = this.ticketFaresList;
            if (tt) {
                fares = fares.filter(f => f.ticket_type === tt);
            }
            if (rt && ft) {
                fares = fares.filter(f => f.route_type === (rtMap[rt]||rt) && f.flight_type === (ftMap[ft]||ft));
            }
            return fares.map(f => {
                let display = f.route + ' | ' + f.airline + ' | ' + f.airline_class + ' | ' + f.ticket_type;
                if (f.ticket_type === 'group' && f.pnr && f.ticket_qty) {
                    display += ' | ' + f.pnr + ' | ' + f.ticket_qty;
                }
                return { display, value: f.id };
            });
        },

        init() {
            this.loadData();
        },

        getToday() {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        },

        formatToDDMMMYY(dateStr) {
            if (!dateStr) return '';
            const parts = dateStr.split('-');
            if (parts.length !== 3) return dateStr;
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const d = parseInt(parts[2]), m = parseInt(parts[1]), y = parts[0];
            if (isNaN(d) || isNaN(m) || m < 1 || m > 12) return dateStr;
            return d + '-' + months[m - 1] + '-' + y.slice(-2);
        },

        parseDDMMMYY(input) {
            if (!input) return '';
            if (/^\d{4}-\d{2}-\d{2}$/.test(input)) return input;
            const parts = input.split('-');
            if (parts.length !== 3) return null;
            const months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
            const d = parseInt(parts[0]), mmm = parts[1].toLowerCase().slice(0, 3), yy = parts[2];
            const mi = months.indexOf(mmm);
            if (isNaN(d) || mi === -1 || !/^\d{2}$/.test(yy)) return null;
            const year = 2000 + parseInt(yy), month = mi + 1;
            if (d < 1 || d > new Date(year, month, 0).getDate()) return null;
            return year + '-' + String(month).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        },

        handleTicketTypeChange() {
            this.form.ticket_option = '';
            this.form.ticket_fare_id = null;
            this.form.route_display = '';
            this.form.airline = '';
            this.form.travel_class = '';
            this.form.route_id = '';
            this.form.airline_id = '';
            this.form.selling_fare = 0;
            this.form.net_fare = 0;
            this.form.baggage_outbound = '';
        },

        handleTicketOptionChange() {
            const val = this.form.ticket_option;
            if (!val) {
                this.form.ticket_fare_id = null;
                this.form.route_display = '';
                this.form.airline = '';
                this.form.travel_class = '';
                this.form.route_id = '';
                this.form.airline_id = '';
                this.form.selling_fare = 0;
                this.form.net_fare = 0;
                this.form.baggage_outbound = '';
                return;
            }
            const fare = this.ticketFaresList.find(f => f.id == val);
            if (fare) {
                this.form.ticket_fare_id = fare.id;
                this.form.route_display = fare.route || '';
                this.form.airline = fare.airline || '';
                this.form.travel_class = fare.airline_class || '';
                this.form.route_id = fare.route_id;
                this.form.airline_id = fare.airline_id;
                this.form.selling_fare = fare.selling_fare || 0;
                this.form.net_fare = fare.net_fare || 0;
            }
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value) params.set(key, value);
                });
                params.set('page', this.currentPage);
                params.set('per_page', this.perPage);
                const response = await fetch(`/api/reports/pending-outbound?${params}`);
                const result = await response.json();
                this.data = result.data || [];
                if (result.summary) this.summary = result.summary;
                if (result.pagination) {
                    this.currentPage = result.pagination.current_page;
                    this.lastPage = result.pagination.last_page;
                    this.totalRecords = result.pagination.total;
                }
            } catch (error) {
                console.error('Failed to load pending outbound report:', error);
                this.data = [];
            } finally {
                this.loading = false;
            }
        },

        changePage(page) {
            if (page < 1 || page > this.lastPage || page === this.currentPage) return;
            this.currentPage = page;
            this.loadData();
        },

        resetFilters() {
            this.filters = {
                search: '',
                booking_date_from: '',
                booking_date_to: '',
                flight_date_from: '',
                flight_date_to: '',
                status: 'all',
            };
            this.currentPage = 1;
            this.loadData();
        },

        openIssueModal(row) {
            this.isEdit = false;
            this.editingRow = row;
            this.form = {
                invoice: row.invoice || '',
                booking_id: row.booking_id,
                passenger_id: row.passenger_id,
                issued_ticket_id: row.id,
                ticket_type: '',
                ticket_option: '',
                flight_type: '',
                outbound_date: '',
                pnr: '',
                ticket_number: '',
                issued_date: this.formatToDDMMMYY(this.getToday()),
                ticket_agent_id: '',
                ticket_fare_id: null,
                route_type: 'One Way-Outbound',
                route_id: '',
                airline_id: '',
                route_display: '',
                airline: '',
                travel_class: '',
                passenger_type: row.passenger_type || 'adult',
                selling_fare: 0,
                net_fare: 0,
                baggage_outbound: '',
                non_refundable: false,
                non_exchangeable: false,
            };
            this.modalOpen = true;
        },

        openEditModal(row) {
            this.isEdit = true;
            this.editingRow = row;
            const cur = row.current_ticket || {};
            const regular = row.regular_ticket || {};
            this.form = {
                invoice: row.invoice || '',
                booking_id: row.booking_id,
                passenger_id: row.passenger_id,
                issued_ticket_id: row.id,
                ticket_type: '',
                ticket_option: '',
                flight_type: regular.flight_type || '',
                outbound_date: cur.outbound_date ? this.formatToDDMMMYY(cur.outbound_date) : '',
                pnr: cur.pnr || '',
                ticket_number: cur.ticket_number || '',
                issued_date: cur.issued_date ? this.formatToDDMMMYY(cur.issued_date) : this.formatToDDMMMYY(this.getToday()),
                ticket_agent_id: cur.ticket_agent_id || regular.ticket_agent_id || '',
                ticket_fare_id: cur.ticket_fare_id || null,
                route_type: 'One Way-Outbound',
                route_id: '',
                airline_id: '',
                route_display: regular.route_display || '',
                airline: regular.airline || '',
                travel_class: regular.travel_class || '',
                passenger_type: row.passenger_type || 'adult',
                selling_fare: cur.selling_fare || regular.selling_fare || 0,
                net_fare: cur.net_fare || regular.net_fare || 0,
                baggage_outbound: cur.baggage_outbound || regular.baggage_outbound || '',
                non_refundable: !cur.is_refundable,
                non_exchangeable: !cur.is_exchangeable,
            };
            this.modalOpen = true;
        },

        closeModal() {
            this.modalOpen = false;
            this.editingRow = null;
        },

        async handleSubmit() {
            const f = this.form;
            if (!f.ticket_number) {
                this.showToast('Ticket number is required.', 'error');
                return;
            }
            if (!f.ticket_agent_id) {
                this.showToast('Ticket agent is required.', 'error');
                return;
            }

            const payload = {
                ticket_number: f.ticket_number || '',
                pnr: f.pnr || '',
                ticket_agent_id: f.ticket_agent_id,
                ticket_fare_id: f.ticket_fare_id || null,
                group_ticket_id: null,
                issued_date: this.parseDDMMMYY(f.issued_date) || '',
                inbound_date: null,
                outbound_date: this.parseDDMMMYY(f.outbound_date) || null,
                selling_fare: parseFloat(f.selling_fare) || 0,
                net_fare: parseFloat(f.net_fare) || 0,
                offer_price: 0,
                is_refundable: !f.non_refundable,
                is_exchangeable: !f.non_exchangeable,
                baggage_inbound: '',
                baggage_outbound: f.baggage_outbound || '',
                outbound_pending: false,
            };

            const url = this.isEdit
                ? `/bookings/${f.booking_id}/passengers/${f.passenger_id}/ticket-edit`
                : `/bookings/${f.booking_id}/passengers/${f.passenger_id}/ticket-issue`;

            try {
                const response = await fetch(url, {
                    method: this.isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(payload),
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast(data.message || (this.isEdit ? 'Ticket updated successfully.' : 'Ticket issued successfully.'));
                    this.closeModal();
                    this.loadData();
                } else {
                    this.showToast(data.message || 'Failed to save ticket.', 'error');
                }
            } catch (err) {
                console.error('Ticket save error:', err);
                this.showToast('Failed to save ticket.', 'error');
            }
        },

        showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 px-6 py-3 rounded-lg text-white text-sm font-medium shadow-lg z-50 transition-all duration-300';
            toast.style.backgroundColor = type === 'error' ? '#ef4444' : '#22c55e';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
        },
    };
}
</script>
@endpush
