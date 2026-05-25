@extends('layouts.app')
@section('title', 'Booking')
@section('content')
<div class="max-w-7xl mx-auto" x-data="bookingIndexApp()">
    <div class="flex justify-between items-center mb-6">
        @php
            $canCreateBooking = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Branch Manager', 'Branch Staff'])->isNotEmpty();
            $canViewFinancialColumns = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Auditor'])->isNotEmpty();
            $canEditInline = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty();
        @endphp
        <h1 class="text-2xl font-bold text-slate-800">Booking</h1>
        @if($canCreateBooking)
        <a href="{{ route('bookings.create') }}" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Booking
        </a>
        @endif
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ session('error') }}
    </div>
    @endif

    <div class="flex gap-2 mb-4">
        <button @click="activeTab = 'booking'" :class="activeTab === 'booking' ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'" class="px-4 py-2 rounded-lg font-medium transition">Booking Index</button>
        <button @click="activeTab = 'passenger'" :class="activeTab === 'passenger' ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'" class="px-4 py-2 rounded-lg font-medium transition">Passenger Index</button>
    </div>

    <div x-show="activeTab === 'booking'" x-cloak>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="mb-4">
                <input type="text" x-model="searchTerm" class="w-full md:w-64 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition" placeholder="Search by Mobile or Invoice No...">
            </div>
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
                            <th class="px-3 py-2 text-left font-medium">Office</th>
                            <th class="px-3 py-2 text-left font-medium">District</th>
                            <th class="px-3 py-2 text-left font-medium">Package</th>
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Total</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Paid</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Due</th>@endif
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($bookings as $booking)
                        <tr>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->invoice_id ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->customer->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->customer->mobile_no ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->passengers->count() }}</td>
                            <td class="px-3 py-2">
                                @if($canEditInline)
                                <select
                                    class="text-sm border border-slate-300 rounded px-2 py-1 bg-white focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
                                    data-original="{{ $booking->fingerprint_location?->value ?? 'office' }}"
                                    onchange="updateFingerprintLocation({{ $booking->id }}, this.value, this)">
                                    <option value="home" {{ ($booking->fingerprint_location?->value ?? '') === 'home' ? 'selected' : '' }}>Home</option>
                                    <option value="office" {{ ($booking->fingerprint_location?->value ?? '') === 'office' ? 'selected' : '' }}>Office</option>
                                </select>
                                @else
                                <span class="text-slate-700">{{ ucfirst($booking->fingerprint_location?->value ?? 'Office') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->office->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->district->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $booking->package->package_name ?? 'N/A' }}</td>
                            @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">{{ $booking->invoice?->total_amount ?? 0 }} SAR</td>@endif
                            @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">{{ $booking->invoice?->paid_amount ?? 0 }} SAR</td>@endif
                            @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">{{ $booking->invoice?->balance ?? 0 }} SAR</td>@endif
                            <td class="px-3 py-2">
                                <a href="{{ route('bookings.show', $booking->id) }}" class="text-slate-600 hover:text-slate-800">View</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $canViewFinancialColumns ? 13 : 10 }}" class="px-3 py-4 text-center text-slate-500">No bookings found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'passenger'" x-cloak>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1800px] text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Booking Date</th>
                            <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                            <th class="px-3 py-2 text-left font-medium">Customer</th>
                            <th class="px-3 py-2 text-left font-medium">PAX QTY</th>
                            <th class="px-3 py-2 text-left font-medium">Mobile</th>
                            <th class="px-3 py-2 text-left font-medium">Name</th>
                            <th class="px-3 py-2 text-left font-medium">Current status</th>
                            <th class="px-3 py-2 text-left font-medium">Passport No</th>
                            <th class="px-3 py-2 text-left font-medium">Route</th>
                            <th class="px-3 py-2 text-left font-medium">Required Flight Date</th>
                            <th class="px-3 py-2 text-left font-medium">Actual Flight Date</th>
                            <th class="px-3 py-2 text-left font-medium">Package</th>
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Package Value</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Total Cost</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Markup (Profit)</th>@endif
                            @if($canViewFinancialColumns)<th class="px-3 py-2 text-left font-medium">Due</th>@endif
                            <th class="px-3 py-2 text-left font-medium">Ticket Fare</th>
                            <th class="px-3 py-2 text-left font-medium">Ticket Status</th>
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
@php $lastBookingId = null; @endphp
@forelse($passengers as $passenger)
@php
$isFirstRow = ($lastBookingId !== $passenger->booking_id);
$lastBookingId = $passenger->booking_id;

