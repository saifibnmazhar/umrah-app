<div>
    <div class="w-full mx-auto pt-6">
        <div class="sticky top-0 z-30 bg-white py-2 mb-3">
            <span class="text-sm text-gray-500 font-medium">Report</span>
            <span class="text-sm text-gray-400 mx-1">></span>
            <span class="text-sm text-gray-700 font-semibold">Fingerprint Report</span>
        </div>

        <div class="sticky top-[40px] z-20 bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700 whitespace-nowrap">SEARCH BOX</label>
                    <input type="text" wire:model.live="search" placeholder="Search by Invoice No, Customer Name, PAX Name, Mobile"
                           class="search-input w-96 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Booking Date</span>
                    <label class="text-xs text-gray-500">From</label>
                    <input type="date" wire:model.live="bookingDateFrom"
                           class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                    <label class="text-xs text-gray-500">To</label>
                    <input type="date" wire:model.live="bookingDateTo"
                           class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Completion Date</span>
                    <label class="text-xs text-gray-500">From</label>
                    <input type="date" wire:model.live="completionDateFrom"
                           class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                    <label class="text-xs text-gray-500">To</label>
                    <input type="date" wire:model.live="completionDateTo"
                           class="date-input w-36 px-3 py-2 text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 border border-gray-300">
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button wire:click="resetFilters"
                            class="filter-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-1 border border-gray-300 bg-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Reset
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 mt-3 pt-3 border-t border-gray-200">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Status</span>
                    <select wire:model.live="status"
                            class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">All Statuses</option>
                        <option value="none">None</option>
                        <option value="processing">Processing</option>
                        <option value="approved">Approved</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Location</span>
                    <select wire:model.live="fingerprintLocation"
                            class="w-36 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">All</option>
                        <option value="home">Home</option>
                        <option value="office">Office</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Assigned Staff</span>
                    <select wire:model.live="assignedStaffId"
                            class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">All Staff</option>
                        @foreach($staffUsers as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Branch</span>
                    <select wire:model.live="branchId"
                            class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">District</span>
                    <select wire:model.live="districtId"
                            class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">All Districts</option>
                        @foreach($districts as $district)
                            <option value="{{ $district->id }}">{{ $district->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Fingerprint Branch</span>
                    <select wire:model.live="fingerprintBranchId"
                            class="w-44 px-3 py-2 text-sm rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">All Fingerprint Branches</option>
                        @foreach($fingerprintBranches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white border-x-2 border-b-2 border-gray-400 shadow-sm flex flex-col" style="max-height: calc(100vh - 280px);">
            <div class="overflow-auto flex-1 min-h-0">
                <table class="w-full min-w-[1800px] table-fixed">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-100">
                            <th colspan="7" class="px-2 py-2 text-xs font-bold text-gray-600 text-center border border-gray-300 bg-gray-50">Basic Information</th>
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
                            <th class="px-2 py-2 text-xs font-bold text-gray-700 text-left border-r border-b border-gray-300">District</th>
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
                    <tbody>
                        @forelse($items as $row)
                            <tr @click="window.dispatchEvent(new CustomEvent('show-fingerprint-details', {detail: {{ $row['fingerprint_detail_id'] ?? 'null' }}}))"
                                class="table-row-fp cursor-pointer"
                                @class([
                                    'bg-slate-50' => $row['_isOddInvoice'],
                                    'border-l-4' => true,
                                    'border-l-blue-600' => $row['_isOddInvoice'],
                                    'border-l-orange-500' => ! $row['_isOddInvoice'],
                                    'border-b-2 border-slate-400' => $row['_isLastPassenger'],
                                ])>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium">{{ $row['_isFirstPassenger'] ? $row['invoice_id'] : '' }}</td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">{{ $row['_isFirstPassenger'] ? $row['customer_name'] : '' }}</td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">{{ $row['_isFirstPassenger'] ? $row['booking_date'] : '' }}</td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">{{ $row['passenger_name'] ?? '-' }}</td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">{{ $row['passport_no'] ?? '-' }}</td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200 whitespace-pre-line">
                                    @if($row['_isFirstPassenger'])
                                        {{ $row['customer_mobile'] }}<br>{{ $row['passenger_mobile'] }}
                                    @else
                                        {{ $row['passenger_mobile'] ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">{{ $row['_isFirstPassenger'] ? $row['district'] : '' }}</td>
                                <td class="px-2 py-2 text-xs text-right border-r border-gray-200 font-medium text-green-700">
                                    @if($row['_isFirstPassenger'] && $canViewFinancials)
                                        {{ number_format($row['fingerprint_charge'], 2) }}
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-xs text-right border-r border-gray-200">@if($row['_isFirstPassenger']){{ number_format($row['fingerprint_cost'], 2) }}@endif</td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">{{ $row['_isFirstPassenger'] ? ($row['fingerprint_deadline'] ?? '-') : '' }}</td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200">{{ $row['completed_date'] ?? '-' }}</td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">{{ $row['required_flight'] ?? '-' }}</td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">{{ $row['actual_flight'] ?? '-' }}</td>
                                <td class="px-2 py-2 text-xs text-center border-r border-gray-200 font-medium">{{ $row['status_display'] }}</td>
                                <td class="px-2 py-2 text-xs text-left border-r border-gray-200">{{ $row['remarks'] ?? '-' }}</td>
                                @if($canViewFinancials)
                                <td class="px-2 py-2 text-xs text-right font-semibold text-green-600">@if($row['_isFirstPassenger'] && $row['profit']){{ number_format($row['profit'], 2) }}@endif</td>
                                <td class="px-2 py-2 text-xs text-right font-semibold text-red-600">@if($row['_isFirstPassenger'] && $row['loss']){{ number_format($row['loss'], 2) }}@endif</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canViewFinancials ? 17 : 15 }}" class="px-3 py-8 text-center text-slate-500">No fingerprint records found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($lastPage > 1)
        <div class="bg-white border-x-2 border-b-2 border-gray-400 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Showing <span class="font-medium">{{ ($currentPage - 1) * $perPage + 1 }}</span>
                    to <span class="font-medium">{{ min($currentPage * $perPage, $totalRecords) }}</span>
                    of <span class="font-medium">{{ $totalRecords }}</span> results
                </div>
                <nav class="flex items-center gap-1">
                    @if($currentPage > 1)
                        <button wire:click="changePage({{ $currentPage - 1 }})"
                                class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors text-slate-600 hover:bg-slate-100">
                            Previous
                        </button>
                    @endif
                    @foreach(range(1, $lastPage) as $page)
                        <button wire:click="changePage({{ $page }})"
                                @class(['px-3 py-1.5 text-sm font-medium rounded-lg border bg-white transition-colors',
                                    'bg-slate-700 text-white border-slate-700' => $page === $currentPage,
                                    'text-slate-600 hover:bg-slate-100 border-slate-300' => $page !== $currentPage,
                                ])>{{ $page }}</button>
                    @endforeach
                    @if($currentPage < $lastPage)
                        <button wire:click="changePage({{ $currentPage + 1 }})"
                                class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white transition-colors text-slate-600 hover:bg-slate-100">
                            Next
                        </button>
                    @endif
                </nav>
            </div>
        </div>
        @endif

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
                                <span class="text-xs font-bold text-gray-800">{{ $summary['total_invoices'] ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total PAX:</span>
                                <span class="text-xs font-bold text-gray-800">{{ $summary['total_pax'] ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Fingerprint Charge:</span>
                                <span class="text-xs font-bold text-green-700">@if($canViewFinancials){{ number_format($summary['total_fingerprint_charge'] ?? 0, 2) }}@else - @endif</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Costing:</span>
                                <span class="text-xs font-bold text-gray-800">{{ number_format($summary['total_fingerprint_cost'] ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Profit:</span>
                                <span class="text-xs font-bold text-green-700">@if($canViewFinancials){{ number_format($summary['total_profit'] ?? 0, 2) }}@else - @endif</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-600">Total Loss:</span>
                                <span class="text-xs font-bold text-red-700">@if($canViewFinancials){{ number_format($summary['total_loss'] ?? 0, 2) }}@else - @endif</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 mt-1">
                                <span class="text-xs font-bold text-gray-700">Net Profit/Loss:</span>
                                <span class="text-xs font-bold text-{{ ($summary['total_profit_loss'] ?? 0) >= 0 ? 'green' : 'red' }}-700">
                                    @if($canViewFinancials){{ number_format($summary['total_profit_loss'] ?? 0, 2) }}@else - @endif
                                </span>
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
                            <a href="{{ route('report.fingerprint.print', array_filter([
                                'search' => $search,
                                'booking_date_from' => $bookingDateFrom,
                                'booking_date_to' => $bookingDateTo,
                                'fingerprint_location' => $fingerprintLocation,
                                'status' => $status,
                                'assigned_staff_id' => $assignedStaffId,
                                'branch_id' => $branchId,
                                'district_id' => $districtId,
                                'fingerprint_branch_id' => $fingerprintBranchId,
                            ])) }}">
                               target="_blank"
                               class="export-btn px-4 py-2 rounded-md text-sm font-medium text-gray-700 flex items-center gap-2 transition-all border border-gray-300 bg-white hover:bg-gray-100">
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
        </div>
    </div>
</div>
