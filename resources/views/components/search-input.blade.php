@props(['placeholder' => 'Search...', 'model' => 'search'])
<input type="text" 
{{ $attributes->merge(['class' => 'w-full md:w-64 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition']) }}
placeholder="{{ $placeholder }}"
x-model="{{ $model }}">