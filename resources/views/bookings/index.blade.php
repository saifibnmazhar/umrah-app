@extends('layouts.app')
@section('title', 'Booking')
@section('content')
@php
$passengersVisaData = $passengers->map(fn($p) => [
    'id' => $p->id,
    'visa' => $p->visaSubmission ? [
        'agent' => $p->visaSubmission?->visaAgent?->name ?? '',
        'visa_number' => $p->visaSubmission?->visa_number ?? '',
        'selling_price' => (float)($p->visaSubmission?->selling_price ?? 0),
        'agent_commission' => (float)($p->visaSubmission?->agent_commission ?? 0),
        'net_visa_cost' => (float)($p->visaSubmission?->net_visa_cost ?? 0),
        'additional_cost' => (float)($p->visaSubmission?->additional_cost ?? 0),
        'remarks' => $p->visaSubmission?->remarks ?? '',
        'final_cost' => (float)($p->visaSubmission?->final_cost ?? 0),
        'commission_agent' => $p->visaSubmission?->commissionAgent?->name ?? '',
    ] : null,
    'visa_status' => $p->visa_status?->value ?? null,
])->values();
@endphp
<div class="w-full mx-auto" x-data="bookingIndexApp()">
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
                            <th class="px-3 py-2 text-left font-medium">Branch(BD)</th>
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
                            <th class="px-3 py-2 text-left font-medium">Visa</th>
                            <th class="px-3 py-2 text-left font-medium">Visa Agent</th>
                            <th class="px-3 py-2 text-left font-medium">Visa Status</th>
                            <th class="px-3 py-2 text-left font-medium">Ticket Fare</th>
                            <th class="px-3 py-2 text-left font-medium">Ticket Status</th>
                            <th class="px-3 py-2 text-left font-medium">Fingerprint Status</th>
                            <th class="px-3 py-2 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
@php $lastBookingId = null; @endphp
@forelse($passengers as $passenger)
@php
$isFirstRow = ($lastBookingId !== $passenger->booking_id);
$lastBookingId = $passenger->booking_id;
$visaSubmission = $passenger->visaSubmission;

