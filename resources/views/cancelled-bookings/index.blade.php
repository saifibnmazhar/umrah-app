@extends('layouts.app')
@section('title', 'Cancelled Bookings')
@section('content')
@php $canSeeInitiatedBy = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty(); @endphp
<div class="w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Cancelled Bookings</h1>
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

    <div class="mb-4 flex border-b border-slate-200">
        <a href="{{ route('cancelled-bookings.index', ['tab' => 'bookings'] + request()->only(['branch_id', 'search'])) }}"
           class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'bookings' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Cancelled Bookings
        </a>
        <a href="{{ route('cancelled-bookings.index', ['tab' => 'passengers'] + request()->only(['branch_id', 'search'])) }}"
           class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'passengers' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Cancelled Passengers
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="mb-4 flex flex-wrap items-center gap-4">
            @unless(auth()->user()->branch_id)
            <select onchange="window.location.href = this.value ? '?tab={{ $tab }}&branch_id=' + this.value + (searchValue() ? '&search=' + encodeURIComponent(searchValue()) : '') : '?tab={{ $tab }}'"
                    class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700">
                <option value="">All Branches</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
            @endunless
            <form method="GET" action="{{ route('cancelled-bookings.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if(request('branch_id'))<input type="hidden" name="branch_id" value="{{ request('branch_id') }}">@endif
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice / Customer / Passenger"
                       class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition bg-white text-slate-700 w-64">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">Search</button>
            </form>
        </div>

        @if($tab === 'bookings')
        <div class="overflow-auto flex-1 min-h-0" style="max-height: calc(95vh - 260px);">
            <table class="w-full min-w-[1000px] text-sm">
                <thead class="bg-slate-50 text-slate-600 sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                        <th class="px-3 py-2 text-left font-medium">Customer</th>
                        <th class="px-3 py-2 text-left font-medium">PAX QTY</th>
                        <th class="px-3 py-2 text-left font-medium">Mobile</th>
                        <th class="px-3 py-2 text-left font-medium">Cancellation Branch</th>
                        <th class="px-3 py-2 text-right font-medium">Total Paid</th>
                        <th class="px-3 py-2 text-right font-medium">Service Charge</th>
                        <th class="px-3 py-2 text-right font-medium">Refund Amount</th>
                        <th class="px-3 py-2 text-left font-medium">Cancel Date</th>
                        <th class="px-3 py-2 text-left font-medium">Status</th>
                        @if($canSeeInitiatedBy)<th class="px-3 py-2 text-left font-medium">Cancelled By</th>@endif
                        <th class="px-3 py-2 text-center font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($cancelledBookings as $cb)
                    <tr>
                        <td class="px-3 py-2 text-slate-700">{{ $cb->booking?->invoice_id ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $cb->booking?->customer?->name ?? 'N/A' }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $cb->booking?->pax_qty ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $cb->booking?->customer?->mobile_no ?? 'N/A' }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $cb->cancellationBranch?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-700 text-right">@currency($cb->total_paid, 2)</td>
                        <td class="px-3 py-2 text-slate-700 text-right">
                            @if($cb->service_charge_deduction !== null)
                                @currency($cb->service_charge_deduction, 2)
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2 text-slate-800 font-medium text-right">@currency($cb->refund_amount, 2)</td>
                        <td class="px-3 py-2 text-slate-600">{{ $cb->created_at->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">
                            @if($cb->status === \App\Enums\CancelledBookingStatus::PROCESSING)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Cancellation Processing</span>
                            @elseif($cb->status === \App\Enums\CancelledBookingStatus::CANCELLED)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Cancelled</span>
                            @else
                                <span class="text-slate-600">{{ $cb->status->value ?? '—' }}</span>
                            @endif
                        </td>
                        @if($canSeeInitiatedBy)<td class="px-3 py-2 text-slate-600">{{ $cb->user?->name ?? '—' }}</td>@endif
                        <td class="px-3 py-2 text-center whitespace-nowrap">
                            <a href="{{ route('cancelled-bookings.show', $cb->id) }}"
                               class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded font-medium">View</a>
                            <a href="{{ route('cancelled-bookings.print', $cb->id) }}"
                               class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded font-medium ml-1">Print</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $canSeeInitiatedBy ? 12 : 11 }}" class="px-3 py-4 text-center text-slate-500">No cancelled bookings found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $cancelledBookings->links() }}</div>

        @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Invoice ID</th>
                        <th class="px-3 py-2 text-left font-medium">Customer</th>
                        <th class="px-3 py-2 text-left font-medium">Passenger</th>
                        <th class="px-3 py-2 text-left font-medium">Cancellation Branch</th>
                        <th class="px-3 py-2 text-right font-medium">Package Value</th>
                        <th class="px-3 py-2 text-right font-medium">Refundable</th>
                        <th class="px-3 py-2 text-right font-medium">Adjusted</th>
                        <th class="px-3 py-2 text-right font-medium">Refund Amount</th>
                        <th class="px-3 py-2 text-left font-medium">Status</th>
                        @if($canSeeInitiatedBy)<th class="px-3 py-2 text-left font-medium">Initiated By</th>@endif
                        <th class="px-3 py-2 text-left font-medium">Date</th>
                        <th class="px-3 py-2 text-center font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($cancelledPassengers as $cp)
                    <tr>
                        <td class="px-3 py-2 text-slate-700">{{ $cp->booking?->invoice_id ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $cp->booking?->customer?->name ?? 'N/A' }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ trim(($cp->passenger?->first_name ?? '') . ' ' . ($cp->passenger?->last_name ?? '')) ?: '—' }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $cp->cancellationBranch?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-700 text-right">@currency($cp->package_value, 2)</td>
                        <td class="px-3 py-2 text-slate-700 text-right">@currency($cp->refundable_amount, 2)</td>
                        <td class="px-3 py-2 text-slate-700 text-right">@currency($cp->balance_adjusted_amount, 2)</td>
                        <td class="px-3 py-2 text-slate-800 font-medium text-right">@currency($cp->refund_amount, 2)</td>
                        <td class="px-3 py-2">
                            @if($cp->status === \App\Enums\CancelledBookingStatus::PROCESSING)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Cancellation Processing</span>
                            @elseif($cp->status === \App\Enums\CancelledBookingStatus::CANCELLED)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Cancelled</span>
                            @else
                                <span class="text-slate-600">{{ $cp->status->value ?? '—' }}</span>
                            @endif
                        </td>
                        @if($canSeeInitiatedBy)<td class="px-3 py-2 text-slate-600">{{ $cp->user?->name ?? '—' }}</td>@endif
                        <td class="px-3 py-2 text-slate-600">{{ $cp->created_at->format('Y-m-d') }}</td>
                        <td class="px-3 py-2 text-center whitespace-nowrap">
                            <a href="{{ route('cancelled-passengers.show', $cp->id) }}"
                               class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded font-medium">View</a>
                            <a href="{{ route('cancelled-passengers.print', $cp->id) }}"
                               class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded font-medium ml-1">Print</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $canSeeInitiatedBy ? 12 : 11 }}" class="px-3 py-4 text-center text-slate-500">No cancelled passengers found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $cancelledPassengers->links() }}</div>
        @endif
    </div>
</div>
<script>
function searchValue() { return document.querySelector('input[name="search"]')?.value || ''; }
</script>
@endsection
