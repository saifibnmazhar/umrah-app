<div x-init="$store.currency.convertAll()" x-cloak>
    <div class="mb-3">
        <span class="text-sm text-gray-500 font-medium">Booking</span>
        <span class="text-sm text-gray-400 mx-1">></span>
        <span class="text-sm text-gray-700 font-semibold">Passenger Index</span>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="mb-4 flex flex-wrap items-end gap-4">
            <div class="flex flex-col min-w-[180px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Search</label>
                <input type="text" wire:model.live="search" x-ref="passengerSearchInput"
                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition"
                       placeholder="Search Name, Mobile, Passport, Invoice, Ticket, PNR...">
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Booking Date From</label>
                <input type="date" wire:model.live="bookingDateFrom"
                       class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Booking Date To</label>
                <input type="date" wire:model.live="bookingDateTo"
                       class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Actual Flight From</label>
                <input type="date" wire:model.live="actualFlightFrom"
                       class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Actual Flight To</label>
                <input type="date" wire:model.live="actualFlightTo"
                       class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Return Date From</label>
                <input type="date" wire:model.live="returnDateFrom"
                       class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Return Date To</label>
                <input type="date" wire:model.live="returnDateTo"
                       class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Fingerprint Status</label>
                <select wire:model.live="fingerprintStatus"
                        class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">All</option>
                    @foreach(($filterOptions['fingerprintStatuses'] ?? $fingerprintLocations) as $status)
                    @php
                        $statusValue = is_array($status) ? ($status['value'] ?? $status->value ?? '') : (is_object($status) ? $status->value : $status);
                        $statusLabel = is_array($status) ? ($status['label'] ?? ucfirst($statusValue)) : ucfirst($statusValue);
                    @endphp
                    <option value="{{ $statusValue }}" {{ ($fingerprintStatus ?? '') === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Visa Status</label>
                <select wire:model.live="visaStatus"
                        class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">All</option>
                    @foreach(($filterOptions['visaStatuses'] ?? []) as $status)
                    @php
                        $statusValue = is_array($status) ? ($status['value'] ?? $status->value ?? '') : (is_object($status) ? $status->value : $status);
                        $statusLabel = is_array($status) ? ($status['label'] ?? ucfirst($statusValue)) : ucfirst($statusValue);
                    @endphp
                    <option value="{{ $statusValue }}" {{ ($visaStatus ?? '') === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Ticket Status</label>
                <select wire:model.live="ticketStatus"
                        class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">All</option>
                    @foreach(($filterOptions['ticketStatuses'] ?? []) as $status)
                    <option value="{{ $status['value'] }}" {{ ($ticketStatus ?? '') === $status['value'] ? 'selected' : '' }}>{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Booking Status</label>
                <select wire:model.live="bookingStatus"
                        class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">All</option>
                    <option value="active" {{ ($bookingStatus ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="cancelled" {{ ($bookingStatus ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            @unless(auth()->user()->branch_id)
            <div class="flex flex-col min-w-[150px]">
                <label class="text-xs font-semibold text-slate-400 mb-1">Branch</label>
                <select wire:model.live="bookingBranchId"
                        class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                    <option value="">All Branches</option>
                    @foreach(($filterOptions['bookingBranches'] ?? []) as $branch)
                    <option value="{{ $branch['id'] }}" {{ ($bookingBranchId ?? '') == $branch['id'] ? 'selected' : '' }}>{{ $branch['name'] }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
            <button wire:click="resetFilters()"
                    class="px-3 py-2 border border-slate-300 rounded-lg hover:bg-slate-100 text-slate-600 transition text-sm">Clear</button>
        </div>

        @if($loading)
            <div class="px-4 py-8 text-center text-slate-500">Loading...</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Booking</th>
                            <th class="px-3 py-2 text-left font-medium">Passenger</th>
                            <th class="px-3 py-2 text-left font-medium">Passport</th>
                            <th class="px-3 py-2 text-left font-medium">Contact</th>
                            <th class="px-3 py-2 text-left font-medium">Visa</th>
                            <th class="px-3 py-2 text-left font-medium">Fingerprint</th>
                            <th class="px-3 py-2 text-left font-medium">Ticket</th>
                            <th class="px-3 py-2 text-left font-medium">Total</th>
                            <th class="px-3 py-2 text-left font-medium">Paid</th>
                            <th class="px-3 py-2 text-left font-medium">Due</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($passengers as $passenger)
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger['invoice_id'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger['full_name'] ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger['passport_no'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger['mobile_no'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger['visa_status'] ? ucfirst($passenger['visa_status']) : '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ ucfirst($passenger['fingerprint_status'] ?? 'pending') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger['ticket_data']['latest_issued_ticket'] ? 'Issued' : 'Pending' }}</td>
                            <td class="px-3 py-2 text-slate-700">@currency($passenger['invoice_total'] ?? 0, 2, $passenger['currency_rate'] ?? 0)</td>
                            <td class="px-3 py-2 text-slate-700">@currency($passenger['invoice_paid'] ?? 0, 2, $passenger['currency_rate'] ?? 0)</td>
                            <td class="px-3 py-2 text-slate-700">@currency($passenger['invoice_balance'] ?? 0, 2, $passenger['currency_rate'] ?? 0)</td>
                            <td class="px-3 py-2 text-slate-700">{{ $passenger['passenger_status'] ?? 'N/A' }}</td>
                            <td class="px-3 py-2">
                                <a href="{{ route('bookings.show', $passenger['booking_id'] ?? 0) }}" class="text-slate-600 hover:text-slate-800">View</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="12" class="px-3 py-4 text-center text-slate-500">No passengers found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(($pagination['last_page'] ?? 1) > 1)
                <div class="mt-4">
                    <span class="text-xs text-gray-600">
                        Page {{ $pagination['current_page'] ?? 1 }} of {{ $pagination['last_page'] ?? 1 }} ({{ $pagination['total'] ?? 0 }} records)
                    </span>
                    <div class="flex gap-2 mt-2">
                        <button wire:click="goToPage({{ ($pagination['current_page'] ?? 1) - 1 }})"
                                @if(($pagination['current_page'] ?? 1) <= 1) disabled @endif
                                class="px-3 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">
                            Previous
                        </button>
                        <button wire:click="goToPage({{ ($pagination['current_page'] ?? 1) + 1 }})"
                                @if(($pagination['current_page'] ?? 1) >= ($pagination['last_page'] ?? 1)) disabled @endif
                                class="px-3 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">
                            Next
                        </button>
                    </div>
                </div>
            @endif
        @endif

        <div class="mt-4 pt-3 border-t border-gray-200 flex justify-between items-center no-print">
            <span class="text-xs text-gray-400">Total Passenger - {{ $summary['totalPassengerCount'] ?? 0 }}</span>
        </div>
    </div>
</div>
