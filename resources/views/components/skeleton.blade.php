@props(['rows' => 5])
<div class="animate-pulse space-y-4">
    <div class="h-4 bg-slate-200 rounded w-1/4"></div>
    @for($i = 0; $i < $rows; $i++)
    <div class="h-12 bg-slate-100 rounded"></div>
    @endfor
</div>