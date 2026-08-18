<div>
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden mb-4">
        <div class="p-4 flex flex-wrap gap-4 items-end">
            <div class="min-w-[150px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                <select wire:model.live="statusFilter" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div>
                <button wire:click="resetFilters"
                      class="px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-600 transition text-sm font-medium">
                    Clear
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Package Name</th>
                        <th class="px-3 py-2 text-left font-medium">Ticket</th>
                        <th class="px-3 py-2 text-right font-medium">Regular Price</th>
                        <th class="px-3 py-2 text-right font-medium">Offer Price</th>
                        <th class="px-3 py-2 text-center font-medium">Status</th>
                        <th class="px-3 py-2 text-center font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($packages as $package)
                        @php
                            if ($package->is_double_ticket) {
                                $inboundRoute = $package->ticketFareInbound?->route;
                                $outboundRoute = $package->ticketFareOutbound?->route;
                                $inboundName = ($inboundRoute ? ($inboundRoute->fromCity?->code ?? '?') . ' → ' . ($inboundRoute->toCity?->code ?? '?') : '?');
                                $outboundName = ($outboundRoute ? ($outboundRoute->fromCity?->code ?? '?') . ' → ' . ($outboundRoute->toCity?->code ?? '?') : '?');
                                $ticketDisplay = $inboundName . ' + ' . $outboundName
                                    . ' | SAR ' . number_format(($package->ticketFareInbound?->selling_fare ?? 0) + ($package->ticketFareOutbound?->selling_fare ?? 0), 0);
                            } else {
                                $route = $package->ticketFare?->route;
                                if ($route && $route->multiSegments && $route->multiSegments->count() > 0) {
                                    $routeName = $route->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
                                } elseif ($route && $route->returnCity) {
                                    $routeName = ($route->fromCity?->code ?? '?') . ' - ' . ($route->toCity?->code ?? '?') . ' - ' . ($route->returnCity?->code ?? '?');
                                } else {
                                    $routeName = ($route?->fromCity?->code ?? '?') . ' → ' . ($route?->toCity?->code ?? '?');
                                }
                                $seats = $package->ticketFare?->groupTicket?->ticket_qty ?? null;
                                $ticketType = $package->ticketFare?->ticket_type?->value;
                                $ticketDisplay = $routeName . ' | ' . strtoupper($ticketType ?? '?') . ' | SAR ' . number_format($package->ticketFare?->selling_fare ?? 0, 0);
                                if ($ticketType === 'offer') {
                                    $ticketDisplay .= ' | SAR ' . number_format($package->ticketFare?->offer_price ?? 0, 0);
                                }
                                if ($ticketType === 'group' && $seats) {
                                    $ticketDisplay .= ' | ' . $seats . ' seats';
                                }
                            }
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-800 font-medium">{{ $package->package_name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $ticketDisplay }}</td>
                            <td class="px-3 py-2 text-right text-slate-800 font-medium">@currency($package->regular_price, 0)</td>
                            <td class="px-3 py-2 text-right text-slate-600">
                                @if($package->offer_price)
                                    @currency($package->offer_price, 0)
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <form method="POST" action="{{ route('packages.toggle-active', $package->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $package->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $package->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                </form>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $package->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $package->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($package->bookings_count > 0)
                                    <button class="text-xs text-slate-400 cursor-not-allowed mr-3" title="Has existing bookings" disabled>Edit</button>
                                    <button class="text-xs text-red-400 cursor-not-allowed" title="Has existing bookings" disabled>Delete</button>
                                @else
                                    <button onclick="editPackage({{ $package->id }})" class="text-xs text-slate-600 hover:text-slate-800 mr-3">Edit</button>
                                    <form method="POST" action="{{ route('packages.destroy', $package->id) }}" onsubmit="return confirm('Are you sure you want to delete this package?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">
                                No packages configured yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-center">
            {{ $packages->links() }}
        </div>
    </div>
</div>
