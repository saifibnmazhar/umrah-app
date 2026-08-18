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
