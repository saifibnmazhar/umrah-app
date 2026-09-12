# Booking Page Performance Fix Plan

## Problem

Production has ~1500+ passengers and growing. The Booking index page loads slowly
because every page view runs catastrophic server-side queries:

1. **90-relation `with()`** on passengers (BookingController.php:309-399)
2. **Invoice aggregates** using `whereIn` subqueries with joins (lines 295-307)
3. **Route loading** — all routes with 5 eager-loaded relations (lines 178-193)
4. **Blade `@php` block** runs `CostTrackingService`, fare mapping, passenger
   data transformations (lines 4-696)
5. **Every filter change** does `window.location.href = url.toString()` —
   full page reload re-running all queries

The `passengerData` API endpoint exists (line 476) and works, but the Blade view
never calls it. The Blade renders everything server-side via `@forelse($passengers)`.

Fingerprint admin/staff pages already use shell-then-AJAX and are fast.

## Goal

Apply the same shell-then-AJAX pattern to the Booking page:

```
GET /bookings  -> shell view only (dropdowns, empty table, role checks)
GET /api/bookings/passengers?page=1&per_page=15&search=...  -> 15 rows JSON + pagination
```

- Page load: fast (dropdown queries only)
- Filter/search: AJAX, no page reload
- Passenger data: fetched via API, rendered by Alpine.js

## Scope Assessment

| Component | Lines | Complexity |
|-----------|-------|------------|
| `BookingController@index` (to slim) | 148-474 (~326 lines) | Medium |
| `BookingController@passengerData` (to enhance) | 476-506 (~30 lines) | Low |
| Blade `@php` block (to remove/reduce) | 4-696 (~692 lines) | High |
| Blade `@forelse` passenger loop (to convert) | 1154-1604 (~450 lines) | Very High |
| Blade filter section (to convert to AJAX) | 900-1140 (~240 lines) | High |
| Alpine.js `bookingIndexApp()` (to rewire) | 3490-3920 (~430 lines) | High |
| Existing `window.location.href` handlers | 60 occurrences | Medium |

---

## Phase 1: Enhance `passengerData` API + Slim `index()` (Controller Only)

**Goal:** Make the API return all data the Blade needs. Remove heavy queries from
`index()`. Each phase is independently deployable and testable.

### Step 1.1: Enhance `passengerData` method

**File:** `app/Http/Controllers/BookingController.php:476-497`

Add full eager loads and compute per-passenger data.

**Current (minimal):**
```php
$page = $query->with([
    'booking:id,invoice_id,customer_id',
    'booking.customer:id,name',
    'ticketFare:id,airline_id',
    'status:id,name',
    'visaSubmission',
])->paginate(15)->withQueryString();
```

**Proposed (full):**
```php
$page = $query->with([
    // Booking context
    'booking' => fn($q) => $q->with([
        'customer', 'invoice', 'package', 'currencyRate',
        'fingerprint', 'passengers', 'documents',
        'customer.documents', 'cancelledBooking',
    ]),
    // Ticket fares
    'ticketFare' => fn($q) => $q->with([
        'route.fromCity', 'route.toCity', 'route.returnCity',
        'route.multiSegments.fromCity', 'route.multiSegments.toCity',
        'airline', 'airlineClass.class', 'baggageAllowances', 'groupTicket',
    ]),
    'ticketFareInbound' => fn($q) => $q->with([
        'route.fromCity', 'route.toCity', 'route.returnCity',
        'route.multiSegments.fromCity', 'route.multiSegments.toCity',
        'airline', 'airlineClass.class', 'baggageAllowances',
    ]),
    'ticketFareOutbound' => fn($q) => $q->with([
        'route.fromCity', 'route.toCity', 'route.returnCity',
        'route.multiSegments.fromCity', 'route.multiSegments.toCity',
        'airline', 'airlineClass.class', 'baggageAllowances',
    ]),
    // Status & visa
    'status', 'visaSubmission.visaAgent',
    'visaSubmission.visaSellingPrice', 'visaSubmission.commissionAgent',
    'visaSubmission.cancelledSubmissions',
    // Fingerprint
    'fingerprintDetail.fingerprint.fingerprintDetails',
    'fingerprintDetail.approvedLog',
    // Tickets
    'allIssuedTickets' => fn($q) => $q->with([
        'ticketAgent', 'ticketFare.airline', 'ticketFare.airlineClass.class',
        'ticketFare.route.fromCity', 'ticketFare.route.toCity',
        'ticketFare.route.returnCity',
        'ticketFare.route.multiSegments.fromCity',
        'ticketFare.route.multiSegments.toCity',
        'latestReIssuedTicket.ticketAgent',
        'latestReIssuedTicket.ticketFare',
        'latestRefundedTicket.ticketAgent',
        'latestRefundedTicket.ticketFare',
        'reIssuedTickets.reason', 'refundedTickets.reason',
        'pendingRequests',
    ]),
    // Cancellation & documents
    'cancelledPassengers', 'documents',
])->paginate((int) $request->input('per_page', 15))->withQueryString();
```

