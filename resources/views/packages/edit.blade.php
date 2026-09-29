@extends('layouts.app')
@section('title', isset($package) ? 'Edit Package' : 'Create Package')
@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($package) ? 'Edit Package' : 'Create Package' }}</h1>
        <p class="text-slate-600">{{ isset($package) ? 'Update package information' : 'Add a new package' }}</p>
    </div>

    @php $isLocked = isset($package) && $package->isLocked(); @endphp
    @if($isLocked)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg mb-4 text-sm">
            This package has existing bookings. Only the package name, service charge, and visa price option can be changed.
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
        <form method="POST" action="{{ isset($package) ? route('packages.update', $package) : route('packages.store') }}">
            @csrf
            @if(isset($package))
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Package Name *</label>
                    <input type="text" name="package_name" value="{{ old('package_name', $package->package_name ?? '') }}" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" required>
                    @error('package_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @php
                    $packageTicketType = $package?->ticketFare?->ticket_type?->value
                        ?? $package?->ticketFareInbound?->ticket_type?->value
                        ?? $package?->ticketFareOutbound?->ticket_type?->value
                        ?? '';
                @endphp
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type *</label>
                    <select id="ticketTypeSelect" name="ticket_type" @if($isLocked) disabled @endif class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @if($isLocked) bg-slate-100 cursor-not-allowed @endif" required>
                        <option value="">Select Ticket Type</option>
                        <option value="regular" {{ (old('ticket_type', $packageTicketType) == 'regular') ? 'selected' : '' }}>REGULAR</option>
                        <option value="offer" {{ (old('ticket_type', $packageTicketType) == 'offer') ? 'selected' : '' }}>OFFER</option>
                        <option value="group" {{ (old('ticket_type', $packageTicketType) == 'group') ? 'selected' : '' }}>GROUP</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="doubleTicketCheck" name="is_double_ticket" value="1" {{ old('is_double_ticket', $package->is_double_ticket ?? false) ? 'checked' : '' }} @if($isLocked) disabled @endif class="w-4 h-4 rounded border-slate-300 text-slate-700 focus:ring-slate-400">
                    <span class="text-sm font-medium text-slate-700">Double Ticket</span>
                </label>
            </div>

            <div id="singleTicketFields" class="mt-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Ticket *</label>
                <select id="ticketSelect" name="ticket_fare_id" @if($isLocked) disabled @endif class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @if($isLocked) bg-slate-100 cursor-not-allowed @endif">
                    <option value="">Select Ticket</option>
                    @foreach($ticketFares as $fare)
                        @php
                            $type = $fare['ticket_type'];
                            $display = $fare['route'] . ' | ' . strtoupper($type ?? '?') . ' | SAR ' . number_format($fare['selling_fare'], 0);
                            if ($type === 'offer') {
                                $display .= ' | SAR ' . number_format($fare['offer_price'] ?? 0, 0);
                            }
                            if ($type === 'group' && ($fare['seats'] ?? null)) {
                                $display .= ' | ' . $fare['seats'] . ' seats';
                            }
                        @endphp
                        <option value="{{ $fare['id'] }}"
                            data-ticket-type="{{ $fare['ticket_type'] }}"
                            data-selling-fare="{{ $fare['selling_fare'] }}"
                            data-offer-price="{{ $fare['offer_price'] ?? 0 }}"
                            data-seats="{{ $fare['seats'] ?? '' }}"
                            {{ (old('ticket_fare_id', $package->ticket_fare_id ?? '') == $fare['id']) ? 'selected' : '' }}>
                            {{ $display }}
                        </option>
                    @endforeach
                </select>
                @error('ticket_fare_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div id="doubleTicketFields" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Inbound *</label>
                    <select id="ticketInboundSelect" name="ticket_fare_inbound_id" @if($isLocked) disabled @endif class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @if($isLocked) bg-slate-100 cursor-not-allowed @endif">
                        <option value="">Select Inbound Ticket</option>
                        @foreach($inboundFares as $fare)
                            @php
                                $display = $fare['route'] . ' | ' . strtoupper($fare['ticket_type'] ?? '?') . ' | SAR ' . number_format($fare['selling_fare'], 0);
                            @endphp
                            <option value="{{ $fare['id'] }}"
                                data-ticket-type="{{ $fare['ticket_type'] }}"
                                data-selling-fare="{{ $fare['selling_fare'] }}"
                                {{ (old('ticket_fare_inbound_id', $package->ticket_fare_inbound_id ?? '') == $fare['id']) ? 'selected' : '' }}>
                                {{ $display }}
                            </option>
                        @endforeach
                    </select>
                    @error('ticket_fare_inbound_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Outbound *</label>
                    <select id="ticketOutboundSelect" name="ticket_fare_outbound_id" @if($isLocked) disabled @endif class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white @if($isLocked) bg-slate-100 cursor-not-allowed @endif">
                        <option value="">Select Outbound Ticket</option>
                        @foreach($outboundFares as $fare)
                            @php
                                $display = $fare['route'] . ' | ' . strtoupper($fare['ticket_type'] ?? '?') . ' | SAR ' . number_format($fare['selling_fare'], 0);
                            @endphp
                            <option value="{{ $fare['id'] }}"
                                data-ticket-type="{{ $fare['ticket_type'] }}"
                                data-selling-fare="{{ $fare['selling_fare'] }}"
                                {{ (old('ticket_fare_outbound_id', $package->ticket_fare_outbound_id ?? '') == $fare['id']) ? 'selected' : '' }}>
                                {{ $display }}
                            </option>
                        @endforeach
                    </select>
                    @error('ticket_fare_outbound_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Regular Price (SAR) *</label>
                    <input type="number" id="regularPrice" name="regular_price" value="{{ old('regular_price', $package->regular_price ?? '') }}" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-slate-50" min="0" step="any" required readonly>
                    @error('regular_price')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="offerPriceContainer" class="{{ (isset($package) && ($package->ticketFare?->ticket_type === \App\Enums\TicketType::OFFER || (!$package->ticketFare && ($package->ticketFareInbound?->ticket_type === \App\Enums\TicketType::OFFER || $package->ticketFareOutbound?->ticket_type === \App\Enums\TicketType::OFFER)))) ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR)</label>
                    <input type="number" id="offerPrice" name="offer_price" value="{{ old('offer_price', $package->offer_price ?? '') }}" @if($isLocked) readonly @endif class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none @if($isLocked) bg-slate-100 cursor-not-allowed @endif" min="0" step="any">
                    @error('offer_price')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Service Charge (SAR)</label>
                    <input type="number" id="serviceCharge" name="service_charge" value="{{ old('service_charge', $package->service_charge ?? 0) }}" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" min="0" step="any">
                    @error('service_charge')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-4 p-4 bg-slate-50 rounded-lg">
                @if(isset($package))
                    <p class="text-sm text-slate-600">
                        <span class="font-medium">Current Visa Selling Price:</span>
                        <span class="text-slate-800 font-medium"><span x-show="$store.currency.mode === 'BDT'" x-cloak>BDT </span><span x-show="$store.currency.mode === 'SAR' || !$store.currency.mode" x-cloak>SAR </span>@currency($package->visaSellingPrice?->selling_price ?? 0, 2)</span>
                    </p>
                @endif
                <p class="text-sm text-slate-600">
                    <span class="font-medium">Visa Selling Price (Latest):</span>
                    <span class="text-slate-800 font-medium">
                        @if($latestVisa)
                            <span x-show="$store.currency.mode === 'BDT'" x-cloak>BDT </span><span x-show="$store.currency.mode === 'SAR' || !$store.currency.mode" x-cloak>SAR </span>@currency($latestVisa->selling_price, 0)
                        @else
                            Not configured
                        @endif
                    </span>
                </p>
                @if(isset($package))
                    <label class="flex items-center gap-2 cursor-pointer mt-2">
                        <input type="checkbox" id="useCurrentVisa" name="use_current_visa" value="1" class="w-4 h-4 rounded border-slate-300 text-slate-700 focus:ring-slate-400">
                        <span class="text-sm font-medium text-slate-700">Update visa selling price to latest</span>
                    </label>
                @endif
            </div>

            <div class="flex gap-3 mt-6">
                <a href="{{ route('packages.index') }}" class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition font-medium text-center">Cancel</a>
                <button type="submit" class="flex-1 px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition font-medium">
                    {{ isset($package) ? 'Update' : 'Create' }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const ticketFares = @json($ticketFares);
const inboundFares = @json($inboundFares);
const outboundFares = @json($outboundFares);
const latestVisaPrice = {{ $latestVisa?->selling_price ?? 0 }};
const currentVisaPrice = {{ $package->visaSellingPrice?->selling_price ?? 0 }};
const useCurrentVisaCheck = document.getElementById('useCurrentVisa');
let stashedRegularPrice = null;
let stashedOfferPrice = null;
let stashedOfferVisible = false;

function effectiveVisaPrice() {
    if (!useCurrentVisaCheck) return latestVisaPrice;
    return useCurrentVisaCheck.checked ? latestVisaPrice : currentVisaPrice;
}

const doubleTicketCheck = document.getElementById('doubleTicketCheck');
const singleTicketFields = document.getElementById('singleTicketFields');
const doubleTicketFields = document.getElementById('doubleTicketFields');
const ticketSelect = document.getElementById('ticketSelect');
const ticketInboundSelect = document.getElementById('ticketInboundSelect');
const ticketOutboundSelect = document.getElementById('ticketOutboundSelect');
const regularPrice = document.getElementById('regularPrice');
const offerPrice = document.getElementById('offerPrice');
const offerPriceContainer = document.getElementById('offerPriceContainer');
const serviceCharge = document.getElementById('serviceCharge');
const ticketTypeSelect = document.getElementById('ticketTypeSelect');

function toggleDoubleTicket() {
    const isDouble = doubleTicketCheck.checked;
    singleTicketFields.classList.toggle('hidden', isDouble);
    doubleTicketFields.classList.toggle('hidden', !isDouble);

    singleTicketFields.querySelectorAll('select, input').forEach(el => {
        if (el.id !== 'ticketSelect') return;
        el.required = !isDouble;
    });
    doubleTicketFields.querySelectorAll('select').forEach(el => el.required = isDouble);

    if (isPackageLocked) {
        return;
    }

    if (isDouble) {
        ticketSelect.value = '';
        calculatePrices();
    } else {
        ticketInboundSelect.value = '';
        ticketOutboundSelect.value = '';
        calculatePrices();
    }
}

function buildDisplay(fare) {
    let disp = fare.route + ' | ' + fare.ticket_type.toUpperCase() + ' | SAR ' + fare.selling_fare;
    if (fare.ticket_type === 'offer') {
        disp += ' | SAR ' + fare.offer_price;
    }
    if (fare.ticket_type === 'group' && fare.seats) {
        disp += ' | ' + fare.seats + ' seats';
    }
    return disp;
}

const isPackageLocked = {{ $isLocked ? 'true' : 'false' }};

function filterTickets() {
    const selectedType = ticketTypeSelect.value;
    if (!isPackageLocked) {
        ticketSelect.value = '';
        ticketInboundSelect.value = '';
        ticketOutboundSelect.value = '';
    }
    [ticketSelect, ticketInboundSelect, ticketOutboundSelect].forEach(sel => {
        Array.from(sel.options).forEach(option => {
            if (option.value === '') return;
            option.style.display = (selectedType === '' || option.dataset.ticketType === selectedType) ? '' : 'none';
        });
    });
    calculatePrices();
}

function calculatePrices() {
    const isDouble = doubleTicketCheck.checked;
    const visaBase = effectiveVisaPrice();

    if (isDouble) {
        const inboundOption = ticketInboundSelect.options[ticketInboundSelect.selectedIndex];
        const outboundOption = ticketOutboundSelect.options[ticketOutboundSelect.selectedIndex];
        const inboundFare = inboundOption && inboundOption.value ? parseFloat(inboundOption.dataset.sellingFare) || 0 : 0;
        const outboundFare = outboundOption && outboundOption.value ? parseFloat(outboundOption.dataset.sellingFare) || 0 : 0;
        const totalFare = inboundFare + outboundFare;

        regularPrice.value = totalFare > 0 ? (totalFare + visaBase).toFixed(6) : '';
        offerPriceContainer.classList.add('hidden');
        offerPrice.value = '';
        return;
    }

    const selectedOption = ticketSelect.options[ticketSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        regularPrice.value = '';
        offerPrice.value = '';
        return;
    }

    const sellingFare = parseFloat(selectedOption.dataset.sellingFare) || 0;
    const offerFare = parseFloat(selectedOption.dataset.offerPrice) || 0;
    const ticketType = selectedOption.dataset.ticketType;

    regularPrice.value = (sellingFare + visaBase).toFixed(6);

    if (ticketType === 'offer') {
        offerPriceContainer.classList.remove('hidden');
        offerPrice.value = (offerFare + visaBase).toFixed(6);
    } else {
        offerPriceContainer.classList.add('hidden');
        offerPrice.value = '';
    }
}

doubleTicketCheck.addEventListener('change', toggleDoubleTicket);
ticketTypeSelect.addEventListener('change', filterTickets);
ticketSelect.addEventListener('change', calculatePrices);
ticketInboundSelect.addEventListener('change', calculatePrices);
ticketOutboundSelect.addEventListener('change', calculatePrices);
if (useCurrentVisaCheck) {
    useCurrentVisaCheck.addEventListener('change', function() {
        if (useCurrentVisaCheck.checked) {
            calculatePrices();
        } else if (isPackageLocked && stashedRegularPrice !== null) {
            regularPrice.value = stashedRegularPrice;
            offerPrice.value = stashedOfferPrice;
            offerPriceContainer.classList.toggle('hidden', !stashedOfferVisible);
        } else {
            calculatePrices();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    toggleDoubleTicket();
    if (!isPackageLocked) {
        filterTickets();
        calculatePrices();
    } else {
        stashedRegularPrice = regularPrice.value;
        stashedOfferPrice = offerPrice.value;
        stashedOfferVisible = !offerPriceContainer.classList.contains('hidden');
    }
});
</script>
@endsection