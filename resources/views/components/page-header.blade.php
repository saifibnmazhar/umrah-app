@props(['title', 'subtitle' => ''])
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-slate-800">{{ $title }}</h2>
    @if($subtitle)
    <span class="text-sm text-slate-500">{{ $subtitle }}</span>
    @endif
</div>