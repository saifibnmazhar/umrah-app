@php
if (! function_exists('cascadeRound')) {
    function cascadeRound($value): int {
        $parts = explode('.', number_format((float) $value, 6, '.', ''));
        if (count($parts) !== 2) return (int) round($value);
        $carry = false;
        for ($i = strlen($parts[1]) - 1; $i >= 0; $i--) {
            $carry = ((int) $parts[1][$i] + ($carry ? 1 : 0)) >= 5;
        }
        return (int) $parts[0] + ($carry ? 1 : 0);
    }
}
@endphp

@if($showSummaryCards)
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
        <div class="text-3xl font-bold text-slate-800 mb-1">{{ $stats['totalInvoice'] ?? 0 }}</div>
        <div class="text-xs text-slate-500 mt-1">Total Invoice (This Month)</div>
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
        <div class="text-3xl font-bold text-emerald-600 mb-1">@currency(cascadeRound($totals['invoiceTotalAmount'] ?? 0), 0, null, cascadeRound($totals['invoiceTotalAmountBdt'] ?? 0))</div>
        <div class="text-xs text-slate-500 mt-1">This Month</div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-600">Total Received (Booking)</h3>
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>
        <div class="text-center mb-1">
            <div class="text-3xl font-bold text-indigo-600">@currency(cascadeRound($totals['totalInitialPayment'] ?? 0), 0, null, cascadeRound($totals['totalInitialPaymentBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
            <div class="text-xs font-medium text-slate-500">Total</div>
        </div>
        <div class="border-t border-slate-200 mt-1 mb-2"></div>
        <div class="flex justify-between items-center">
            <div class="text-left">
                <div class="text-2xl font-bold text-emerald-600">@currency(cascadeRound($totals['initialPaymentCash'] ?? 0), 0, null, cascadeRound($totals['initialPaymentCashBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
                <div class="text-xs font-medium text-slate-500">Cash</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-blue-600">@currency(cascadeRound($totals['initialPaymentBank'] ?? 0), 0, null, cascadeRound($totals['initialPaymentBankBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
                <div class="text-xs font-medium text-slate-500">Bank</div>
            </div>
        </div>
        <div class="text-xs text-slate-500 mt-1">This Month</div>
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
        <div class="text-3xl font-bold text-orange-600 mb-1">@currency(cascadeRound($totals['totalDue'] ?? 0), 0, null, cascadeRound($totals['totalDueBdt'] ?? 0))</div>
        <div class="text-xs text-slate-500 mt-1">Receivable (This Month)</div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-600">Total Due Collection</h3>
            <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>
        <div class="text-center mb-1">
            <div class="text-3xl font-bold text-rose-600">@currency(cascadeRound($totals['totalDueCollection'] ?? 0), 0, null, cascadeRound($totals['totalDueCollectionBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
            <div class="text-xs font-medium text-slate-500">Total</div>
        </div>
        <div class="border-t border-slate-200 mt-1 mb-2"></div>
        <div class="flex justify-between items-center">
            <div class="text-left">
                <div class="text-2xl font-bold text-emerald-600">@currency(cascadeRound($totals['dueCollectionCash'] ?? 0), 0, null, cascadeRound($totals['dueCollectionCashBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
                <div class="text-xs font-medium text-slate-500">Cash</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-blue-600">@currency(cascadeRound($totals['dueCollectionBank'] ?? 0), 0, null, cascadeRound($totals['dueCollectionBankBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
                <div class="text-xs font-medium text-slate-500">Bank</div>
            </div>
        </div>
        <div class="text-xs text-slate-500 mt-1">Collection (This Month)</div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-600">Total Receiving</h3>
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>
        <div class="text-center mb-1">
            <div class="text-3xl font-bold text-indigo-600">@currency(cascadeRound($totals['totalReceiving'] ?? 0), 0, null, cascadeRound($totals['totalReceivingBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
            <div class="text-xs font-medium text-slate-500">Total</div>
        </div>
        <div class="border-t border-slate-200 mt-1 mb-2"></div>
        <div class="flex justify-between items-center">
            <div class="text-left">
                <div class="text-2xl font-bold text-emerald-600">@currency(cascadeRound($totals['receivingCash'] ?? 0), 0, null, cascadeRound($totals['receivingCashBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
                <div class="text-xs font-medium text-slate-500">Cash</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-blue-600">@currency(cascadeRound($totals['receivingBank'] ?? 0), 0, null, cascadeRound($totals['receivingBankBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
                <div class="text-xs font-medium text-slate-500">Bank</div>
            </div>
        </div>
        <div class="text-xs text-slate-500 mt-1">Collection (This Month)</div>
    </div>

    @if($showProfitCards)
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-600">Total Profit</h3>
            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-0l-8 8-4-4-6 6"/>
                </svg>
            </div>
        </div>
        @if(cascadeRound($totals['totalProfit'] ?? 0) >= 0)
            <div class="text-3xl font-bold text-emerald-600 mb-1">+@currency(cascadeRound($totals['totalProfit'] ?? 0), 0, null, cascadeRound($totals['totalProfitBdt'] ?? 0)) <span x-text="$store.currency.mode"></span></div>
        @else
            <div class="text-3xl font-bold text-red-600 mb-1">-@currency(cascadeRound(abs($totals['totalProfit'] ?? 0)), 0, null, cascadeRound(abs($totals['totalProfitBdt'] ?? 0))) <span x-text="$store.currency.mode"></span></div>
        @endif
        <div class="text-xs text-slate-500 mt-1">This Month</div>
    </div>
    @endif

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-sm font-semibold text-slate-600">Fingerprint</h3>
            <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0 0 15.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 0 0 8 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                </svg>
            </div>
        </div>
        <div class="flex justify-between items-center mb-2">
            <div class="text-left">
                <div class="text-2xl font-bold text-slate-800">{{ $stats['fingerprintApproved'] ?? 0 }}</div>
                <div class="text-xs font-medium text-blue-600">Approved</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-slate-800">{{ $stats['fingerprintDone'] ?? 0 }}</div>
                <div class="text-xs font-medium text-emerald-600">Done</div>
            </div>
        </div>
        <div class="text-center pt-2 border-t border-slate-100">
            <div class="text-xl font-bold text-slate-800">{{ $stats['fingerprintProcessing'] ?? 0 }}</div>
            <div class="text-xs font-medium text-amber-600">Processing</div>
        </div>
        <div class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">This Month</div>
    </div>

    @if($showProfitCards)
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-600">Total Fingerprint Profit</h3>
            <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
        </div>
        @if(cascadeRound($totals['totalFingerprintProfit'] ?? 0) > 0)
            <div class="text-3xl font-bold text-emerald-600 mb-1">+@currency(cascadeRound($totals['totalFingerprintProfit'] ?? 0), 0, null, cascadeRound($totals['totalFingerprintProfitBdt'] ?? 0))</div>
        @elseif(cascadeRound($totals['totalFingerprintProfit'] ?? 0) < 0)
            <div class="text-3xl font-bold text-red-600 mb-1">@currency(cascadeRound($totals['totalFingerprintProfit'] ?? 0), 0, null, cascadeRound($totals['totalFingerprintProfitBdt'] ?? 0))</div>
        @else
            <div class="text-3xl font-bold text-teal-600 mb-1">@currency(cascadeRound($totals['totalFingerprintProfit'] ?? 0), 0, null, cascadeRound($totals['totalFingerprintProfitBdt'] ?? 0))</div>
        @endif
        <div class="text-xs text-slate-500 mt-1">This Month</div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-600">Total Service Charge Deduction</h3>
            <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-rose-600 mb-1">@currency(cascadeRound($totals['totalServiceChargeDeduction'] ?? 0), 0, null, cascadeRound($totals['totalServiceChargeDeductionBdt'] ?? 0))</div>
        <div class="text-xs text-slate-500 mt-1">This Month</div>
    </div>
    @endif

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-600">Total Refund</h3>
            <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-8 0v1m0 0a4 4 0 01-4 4h12a4 4 0 01-4-4H8z"/>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-orange-600 mb-1">@currency(cascadeRound($totals['totalRefund'] ?? 0), 0, null, cascadeRound($totals['totalRefundBdt'] ?? 0))</div>
        <div class="text-xs text-slate-500 mt-1">This Month</div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-600">Total Ticket Refunds</h3>
            <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-orange-600 mb-1">@currency(cascadeRound($totals['totalTicketRefund'] ?? 0), 0, null, cascadeRound($totals['totalTicketRefundBdt'] ?? 0))</div>
        <div class="text-xs text-slate-500 mt-1">This Month</div>
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
                <div class="text-2xl font-bold text-slate-800">{{ $stats['visaSubmitted'] ?? 0 }}</div>
                <div class="text-xs font-medium text-blue-600">Submitted</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-slate-800">{{ $stats['visaIssued'] ?? 0 }}</div>
                <div class="text-xs font-medium text-emerald-600">Issued</div>
            </div>
        </div>
        <div class="text-center pt-2 border-t border-slate-100">
            <div class="text-xl font-bold text-slate-800">{{ $stats['visaPending'] ?? 0 }}</div>
            <div class="text-xs font-medium text-amber-600">Pending</div>
        </div>
        <div class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">This Month</div>
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
                <div class="text-2xl font-bold text-slate-800">{{ $stats['inboundTicket'] ?? 0 }}</div>
                <div class="text-xs font-medium text-emerald-600">Inbound Ticket</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-slate-800">{{ $stats['outboundTicket'] ?? 0 }}</div>
                <div class="text-xs font-medium text-red-600">Outbound Ticket</div>
            </div>
        </div>
        <div class="text-center pt-2 border-t border-slate-100">
            <div class="text-xl font-bold text-slate-800">{{ $stats['pendingTicket'] ?? 0 }}</div>
            <div class="text-xs font-medium text-amber-600">Pending Ticket</div>
        </div>
        <div class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">This Month</div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 rounded-full bg-pink-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-slate-800 mb-1">{{ $stats['totalPassengers'] ?? 0 }}</div>
        <div class="text-sm font-semibold text-slate-700">Total Passengers</div>
        <div class="text-xs text-slate-500 mt-1">This Month</div>
    </div>
</div>
@endif
