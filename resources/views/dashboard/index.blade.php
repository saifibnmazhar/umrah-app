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
    @php
        $reissueChunks = $reissueRequests->chunk(3);
        $addChunks = $addTicketRequests->chunk(3);
        $refundChunks = $refundRequests->chunk(3);
    @endphp
    <div class="mb-6" x-data="{ activeTab: 'reissue' }">
        <div class="flex border-b border-slate-200 mb-4">
            <button @click="activeTab = 'reissue'" :class="activeTab === 'reissue' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500'" class="px-4 py-2 font-medium text-sm border-b-2 transition">Re-Issue Requests</button>
            <button @click="activeTab = 'addticket'" :class="activeTab === 'addticket' ? 'border-purple-600 text-purple-600' : 'border-transparent text-slate-500'" class="px-4 py-2 font-medium text-sm border-b-2 transition">Add. Tkt Requests</button>
            <button @click="activeTab = 'refund'" :class="activeTab === 'refund' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-500'" class="px-4 py-2 font-medium text-sm border-b-2 transition">Refund Requests</button>
        </div>

        <div x-show="activeTab === 'reissue'" x-data="{ currentSlide: 0 }" x-cloak class="relative">
            @forelse($reissueChunks as $chunkIndex => $chunk)
            <div x-show="currentSlide === {{ $chunkIndex }}" x-cloak class="space-y-3">
                @foreach($chunk as $request)
                <a href="{{ route('re-issues.confirmation', $request['booking_id']) }}" class="block bg-white rounded-lg shadow p-4 hover:bg-slate-50 transition border-l-4 border-blue-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-slate-800">{{ $request['invoice_no'] }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-slate-500">{{ $request['branch'] }}</span>
                            <span class="text-sm text-slate-500">{{ $request['passenger_count'] }} passenger(s)</span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            @empty
            <div class="text-center py-8 text-slate-500">No re-issue requests</div>
            @endforelse
            @if($reissueChunks->count() > 1)
            <div class="flex items-center justify-end gap-3 mt-3">
                <button @click="currentSlide = Math.max(0, currentSlide - 1)" :disabled="currentSlide === 0" :class="currentSlide === 0 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:text-slate-800'" class="p-1.5 rounded-lg hover:bg-slate-100 transition disabled:hover:bg-transparent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <span class="text-sm text-slate-500 tabular-nums" x-text="`${currentSlide + 1} / {{ $reissueChunks->count() }}`"></span>
                <button @click="currentSlide = Math.min({{ $reissueChunks->count() - 1 }}, currentSlide + 1)" :disabled="currentSlide === {{ $reissueChunks->count() - 1 }}" :class="currentSlide === {{ $reissueChunks->count() - 1 }} ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:text-slate-800'" class="p-1.5 rounded-lg hover:bg-slate-100 transition disabled:hover:bg-transparent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
            <div class="flex justify-center mt-4 gap-2">
                @foreach($reissueChunks as $dotIndex => $dotChunk)
                <button @click="currentSlide = {{ $dotIndex }}" :class="currentSlide === {{ $dotIndex }} ? 'bg-blue-500' : 'bg-slate-300'" class="w-2 h-2 rounded-full transition-colors hover:bg-blue-400"></button>
                @endforeach
            </div>
            @endif
        </div>

        <div x-show="activeTab === 'addticket'" x-data="{ currentSlide: 0 }" x-cloak class="relative">
            @forelse($addChunks as $chunkIndex => $chunk)
            <div x-show="currentSlide === {{ $chunkIndex }}" x-cloak class="space-y-3">
                @foreach($chunk as $request)
                <a href="{{ route('tickets.add-confirmation', $request['booking_id']) }}" class="block bg-white rounded-lg shadow p-4 hover:bg-slate-50 transition border-l-4 border-purple-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-slate-800">{{ $request['invoice_no'] }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-slate-500">{{ $request['branch'] }}</span>
                            <span class="text-sm text-slate-500">{{ $request['passenger_count'] }} passenger(s)</span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            @empty
            <div class="text-center py-8 text-slate-500">No additional ticket requests</div>
            @endforelse
            @if($addChunks->count() > 1)
            <div class="flex items-center justify-end gap-3 mt-3">
                <button @click="currentSlide = Math.max(0, currentSlide - 1)" :disabled="currentSlide === 0" :class="currentSlide === 0 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:text-slate-800'" class="p-1.5 rounded-lg hover:bg-slate-100 transition disabled:hover:bg-transparent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <span class="text-sm text-slate-500 tabular-nums" x-text="`${currentSlide + 1} / {{ $addChunks->count() }}`"></span>
                <button @click="currentSlide = Math.min({{ $addChunks->count() - 1 }}, currentSlide + 1)" :disabled="currentSlide === {{ $addChunks->count() - 1 }}" :class="currentSlide === {{ $addChunks->count() - 1 }} ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:text-slate-800'" class="p-1.5 rounded-lg hover:bg-slate-100 transition disabled:hover:bg-transparent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
            <div class="flex justify-center mt-4 gap-2">
                @foreach($addChunks as $dotIndex => $dotChunk)
                <button @click="currentSlide = {{ $dotIndex }}" :class="currentSlide === {{ $dotIndex }} ? 'bg-purple-500' : 'bg-slate-300'" class="w-2 h-2 rounded-full transition-colors hover:bg-purple-400"></button>
                @endforeach
            </div>
            @endif
        </div>

        <div x-show="activeTab === 'refund'" x-data="{ currentSlide: 0 }" x-cloak class="relative">
            @forelse($refundChunks as $chunkIndex => $chunk)
            <div x-show="currentSlide === {{ $chunkIndex }}" x-cloak class="space-y-3">
                @foreach($chunk as $request)
                <a href="{{ route('refunds.confirmation', $request['booking_id']) }}" class="block bg-white rounded-lg shadow p-4 hover:bg-slate-50 transition border-l-4 border-orange-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold text-slate-800">{{ $request['invoice_no'] }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-slate-500">{{ $request['branch'] }}</span>
                            <span class="text-sm text-slate-500">{{ $request['passenger_count'] }} passenger(s)</span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            @empty
            <div class="text-center py-8 text-slate-500">No refund requests</div>
            @endforelse
            @if($refundChunks->count() > 1)
            <div class="flex items-center justify-end gap-3 mt-3">
                <button @click="currentSlide = Math.max(0, currentSlide - 1)" :disabled="currentSlide === 0" :class="currentSlide === 0 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:text-slate-800'" class="p-1.5 rounded-lg hover:bg-slate-100 transition disabled:hover:bg-transparent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <span class="text-sm text-slate-500 tabular-nums" x-text="`${currentSlide + 1} / {{ $refundChunks->count() }}`"></span>
                <button @click="currentSlide = Math.min({{ $refundChunks->count() - 1 }}, currentSlide + 1)" :disabled="currentSlide === {{ $refundChunks->count() - 1 }}" :class="currentSlide === {{ $refundChunks->count() - 1 }} ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:text-slate-800'" class="p-1.5 rounded-lg hover:bg-slate-100 transition disabled:hover:bg-transparent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
            <div class="flex justify-center mt-4 gap-2">
                @foreach($refundChunks as $dotIndex => $dotChunk)
                <button @click="currentSlide = {{ $dotIndex }}" :class="currentSlide === {{ $dotIndex }} ? 'bg-orange-500' : 'bg-slate-300'" class="w-2 h-2 rounded-full transition-colors hover:bg-orange-400"></button>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
@endpush