$ticketFare = $passenger->ticketFare;
$baseFare = $ticketFare?->selling_fare ?? $ticketFare?->net_fare ?? 0;
$passengerTypeVal = $passenger->passenger_type?->value;
$fareAmount = match($passengerTypeVal) {
    'child' => $baseFare * ($ticketFare?->child_fare_percentage ?? 75) / 100,
    'infant' => $baseFare * ($ticketFare?->infant_fare_percentage ?? 10) / 100,
    default => $baseFare,
};

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
    <td class="px-3 py-2">
        <div class="flex items-center gap-1 flex-wrap">
            @if($visaSubmission && $visaSubmission->visa_number)
                <span class="text-slate-800 font-medium text-xs mr-1">{{ $visaSubmission->visa_number }}</span>
            @endif
            <button @click="openVisaSubmitModal({{ $loop->index }})" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded font-medium transition">Submit</button>
            <button @click="openVisaIssueModal({{ $loop->index }})" class="text-xs bg-green-100 hover:bg-green-200 text-green-600 px-2 py-1 rounded font-medium transition">Issue</button>
            <button @click="openVisaEditModal({{ $loop->index }})" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded font-medium transition">Edit</button>
        </div>
    </td>
    <td class="px-3 py-2 text-slate-600">{{ $visaSubmission?->visaAgent?->name ?? '—' }}</td>
    <td class="px-3 py-2">
        @php $vs = $passenger->visa_status; @endphp
        @if($vs)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                {{ $vs === \App\Enums\VisaStatus::ISSUED ? 'bg-green-100 text-green-700' : ($vs === \App\Enums\VisaStatus::SUBMITTED ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700') }}">
                {{ ucfirst($vs->value) }}
            </span>
        @else
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">—</span>
        @endif
    </td>
    <td class="px-3 py-2 text-slate-700">
        <div class="flex items-center gap-1 flex-wrap">
            <span class="font-medium text-sm">{{ $fareAmount > 0 ? number_format($fareAmount, 2) . ' SAR' : '—' }}</span>
            <button @click="openTicketFareModal({{ $passenger->id }}, 'issue')" class="text-xs bg-green-100 hover:bg-green-200 text-green-600 px-2 py-1 rounded font-medium transition">Issue</button>
            <button @click="openTicketFareModal({{ $passenger->id }}, 'edit')" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded font-medium transition">Edit</button>
        </div>
    </td>
    <td class="px-3 py-2">
        @php $ticketStatus = $passenger->ticketFare?->ticketStatus; @endphp
        @if($ticketStatus)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                {{ $ticketStatus->value === 'issued' ? 'bg-green-100 text-green-700' : ($ticketStatus->value === 're-issued' ? 'bg-purple-100 text-purple-700' : ($ticketStatus->value === 'refunded' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600')) }}">
                {{ ucfirst($ticketStatus->value) }}
            </span>
        @else
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">—</span>
        @endif
    </td>
    <td class="px-3 py-2">
        @php $fingerprintStatus = $passenger->fingerprintDetail?->status; @endphp
        @if($fingerprintStatus)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                {{ $fingerprintStatus->value === 'approved' ? 'bg-green-100 text-green-700' : ($fingerprintStatus->value === 'processing' ? 'bg-blue-100 text-blue-700' : ($fingerprintStatus->value === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600')) }}">
                {{ ucfirst($fingerprintStatus->value) }}
            </span>
        @else
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">—</span>
        @endif
    </td>
    <td class="px-3 py-2">
        <a href="{{ route('passengers.show', $passenger->id) }}" class="text-slate-600 hover:text-slate-800">View</a>
    </td>
</tr>
@empty
<tr>
    <td colspan="{{ $canViewFinancialColumns ? 23 : 19 }}" class="px-3 py-4 text-center text-slate-500">No passengers found</td>
@endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $passengers->links() }}
            </div>
        </div>
    </div>

    {{-- Visa Submit Modal --}}
    <div x-show="visaSubmitModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeVisaSubmitModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeVisaSubmitModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4" x-text="visaSubmitForm.isEdit ? 'Edit Visa Submit' : 'Visa Submit Form'"></h3>
            <form @submit.prevent="closeVisaSubmitModal()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent *</label>
                        <select x-model="visaSubmitForm.agent" required @change="updateSubmitCommissionAgents(visaSubmitForm.agent)" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Agent</option>
                            <option value="Visa Agent A">Visa Agent A</option>
                            <option value="Visa Agent B">Visa Agent B</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Commission Agent *</label>
                        <select x-model="visaSubmitForm.commissionAgent" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Commission Agent</option>
                            <template x-for="agent in visaSubmitForm.commissionAgents" :key="agent">
                                <option :value="agent" x-text="agent"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Selling Price (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.sellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.agentCommission" min="0" @input="calculateVisaCost()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.netVisaCost" min="0" @input="calculateVisaCost()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Final Visa Cost (SAR)</label>
                        <input type="number" x-model="visaSubmitForm.finalCost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                    <button type="button" @click="closeVisaSubmitModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Visa Issue Modal --}}
    <div x-show="visaIssueModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeVisaIssueModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeVisaIssueModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Visa Issue Form</h3>
            <form @submit.prevent="closeVisaIssueModal()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent</label>
                        <input type="text" x-model="visaIssueForm.agent" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Number *</label>
                        <input type="text" x-model="visaIssueForm.visaNumber" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter visa number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (SAR)</label>
                        <input type="number" x-model="visaIssueForm.sellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Total Cost (SAR)</label>
                        <input type="number" x-model="visaIssueForm.totalCost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Additional Cost (SAR)</label>
                        <input type="number" x-model="visaIssueForm.additionalCost" min="0" @input="calculateVisaIssueTotal()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                        <input type="text" x-model="visaIssueForm.remarks" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter remarks">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Issue</button>
                    <button type="button" @click="closeVisaIssueModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Visa Edit Modal --}}
    <div x-show="visaEditModalVisible" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeVisaEditModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeVisaEditModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Edit Visa</h3>
            <form @submit.prevent="closeVisaEditModal()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Agent *</label>
                        <select x-model="visaEditForm.agent" required @change="updateEditCommissionAgents(visaEditForm.agent)" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Agent</option>
                            <option value="Visa Agent A">Visa Agent A</option>
                            <option value="Visa Agent B">Visa Agent B</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Number *</label>
                        <input type="text" x-model="visaEditForm.visaNumber" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter visa number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Commission Agent *</label>
                        <select x-model="visaEditForm.commissionAgent" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                            <option value="">Select Commission Agent</option>
                            <template x-for="agent in visaEditForm.commissionAgents" :key="agent">
                                <option :value="agent" x-text="agent"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Visa Selling Price (SAR)</label>
                        <input type="number" x-model="visaEditForm.sellingPrice" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Agent Commission (SAR)</label>
                        <input type="number" x-model="visaEditForm.agentCommission" min="0" @input="calculateVisaEditFinal()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Net Visa Cost (SAR)</label>
                        <input type="number" x-model="visaEditForm.netVisaCost" min="0" @input="calculateVisaEditFinal()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Additional Cost (SAR)</label>
                        <input type="number" x-model="visaEditForm.additionalCost" min="0" @input="calculateVisaEditFinal()" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                        <input type="text" x-model="visaEditForm.remarks" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter remarks">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Final Cost (SAR)</label>
                        <input type="number" x-model="visaEditForm.finalCost" readonly class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <span :class="visaEditForm.issued ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium" x-text="visaEditForm.issued ? 'Issued' : 'Pending'"></span>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">Save</button>
                    <button type="button" @click="closeVisaEditModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Ticket Fare Modal --}}
    <div x-show="isTicketFareModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape="closeTicketFareModal()">
        <div class="fixed inset-0 bg-black/50" @click="closeTicketFareModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-xl font-semibold text-slate-800" x-text="ticketFareModalMode === 'issue' ? 'Issue Ticket' : 'Edit Ticket'"></h3>
                <button @click="closeTicketFareModal()" class="text-slate-400 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form @submit.prevent="closeTicketFareModal()">
                <div class="space-y-5">
                    {{-- Ticket Type --}}
                    <div class="bg-slate-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Ticket Type</h4>
                        <div>
                            <select x-model="ticketFareForm.ticket_type" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                <option value="new">New Ticket</option>
                                <option value="reissue">Reissue</option>
                                <option value="exchange">Exchange</option>
                            </select>
                        </div>
                    </div>

                    {{-- Ticket Information --}}
                    <div class="bg-slate-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Ticket Information</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">PNR</label>
                                <input type="text" x-model="ticketFareForm.pnr" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter PNR">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Number</label>
                                <input type="text" x-model="ticketFareForm.ticket_number" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter ticket number">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Airline</label>
                                <input type="text" x-model="ticketFareForm.airline" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter airline">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Flight No</label>
                                <input type="text" x-model="ticketFareForm.flight_no" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter flight no">
                            </div>
                        </div>
                    </div>

                    {{-- Travel Details --}}
                    <div class="bg-slate-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Travel Details</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Departure</label>
                                <input type="text" x-model="ticketFareForm.departure" class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-600" readonly placeholder="Departure city">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Arrival</label>
                                <input type="text" x-model="ticketFareForm.arrival" class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-600" readonly placeholder="Arrival city">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Departure Time</label>
                                <input type="text" x-model="ticketFareForm.departure_time" class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-600" readonly placeholder="Departure time">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Arrival Time</label>
                                <input type="text" x-model="ticketFareForm.arrival_time" class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-600" readonly placeholder="Arrival time">
                            </div>
                        </div>
                    </div>

                    {{-- Fare Calculation --}}
                    <div class="bg-slate-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Fare Calculation</h4>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Fare Amount (SAR)</label>
                                <input type="number" x-model="ticketFareForm.fare_amount" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Tax (SAR)</label>
                                <input type="number" x-model="ticketFareForm.tax_amount" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Total (SAR)</label>
                                <input type="number" x-model="ticketFareForm.total_amount" class="w-full px-4 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-800 font-semibold" readonly placeholder="Auto-calculated">
                            </div>
                        </div>
                    </div>

                    {{-- Baggage Info --}}
                    <div class="bg-slate-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Baggage Info</h4>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Baggage Allowance</label>
                            <input type="text" x-model="ticketFareForm.baggage_allowance" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="e.g. 20kg">
                        </div>
                    </div>

                    {{-- Ticket Options --}}
                    <div class="bg-slate-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-slate-500 mb-3 pb-2 border-b border-slate-200">Ticket Options</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Option</label>
                                <select x-model="ticketFareForm.ticket_option" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                    <option value="">Select</option>
                                    <option value="inbound">Inbound</option>
                                    <option value="outbound">Outbound</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                                <select x-model="ticketFareForm.status" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
                                    <option value="">Select</option>
                                    <option value="issued">Issued</option>
                                    <option value="pending">Pending</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                            <textarea x-model="ticketFareForm.remarks" rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" placeholder="Enter remarks"></textarea>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium" x-text="ticketFareModalMode === 'issue' ? 'Save' : 'Update'"></button>
                    <button type="button" @click="closeTicketFareModal()" class="flex-1 px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.modal-overlay { transition: opacity 0.2s ease; }
.modal-content { transition: transform 0.2s ease, opacity 0.2s ease; }
</style>

<script>
function bookingIndexApp() {
    return {
        activeTab: '{{ $tab ?? 'booking' }}',
        searchTerm: '',

        passengersVisaData: @json($passengersVisaData),

        visaCommissionAgents: {
            "Visa Agent A": ["Commission Agent 1", "Commission Agent 2"],
            "Visa Agent B": ["Commission Agent 3", "Commission Agent 4"]
        },

        visaSubmitModalVisible: false,
        visaIssueModalVisible: false,
        visaEditModalVisible: false,
        editingVisaIndex: null,

        visaSubmitForm: {
            agent: '',
            commissionAgent: '',
            sellingPrice: 500,
            agentCommission: 0,
            netVisaCost: 0,
            finalCost: 0,
            commissionAgents: []
        },

        visaIssueForm: {
            agent: '',
            visaNumber: '',
            sellingPrice: 0,
            additionalCost: 0,
            totalCost: 0,
            remarks: ''
        },

        visaEditForm: {
            agent: '',
            visaNumber: '',
            commissionAgent: '',
            sellingPrice: 0,
            agentCommission: 0,
            netVisaCost: 0,
            additionalCost: 0,
            remarks: '',
            finalCost: 0,
            issued: false,
            commissionAgents: []
        },

        getCommissionAgents(agentName) {
            return this.visaCommissionAgents[agentName] || [];
        },

        openVisaSubmitModal(index) {
            this.editingVisaIndex = index;
            const data = this.passengersVisaData[index];

            this.visaSubmitForm.agent = data?.visa?.agent || '';
            this.visaSubmitForm.commissionAgents = this.getCommissionAgents(data?.visa?.agent);
            this.visaSubmitForm.sellingPrice = data?.visa?.selling_price || 500;
            this.visaSubmitForm.agentCommission = data?.visa?.agent_commission || 0;
            this.visaSubmitForm.netVisaCost = data?.visa?.net_visa_cost || 0;

            this.$nextTick(() => {
                this.visaSubmitForm.commissionAgent = data?.visa?.commission_agent || '';
            });

            this.calculateVisaCost();
            this.visaSubmitModalVisible = true;
        },

        closeVisaSubmitModal() {
            this.editingVisaIndex = null;
            this.visaSubmitModalVisible = false;
        },

        updateSubmitCommissionAgents(agentName) {
            this.visaSubmitForm.commissionAgents = this.getCommissionAgents(agentName);
            this.visaSubmitForm.commissionAgent = '';
        },

        calculateVisaCost() {
            const commission = parseFloat(this.visaSubmitForm.agentCommission) || 0;
            const net = parseFloat(this.visaSubmitForm.netVisaCost) || 0;
            this.visaSubmitForm.finalCost = commission + net;
        },

        openVisaIssueModal(index) {
            this.editingVisaIndex = index;
            const data = this.passengersVisaData[index];
            const visa = data?.visa;

            this.visaIssueForm.agent = visa?.agent || '';
            this.visaIssueForm.visaNumber = visa?.visa_number || '';
            this.visaIssueForm.sellingPrice = visa?.selling_price || 0;
            this.visaIssueForm.additionalCost = visa?.additional_cost || 0;
            this.visaIssueForm.remarks = visa?.remarks || '';

            this.calculateVisaIssueTotal();
            this.visaIssueModalVisible = true;
        },

        closeVisaIssueModal() {
            this.editingVisaIndex = null;
            this.visaIssueModalVisible = false;
        },

        calculateVisaIssueTotal() {
            const selling = parseFloat(this.visaIssueForm.sellingPrice) || 0;
            const additional = parseFloat(this.visaIssueForm.additionalCost) || 0;
            this.visaIssueForm.totalCost = selling + additional;
        },

        openVisaEditModal(index) {
            this.editingVisaIndex = index;
            const data = this.passengersVisaData[index];
            const visa = data?.visa;

            this.visaEditForm.agent = visa?.agent || '';
            this.visaEditForm.visaNumber = visa?.visa_number || '';

            this.visaEditForm.commissionAgents = visa?.agent ? this.getCommissionAgents(visa.agent) : [];
            this.$nextTick(() => {
                this.visaEditForm.commissionAgent = visa?.commission_agent || '';
            });

            this.visaEditForm.sellingPrice = visa?.selling_price || 0;
            this.visaEditForm.agentCommission = visa?.agent_commission || 0;
            this.visaEditForm.netVisaCost = visa?.net_visa_cost || 0;
            this.visaEditForm.additionalCost = visa?.additional_cost || 0;
            this.visaEditForm.remarks = visa?.remarks || '';
            this.visaEditForm.issued = data?.visa_status === 'issued';

            this.calculateVisaEditFinal();
            this.visaEditModalVisible = true;
        },

        closeVisaEditModal() {
            this.editingVisaIndex = null;
            this.visaEditModalVisible = false;
        },

        updateEditCommissionAgents(agentName) {
            this.visaEditForm.commissionAgents = this.getCommissionAgents(agentName);
            this.visaEditForm.commissionAgent = '';
        },

        calculateVisaEditFinal() {
            const commission = parseFloat(this.visaEditForm.agentCommission) || 0;
            const net = parseFloat(this.visaEditForm.netVisaCost) || 0;
            const additional = parseFloat(this.visaEditForm.additionalCost) || 0;
            this.visaEditForm.finalCost = commission + net + additional;
        },

        isTicketFareModalOpen: false,
        ticketFareModalMode: 'issue',
        selectedPassengerId: null,

        ticketFareForm: {
            ticket_type: 'new',
            ticket_number: '',
            pnr: '',
            airline: '',
            flight_no: '',
            departure: '',
            arrival: '',
            departure_time: '',
            arrival_time: '',
            fare_amount: '',
            tax_amount: '',
            total_amount: '',
            baggage_allowance: '',
            ticket_option: '',
            status: '',
            remarks: ''
        },

        openTicketFareModal(passengerId, mode) {
            this.selectedPassengerId = passengerId;
            this.ticketFareModalMode = mode;
            this.isTicketFareModalOpen = true;
            for (let key in this.ticketFareForm) {
                if (key !== 'ticket_type') {
                    this.ticketFareForm[key] = '';
                }
            }
            this.ticketFareForm.ticket_type = 'new';
        },

        closeTicketFareModal() {
            this.isTicketFareModalOpen = false;
            this.selectedPassengerId = null;
        }
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