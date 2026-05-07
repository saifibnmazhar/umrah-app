@props(['title', 'value', 'subtitle' => '', 'color' => 'blue'])
<div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
    <div class="flex justify-between items-center mb-2">
        <h3 class="text-sm font-semibold text-slate-600">{{ $title }}</h3>
        @if(isset($icon))
        <div class="w-10 h-10 rounded-full bg-{{ $color }}-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-{{ $color }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {{ $icon }}
            </svg>
        </div>
        @endif
    </div>
    <div class="text-2xl font-bold text-slate-800">{{ $value }}</div>
    @if($subtitle)
    <div class="text-xs font-medium text-{{ $color }}-600 mt-1">{{ $subtitle }}</div>
    @endif
</div>