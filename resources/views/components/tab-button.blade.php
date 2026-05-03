@props(['name', 'active' => false])
<button type="button" 
{{ $attributes->merge(['class' => 'px-4 py-2 font-medium text-sm border-b-2 transition']) }}
:class="active ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
@click="{{ $name }} = '{{ $name }}'">
    {{ $slot }}
</button>