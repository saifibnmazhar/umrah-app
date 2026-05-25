@props(['status' => 'pending'])
@php
$colorMap = [
    'pending' => 'yellow',
    'issued' => 'emerald',
    'done' => 'emerald',
    'processing' => 'blue',
    'submitted' => 'blue',
    'cancel' => 'red',
    'cancelled' => 'red',
    'return' => 'red',
    'returned' => 'red',
    're-issued' => 'purple',
    'refunded' => 'red',
    'none' => 'slate',
    'hold' => 'slate',
];
$color = $colorMap[strtolower($status)] ?? 'slate';
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
    {{ $status }}
</span>