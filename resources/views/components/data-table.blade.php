@props(['headers' => []])
<div class="overflow-x-auto">
    <table class="w-full min-w-[800px] text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                @foreach($headers as $header)
                <th class="px-3 py-2 text-left font-medium">{{ $header }}</th>
                @endforeach
                {{ $thead ?? '' }}
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            {{ $slot }}
        </tbody>
    </table>
</div>