Add `map()` to compute per-passenger data:

```php
$currencyRateService = app(CurrencyRateService::class);
$firstRate = (float) ($currencyRateService->getFirstRate()?->rate ?? 0);
$costService = app(CostTrackingService::class);
$profitService = app(ProfitCalculationService::class);

$data = $page->getCollection()->map(function ($p) use ($firstRate, $costService, $profitService) {
    $passBookingRate = $p->booking?->currencyRate?->rate
        ?? app(CurrencyRateService::class)->getRateForDate($p->booking?->created_at)?->rate
        ?? $firstRate;

    return [
        // Base passenger data (for Blade columns that need it)
        'id' => $p->id,
        'booking_id' => $p->booking_id,
        'first_name' => $p->first_name,
        'last_name' => $p->last_name,
        'passport_no' => $p->passport_no,
        'mobile_no' => $p->mobile_no,
        'flight_date_from' => $p->flight_date_from?->format('d M Y'),
        'flight_date_to' => $p->flight_date_to?->format('d M Y'),
        'stay_duration' => $p->stay_duration,
        'passenger_type' => $p->passenger_type?->value,
        'service_required' => $p->service_required?->value,
        'package_value' => $p->package_value,
        'ticket_remarks' => $p->ticket_remarks,
        'is_ticket_held' => (bool)($p->is_ticket_held ?? false),
        'is_visa_held' => (bool)($p->is_visa_held ?? false),
        'is_cancelled' => $p->booking?->is_cancelled ?? false,
        'profit' => (float) ($p->profit ?? 0),

        // Booking context
        'booking' => [
            'created_at' => $p->booking?->created_at?->format('d M Y'),
            'invoice_id' => $p->booking?->invoice_id,
            'pax_qty' => $p->booking?->pax_qty,
            'remarks' => $p->booking?->remarks,
            'is_cancelled' => $p->booking?->is_cancelled ?? false,
            'customer' => [
                'name' => $p->booking?->customer?->name,
                'mobile_no' => $p->booking?->customer?->mobile_no,
            ],
            'package' => [
                'package_name' => $p->booking?->package?->package_name,
            ],
            'invoice' => $p->booking?->invoice ? [
                'total_amount' => (float)($p->booking->invoice->total_amount ?? 0),
                'balance' => (float)($p->booking->invoice->balance ?? 0),
                'paid_amount' => (float)($p->booking->invoice->paid_amount ?? 0),
                'discount_amount' => (float)($p->booking->discount_amount ?? 0),
            ] : null,
        ],

        // Route display
        'route_display' => $p->route_display ?? '—',

        // Computed fare amount
        'fare_amount' => $this->computeFareAmount($p),

        // Actual flight date (from issued tickets)
        'actual_flight_date' => $this->computeActualFlightDate($p),

        // Return date (from issued tickets)
        'return_date' => $this->computeReturnDate($p),

        // Fingerprint display status
        'fingerprint_display_status' => $this->computeFingerprintDisplayStatus($p),

        // Currency rate for this row
        'pass_booking_rate' => $passBookingRate,

        // Status info
        'status' => [
            'name' => $p->status?->name ?? 'None',
            'id' => $p->passenger_status_id,
        ],
        'cancelled_passenger' => $this->computeCancelledPassengerInfo($p),

        // Documents
        'documents_count' => $p->documents->count(),
        'has_booking_documents' => $p->booking?->documents?->isNotEmpty() ?? false,
        'has_customer_documents' => ($p->booking?->customer && $p->booking->customer->documents->isNotEmpty()) ?? false,

        // URLs
        'passenger_url' => route('passengers.show', $p->id) . '?return_url=' . urlencode(request()->fullUrl()),
        'passenger_download_url' => route('passengers.download-all-docs', $p->id),
        'booking_download_url' => route('bookings.download-all-docs', ['booking' => $p->booking_id, 'passenger_id' => $p->id]),

        // Visa data (same structure as passengersVisaData)
        'visa_data' => $this->computeVisaData($p, $passBookingRate),

        // Ticket data (same structure as passengersTicketData)
        'ticket_data' => $this->computeTicketData($p, $passBookingRate),

        // Cost
        'cost' => $this->computePassengerCost($p, $costService),
    ];
})->values();
```

