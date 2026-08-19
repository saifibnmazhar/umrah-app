<div x-init="$store.currency.convertAll()" x-cloak>
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Booking</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Booking Index</span>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="mb-4 flex flex-wrap items-center gap-4">
            <input type="text" wire:model.live="search" x-ref="searchInput"
                   class="w-full md:w-64 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition"
                   placeholder="Search by Mobile or Invoice No...">
            <input type="date" wire:model.live="bookingDateFrom"
                   class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            <input type="date" wire:model.live="bookingDateTo"
                   class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            <select wire:model.live="fingerprintLocation"
                    class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                <option value="">Fingerprint Location</option>
                @foreach($fingerprintLocations as $location)
                <option value="{{ $location->value }}" {{ ($fingerprintLocation ?? '') === $location->value ? 'selected' : '' }}>{{ ucfirst($location->value) }}</option>
                @endforeach
            </select>
            <select wire:model.live="bookingStatus"
                    class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                <option value="">Booking Status</option>
                <option value="active" {{ ($bookingStatus ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="cancellation_processing" {{ ($bookingStatus ?? '') === 'cancellation_processing' ? 'selected' : '' }}>Cancellation Processing</option>
                <option value="cancelled" {{ ($bookingStatus ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            @unless(auth()->user()->branch_id)
            <select wire:model.live="branchId"
                    class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                <option value="">All Branches</option>
                @foreach($branches as $branch)
                <option value="{{ $branch['id'] }}" {{ ($branchId ?? '') == $branch['id'] ? 'selected' : '' }}>{{ $branch['name'] }}</option>
                @endforeach
            </select>
            @endunless
            <button wire:click="resetFilters()"
                    class="px-3 py-2 border border-slate-300 rounded-lg hover:bg-slate-100 text-slate-600 transition text-sm">Clear</button>
            <span class="flex-1 min-w-0"></span>
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 text-white font-semibold rounded-lg whitespace-nowrap shadow-sm">
                Total Booking - {{ $summary['totalBookingCount'] }}
            </span>
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 text-white font-semibold rounded-lg whitespace-nowrap shadow-sm">
                Total Passenger - {{ $summary['totalBookingPassengerCount'] }}
            </span>
        </div>

        @if($loading)
            <div class="px-4 py-8 text-center text-slate-500">Loading...</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                            <th class="px-3 py-2 text-left font-medium">Booking Date</th>
                            <th class="px-3 py-2 text-left font-medium">Customer</th>
                            <th class="px-3 py-2 text-left font-medium">Mobile</th>
                            <th class="px-3 py-2 text-left font-medium">Passengers</th>
                            <th class="px-3 py-2 text-left font-medium">Fingerprint Location</th>
                            <th class="px-3 py-2 text-left font-medium">Booking Branch</th>
                            <th class="px-3 py-2 text-left font-medium">Fingerprint Branch</th>
                            <th class="px-3 py-2 text-left font-medium">District</th>
                            <th class="px-3 py-2 text-left font-medium">Package</th>
                            <th class="px-3 py-2 text-left font-medium">Total</th>
                            <th class="px-3 py-2 text-left font-medium">Paid</th>
                            <th class="px-3 py-2 text-left font-medium">Due</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            @if($summary['canViewActionColumn'] ?? true)<th class="px-3 py-2 text-left font-medium">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($bookings as $booking)
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $booking['invoice_id'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking['booking_date'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking['customer_name'] ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking['customer_mobile'] ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking['pax_qty'] ?? 0 }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $canEditFingerprint = $booking['can_edit_fingerprint'] ?? false;
                                    $isFingerprintAdmin = auth()->user()->hasRole('Fingerprint Admin');
                                    $fpLocation = $booking['fingerprint_location'] ?? 'office';
                                @endphp
                                @if($canEditFingerprint && !($isFingerprintAdmin && $fpLocation === 'home'))
                                    <select
                                        class="text-sm border border-slate-300 rounded px-2 py-1 bg-white focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
                                        data-original="{{ $fpLocation }}"
                                        data-rate="{{ $booking['currency_rate'] ?? 0 }}"
                                        onchange="updateFingerprintLocation({{ $booking['id'] }}, this.value, this)">
                                        <option value="home" {{ $fpLocation === 'home' ? 'selected' : '' }}>Home</option>
                                        <option value="office" {{ $fpLocation === 'office' ? 'selected' : '' }}>Office</option>
                                    </select>
                                @else
                                    <span class="text-slate-700">{{ ucfirst($fpLocation) }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking['booking_branch'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking['fingerprint_branch'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking['district'] ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking['package'] ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">@currency($booking['invoice_total'] ?? 0, 2, $booking['currency_rate'] ?? 0)</td>
                            <td class="px-3 py-2 text-slate-700">@currency($booking['invoice_paid'] ?? 0, 2, $booking['currency_rate'] ?? 0)</td>
                            <td class="px-3 py-2 text-slate-700">@currency($booking['invoice_balance'] ?? 0, 2, $booking['currency_rate'] ?? 0)</td>
                            <td class="px-3 py-2">
                                @if($booking['is_cancelled'])
                                    @if($booking['cancelled_status'] === 'cancellation processing')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Cancellation Processing
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Cancelled
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <a href="{{ route('bookings.show', $booking['id']) }}" class="text-slate-600 hover:text-slate-800">View</a>
                                @if($booking['can_cancel'] ?? false)
                                <button onclick="openCancelModal({{ $booking['id'] }})"
                                        class="text-orange-600 hover:text-orange-800 font-medium ml-3">
                                    Cancel
                                </button>
                                @endif
                                @if($booking['can_delete'] ?? false)
                                <form method="POST" action="{{ route('bookings.destroy', $booking['id']) }}"
                                      onsubmit="return confirm('Are you sure you want to delete this booking?')" class="inline ml-3">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="15" class="px-3 py-4 text-center text-slate-500">No bookings found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($pagination['last_page'] > 1)
                <div class="mt-4">
                    <span class="text-xs text-gray-600">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }} ({{ $pagination['total'] }} records)
                    </span>
                    <div class="flex gap-2 mt-2">
                        <button wire:click="goToPage({{ $pagination['current_page'] - 1 }})"
                                @if($pagination['current_page'] <= 1) disabled @endif
                                class="px-3 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">
                            Previous
                        </button>
                        <button wire:click="goToPage({{ $pagination['current_page'] + 1 }})"
                                @if($pagination['current_page'] >= $pagination['last_page']) disabled @endif
                                class="px-3 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">
                            Next
                        </button>
                    </div>
                </div>
            @endif

            <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center no-print">
                <span class="text-xs text-gray-400">Generated by BM Umrah System</span>
            </div>
        @endif
    </div>
</div>