$route = $passenger->ticketFare?->route ?? $passenger->booking?->package?->ticketFare?->route;
$routeDisplay = '—';
if ($route) {
    $routeType = $route->route_type?->value;
    if ($routeType === 'multi_city') {
        $routeDisplay = $route->multiSegments->map(fn($s) => ($s->fromCity?->code ?? '?') . '-' . ($s->toCity?->code ?? '?'))->implode(', ');
    } elseif ($routeType === 'round') {
        $routeDisplay = ($route->fromCity?->code ?? '?') . '-' . ($route->toCity?->code ?? '?') . '-' . ($route->returnCity?->code ?? '?');
    } else {
        $routeDisplay = ($route->fromCity?->code ?? '?') . ' → ' . ($route->toCity?->code ?? '?');
    }
}
@endphp
<tr>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->booking?->created_at?->format('d M Y') ?? '—' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->booking?->invoice_id ?? '—' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->booking->customer->name ?? 'N/A' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $isFirstRow ? ($passenger->booking?->pax_qty ?? '—') : '' }}</td>
    <td class="px-3 py-2 text-slate-700">
        <div class="leading-tight">
            <span>{{ $passenger->booking?->customer?->mobile_no ?? '—' }}</span><br>
            <span>{{ $passenger->mobile_no ?? '—' }}</span>
        </div>
    </td>
    <td class="px-3 py-2 text-slate-700">{{ trim($passenger->first_name . ' ' . $passenger->last_name) ?: '—' }}</td>
    <td class="px-3 py-2">
        @if($canEditInline)
        <select
            class="text-sm border border-slate-300 rounded px-2 py-1 bg-white focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none"
            onchange="updatePassengerStatus({{ $passenger->id }}, this.value)">
            <option value="" {{ is_null($passenger->passenger_status_id) ? 'selected' : '' }}>None</option>
            @foreach($passengerStatuses as $status)
                <option value="{{ $status->id }}" {{ $passenger->passenger_status_id == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
            @endforeach
        </select>
        @else
        <span class="text-slate-700">{{ $passengerStatuses->firstWhere('id', $passenger->passenger_status_id)->name ?? 'None' }}</span>
        @endif
    </td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->passport_no ?? '—' }}</td>
    <td class="px-3 py-2 text-slate-600">{{ $routeDisplay }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->flight_date_from?->format('d M Y') . ' → ' . $passenger->flight_date_to?->format('d M Y') ?? '—' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ optional($passenger->actual_flight_date)->format('d M Y') ?: 'N/A' }}</td>
    <td class="px-3 py-2 text-slate-700">{{ $passenger->booking?->package?->package_name ?? '—' }}</td>
    @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">{{ $passenger->package_value ? number_format($passenger->package_value, 2) . ' SAR' : '—' }}</td>@endif
    @if($canViewFinancialColumns)<td class="px-3 py-2"></td>@endif
    @if($canViewFinancialColumns)<td class="px-3 py-2"></td>@endif
    @if($canViewFinancialColumns)<td class="px-3 py-2 text-slate-700">{{ $isFirstRow ? ($passenger->booking?->invoice?->balance ? number_format($passenger->booking->invoice->balance, 2) . ' SAR' : '—') : '' }}</td>@endif
    @php $ticketFareAmount = $passenger->ticketFare?->selling_fare ?? $passenger->ticketFare?->net_fare; @endphp
    <td class="px-3 py-2">
        <div class="flex items-center gap-2">
            @if($ticketFareAmount)
                <span class="text-slate-800 font-medium whitespace-nowrap">{{ number_format((float)$ticketFareAmount, 2) }} SAR</span>
            @else
                <span class="text-slate-400">—</span>
            @endif
            <div class="flex gap-1 ml-auto">
                <button @click="openTicketFareModal({{ $passenger->id }}, 'issue')"
                    class="text-xs text-slate-500 hover:text-slate-700 hover:bg-slate-100 px-2 py-0.5 rounded transition">Issue</button>
                <button @click="openTicketFareModal({{ $passenger->id }}, 'edit')"
                    class="text-xs text-slate-500 hover:text-slate-700 hover:bg-slate-100 px-2 py-0.5 rounded transition">Edit</button>
            </div>
        </div>
    </td>
    @php
        $ts = $passenger->ticket_status?->value;
        $ticketStatusColorMap = ['pending' => 'bg-yellow-100 text-yellow-700', 'issued' => 'bg-green-100 text-green-700', 're-issued' => 'bg-purple-100 text-purple-700', 'refunded' => 'bg-red-100 text-red-700'];
        $tsBadgeClass = $ticketStatusColorMap[$ts] ?? 'bg-slate-100 text-slate-600';
    @endphp
    <td class="px-3 py-2">
        @if($ts)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tsBadgeClass }}">{{ ucwords(str_replace('-', ' ', $ts)) }}</span>
        @else
            <span class="text-slate-400">—</span>
        @endif
    </td>
    <td class="px-3 py-2">
        <a href="{{ route('passengers.show', $passenger->id) }}" class="text-slate-600 hover:text-slate-800">View</a>
    </td>