**New helper methods** to add on `BookingController`:

```php
private function computeFareAmount(Passenger $p): float
{
    $calcFare = function ($fare, $pType) {
        if (! $fare) return 0;
        $base = $fare->ticket_type?->value === 'offer'
            ? ($fare->offer_price ?? $fare->selling_fare ?? $fare->net_fare ?? 0)
            : ($fare->selling_fare ?? $fare->net_fare ?? 0);
        return match ($pType) {
            'child' => $base * ($fare->child_fare_percentage) / 100,
            'infant' => $base * ($fare->infant_fare_percentage) / 100,
            default => $base,
        };
    };

    if ($p->ticket_fare_inbound_id && $p->ticket_fare_outbound_id) {
        return $calcFare($p->ticketFareInbound, $p->passenger_type?->value)
             + $calcFare($p->ticketFareOutbound, $p->passenger_type?->value);
    }

    return $calcFare($p->ticketFare, $p->passenger_type?->value);
}

private function computeActualFlightDate(Passenger $p): ?string
{
    $regularTicket = $p->allIssuedTickets
        ->first(fn ($t) => in_array($t->issue_type, [null, 'regular'], true)
            && in_array($t->status, ['issued', 're-issued']));

    if (! $regularTicket) return null;

    $date = $regularTicket->status === 're-issued'
        ? ($regularTicket->latestReIssuedTicket?->inbound_date ?? $regularTicket->inbound_date)
        : $regularTicket->inbound_date;

    return $date?->format('d M Y');
}

private function computeReturnDate(Passenger $p): ?string
{
    $regularTicket = $p->allIssuedTickets
        ->first(fn ($t) => in_array($t->issue_type, [null, 'regular'], true)
            && in_array($t->status, ['issued', 're-issued']));

    if (! $regularTicket) return null;

    if ($regularTicket->outbound_pending) {
        $pendingTicket = $p->allIssuedTickets
            ->first(fn ($t) => $t->issue_type === 'pending_outbound'
                && in_array($t->status, ['issued', 're-issued'], true));
        if ($pendingTicket) {
            $d = $pendingTicket->status === 're-issued'
                ? ($pendingTicket->latestReIssuedTicket?->outbound_date ?? $pendingTicket->outbound_date)
                : $pendingTicket->outbound_date;
            return $d?->format('d M Y');
        }
        return null;
    }

    $d = $regularTicket->status === 're-issued'
        ? ($regularTicket->latestReIssuedTicket?->outbound_date ?? $regularTicket->outbound_date)
        : $regularTicket->outbound_date;

    return $d?->format('d M Y');
}

private function computeFingerprintDisplayStatus(Passenger $p): array
{
    $detail = $p->fingerprintDetail;
    $rawStatus = $detail?->status?->value;
    $displayStatus = $rawStatus;
    $approvedDate = null;

    if ($rawStatus === 'approved') {
        $allDetails = $detail->fingerprint?->fingerprintDetails;
        $allApproved = $allDetails && $allDetails->every(fn ($d) => $d->status->value === 'approved');
        if (! $allApproved) {
            $displayStatus = 'Partially Approved';
        }
        $approvedDate = ($detail->approvedLog?->created_at ?? $detail->updated_at)?->format('d|m|y');
    } elseif ($rawStatus === 'done') {
        $displayStatus = 'Pending Pax Completion';
    }

    return [
        'raw' => $rawStatus,
        'display' => $displayStatus,
        'approved_date' => $approvedDate,
    ];
}

private function computeCancelledPassengerInfo(Passenger $p): ?array
{
    $activeCancellation = $p->cancelledPassengers->first();
    if (! $activeCancellation) return null;

    return [
        'is_confirmed' => $activeCancellation->status === 'cancelled' && $activeCancellation->confirmed_at,
        'is_processing' => $activeCancellation->status === 'cancellation processing' && ! $activeCancellation->confirmed_at,
    ];
}

private function computeVisaData(Passenger $p, float $rate): array
{
    return [
        'id' => $p->id,
        'booking_id' => $p->booking_id,
        'rate' => $rate,
        'service_required' => $p->service_required?->value ?? 'all',
        'is_visa_held' => (bool) ($p->is_visa_held ?? false),
        'visa' => $p->visaSubmission ? [
            'id' => $p->visaSubmission->id,
            'agent_id' => $p->visaSubmission->visa_agent_id,
            'agent' => $p->visaSubmission?->visaAgent?->name ?? '',
            'visa_number' => $p->visaSubmission?->visa_number ?? '',
            'selling_price' => (float) ($p->visaSubmission?->visaSellingPrice?->selling_price ?? 0),
            'agent_commission' => (float) ($p->visaSubmission?->agent_commission ?? 0),
            'net_visa_cost' => (float) ($p->visaSubmission?->net_visa_cost ?? 0),
            'additional_cost' => (float) ($p->visaSubmission?->additional_cost ?? 0),
            'remarks' => $p->visaSubmission?->remarks ?? '',
            'final_cost' => (float) ($p->visaSubmission?->final_cost ?? 0),
            'commission_agent_id' => $p->visaSubmission?->commission_agent_id,
            'commission_agent' => $p->visaSubmission?->commissionAgent?->name ?? '',
            'status' => $p->visaSubmission->status?->value ?? 'pending',
        ] : null,
    ];
}

private function computeTicketData(Passenger $p, float $rate): array
{
    $costService = app(CostTrackingService::class);
    $profitService = app(ProfitCalculationService::class);
    $paxCount = max($p->booking->passengers->count(), 1);
    $fpCost = $p->booking->fingerprint?->cost ?? 0;

    return [
        'id' => $p->id,
        'booking_id' => $p->booking_id,
        'service_required' => $p->service_required?->value ?? 'all',
        'booking_date' => $p->booking?->created_at?->format('Y-m-d') ?? '',
        'invoice_no' => $p->booking?->invoice_id ?? '',
        'passenger_name' => trim($p->first_name . ' ' . $p->last_name),
        'passport' => $p->passport_no ?? '',
        'route' => $p->route_display ?? '',
        'airline' => $p->ticketFare?->airline?->name ?? $p->booking?->package?->ticketFare?->airline?->name ?? '',
        'travel_class' => $p->ticketFare?->airlineClass?->class?->name ?? $p->booking?->package?->ticketFare?->airlineClass?->class?->name ?? '',
        'passenger_type' => $p->passenger_type?->value ?? 'adult',
        'mobile_no' => $p->mobile_no ?? '',
        'is_ticket_held' => (bool) ($p->is_ticket_held ?? false),
        'ticket_status' => $p->allIssuedTickets
            ->filter(fn ($t) => is_null($t->issue_type) || $t->issue_type === 'regular')
            ->sortByDesc('id')
            ->first()?->status ?? null,
        'ticket_remarks' => $p->ticket_remarks ?? '',
        'due' => $p->booking?->invoice?->balance ?? 0,
        'refund_payable' => (float) ($p->refund_payable ?? 0),
        'profit' => (float) ($p->profit ?? 0),
        'profit_breakdown' => $profitService->getPassengerProfitBreakdown($p),
        'required_flight_date' => $p->flight_date_from?->format('Y-m-d') ?? '',
        'actual_flight_date' => $p->actual_flight_date?->format('Y-m-d') ?? '',
        'fingerprint_location' => $p->booking?->fingerprint_location?->value ?? 'None',
        'fingerprint_status' => $p->fingerprintDetail?->status?->value ?? null,
        'status' => $p->status?->name ?? 'None',
        'is_cancelled' => $p->booking?->is_cancelled ?? false,
        'fingerprint_cost' => $fpCost > 0 ? round($fpCost / $paxCount, 6) : 0,
        'ticket_fare_inbound_id' => $p->ticket_fare_inbound_id,
        'ticket_fare_outbound_id' => $p->ticket_fare_outbound_id,
        'is_double_ticket' => ! is_null($p->ticket_fare_inbound_id),
        'package_is_double_ticket' => $p->booking?->package?->is_double_ticket ?? false,

        // Inbound fare data (for fare modal)
        'inbound_ticket_fare' => $this->computeTicketFareData($p->ticketFareInbound, $p, 'inbound'),

        // Outbound fare data (for fare modal)
        'outbound_ticket_fare' => $this->computeTicketFareData($p->ticketFareOutbound, $p, 'outbound'),

        // Current ticket fare data (for fare modal)
        'ticket_fare' => $this->computeCurrentTicketFareData($p),

        // Latest issued ticket
        'latest_issued_ticket' => $this->computeLatestIssuedTicket($p),

        // All issued tickets (for ticket status display)
        'all_issued_tickets' => $this->computeAllIssuedTickets($p),

        // Pending outbound ticket
        'pending_outbound_issued_ticket' => $this->computePendingOutboundTicket($p),
    ];
}

private function computePassengerCost(Passenger $p, CostTrackingService $costService): float
{
    static $bookingCostCache = [];
    $booking = $p->booking;
    if (! $booking) return 0;

    $bid = $booking->id;
    if (! isset($bookingCostCache[$bid])) {
        $bookingCostCache[$bid] = $costService->getPassengerCosts($booking)->keyBy('passenger_id');
    }
    $c = $bookingCostCache[$bid]->get($p->id);

    return (float) ($c['total_cost'] ?? 0);
}

// + helper methods for computeTicketFareData, computeCurrentTicketFareData,
//   computeLatestIssuedTicket, computeAllIssuedTickets, computePendingOutboundTicket
//   (each mirrors the corresponding section from passengersTicketData in the Blade)
```

