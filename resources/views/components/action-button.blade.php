@props(['type' => 'primary', 'icon' => '', 'label' => ''])
<button type="button" {{ $attributes->class([
    'px-4 py-2 rounded-lg font-medium transition flex items-center gap-2',
    'bg-slate-700 text-white hover:bg-slate-800' => $type === 'primary',
    'bg-slate-100 text-slate-700 hover:bg-slate-200' => $type === 'secondary',
]) }}>
    @if($icon)
    {{ $icon }}
    @endif
    @if($label)
    {{ $label }}
    @endif
    {{ $slot }}
</button>