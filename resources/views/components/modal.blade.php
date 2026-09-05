@props(['open' => false, 'title' => ''])
<div x-show="{{ $open }}" x-cloak
x-transition:enter="transition ease-out duration-200"
x-transition:enter-start="opacity-0"
x-transition:enter-end="opacity-100"
x-transition:leave="transition ease-in duration-150"
x-transition:leave-start="opacity-100"
x-transition:leave-end="opacity-0"
class="fixed inset-0 z-50 overflow-y-auto"
role="dialog"
aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/50 modal-overlay" @click="{{ $attributes->get('close') ?? 'false' }}"></div>
        
        <div x-show="{{ $open }}" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="relative bg-white rounded-lg shadow-xl w-full modal-content"
        style="max-width: {{ $attributes->get('max-width') ?? 'max-w-lg' }}">
            @if($title)
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-700">{{ $title }}</h3>
                <button type="button" @click="{{ $attributes->get('close') ?? 'false' }}" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            @endif
            <div class="p-4">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>