### Step 1.2: Slim `index()`

**File:** `app/Http/Controllers/BookingController.php:148-474`

**Remove:**
- Lines 284-287: `$passengerQuery`, `$passengersBase`, `$totalPassengerCount`
- Lines 289-290: `$currencyRateService`, `$firstRate` (move to `passengerData`)
- Lines 292-307: `$bookingIdsSub`, `$invoiceTotals`, `$bdtTotals` aggregates
- Lines 309-403: The entire 90-relation passenger `with()` + `paginate(15)`
- Lines 404-411: `$passengerStatuses`, `$statusChangeOptions` (keep — needed for dropdown)
- Lines 456-473: Remove `$passengers`, `$totalPassengerCount`, `$totalPackageValue`, `$totalDue`, `$totalPackageBdt`, `$totalDueBdt` from `compact()`

**Keep:**
- Lines 150-177: All `$selected*` variables (needed for filter pre-selection)
- Lines 178-199: Route loading (needed for route dropdown) — **optimize later**
- Lines 201-208: `$branchCounts`, `$allBookingCount` (booking tab summary)
- Lines 210-277: Booking tab query + pagination (stays as-is)
- Lines 279-282: `$canFilterBy*` role checks
- Lines 413-454: `$visaAgents`, `$ticketAgents`, `$reIssueReasons`, enums, statuses (dropdown data)

