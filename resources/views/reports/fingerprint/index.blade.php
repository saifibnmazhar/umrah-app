@extends('layouts.app')
@section('title', 'Fingerprint Report')
@php
    $canViewFinancials = auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Co Admin') || auth()->user()->hasRole('Auditor');
@endphp
@section('content')
<div class="w-full mx-auto pt-6" x-data="fingerprintReport({
    canViewFinancials: @json($canViewFinancials),
    filters: {
        search: '{{ request('search') }}',
        booking_date_from: '{{ request('booking_date_from') }}',
        booking_date_to: '{{ request('booking_date_to') }}',
        completion_date_from: '{{ request('completion_date_from') }}',
        completion_date_to: '{{ request('completion_date_to') }}',
        status: '{{ request('status') }}',
        assigned_staff_id: '{{ request('assigned_staff_id') }}',
        fingerprint_location: '{{ request('fingerprint_location') }}',
        branch_id: '{{ request('branch_id') }}',
        district_id: '{{ request('district_id') }}',
        fingerprint_branch_id: '{{ request('fingerprint_branch_id') }}',
    }
})">
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Report</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Fingerprint Report</span>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700 whitespace-nowrap">SEARCH BOX</label>
                <input type="text" x-model="filters.search" @keydown.enter="currentPage = 1; loadData()" placeholder="Search by Invoice No, Customer Name, PAX Name, Mobile"
                       class="search-input w-96 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Booking Date</span>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="filters.booking_date_from" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="filters.booking_date_to" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Completion Date</span>
                <label class="text-xs text-gray-500">From</label>
                <input type="date" x-model="filters.completion_date_from" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                <label class="text-xs text-gray-500">To</label>
                <input type="date" x-model="filters.completion_date_to" class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <button @click="currentPage = 1; loadData()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1 border border-gray-300 bg-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filter
                </button>
                <button @click="resetFilters()" class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1 border border-gray-300 bg-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mt-3 pt-3 border-t border-gray-200">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Status</span>
                <select x-model="filters.status" class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Statuses</option>
                    <option value="none">None</option>
                    <option value="processing">Processing</option>
                    <option value="approved">Approved</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Location</span>
                <select x-model="filters.fingerprint_location" class="w-36 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All</option>
                    <option value="home">Home</option>
                    <option value="office">Office</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Assigned Staff</span>
                <select x-model="filters.assigned_staff_id" class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Staff</option>
                    @foreach($staffUsers as $staff)
                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Branch</span>
                <select x-model="filters.branch_id" class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Branches</option>
                    @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">District</span>
                <select x-model="filters.district_id" class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Districts</option>
                    @foreach($districts as $district)
                    <option value="{{ $district->id }}">{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700">Fingerprint Branch</span>
                <select x-model="filters.fingerprint_branch_id" class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Fingerprint Branches</option>
                    @foreach($fingerprintBranches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 overflow-hidden shadow-sm scrollbar-thin">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1800px] table-fixed">
                <thead>
                    <tr class="bg-gray-100">
                        <th colspan="6" class="px-2 py-2 text-xs font-bold text-gray-600 text-center border border-gray-300 bg-gray-50">Basic Information</th>
                        <th colspan="2" class="px-2 py-2 text-xs font-bold text-gray-600 text-center border border-gray-300 bg-gray-50">Fingerprint Charge Calculation</th>
                        <th colspan="2" class="px-2 py-2 text-xs font-bold text-gray-600 text-center border border-gray-300 bg-gray-50">Fingerprint Status</th>
                        <th colspan="2" class="px-2 py-2 text-xs font-bold text-gray-600 text-center border border-gray-300 bg-gray-50">Flight Status</th>
                        <th colspan="2" class="px-2 py-2 text-xs font-bold text-gray-600 text-center border border-gray-300 bg-gray-50">Remarks & Status</th>
                        @if($canViewFinancials)
                        <th colspan="2" class="px-2 py-2 text-xs font-bold text-gray-600 text-center border border-gray-300 bg-gray-50">Profit & Loss</th>
                        @endif
                    </tr>
                    <tr class="table-header">
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-center border-r border-b border-gray-300">Invoice ID</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-b border-gray-300">Customer Name</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-center border-r border-b border-gray-300">Booking Date</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-b border-gray-300">Passenger Name</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-center border-r border-b border-gray-300">Passport No</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-b border-gray-300">Mobile</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-b border-gray-300">Fingerprint Charge</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-b border-gray-300">Fingerprint Cost</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-center border-r border-b border-gray-300">Fingerprint Deadline</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-center border-r border-b border-gray-300">Completed Date</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-b border-gray-300">Required Flight</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-b border-gray-300">Actual Flight</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-center border-r border-b border-gray-300">Status</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-b border-gray-300">Remarks</th>
                        @if($canViewFinancials)
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-r border-b border-gray-300">Profit</th>
                        <th class="px-2 py-2 text-xs font-bold text-gray-700 text-right border-b border-gray-300">Loss</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <template x-if="loading">
                        <tr>
                            <td :colspan="canViewFinancials ? 16 : 14" class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && data.length === 0">
                        <tr>
                            <td :colspan="canViewFinancials ? 16 : 14" class="px-3 py-8 text-center text-slate-500">No fingerprint records found</td>
                        </tr>
                    </template>
                    <template x-for="(row, rowIndex) in data" :key="row.fingerprint_detail_id || rowIndex">
                        <tr @click="showDetails(row.fingerprint_detail_id)"
                            class="table-row-fp cursor-pointer"
                            :class="[
                                row._isOddInvoice ? 'bg-slate-50' : 'bg-white',
                                'border-l-4',
                                row._isOddInvoice ? 'border-l-blue-600' : 'border-l-orange-500',
                                row._isLastPassenger ? 'border-b-2 border-slate-400' : ''
                            ]">
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium" x-text="row._isFirstPassenger ? row.invoice_id : ''"></td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200" x-text="row._isFirstPassenger ? row.customer_name : ''"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row._isFirstPassenger ? row.booking_date : ''"></td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200" x-text="row.passenger_name || '-'"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row.passport_no || '-'"></td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200 whitespace-pre-line">
                                <template x-if="row._isFirstPassenger">
                                    <span x-text="row.customer_mobile + '\n' + row.passenger_mobile"></span>
                                </template>
                                <template x-if="!row._isFirstPassenger">
                                    <span x-text="row.passenger_mobile || '-'"></span>
                                </template>
                            </td>
                            <td class="px-2 py-2 text-xs text-right border-r border-gray-200 font-medium text-green-700">
                                <span x-show="row._isFirstPassenger && canViewFinancials" x-text="$currency(row.fingerprint_charge)"></span>
                            </td>
                            <td class="px-2 py-2 text-xs text-right border-r border-gray-200" x-text="row._isFirstPassenger ? $currency(row.fingerprint_cost) : ''"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row._isFirstPassenger ? (row.fingerprint_deadline || '-') : ''"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200" x-text="row.completed_date || '-'"></td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200" x-text="row.required_flight || '-'"></td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200" x-text="row.actual_flight || '-'"></td>
                            <td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium"
                                x-text="row.status_display"
                                :class="getStatusClass(row.status_display)"></td>
                            <td class="px-2 py-2 text-xs text-left border-r border-gray-200" x-text="row.remarks || '-'"></td>
                            <td x-show="canViewFinancials" class="px-2 py-2 text-xs text-right font-semibold text-green-600"
                                x-text="row._isFirstPassenger && row.profit ? $currency(row.profit) : ''"></td>
                            <td x-show="canViewFinancials" class="px-2 py-2 text-xs text-right font-semibold text-red-600"
                                x-text="row._isFirstPassenger && row.loss ? $currency(row.loss) : ''"></td>
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
                <button @click="changePage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors">
                    Previous
                </button>
                <template x-for="page in paginationPages" :key="page">
                    <button @click="changePage(page)"
                            :class="page === currentPage ? 'bg-slate-700 text-white border-slate-700' : 'text-slate-600 hover:bg-slate-100 border-slate-300'"
                            class="px-3 py-1.5 text-sm font-medium rounded-lg border bg-white transition-colors"
                            x-text="page">
                    </button>
                </template>
                <button @click="changePage(currentPage + 1)"
                        :disabled="currentPage === lastPage"
                        :class="currentPage === lastPage ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors">
                    Next
                </button>
            </nav>
        </div>
    </div>

    <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm mt-0">
        <div class="flex flex-wrap gap-6">
            <div class="footer-box rounded-lg overflow-hidden min-w-[320px] border-2 border-gray-300">
                <div class="footer-box-header px-4 py-2 bg-gray-100 border-b border-gray-300">
                    <span class="text-sm font-bold text-gray-700">Fingerprint Report Summary</span>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Invoices:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="summary.total_invoices"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total PAX:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="summary.total_pax"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Fingerprint Charge:</span>
                            <span class="text-xs font-bold text-green-700" x-text="canViewFinancials ? $currency(summary.total_fingerprint_charge) : '-'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Costing:</span>
                            <span class="text-xs font-bold text-gray-800" x-text="$currency(summary.total_fingerprint_cost)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Profit:</span>
                            <span class="text-xs font-bold text-green-700" x-text="canViewFinancials ? $currency(summary.total_profit) : '-'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Total Loss:</span>
                            <span class="text-xs font-bold text-red-700" x-text="canViewFinancials ? $currency(summary.total_loss) : '-'"></span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 mt-1">
                            <span class="text-xs font-bold text-gray-700">Net Profit/Loss:</span>
                            <span class="text-xs font-bold" x-show="canViewFinancials"
                                  :class="summary.total_profit_loss >= 0 ? 'text-green-700' : 'text-red-700'"
                                  x-text="$currency(summary.total_profit_loss)"></span>
                            <span class="text-xs font-bold text-gray-700" x-show="!canViewFinancials">-</span>
                        </div>
                    </div>
                </div>
                <div class="px-4 pb-3">
                    <p class="text-xs text-gray-500">Click any row to view detailed passenger information and reschedule history.</p>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="footer-box rounded-lg overflow-hidden border-2 border-gray-300">
                    <div class="footer-box-header px-4 py-2 bg-gray-100 border-b border-gray-300">
                        <span class="text-sm font-bold text-gray-700">Export Options</span>
                    </div>
                    <div class="p-4 flex gap-3">
                        <a :href="printUrl" target="_blank" class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all border border-gray-300 bg-white hover:bg-gray-100">
                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                            </svg>
                            Print
                        </a>
                    </div>
                </div>

                <div class="footer-box rounded-lg overflow-hidden border-2 border-gray-300">
                    <div class="footer-box-header px-4 py-2 bg-gray-100 border-b border-gray-300">
                        <span class="text-sm font-bold text-gray-700">Report Info</span>
                    </div>
                    <div class="p-4">
                        <p class="text-xs text-gray-600 leading-relaxed">
                            • Click on any row to view passenger details and reschedule history<br>
                            • Fingerprint Charge, Costing, and Profit/Loss appear <strong>once per Invoice</strong><br>
                            • Multiple PAX under same invoice are grouped<br>
                            • Green = Profit | Red = Loss<br>
                            • Orange/Blue borders indicate invoice groups
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center">
            <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
        </div>
    </div>

    <div x-show="showDetailsModal"
         class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
         :class="showDetailsModal ? '' : 'hidden'">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-bold text-gray-800">Passenger Details</h3>
                <button @click="closeDetailsModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <template x-if="loadingDetails">
                    <div class="text-center py-8 text-gray-500">Loading...</div>
                </template>
                <template x-if="!loadingDetails && details">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium text-gray-500">Invoice ID</label>
                                <p class="text-sm font-semibold" x-text="details.invoice_id"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500">Customer Name</label>
                                <p class="text-sm" x-text="details.customer_name"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500">Booking Date</label>
                                <p class="text-sm" x-text="details.booking_date"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500">Fingerprint Deadline</label>
                                <p class="text-sm" x-text="details.fingerprint_deadline"></p>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3">Passenger Information</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Passenger Name</label>
                                    <p class="text-sm" x-text="details.passenger.name"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Passport No</label>
                                    <p class="text-sm" x-text="details.passenger.passport_no"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Mobile</label>
                                    <p class="text-sm whitespace-pre-line" x-text="details.customer_mobile + '\n' + details.passenger.mobile"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Address</label>
                                    <p class="text-sm" x-text="details.passenger.address || '-'"></p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3">Fingerprint Status</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Completed Date</label>
                                    <p class="text-sm" x-text="details.completed_date || '-'"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Fingerprint Status</label>
                                    <p class="text-sm font-medium" x-text="details.status_display"></p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3">Flight Information</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Required Flight</label>
                                    <p class="text-sm" x-text="details.required_flight || '-'"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Actual Flight</label>
                                    <p class="text-sm" x-text="details.actual_flight || '-'"></p>
                                </div>
                            </div>
                        </div>

                        <template x-if="canViewFinancials">
                            <div class="border-t border-gray-200 pt-4">
                                <h4 class="text-sm font-bold text-gray-700 mb-3">Financial Summary</h4>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="text-xs font-medium text-gray-500">Fingerprint Charge</label>
                                        <p class="text-sm font-semibold text-green-700" x-text="$currency(details.fingerprint_charge)"></p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-500">Fingerprint Cost</label>
                                        <p class="text-sm" x-text="$currency(details.fingerprint_cost)"></p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-500">Profit/Loss</label>
                                        <p class="text-sm font-semibold">
                                            <span x-show="details.profit > 0" class="text-green-700" x-text="'Profit: ' + $currency(details.profit)"></span>
                                            <span x-show="details.loss > 0" class="text-red-700" x-text="'Loss: ' + $currency(details.loss)"></span>
                                            <span x-show="!details.profit && !details.loss">-</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3">Reschedule History</h4>
                            <template x-if="details.reschedule_history && details.reschedule_history.length > 0">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs border border-gray-300">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Previous Date</th>
                                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">New Date</th>
                                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Rescheduled By</th>
                                                <th class="px-3 py-2 text-left font-medium border-b border-r border-gray-300">Rescheduled At</th>
                                                <th class="px-3 py-2 text-left font-medium border-b">Notes/Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(item, idx) in details.reschedule_history" :key="idx">
                                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                                    <td class="px-3 py-2 border-r border-gray-200" x-text="item.previous_date"></td>
                                                    <td class="px-3 py-2 border-r border-gray-200" x-text="item.new_date"></td>
                                                    <td class="px-3 py-2 border-r border-gray-200" x-text="item.rescheduled_by"></td>
                                                    <td class="px-3 py-2 border-r border-gray-200" x-text="item.rescheduled_at"></td>
                                                    <td class="px-3 py-2" x-text="item.reason + (item.remarks !== '-' ? ' - ' + item.remarks : '')"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            <template x-if="!details.reschedule_history || details.reschedule_history.length === 0">
                                <p class="text-sm text-gray-500 text-center py-4">No reschedule history for this passenger</p>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fingerprintReport(options = {}) {
    return {
        data: [],
        loading: true,
        currentPage: 1,
        lastPage: 1,
        totalRecords: 0,
        perPage: 25,
        canViewFinancials: options.canViewFinancials ?? false,
        filters: options.filters || {
            search: '',
            booking_date_from: '',
            booking_date_to: '',
            completion_date_from: '',
            completion_date_to: '',
            status: '',
            assigned_staff_id: '',
            fingerprint_location: '',
            branch_id: '',
            district_id: '',
            fingerprint_branch_id: '',
        },
        summary: {
            total_invoices: 0,
            total_pax: 0,
            total_fingerprint_charge: 0,
            total_fingerprint_cost: 0,
            total_profit_loss: 0,
        },
        showDetailsModal: false,
        loadingDetails: false,
        details: null,

        get paginationPages() {
            const pages = [];
            const start = Math.max(1, this.currentPage - 2);
            const end = Math.min(this.lastPage, this.currentPage + 2);
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },

        get printUrl() {
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) params.set(key, value);
            });
            return `/reports/fingerprint/print?${params.toString()}`;
        },

        init() {
            this.loadData();
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ ...this.filters, page: this.currentPage, per_page: this.perPage });
                const response = await fetch(`/api/reports/fingerprint?${params}`);
                const result = await response.json();
                this.data = result.data || [];
                if (result.summary) {
                    this.summary = result.summary;
                }
                if (result.pagination) {
                    this.currentPage = result.pagination.current_page;
                    this.lastPage = result.pagination.last_page;
                    this.totalRecords = result.pagination.total;
                }
            } catch (error) {
                console.error('Failed to load fingerprint report:', error);
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
                completion_date_from: '',
                completion_date_to: '',
                status: '',
                assigned_staff_id: '',
                fingerprint_location: '',
                branch_id: '',
                district_id: '',
                fingerprint_branch_id: '',
            };
            this.currentPage = 1;
            this.loadData();
        },

        async showDetails(fingerprintDetailId) {
            if (!fingerprintDetailId) {
                window.showToast('No detail record available', 'info');
                return;
            }
            this.loadingDetails = true;
            this.showDetailsModal = true;
            this.details = null;
            try {
                const response = await fetch(`/api/reports/fingerprint/details/${fingerprintDetailId}`);
                const result = await response.json();
                this.details = result;
            } catch (error) {
                console.error('Failed to load details:', error);
                window.showToast('Failed to load details', 'error');
            } finally {
                this.loadingDetails = false;
            }
        },

        closeDetailsModal() {
            this.showDetailsModal = false;
            this.details = null;
        },



        getStatusClass(status) {
            if (!status) return 'text-gray-800';
            const classes = {
                'None': 'text-gray-500',
                'Processing': 'text-yellow-600',
                'Partially Approved': 'text-blue-600',
                'Done': 'text-green-600',
                'Rescheduled by Client': 'text-orange-600',
                'Rescheduled by BMT': 'text-purple-600',
                'NFC Problem': 'text-red-600',
                'Hold by BMT': 'text-red-700',
            };
            return classes[status] || 'text-gray-800';
        },
    };
}
</script>
@endpush
