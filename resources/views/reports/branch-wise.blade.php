@extends('layouts.app')

@section('title', 'Branch Wise Report')

@section('content')
<style>[x-cloak] { display: none !important; }</style>
<div class="max-w-3xl mx-auto pt-6" x-data="branchWiseReport({ vouchersByDate: {{ $vouchersByDateJson }}, dateFrom: '{{ $dateFrom->format('Y-m-d') }}', dateTo: '{{ $dateTo->format('Y-m-d') }}', branchId: '{{ $selectedBranch }}' })">
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

    @php
    $dateLabel = $dateFrom->format('d M Y') . ' - ' . $dateTo->format('d M Y');

    function cascadeRound($value): int {
        $parts = explode('.', number_format((float) $value, 6, '.', ''));
        if (count($parts) !== 2) return (int) round($value);
        $carry = false;
        for ($i = strlen($parts[1]) - 1; $i >= 0; $i--) {
            $carry = ((int) $parts[1][$i] + ($carry ? 1 : 0)) >= 5;
        }
        return (int) $parts[0] + ($carry ? 1 : 0);
    }
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-600">Bookings</h3>
                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-800 mb-1">{{ $invoiceCount }}</div>
                <div class="text-xs text-slate-500 mt-1">Total Invoice ({{ $dateLabel }})</div>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-600">Total Sales</h3>
                    <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold text-emerald-600 mb-1">@currency(cascadeRound($invoiceTotalAmount), 0, null, cascadeRound($invoiceTotalAmountBdt))</div>