---

## Phase 2: Convert Blade Passenger Tab to AJAX (Blade + Alpine)

**Goal:** Blade renders an empty table skeleton on page load. Alpine.js fetches
from the API and fills it.

### Step 2.1: Remove heavy `@php` block (lines 4-181)

**Remove from `@php` block:**
- Lines 5-19: `$costService`, `$bookingCostCache`, `$passengerTotalCostMap`
- Lines 21-47: `$passengersVisaData`
- Lines 148-695: `$passengersTicketData` (this is in the `@php` block before the Blade HTML)

**Keep in `@php` block:**
- Lines 49-52: `$packagesList` — small, needed for package dropdown
- Lines 54-81: `$flightDateRanges` — computed in Blade, no DB
- Lines 83-87: `$airlinesList` — needed for fare modal
- Lines 89-92: `$classesList` — needed for fare modal
- Lines 94: `$refundReasons` — needed for refund modal

**Remove from Blade (lines 498-696):**
- Lines 498-695: The entire second half of `$passengersTicketData` computation

### Step 2.2: Convert `@forelse` to Alpine `x-for` (lines 1154-1604)

Replace the entire `@forelse($passengers as $passenger) ... @endforelse` block.

The passenger tab section becomes:

```html
<div x-show="activeTab === 'passenger'" x-cloak>
    <!-- Filter section (same dropdowns, but with @change="loadPassengers()" instead of page reload) -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <!-- Filters (lines 900-1140 converted) -->
    </div>

    <!-- Passenger table -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[2000px] text-sm">
                <thead><!-- same headers --></thead>
                <tbody class="divide-y divide-slate-200">
                    <template x-if="passengerLoading">
                        <tr>
                            <td colspan="..." class="px-3 py-8 text-center text-slate-500">Loading...</td>
                        </tr>
                    </template>
                    <template x-if="!passengerLoading && passengers.length === 0">
                        <tr>
                            <td colspan="..." class="px-3 py-4 text-center text-slate-500">No passengers found</td>
                        </tr>
                    </template>
                    <template x-for="(p, i) in passengers" :key="p.id">
                        <tr>
                            <!-- Each column uses x-text/x-html with API data -->
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="mt-4" x-html="passengerPaginationHtml"></div>
    </div>
</div>
```

