@if($showPackages)
@php
    $packageChunks = $packages->chunk(6);
@endphp
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