</tr>
@empty
<tr>
    <td colspan="{{ $canViewFinancialColumns ? 19 : 15 }}" class="px-3 py-4 text-center text-slate-500">No passengers found</td>
</tr>
@endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $passengers->links() }}
            </div>
        </div>
    </div>

    <!-- Ticket Fare Modal -->
    <div x-show="isTicketFareModalOpen" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50" @click="closeTicketFareModal()"></div>
        <div x-show="isTicketFareModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-4"
            class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4" x-text="ticketFareModalMode === 'edit' ? 'Edit Ticket' : 'Issue Ticket'"></h3>
            <form @submit.prevent="closeTicketFareModal()">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type</label>
                    <select x-model="ticketFareForm.ticketType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select</option>
                        <option value="regular">Regular</option>
                        <option value="offer">Offer</option>
                        <option value="group">Group</option>
                    </select>
                </div>

                <div x-show="ticketFareForm.ticketType === 'group'" class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Group Ticket</label>
                    <select x-model="ticketFareForm.groupTicketId" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                        <option value="">Select Ticket</option>
                    </select>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Ticket Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                            <select x-model="ticketFareForm.routeType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select</option>
                                <option value="One Way-Inbound">One Way-Inbound</option>
                                <option value="One Way-Outbound">One Way-Outbound</option>
                                <option value="Round">Round</option>
                                <option value="Multi City">Multi City</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                            <select x-model="ticketFareForm.flightType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select</option>
                                <option value="Transit">Transit</option>
                                <option value="Direct">Direct</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date *</label>
                            <input type="date" x-model="ticketFareForm.upDate" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date *</label>
                            <input type="date" x-model="ticketFareForm.downDate" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">PNR</label>
                            <input type="text" x-model="ticketFareForm.pnr" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter PNR">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Number *</label>
                            <input type="text" x-model="ticketFareForm.ticketNumber" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter Ticket Number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date *</label>
                            <input type="date" x-model="ticketFareForm.date" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Agent *</label>
                            <select x-model="ticketFareForm.ticketAgent" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="">Select Agent</option>
                                <option value="Al-Reem">Al-Reem</option>
                                <option value="Nasser">Nasser</option>
                                <option value="Al-Masria">Al-Masria</option>
                                <option value="Umrah Plus">Umrah Plus</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Travel Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route *</label>
                            <input type="text" x-model="ticketFareForm.route" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                            <input type="text" x-model="ticketFareForm.airline" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Class *</label>
                            <input type="text" x-model="ticketFareForm.travelClass" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Passenger Type</label>
                            <input type="text" x-model="ticketFareForm.passengerType" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Fare Calculation</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR)</label>
                            <input type="number" x-model="ticketFareForm.sellingFare" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (SAR)</label>
                            <input type="number" x-model="ticketFareForm.netFare" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0">
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Baggage Info</h4>
                    <div class="mb-4">
                        <h5 class="text-sm font-medium text-slate-700 mb-2">Inbound</h5>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Adult</label>
                                <input type="number" x-model="ticketFareForm.inboundAdult" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Child</label>
                                <input type="number" x-model="ticketFareForm.inboundChild" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Infant</label>
                                <input type="number" x-model="ticketFareForm.inboundInfant" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0">
                            </div>
                        </div>
                    </div>
                    <div>
                        <h5 class="text-sm font-medium text-slate-700 mb-2">Outbound</h5>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Adult</label>
                                <input type="number" x-model="ticketFareForm.outboundAdult" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Child</label>
                                <input type="number" x-model="ticketFareForm.outboundChild" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-600 mb-1">Infant</label>
                                <input type="number" x-model="ticketFareForm.outboundInfant" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Ticket Options</h4>
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="ticketFareForm.nonRefundable" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                            <span class="text-sm text-slate-700">Non-Refundable</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="ticketFareForm.nonExchangeable" class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
                            <span class="text-sm text-slate-700">Non-Exchangeable</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                        <span x-text="ticketFareModalMode === 'edit' ? 'Update' : 'Save'"></span>
                    </button>
                    <button type="button" @click="closeTicketFareModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function bookingIndexApp() {
    return {
        activeTab: '{{ $tab ?? 'booking' }}',
        searchTerm: '',
        isTicketFareModalOpen: false,
        ticketFareModalMode: 'issue',
        selectedPassengerId: null,
        ticketFareForm: {
            ticketType: '', routeType: '', flightType: '',
            upDate: '', downDate: '', pnr: '', ticketNumber: '',
            date: '', ticketAgent: '', route: '', airline: '',
            travelClass: '', passengerType: '',
            sellingFare: 0, netFare: 0,
            inboundAdult: 30, inboundChild: 30, inboundInfant: 0,
            outboundAdult: 50, outboundChild: 50, outboundInfant: 0,
            nonRefundable: false, nonExchangeable: false, groupTicketId: '',
        },
        openTicketFareModal(passengerId, mode) {
            this.ticketFareModalMode = mode;
            this.selectedPassengerId = passengerId;
            this.ticketFareForm = {
                ticketType: '', routeType: '', flightType: '',
                upDate: '', downDate: '', pnr: '', ticketNumber: '',
                date: '', ticketAgent: '', route: '', airline: '',
                travelClass: '', passengerType: '',
                sellingFare: 0, netFare: 0,
                inboundAdult: 30, inboundChild: 30, inboundInfant: 0,
                outboundAdult: 50, outboundChild: 50, outboundInfant: 0,
                nonRefundable: false, nonExchangeable: false, groupTicketId: '',
            };
            this.isTicketFareModalOpen = true;
        },
        closeTicketFareModal() {
            this.isTicketFareModalOpen = false;
            this.selectedPassengerId = null;
        },
    }
}

function updatePassengerStatus(passengerId, statusId) {
    fetch(`/passengers/${passengerId}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ passenger_status_id: statusId || null })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Status updated successfully');
        } else {
            alert('Failed to update status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update status');
    });
}

function updateFingerprintLocation(bookingId, location, select) {
    const selectEl = select || event.target;
    const originalValue = selectEl.dataset.original;

    fetch(`/bookings/${bookingId}/fingerprint-location`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ fingerprint_location: location })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            selectEl.dataset.original = location;
            if (data.invoice) {
                const row = selectEl.closest('tr');
                const cells = row.querySelectorAll('td');
                @if($canViewFinancialColumns)
                if (cells.length >= 12) {
                    cells[9].textContent = data.invoice.total_amount + ' SAR';
                    cells[10].textContent = data.invoice.paid_amount + ' SAR';
                    cells[11].textContent = data.invoice.balance + ' SAR';
                }
                @endif
            }
        } else {
            alert('Failed to update fingerprint location');
            selectEl.value = originalValue;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update fingerprint location');
        selectEl.value = originalValue;
    });
}
</script>
@endsection