### Step 2.3: Add `passengerTabData()` Alpine component

Add to the existing `bookingIndexApp()` function or as a separate `x-data`:

```js
// In bookingIndexApp():
passengers: [],
passengersVisaData: [],
passengersTicketData: [],
passengerTotalCostMap: {},
passengerLoading: false,
passengerPaginationHtml: '',
passengerCurrentPage: 1,

async loadPassengers() {
    this.passengerLoading = true;
    const params = new URLSearchParams({
        page: this.passengerCurrentPage,
        per_page: 15,
        tab: 'passenger',
    });
    // Add all filter params
    if (this.searchTerm) params.set('search', this.searchTerm);
    if (this.selectedFingerprintStatus) params.set('fingerprint_status', this.selectedFingerprintStatus);
    if (this.selectedVisaStatus) params.set('visa_status', this.selectedVisaStatus);
    if (this.selectedTicketStatus) params.set('ticket_status', this.selectedTicketStatus);
    if (this.selectedVisaAgentId) params.set('visa_agent_id', this.selectedVisaAgentId);
    if (this.selectedBookingBranchId) params.set('booking_branch_id', this.selectedBookingBranchId);
    if (this.selectedBookingDateFrom) params.set('booking_date_from', this.selectedBookingDateFrom);
    if (this.selectedBookingDateTo) params.set('booking_date_to', this.selectedBookingDateTo);
    if (this.selectedFingerprintLocation) params.set('fingerprint_location', this.selectedFingerprintLocation);
    if (this.selectedBookingStatus) params.set('booking_status', this.selectedBookingStatus);
    if (this.selectedPassengerStatus) params.set('passenger_status', this.selectedPassengerStatus);
    if (this.selectedRouteDisplay) params.set('route_display', this.selectedRouteDisplay);
    if (this.selectedPackageId) params.set('package_id', this.selectedPackageId);
    if (this.selectedTicketAgentId) params.set('ticket_agent_id', this.selectedTicketAgentId);
    if (this.selectedActualFlightFrom) params.set('actual_flight_from', this.selectedActualFlightFrom);
    if (this.selectedActualFlightTo) params.set('actual_flight_to', this.selectedActualFlightTo);
    if (this.selectedReturnDateFrom) params.set('return_date_from', this.selectedReturnDateFrom);
    if (this.selectedReturnDateTo) params.set('return_date_to', this.selectedReturnDateTo);
    if (this.selectedStatusChangeAction) params.set('status_change_action', this.selectedStatusChangeAction);
    if (this.selectedStatusChangeFrom) params.set('status_change_from', this.selectedStatusChangeFrom);
    if (this.selectedStatusChangeTo) params.set('status_change_to', this.selectedStatusChangeTo);
    if (this.selectedPaymentWise) params.set('payment_wise', this.selectedPaymentWise);

    try {
        const res = await fetch(`/api/bookings/passengers?${params}`);
        const json = await res.json();
        this.passengers = json.data;
        this.passengersVisaData = json.data.map(p => p.visa_data);
        this.passengersTicketData = json.data.map(p => p.ticket_data);
        this.passengerTotalCostMap = Object.fromEntries(json.data.map(p => [p.id, p.cost]));
        this.totalPassengerCount = json.summary.total;
        // Build pagination HTML
        this.passengerPaginationHtml = this.buildPaginationHtml(json.pagination);
    } catch (e) {
        console.error('Failed to load passengers', e);
    }
    this.passengerLoading = false;
},

goPassengerPage(page) {
    this.passengerCurrentPage = page;
    this.loadPassengers();
},

buildPaginationHtml(pagination) {
    if (pagination.last_page <= 1) return '';
    let html = '<div class="flex gap-1">';
    for (let i = 1; i <= pagination.last_page; i++) {
        const active = i === pagination.current_page;
        html += `<button onclick="document.querySelector('[x-data]').__x.$data.goPassengerPage(${i})"
            class="px-3 py-1 rounded ${active ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'}">${i}</button>`;
    }
    html += '</div>';
    return html;
},
```