<div class="text-xs text-slate-500 mt-1">{{ $dateLabel }}</div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-600">Total Cash Received</h3>
                    <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold text-emerald-600 mb-1">@currency(cascadeRound($totalCashPayment), 0, null, cascadeRound($totalCashPaymentBdt)) <span x-text="$store.currency.mode"></span></div>
                <div class="text-xs text-slate-500 mt-1">Cash Payment ({{ $dateLabel }})</div>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-600">Total Bank Received</h3>
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold text-blue-600 mb-1">@currency(cascadeRound($totalBankPayment), 0, null, cascadeRound($totalBankPaymentBdt)) <span x-text="$store.currency.mode"></span></div>
                <div class="text-xs text-slate-500 mt-1">Bank Payment ({{ $dateLabel }})</div>
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
            <div class="text-3xl font-bold text-orange-600 mb-1">@currency(cascadeRound($totalDue), 0, null, cascadeRound($totalDueBdt)) <span x-text="$store.currency.mode"></span></div>
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
            <div class="text-3xl font-bold text-slate-800 mb-1">@currency(cascadeRound($totalDueCollection), 0, null, cascadeRound($totalDueCollectionBdt)) <span x-text="$store.currency.mode"></span></div>
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

    <div class="mt-6 mb-4">
        <button @click="openModal()" class="w-full flex items-center justify-between bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5 cursor-pointer">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Payment History</h3>
                    <p class="text-sm text-slate-500">{{ collect($vouchersByDate)->sum(fn($vouchers) => count($vouchers)) }} payments ({{ $dateLabel }})</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
    </div>

    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-transparent" @click="closeModal()"></div>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="relative bg-white rounded-lg shadow-2xl w-full max-w-7xl max-h-[95vh] overflow-hidden">
                <div class="bg-slate-700 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-white">Payment History</h2>
                    <button @click="closeModal()" class="text-white hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[calc(95vh-130px)]">
                    <template x-if="selectedVouchers.length === 0">
                        <p class="text-center text-gray-500 py-8">No records found.</p>
                    </template>
                    <template x-if="selectedVouchers.length > 0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1100px]">
                                <thead>
                                    <tr class="table-header">
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Invoice ID</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Voucher No</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-center border-r border-gray-300">Method</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Transaction Type</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Trx ID</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Receive By</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Receive At</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-left border-r border-gray-300">Payment Date</th>
                                        <th class="px-3 py-3 text-xs font-bold text-gray-700 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(v, idx) in paginatedVouchers" :key="idx">
                                        <tr class="border-b border-gray-100 even:bg-[#fafafa]">
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.invoice_id"></td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.voucher_no"></td>
                                            <td class="px-3 py-2 text-sm text-center border-r border-gray-200">
                                                <span class="px-2 py-0.5 rounded text-xs font-medium"
                                                      :class="v.method === 'Bank' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'"
                                                      x-text="v.method"></span>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.transaction_type"></td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.trx_id"></td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.receive_by"></td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.receive_at"></td>
                                            <td class="px-3 py-2 text-sm text-left border-r border-gray-200" x-text="v.payment_date"></td>
                                             <td class="px-3 py-2 text-sm text-right font-semibold" x-text="formatAmount(v.amount, v.bdt_amount, v.currency_rate)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
                <div x-show="totalPages > 1" class="flex justify-between items-center px-6 py-3 border-t border-gray-200 bg-gray-50">
                    <span class="text-sm text-gray-500">
                        Showing <span x-text="((currentPage - 1) * perPage) + 1"></span>-<span x-text="Math.min(currentPage * perPage, selectedVouchers.length)"></span> of <span x-text="selectedVouchers.length"></span>
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span x-show="currentPage === 1" class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5">Previous</span>
                        <button x-show="currentPage > 1" @click="goToPage(currentPage - 1)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100">Previous</button>
                        <span class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md leading-5" x-text="currentPage"></span>
                        <button x-show="currentPage < totalPages" @click="goToPage(currentPage + 1)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md leading-5 hover:bg-gray-100">Next</button>
                        <span x-show="currentPage === totalPages" class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md cursor-not-allowed leading-5">Next</span>
                    </span>
                </div>
                <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex justify-between items-center">
                    <div class="flex gap-6 text-sm">
                        <span class="font-medium text-green-700">
                            Cash: <span x-text="formatAmount(totalCash, totalCashBdt, null)"></span>
                        </span>
                        <span class="font-medium text-blue-700">
                            Bank: <span x-text="formatAmount(totalBank, totalBankBdt, null)"></span>
                        </span>
                        <span class="font-medium text-gray-800">
                            Total: <span x-text="formatAmount(totalAmount, totalAmountBdt, null)"></span>
                        </span>
                    </div>
                    <a :href="printUrl" target="_blank" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </a>
                    <button @click="closeModal()" class="no-print px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100">
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
    function branchWiseReport(options = {}) {
        return {
            vouchersByDate: options.vouchersByDate || {},
            dateFrom: options.dateFrom || '',
            dateTo: options.dateTo || '',
            branchId: options.branchId || '',
            modalOpen: false,
            selectedVouchers: [],
            currentPage: 1,
            perPage: 50,

            get paginatedVouchers() {
                const start = (this.currentPage - 1) * this.perPage;
                return this.selectedVouchers.slice(start, start + this.perPage);
            },

            get totalPages() {
                return Math.ceil(this.selectedVouchers.length / this.perPage);
            },

            goToPage(page) {
                if (page >= 1 && page <= this.totalPages) {
                    this.currentPage = page;
                }
            },

            openModal() {
                this.selectedVouchers = Object.values(this.vouchersByDate).flat();
                this.currentPage = 1;
                this.modalOpen = true;
            },

            closeModal() {
                this.modalOpen = false;
            },

            get printUrl() {
                const params = new URLSearchParams();
                if (this.dateFrom) params.set('date_from', this.dateFrom);
                if (this.dateTo) params.set('date_to', this.dateTo);
                if (this.branchId) params.set('branch_id', this.branchId);
                params.set('currency', Alpine.store('currency').mode);
                return `/reports/branch-wise/payment-history/print?${params.toString()}`;
            },

            get totalCash() {
                return this.selectedVouchers.filter(v => v.method === 'Cash').reduce((s, v) => s + v.amount, 0);
            },
            get totalCashBdt() {
                return this.selectedVouchers.filter(v => v.method === 'Cash').reduce((s, v) => s + (v.bdt_amount > 0 ? v.bdt_amount : v.amount * v.currency_rate), 0);
            },
            get totalBank() {
                return this.selectedVouchers.filter(v => v.method === 'Bank').reduce((s, v) => s + v.amount, 0);
            },
            get totalBankBdt() {
                return this.selectedVouchers.filter(v => v.method === 'Bank').reduce((s, v) => s + (v.bdt_amount > 0 ? v.bdt_amount : v.amount * v.currency_rate), 0);
            },
            get totalAmount() {
                return this.selectedVouchers.reduce((s, v) => s + v.amount, 0);
            },
            get totalAmountBdt() {
                return this.selectedVouchers.reduce((s, v) => s + (v.bdt_amount > 0 ? v.bdt_amount : v.amount * v.currency_rate), 0);
            },

            formatAmount(amount, bdtAmount, currencyRate) {
                if (window.Alpine) {
                    if (bdtAmount && bdtAmount > 0) {
                        return Alpine.store('currency').format(amount, 2, null, bdtAmount);
                    }
                    if (currencyRate && currencyRate > 0) {
                        return Alpine.store('currency').format(amount, 2, null, amount * currencyRate);
                    }
                    return Alpine.store('currency').format(amount, 2);
                }
                return Number(amount).toFixed(2);
            },
        };
    }
</script>
@endpush
