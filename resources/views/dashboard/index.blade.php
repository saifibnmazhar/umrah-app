@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
function cascadeRound($value): int {
    $parts = explode('.', number_format((float) $value, 6, '.', ''));
    if (count($parts) !== 2) return (int) round($value);
    $carry = false;
    for ($i = strlen($parts[1]) - 1; $i >= 0; $i--) {
        $carry = ((int) $parts[1][$i] + ($carry ? 1 : 0)) >= 5;
    }
    return (int) $parts[0] + ($carry ? 1 : 0);
}

$showSummaryCards = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Auditor', 'Ticket Admin', 'Visa Admin', 'Branch Manager', 'Fingerprint Admin'])->isNotEmpty();
$showPackages = true;
$showRequests = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Ticket Admin', 'Ticket Staff'])->isNotEmpty();
$stats = [
    'visaSubmitted' => $visaSubmitted,
    'visaIssued' => $visaIssued,
    'visaPending' => $visaPending,
    'inboundTicket' => $inboundTicket,
    'outboundTicket' => $outboundTicket,
    'pendingTicket' => $pendingTicket,
    'totalDue' => $totalDue,
    'totalInvoice' => $invoiceCount,
    // 'totalProfit' => '89,750 SAR',
    'totalPassengers' => $totalPassengers,
    'totalReceived' => 86,
    'departureDone' => 50,
    'departureStay' => 30,
];
$reissueRequests = [
    ['id' => 1, 'invoiceId' => 1001, 'invoiceNo' => 'INV-2024-001', 'branch' => 'Riyadh', 'passengers' => ['count' => 2]],
];
$addTicketRequests = [];
$refundRequests = [];
@endphp
<div class="max-w-7xl mx-auto pt-6">
    <section class="mb-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-slate-800">Dashboard</h2>
            <div class="relative" x-data="{
                query: '',
                suggestions: [],
                showSuggestions: false,
                debounceTimer: null,
                search() {
                    clearTimeout(this.debounceTimer);
                    if (this.query.length < 1) {
                        this.suggestions = [];
                        this.showSuggestions = false;
                        return;
                    }
                    this.debounceTimer = setTimeout(async () => {
                        try {
                            const response = await fetch(`/api/bookings/search-invoice?q=${encodeURIComponent(this.query)}`);
                            const data = await response.json();
                            this.suggestions = data;
                            this.showSuggestions = data.length > 0;
                        } catch (e) {
                            this.suggestions = [];
                            this.showSuggestions = false;
                        }
                    }, 300);
                }
            }">
                <input type="text"
                    x-model="query"
                    @input="search()"
                    @keydown.escape="showSuggestions = false"
                    @click.away="showSuggestions = false"
                    @focus="if (suggestions.length) showSuggestions = true"
                    placeholder="Search by Invoice ID"
                    class="w-64 px-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition placeholder:text-slate-400">
                <div x-show="showSuggestions && suggestions.length > 0"
                    x-cloak
                    class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    <template x-for="booking in suggestions" :key="booking.id">
                        <a :href="`/bookings/${booking.id}`"
                            class="block px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100 text-sm text-slate-700">
                            <span x-text="booking.invoice_id"></span>
                        </a>
                    </template>
                </div>
            </div>
        </div>
        
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
                <div class="text-3xl font-bold text-slate-800 mb-1">{{ $invoiceCount }}</div>
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
                <div class="text-3xl font-bold text-emerald-600 mb-1">@currency(cascadeRound($invoiceTotalAmount), 0, null, cascadeRound($invoiceTotalAmountBdt))</div>
                <div class="text-xs text-slate-500 mt-1">This Month</div>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-600">Total Received (Booking)</h3>
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="text-center mb-1">
                    <div class="text-3xl font-bold text-indigo-600">@currency(cascadeRound($totalInitialPayment), 0, null, cascadeRound($totalInitialPaymentBdt)) <span x-text="$store.currency.mode"></span></div>
                    <div class="text-xs font-medium text-slate-500">Total</div>
                </div>
                <div class="border-t border-slate-200 mt-1 mb-2"></div>
                <div class="flex justify-between items-center">
                    <div class="text-left">
                        <div class="text-2xl font-bold text-emerald-600">@currency(cascadeRound($initialPaymentCash), 0, null, cascadeRound($initialPaymentCashBdt)) <span x-text="$store.currency.mode"></span></div>
                        <div class="text-xs font-medium text-slate-500">Cash</div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-600">@currency(cascadeRound($initialPaymentBank), 0, null, cascadeRound($initialPaymentBankBdt)) <span x-text="$store.currency.mode"></span></div>
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
                <div class="text-3xl font-bold text-orange-600 mb-1">@currency(cascadeRound($totalDue), 0, null, cascadeRound($totalDueBdt))</div>
                <div class="text-xs text-slate-500 mt-1">Receivable (This Month)</div>
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
                <div class="text-center mb-1">
                    <div class="text-3xl font-bold text-rose-600">@currency(cascadeRound($totalDueCollection), 0, null, cascadeRound($totalDueCollectionBdt)) <span x-text="$store.currency.mode"></span></div>
                    <div class="text-xs font-medium text-slate-500">Total</div>
                </div>
                <div class="border-t border-slate-200 mt-1 mb-2"></div>
                <div class="flex justify-between items-center">
                    <div class="text-left">
                        <div class="text-2xl font-bold text-emerald-600">@currency(cascadeRound($dueCollectionCash), 0, null, cascadeRound($dueCollectionCashBdt)) <span x-text="$store.currency.mode"></span></div>
                        <div class="text-xs font-medium text-slate-500">Cash</div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-600">@currency(cascadeRound($dueCollectionBank), 0, null, cascadeRound($dueCollectionBankBdt)) <span x-text="$store.currency.mode"></span></div>
                        <div class="text-xs font-medium text-slate-500">Bank</div>
                    </div>
                </div>
                <div class="text-xs text-slate-500 mt-1">Collection (This Month)</div>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-600">Total Profit</h3>
                    <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                </div>
                @if($totalProfit >= 0)
                    <div class="text-3xl font-bold text-emerald-600 mb-1">+@currency(cascadeRound($totalProfit), 0, null, cascadeRound($totalProfit)) <span x-text="$store.currency.mode"></span></div>
                @else
                    <div class="text-3xl font-bold text-red-600 mb-1">-@currency(cascadeRound(abs($totalProfit)), 0, null, cascadeRound(abs($totalProfit))) <span x-text="$store.currency.mode"></span></div>
                @endif
                <div class="text-xs text-slate-500 mt-1">This Month</div>
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
                <div class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">This Month</div>
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
                        <div class="text-2xl font-bold text-slate-800">{{ $stats['visaSubmitted'] }}</div>
                        <div class="text-xs font-medium text-blue-600">Submitted</div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-slate-800">{{ $stats['visaIssued'] }}</div>
                        <div class="text-xs font-medium text-emerald-600">Issued</div>
                    </div>
                </div>
                <div class="text-center pt-2 border-t border-slate-100">
                    <div class="text-xl font-bold text-slate-800">{{ $stats['visaPending'] }}</div>
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
                <div class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">This Month</div>
            </div>


            {{--
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold text-emerald-600 mb-1">{{ $totalProfit ?? '89,750 SAR' }}</div>
                <div class="text-sm font-semibold text-slate-700">Total Profit</div>
                <div class="text-xs text-slate-500 mt-1">This Month</div>
            </div>
            --}}


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
                <div class="text-xs text-slate-500 mt-1">This Month</div>
            </div>

            {{--
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-full bg-cyan-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-800 mb-1">{{ $totalReceived ?? 86 }}</div>
                <div class="text-sm font-semibold text-slate-700">Total Received</div>
                <div class="text-xs text-slate-500 mt-1">New Booking (This Month)</div>
            </div>
            --}}


            {{--
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="text-sm font-semibold text-slate-600">Departure</h3>
                    <div class="w-10 h-10 rounded-full bg-cyan-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4m0 2a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V4zm16 12h-4a2 2 0 100 4h4a2 2 0 100-4z"/>
                        </svg>
                    </div>
                </div>
                <div class="flex justify-between items-center">
                    <div class="text-left">
                        <div class="text-2xl font-bold text-emerald-600">{{ $departureDone ?? 50 }}</div>
                        <div class="text-xs font-medium text-emerald-600">Done</div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-red-600">{{ $departureStay ?? 30 }}</div>
                        <div class="text-xs font-medium text-red-600">Stay</div>
                    </div>
                </div>
                <div class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">This Month</div>
            </div>
            --}}
        </div>

        <div class="text-right mt-4">
            <span class="text-xs text-slate-400">Last Updated: Just now</span>
        </div>
        @endif
    </section>

    @if($showPackages)
    @php $packageChunks = $packages->chunk(6); @endphp
    <section class="mb-6" x-data="{ currentSlide: 0 }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-slate-800">Packages</h3>
            @if($packageChunks->count() > 1)
            <div class="flex items-center gap-3">
                <button @click="currentSlide = Math.max(0, currentSlide - 1)" :disabled="currentSlide === 0" :class="currentSlide === 0 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:text-slate-800'" class="p-1.5 rounded-lg hover:bg-slate-100 transition disabled:hover:bg-transparent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <span class="text-sm text-slate-500 tabular-nums" x-text="`${currentSlide + 1} / {{ $packageChunks->count() }}`"></span>
                <button @click="currentSlide = Math.min({{ $packageChunks->count() - 1 }}, currentSlide + 1)" :disabled="currentSlide === {{ $packageChunks->count() - 1 }}" :class="currentSlide === {{ $packageChunks->count() - 1 }} ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:text-slate-800'" class="p-1.5 rounded-lg hover:bg-slate-100 transition disabled:hover:bg-transparent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
            @endif
        </div>

        <div class="relative">
            @forelse($packageChunks as $index => $chunk)
            <div x-show="currentSlide === {{ $index }}" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($chunk as $package)
                <a href="{{ route('settings.package.show', $package->id) }}" class="block bg-white rounded-lg shadow p-4 cursor-pointer hover:bg-slate-50 transition border-l-4 border-emerald-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-medium text-slate-800">{{ $package->package_name }}</span>
                            @if($package->ticketFare?->ticket_type?->value === 'offer')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 ml-2">Offer</span>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="font-semibold text-slate-800">
                                @currency(($package->regular_price ?? 0) + ($package->service_charge ?? 0), 0)
                            </span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            @empty
            <div class="text-center py-8 text-slate-500">No packages available</div>
            @endforelse
        </div>

        @if($packageChunks->count() > 1)
        <div class="flex justify-center mt-4 gap-2">
            @foreach($packageChunks as $index => $chunk)
            <button @click="currentSlide = {{ $index }}" :class="currentSlide === {{ $index }} ? 'bg-emerald-500' : 'bg-slate-300'" class="w-2 h-2 rounded-full transition-colors hover:bg-emerald-400"></button>
            @endforeach
        </div>
        @endif
    </section>
    @endif

    @if($showRequests)
    <div class="mb-6" x-data="{ activeTab: 'reissue' }">
        <div class="flex border-b border-slate-200 mb-4">
            <button @click="activeTab = 'reissue'" :class="activeTab === 'reissue' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500'" class="px-4 py-2 font-medium text-sm border-b-2 transition">Re-Issue Requests</button>
            <button @click="activeTab = 'addticket'" :class="activeTab === 'addticket' ? 'border-purple-600 text-purple-600' : 'border-transparent text-slate-500'" class="px-4 py-2 font-medium text-sm border-b-2 transition">Add. Tkt Requests</button>
            <button @click="activeTab = 'refund'" :class="activeTab === 'refund' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-500'" class="px-4 py-2 font-medium text-sm border-b-2 transition">Refund Requests</button>
        </div>

        <div x-show="activeTab === 'reissue'" class="space-y-3">
            {{-- @forelse($reissueRequests ?? [] as $request)  --}}
            {{-- <a href="{{ route('re-issues.confirmation', $request['id']) }}" class="block bg-white rounded-lg shadow p-4 hover:bg-slate-50 transition border-l-4 border-blue-500">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-medium text-slate-800">Invoice ID: {{ $request['invoiceId'] }}</span>
                        <span class="text-slate-500 text-sm ml-2">({{ $request['invoiceNo'] }}{{ isset($request['branch']) ? ' • ' . $request['branch'] : '' }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-500">{{ $request['passengers']['count'] ?? 1 }} passenger(s)</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                    </div>
                </div>
            </a> --}}
            {{-- @empty --}}
            <div class="text-center py-8 text-slate-500">No pending re-issue requests</div>
            {{-- @endforelse --}}
        </div>

        <div x-show="activeTab === 'addticket'" class="space-y-3" style="display: none;">
            @forelse($addTicketRequests ?? [] as $request)
            <a href="{{ route('tickets.add-confirmation', $request['id']) }}" class="block bg-white rounded-lg shadow p-4 hover:bg-slate-50 transition border-l-4 border-purple-500">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-medium text-slate-800">Invoice ID: {{ $request['invoiceId'] }}</span>
                        <span class="text-slate-500 text-sm ml-2">({{ $request['invoiceNo'] }}{{ isset($request['branch']) ? ' • ' . $request['branch'] : '' }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-500">{{ $request['passengers']['count'] ?? 1 }} passenger(s)</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                    </div>
                </div>
            </a> 
            @empty
            <div class="text-center py-8 text-slate-500">No pending additional ticket requests</div>
            @endforelse
        </div>

        <div x-show="activeTab === 'refund'" class="space-y-3" style="display: none;">
            @forelse($refundRequests ?? [] as $request)
            <a href="{{ route('refunds.confirmation', $request['id']) }}" class="block bg-white rounded-lg shadow p-4 hover:bg-slate-50 transition border-l-4 border-orange-500">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-medium text-slate-800">Invoice ID: {{ $request['invoiceId'] }}</span>
                        <span class="text-slate-500 text-sm ml-2">({{ $request['invoiceNo'] }}{{ isset($request['branch']) ? ' • ' . $request['branch'] : '' }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-500">{{ $request['passengers']['count'] ?? 1 }} passenger(s)</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                    </div>
                </div>
            </a>
            @empty
            <div class="text-center py-8 text-slate-500">No pending refund requests</div>
            @endforelse
        </div>
    </div>
    @endif
</div>
@endsection