### Step 2.4: Convert filter handlers to AJAX

Replace all `window.location.href = url.toString()` handlers:

```js
// BEFORE (page reload):
onVisaStatusChange() {
    const url = new URL(window.location.href);
    if (this.selectedVisaStatus) url.searchParams.set('visa_status', this.selectedVisaStatus);
    else url.searchParams.delete('visa_status');
    url.searchParams.delete('page');
    window.location.href = url.toString();
},

// AFTER (AJAX):
onVisaStatusChange() {
    this.passengerCurrentPage = 1;
    this.loadPassengers();
},
```

Apply same pattern to all 18+ filter handlers.

### Step 2.5: Convert search to AJAX

```js
// BEFORE: 1500ms debounce + page reload
this.$watch('searchTerm', (val) => {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
        window.location.href = url.toString();
    }, 1500);
});

// AFTER: 400ms debounce + AJAX
this.$watch('searchTerm', (val) => {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
        this.passengerCurrentPage = 1;
        this.loadPassengers();
    }, 400);
});
```

### Step 2.6: Convert `navigateToTab` to AJAX

```js
// BEFORE: page reload
navigateToTab(tab) {
    window.location.href = url.toString();
},

// AFTER: AJAX
navigateToTab(tab) {
    this.activeTab = tab;
    if (tab === 'passenger' && this.passengers.length === 0) {
        this.loadPassengers();
    }
    document.body.style.overflow = tab === 'passenger' ? 'hidden' : '';
},
```

### Step 2.7: Convert passenger action handlers

