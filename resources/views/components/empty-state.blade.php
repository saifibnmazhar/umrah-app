@props(['title' => '', 'message' => 'No records found', 'icon' => ''])
<div class="text-center py-8 text-slate-500">
    @if($icon)
    <div class="mb-4">{!! $icon !!}</div>
    @endif
    @if($title)
    <h3 class="text-lg font-medium text-slate-700 mb-2">{{ $title }}</h3>
    @endif
    <p>{{ $message }}</p>
    {{ $slot }}
</div>