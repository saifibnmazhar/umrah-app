@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
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

@php
    $reissueRequests = $pendingReIssueRequests ?? collect();
    $addTicketRequests = $pendingAdditionalRequests ?? collect();
    $refundRequests = $pendingRefundRequests ?? collect();
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
            <livewire:dashboard.dashboard-summary :stats="$stats" :show-summary-cards="$showSummaryCards" :show-profit-cards="$showProfitCards" :show-requests="$showRequests" :totals="$totals" />
        @endif
    </section>

    @if($showPackages)
        <livewire:dashboard.dashboard-package-slider :packages="$packages" :show-packages="$showPackages" />
    @endif

    @if($showRequests)
        <livewire:dashboard.dashboard-request-tabs :reissue-requests="$pendingReIssueRequests" :add-ticket-requests="$pendingAdditionalRequests" :refund-requests="$pendingRefundRequests" :show-requests="$showRequests" />
    @endif
</div>
@endsection