All handlers that currently use `window.location.reload()` after an action
should call `this.loadPassengers()` instead:

- `updatePassengerStatus()` → after success: `this.loadPassengers()`
- `toggleVisaHold()` → after success: `this.loadPassengers()`
- `toggleTicketHold()` → after success: `this.loadPassengers()`
- `confirmTickets()` → after success: `this.loadPassengers()`
- Visa submit/issue/cancel/edit/revert → after success: `this.loadPassengers()`
- Ticket issue/issue-out → after success: `this.loadPassengers()`
- Remarks update → after success: `this.loadPassengers()`

---

## Phase 3: Booking Tab AJAX (Optional, Lower Priority)

The booking tab (`@forelse($bookings as $booking)`, lines 807-876) is simpler.
Could also convert to AJAX but lower priority since it paginates at 10 per page.

---

## What Stays Server-Side in `index()`

| Item | Why |
|------|-----|
| `Package::get()` | Dropdown data, small table |
| `Airline::with('travelClasses')->get()` | Fare modal dropdown |
| `TravelClass::all()` | Fare modal dropdown |
| `TicketFare::with([...])->get()` | Fare modal data |
| `PassengerStatus::all()` | Status dropdown |
| `VisaAgent::get()`, `TicketAgent::all()` | Agent dropdowns |
| `$flightDateRanges` | Computed in Blade, no DB query |
| Route loading | Route dropdown (optimize later) |
| `$canFilterBy*`, `$canEdit*` | Role checks, no DB queries |
| `$reIssueReasons` | Re-issue modal data |
| Booking tab queries | Paginated at 10, already fast |

---

## Rollout Order

| Phase | Files Changed | Risk | Test |
|-------|--------------|------|------|
| **1.1** | `BookingController.php` (passengerData + helpers) | Low | API returns correct data, all fields present |
| **1.2** | `BookingController.php` (slim index) | Low | Page loads, booking tab works, passenger tab empty |
| **2.1** | `bookings/index.blade.php` (@php block) | Medium | Passenger tab shows loading, then data via AJAX |
| **2.2** | `bookings/index.blade.php` (forelse → x-for) | High | All 27 columns render correctly |
| **2.3-2.5** | `bookings/index.blade.php` (filters + search) | Medium | All filters use AJAX, no page reload |
| **2.6-2.7** | `bookings/index.blade.php` (actions) | Medium | All inline actions work |
| **3** | `bookings/index.blade.php` (booking tab) | Low | Booking tab also uses AJAX |

---

## Verification Checklist

- [ ] Page load time < 2 seconds (was ~10+ seconds)
- [ ] Passenger tab data loads via AJAX within 1 second
- [ ] All 18+ filter dropdowns work (AJAX, no page reload)
- [ ] Search works with 400ms debounce
- [ ] Pagination works (page navigation via AJAX)
- [ ] All modals work (visa, ticket, remarks, fare, re-issue, refund)
- [ ] Inline status changes work
- [ ] Hold/Unhold visa and ticket work
- [ ] Financial columns visible for authorized roles
- [ ] Visa columns visible for authorized roles
- [ ] Ticket panel visible for authorized roles
- [ ] Download/Download All links work
- [ ] View Passenger link works
- [ ] Booking tab still works as before
- [ ] `vendor/bin/pint` passes
- [ ] `npm run build` passes
- [ ] All existing tests pass

---

## Risks / Notes

- The `passengersTicketData` computation in Blade is ~550 lines (lines 148-695).
  Moving it to the API means the API response per passenger will be large.
  Consider adding `fields` parameter to return only needed fields in future.
- `CurrencyRateService::getRateForDate()` is called per passenger in the API.
  This is in-request cached, so should be fine for 15 passengers per page.
- `CostTrackingService::getPassengerCosts()` is called per booking (not per
  passenger) thanks to the `bookingCostCache` pattern — keep this optimization.
- The Blade has ~60 `window.location.href` occurrences. Each must be converted
  to AJAX. Grep for `window.location.href` to find all of them.
- Do not modify `ui-references/` files; it is reference only.
