# Final Implementation Plan: Issued Ticket Records

---

## Overview

Replace the current client-side-only "Issue Ticket" flow with a proper server-persisted system. Create a dedicated `issued_tickets` table with an audit log (`issued_ticket_logs`), a controller with API endpoints, and update the frontend to persist data to the backend. Also embed the full "Create Ticket Fare" form inside the Issue Ticket modal for creating new fares on the fly, and backfill existing passengers.

---

## Phase 1: Database Migrations

### 1a. `issued_tickets` Table

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_create_issued_tickets_table.php`

```php
Schema::create('issued_tickets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('passenger_id')->constrained()->restrictOnDelete()->onUpdate('cascade');
    $table->foreignId('booking_id')->constrained()->restrictOnDelete()->onUpdate('cascade');
    $table->foreignId('user_id')->constrained()->restrictOnDelete();
    $table->foreignId('ticket_agent_id')->nullable()->constrained('ticket_agents')->restrictOnDelete();
    $table->foreignId('ticket_fare_id')->nullable()->constrained('ticket_fares')->nullOnDelete();
    $table->foreignId('group_ticket_id')->nullable()->constrained('group_tickets')->nullOnDelete();

    $table->string('ticket_number', 100)->nullable();
    $table->string('pnr', 50)->nullable();

    $table->date('issued_date')->nullable();
    $table->date('inbound_date')->nullable();
    $table->date('outbound_date')->nullable();

    $table->decimal('selling_fare', 12, 2)->default(0);
    $table->decimal('net_fare', 12, 2)->default(0);

    $table->boolean('is_refundable')->default(false);
    $table->boolean('is_exchangeable')->default(false);

    $table->string('baggage_inbound')->nullable();
    $table->string('baggage_outbound')->nullable();

    $table->boolean('outbound_pending')->default(false);

    $table->enum('issue_type', ['regular', 'additional', 'pending_outbound'])->nullable();
    $table->enum('status', ['pending', 'issued', 're-issued', 'refunded'])->default('pending');

    $table->softDeletes();
    $table->timestamps();
});
```

### 1b. `issued_ticket_logs` Table

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_create_issued_ticket_logs_table.php`

```php
Schema::create('issued_ticket_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('issued_ticket_id')
        ->constrained('issued_tickets')
        ->cascadeOnDelete();
    $table->foreignId('user_id')
        ->constrained()
        ->restrictOnDelete();
    $table->enum('action', ['issued', 'edited', 're-issued', 'refunded']);
    $table->json('old_data')->nullable();
    $table->json('new_data')->nullable();
    $table->timestamps();
});
```

---

## Phase 2: Eloquent Models

### 2a. `App\Models\IssuedTicket`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssuedTicket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'passenger_id', 'booking_id', 'user_id',
        'ticket_agent_id', 'ticket_fare_id', 'group_ticket_id',
        'ticket_number', 'pnr',
        'issued_date', 'inbound_date', 'outbound_date',
        'selling_fare', 'net_fare',
        'is_refundable', 'is_exchangeable',
        'baggage_inbound', 'baggage_outbound',
        'outbound_pending',
        'issue_type', 'status',
    ];

    protected $casts = [
        'issued_date'      => 'date',
        'inbound_date'     => 'date',
        'outbound_date'    => 'date',
        'is_refundable'    => 'boolean',
        'is_exchangeable'  => 'boolean',
        'outbound_pending' => 'boolean',
    ];

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ticketAgent(): BelongsTo
    {
        return $this->belongsTo(TicketAgent::class);
    }

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }

    public function groupTicket(): BelongsTo
    {
        return $this->belongsTo(GroupTicket::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IssuedTicketLog::class);
    }

    public function logAction(string $action, ?array $oldData, ?array $newData): void
    {
        $this->logs()->create([
            'user_id'  => auth()->id(),
            'action'   => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }
}
```

### 2b. `App\Models\IssuedTicketLog`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedTicketLog extends Model
{
    protected $fillable = [
        'issued_ticket_id', 'user_id', 'action', 'old_data', 'new_data',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function issuedTicket(): BelongsTo
    {
        return $this->belongsTo(IssuedTicket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### 2c. Update `App\Models\Passenger`

Add to existing class:

```php
public function issuedTickets(): HasMany
{
    return $this->hasMany(IssuedTicket::class);
}

public function latestIssuedTicket(): HasOne
{
    return $this->hasOne(IssuedTicket::class)->latestOfMany();
}
```

Add import: `use Illuminate\Database\Eloquent\Relations\HasOne;`

---

## Phase 3: Auto-Creation at Booking / Passenger Creation

### 3a. `BookingController@store` (after each passenger created)

```php
IssuedTicket::create([
    'passenger_id'    => $passenger->id,
    'booking_id'      => $booking->id,
    'user_id'         => auth()->id(),
    'ticket_fare_id'  => $passenger->ticket_fare_id,
    'issue_type'      => null,
    'status'          => 'pending',
    'is_refundable'   => false,
    'is_exchangeable' => false,
    'outbound_pending'=> false,
]);
```

### 3b. `BookingController@addPassenger` (after `Passenger::create($validated)`)

Same as above.

---

## Phase 4: Controller — `TicketIssueController`

**File:** `app/Http/Controllers/TicketIssueController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Passenger;
use App\Models\IssuedTicket;
use App\Models\GroupTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketIssueController extends Controller
{
    public function issue(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            return response()->json(['success' => false, 'message' => 'Passenger does not belong to this booking'], 403);
        }

        $validated = $request->validate([
            'ticket_number'    => 'nullable|string|max:100',
            'pnr'              => 'nullable|string|max:50',
            'ticket_agent_id'  => 'nullable|exists:ticket_agents,id',
            'ticket_fare_id'   => 'nullable|exists:ticket_fares,id',
            'group_ticket_id'  => 'nullable|exists:group_tickets,id',
            'issued_date'      => 'nullable|date',
            'inbound_date'     => 'nullable|date',
            'outbound_date'    => 'nullable|date',
            'selling_fare'     => 'nullable|numeric|min:0',
            'net_fare'         => 'nullable|numeric|min:0',
            'is_refundable'    => 'boolean',
            'is_exchangeable'  => 'boolean',
            'baggage_inbound'  => 'nullable|string|max:255',
            'baggage_outbound' => 'nullable|string|max:255',
            'outbound_pending' => 'boolean',
            'issue_type'       => 'nullable|in:regular,additional,pending_outbound',
        ]);

        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (!$issuedTicket) {
            return response()->json(['success' => false, 'message' => 'No pending ticket record found for this passenger'], 404);
        }

        DB::transaction(function () use ($validated, $issuedTicket, $passenger) {
            // Decrement group ticket qty if applicable
            if (!empty($validated['group_ticket_id'])) {
                $groupTicket = GroupTicket::findOrFail($validated['group_ticket_id']);
                if ($groupTicket->ticket_qty > 0) {
                    $groupTicket->decrement('ticket_qty');
                }
            }

            $oldData = $issuedTicket->toArray();

            $updateData = array_merge($validated, [
                'status'  => 'issued',
                'user_id' => auth()->id(),
            ]);
            $issuedTicket->update($updateData);

            $passenger->update(['ticket_status' => 'issued']);

            $issuedTicket->logAction('issued', $oldData, $issuedTicket->fresh()->toArray());
        });

        return response()->json([
            'success'       => true,
            'message'       => 'Ticket issued successfully',
            'issued_ticket' => $issuedTicket->fresh()->load([
                'ticketAgent', 'ticketFare.airline', 'ticketFare.airlineClass.class', 'ticketFare.route',
            ]),
        ]);
    }

    public function edit(Request $request, Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            return response()->json(['success' => false, 'message' => 'Passenger does not belong to this booking'], 403);
        }

        $validated = $request->validate([
            'ticket_number'    => 'nullable|string|max:100',
            'pnr'              => 'nullable|string|max:50',
            'ticket_agent_id'  => 'nullable|exists:ticket_agents,id',
            'ticket_fare_id'   => 'nullable|exists:ticket_fares,id',
            'group_ticket_id'  => 'nullable|exists:group_tickets,id',
            'issued_date'      => 'nullable|date',
            'inbound_date'     => 'nullable|date',
            'outbound_date'    => 'nullable|date',
            'selling_fare'     => 'nullable|numeric|min:0',
            'net_fare'         => 'nullable|numeric|min:0',
            'is_refundable'    => 'boolean',
            'is_exchangeable'  => 'boolean',
            'baggage_inbound'  => 'nullable|string|max:255',
            'baggage_outbound' => 'nullable|string|max:255',
            'outbound_pending' => 'boolean',
        ]);

        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)
            ->where('status', 'issued')
            ->latest()
            ->first();

        if (!$issuedTicket) {
            return response()->json(['success' => false, 'message' => 'No issued ticket found for this passenger'], 404);
        }

        DB::transaction(function () use ($validated, $issuedTicket) {
            $oldData = $issuedTicket->toArray();

            // Do NOT update user_id — frozen after first issue
            $issuedTicket->update($validated);

            $issuedTicket->logAction('edited', $oldData, $issuedTicket->fresh()->toArray());
        });

        return response()->json([
            'success'       => true,
            'message'       => 'Ticket updated successfully',
            'issued_ticket' => $issuedTicket->fresh()->load([
                'ticketAgent', 'ticketFare.airline', 'ticketFare.airlineClass.class', 'ticketFare.route',
            ]),
        ]);
    }

    // reissue() — future
    // refund() — future
}
```

---

## Phase 5: Routes

**File:** `routes/web.php`

Add inside the `auth` middleware group, after visa routes:

```php
// Ticket issue routes
Route::middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff')->group(function () {
    Route::post('/bookings/{booking}/passengers/{passenger}/ticket-issue', [TicketIssueController::class, 'issue'])
        ->name('bookings.passengers.ticket-issue');
    Route::put('/bookings/{booking}/passengers/{passenger}/ticket-edit', [TicketIssueController::class, 'edit'])
        ->name('bookings.passengers.ticket-edit');
    // Future:
    // Route::post('/bookings/{booking}/passengers/{passenger}/ticket-reissue', [TicketIssueController::class, 'reissue']);
    // Route::post('/bookings/{booking}/passengers/{passenger}/ticket-refund', [TicketIssueController::class, 'refund']);
});

// API: Quick-create ticket fare from "Add New Ticket"
Route::post('/api/ticket-fares/quick-create', [TicketFareController::class, 'quickStore'])
    ->middleware('auth');
```

---

## Phase 6: `TicketFareController@quickStore`

**File:** `app/Http/Controllers/TicketFareController.php` — add new method:

```php
public function quickStore(Request $request)
{
    $validated = $request->validate([
        'route_type'             => 'required|string',
        'flight_type'            => 'required|string',
        'airline_id'             => 'required|exists:airlines,id',
        'airline_classes_id'     => 'required|exists:airline_classes,id',
        'route_id'               => 'required|exists:routes,id',
        'ticket_type'            => 'required|in:regular,offer,group',
        'effective_from'         => 'nullable|date',
        'effective_to'           => 'nullable|date',
        'with_meal'              => 'boolean',
        'net_fare'               => 'required|numeric|min:0',
        'selling_fare'           => 'required|numeric|min:0',
        'offer_price'            => 'nullable|numeric|min:0',
        'child_fare_percentage'  => 'nullable|numeric|min:0|max:100',
        'infant_fare_percentage' => 'nullable|numeric|min:0|max:100',
        'pnr'                    => 'nullable|string|max:50',
        'ticket_qty'             => 'nullable|integer|min:1',
        'inbound_date'           => 'nullable|date',
        'outbound_date'          => 'nullable|date',
        'is_non_refundable'      => 'boolean',
        'is_non_exchangable'     => 'boolean',
        'inbound_adult'          => 'nullable|numeric|min:0',
        'inbound_child'          => 'nullable|numeric|min:0',
        'inbound_infant'         => 'nullable|numeric|min:0',
        'outbound_adult'         => 'nullable|numeric|min:0',
        'outbound_child'         => 'nullable|numeric|min:0',
        'outbound_infant'        => 'nullable|numeric|min:0',
    ]);

    $ticketFare = DB::transaction(function () use ($validated) {
        $fare = TicketFare::create([
            'airline_id'             => $validated['airline_id'],
            'airline_classes_id'     => $validated['airline_classes_id'],
            'route_id'               => $validated['route_id'],
            'route_type'             => $validated['route_type'],
            'ticket_type'            => $validated['ticket_type'],
            'effective_from'         => $validated['effective_from'] ?? now(),
            'effective_to'           => $validated['effective_to'] ?? now()->addYear(),
            'net_fare'               => $validated['net_fare'],
            'selling_fare'           => $validated['selling_fare'],
            'offer_price'            => $validated['offer_price'] ?? null,
            'child_fare_percentage'  => $validated['child_fare_percentage'] ?? 70,
            'infant_fare_percentage' => $validated['infant_fare_percentage'] ?? 30,
            'with_meal'              => $validated['with_meal'] ?? false,
            'user_id'                => auth()->id(),
        ]);

        if ($validated['ticket_type'] === 'group') {
            $fare->groupTicket()->create([
                'pnr'            => $validated['pnr'] ?? '',
                'ticket_qty'     => $validated['ticket_qty'] ?? 1,
                'inbound_date'   => $validated['inbound_date'] ?? null,
                'outbound_date'  => $validated['outbound_date'] ?? null,
                'is_refundable'  => !($validated['is_non_refundable'] ?? false),
                'is_exchangable' => !($validated['is_non_exchangable'] ?? false),
            ]);
        }

        // Create baggage allowances
        $baggageData = [];
        foreach (['inbound', 'outbound'] as $direction) {
            foreach (['adult', 'child', 'infant'] as $type) {
                $key = "{$direction}_{$type}";
                $val = $validated[$key] ?? null;
                if ($val !== null) {
                    $baggageData[] = new BaggageAllowance([
                        'passenger_type'   => $type,
                        'travel_direction' => $direction,
                        'allowance'        => (string) $val,
                    ]);
                }
            }
        }
        if (!empty($baggageData)) {
            $fare->baggageAllowances()->saveMany($baggageData);
        }

        return $fare;
    });

    $ticketFare->load([
        'airline', 'airlineClass.class', 'route.fromCity', 'route.toCity', 'route.returnCity',
        'route.multiSegments.fromCity', 'route.multiSegments.toCity',
        'baggageAllowances',
    ]);

    return response()->json([
        'success'     => true,
        'ticket_fare' => $ticketFare,
    ]);
}
```

---

## Phase 7: Update `BookingController@index` — Eager Loading

In the `index()` method, add to the passenger eager load:

```php
$passengers = Passenger::with([
    // ... existing loads
    'latestIssuedTicket.ticketAgent',
    'latestIssuedTicket.ticketFare.airline',
    'latestIssuedTicket.ticketFare.airlineClass.class',
    'latestIssuedTicket.ticketFare.route',
    'ticketFare.baggageAllowances',
])->get();
```

---

## Phase 8: Update `$passengersTicketData` PHP Prep

**File:** `resources/views/bookings/index.blade.php` (lines 57-113)

Add these fields to the passenger map:

```php
'booking_id' => $p->booking_id,

'latest_issued_ticket' => $p->latestIssuedTicket ? [
    'id'              => $p->latestIssuedTicket->id,
    'ticket_number'   => $p->latestIssuedTicket->ticket_number,
    'pnr'             => $p->latestIssuedTicket->pnr,
    'ticket_agent'    => $p->latestIssuedTicket->ticketAgent?->name ?? '',
    'ticket_agent_id' => $p->latestIssuedTicket->ticket_agent_id,
    'ticket_fare_id'  => $p->latestIssuedTicket->ticket_fare_id,
    'group_ticket_id' => $p->latestIssuedTicket->group_ticket_id,
    'issued_date'     => $p->latestIssuedTicket->issued_date?->format('Y-m-d'),
    'inbound_date'    => $p->latestIssuedTicket->inbound_date?->format('Y-m-d'),
    'outbound_date'   => $p->latestIssuedTicket->outbound_date?->format('Y-m-d'),
    'selling_fare'    => (float) $p->latestIssuedTicket->selling_fare,
    'net_fare'        => (float) $p->latestIssuedTicket->net_fare,
    'is_refundable'   => $p->latestIssuedTicket->is_refundable,
    'is_exchangeable' => $p->latestIssuedTicket->is_exchangeable,
    'baggage_inbound' => $p->latestIssuedTicket->baggage_inbound,
    'baggage_outbound'=> $p->latestIssuedTicket->baggage_outbound,
    'outbound_pending'=> $p->latestIssuedTicket->outbound_pending,
    'issue_type'      => $p->latestIssuedTicket->issue_type,
    'status'          => $p->latestIssuedTicket->status,
] : null,

'baggage_allowances' => $p->ticketFare?->baggageAllowances->map(fn($b) => [
    'passenger_type'   => $b->passenger_type,
    'travel_direction' => $b->travel_direction,
    'allowance'        => $b->allowance,
]) ?? [],
```

---

## Phase 9: Frontend — Issue Ticket Modal (index.blade.php)

### 9a. Replace Baggage Fields (lines 713-749)

Remove the 6 separate baggage fields. Replace with 2 string fields:

```blade
<div x-show="ticketFareForm.showBaggage" class="mb-4">
    <h4 class="text-sm font-medium text-slate-600 mb-3 pb-2 border-b border-slate-200">Baggage Info</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div x-show="ticketFareForm.showInboundBaggage">
            <label class="block text-sm font-medium text-slate-700 mb-1">Baggage Inbound</label>
            <input type="text" x-model="ticketFareForm.baggage_inbound"
                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none"
                   placeholder="Auto-suggested from fare">
        </div>
        <div x-show="ticketFareForm.showOutboundBaggage">
            <label class="block text-sm font-medium text-slate-700 mb-1">Baggage Outbound</label>
            <input type="text" x-model="ticketFareForm.baggage_outbound"
                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 outline-none"
                   placeholder="Auto-suggested from fare">
        </div>
    </div>
</div>
```

### 9b. Add "Outbound Ticket Pending" Checkbox

In the Ticket Options section (after Non-Exchangeable):

```blade
<label class="flex items-center gap-2 cursor-pointer">
    <input type="checkbox" x-model="ticketFareForm.outbound_pending"
           class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-400">
    <span class="text-sm text-slate-700">Outbound Ticket Pending</span>
</label>
```

### 9c. Update `ticketFareForm` Alpine Data

Replace 6 baggage fields with 2; add `outbound_pending`:

```javascript
ticketFareForm: {
    // ... existing fields unchanged
    baggage_inbound: '',
    baggage_outbound: '',
    outbound_pending: false,
    // ... show flags unchanged
},
```

### 9d. Baggage Auto-Suggestion (`suggestBaggage()`)

```javascript
suggestBaggage() {
    const index = this.editingPassengerIndex;
    if (index === null) return;
    const row = this.passengersTicketData[index];
    if (!row?.baggage_allowances) return;

    const pType = row.passenger_type || 'adult';
    const matches = row.baggage_allowances.filter(b => b.passenger_type === pType);

    const inbound = matches.find(b => b.travel_direction === 'inbound');
    const outbound = matches.find(b => b.travel_direction === 'outbound');

    if (inbound) this.ticketFareForm.baggage_inbound = inbound.allowance;
    if (outbound) this.ticketFareForm.baggage_outbound = outbound.allowance;
},
```

Call `this.suggestBaggage()` at the end of `handleTicketOptionChange()`.

### 9e. Rewrite `handleTicketFareSubmit()` — API Call

Replace the existing client-side-only function:

```javascript
handleTicketFareSubmit() {
    if (this.editingPassengerIndex === null) return;
    const row = this.passengersTicketData[this.editingPassengerIndex];
    if (!row) return;

    const isEdit = row.ticket_status === 'issued' || row.ticket_status === 're-issued';
    const method = isEdit ? 'PUT' : 'POST';
    const url = isEdit
        ? `/bookings/${row.booking_id}/passengers/${row.id}/ticket-edit`
        : `/bookings/${row.booking_id}/passengers/${row.id}/ticket-issue`;

    const agentId = this.getAgentIdByName(this.ticketFareForm.ticket_agent);

    const payload = {
        ticket_number:    this.ticketFareForm.ticket_number || '',
        pnr:              this.ticketFareForm.pnr || '',
        ticket_agent_id:  agentId,
        ticket_fare_id:   this.getSelectedFareId(),
        group_ticket_id:  this.ticketFareForm.group_ticket_id || null,
        issued_date:      this.ticketFareForm.date || null,
        inbound_date:     this.ticketFareForm.inbound_date || null,
        outbound_date:    this.ticketFareForm.outbound_date || null,
        selling_fare:     parseFloat(this.ticketFareForm.selling_fare) || 0,
        net_fare:         parseFloat(this.ticketFareForm.net_fare) || 0,
        is_refundable:    !this.ticketFareForm.non_refundable,
        is_exchangeable:  !this.ticketFareForm.non_exchangeable,
        baggage_inbound:  this.ticketFareForm.baggage_inbound || null,
        baggage_outbound: this.ticketFareForm.baggage_outbound || null,
        outbound_pending: this.ticketFareForm.outbound_pending,
    };

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            row.ticket_status = res.issued_ticket.status;
            row.ticket_fare = this.mapIssuedTicketToForm(res.issued_ticket);
            this.showToast(isEdit ? 'Ticket updated successfully' : 'Ticket issued successfully');
            this.closeTicketFareModal();
        } else {
            alert(res.message || 'Failed to save ticket');
        }
    })
    .catch(err => {
        console.error('Ticket save error:', err);
        alert('Failed to save ticket');
    });
},

getAgentIdByName(name) {
    const agent = this.ticketAgents.find(a => a.name === name);
    return agent ? agent.id : null;
},

getSelectedFareId() {
    const row = this.passengersTicketData[this.editingPassengerIndex];
    if (!row) return null;
    if (row.latest_issued_ticket?.ticket_fare_id) return row.latest_issued_ticket.ticket_fare_id;
    return row.ticket_fare_id || null;
},

mapIssuedTicketToForm(ticket) {
    return {
        ticket_type:      ticket.ticket_fare?.ticket_type || 'regular',
        route_type:       ticket.ticket_fare?.route?.route_type || '',
        flight_type:      ticket.ticket_fare?.route?.flight_type || '',
        group_ticket_id:  ticket.group_ticket_id,
        inbound_date:     ticket.inbound_date || '',
        outbound_date:    ticket.outbound_date || '',
        pnr:              ticket.pnr || '',
        ticket_number:    ticket.ticket_number || '',
        date:             ticket.issued_date || '',
        ticket_agent:     ticket.ticket_agent?.name || '',
        selling_fare:     ticket.selling_fare,
        net_fare:         ticket.net_fare,
        non_refundable:   !ticket.is_refundable,
        non_exchangeable: !ticket.is_exchangeable,
        baggage_inbound:  ticket.baggage_inbound || '',
        baggage_outbound: ticket.baggage_outbound || '',
        outbound_pending: ticket.outbound_pending,
    };
},
```

### 9f. Conditional Button Visibility (lines 378-379)

Replace with Alpine `x-show`:

```blade
<button @click="openTicketFareModal({{ $loop->index }})"
        x-show="passengersTicketData[{{ $loop->index }}]?.ticket_status === 'pending' || !passengersTicketData[{{ $loop->index }}]?.ticket_status"
        class="text-xs bg-green-100 hover:bg-green-200 text-green-600 px-2 py-1 rounded font-medium transition">
    Issue
</button>
<button @click="openTicketFareModal({{ $loop->index }})"
        x-show="passengersTicketData[{{ $loop->index }}]?.ticket_status === 'issued' || passengersTicketData[{{ $loop->index }}]?.ticket_status === 're-issued'"
        class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-1 rounded font-medium transition">
    Edit
</button>
```

### 9g. Update `openTicketFareModal()` — Load from `latest_issued_ticket`

```javascript
openTicketFareModal(rowIndex) {
    this.editingPassengerIndex = rowIndex;
    const row = this.passengersTicketData[rowIndex];
    if (!row) return;

    const isAlreadyIssued = row.ticket_status === 'issued' || row.ticket_status === 're-issued';
    this.ticketFareModalTitle = isAlreadyIssued ? 'Edit Ticket' : 'Issue Ticket';

    // Always populate route/airline/class from passenger data
    this.ticketFareForm.route = row.route || '';
    this.ticketFareForm.airline = row.airline || '';
    this.ticketFareForm.travel_class = row.travel_class || '';
    this.ticketFareForm.passenger_type = row.passenger_type || 'adult';

    if (isAlreadyIssued && row.latest_issued_ticket) {
        // Populate from persisted issued ticket
        const t = row.latest_issued_ticket;
        this.ticketFareForm.ticket_type = t.ticket_type || '';
        this.ticketFareForm.route_type = t.route_type || '';
        this.ticketFareForm.flight_type = t.flight_type || '';
        this.ticketFareForm.group_ticket_id = t.group_ticket_id || '';
        this.ticketFareForm.inbound_date = t.inbound_date || '';
        this.ticketFareForm.outbound_date = t.outbound_date || '';
        this.ticketFareForm.pnr = t.pnr || '';
        this.ticketFareForm.ticket_number = t.ticket_number || '';
        this.ticketFareForm.date = t.issued_date || this.getToday();
        this.ticketFareForm.ticket_agent = t.ticket_agent || '';
        this.ticketFareForm.selling_fare = t.selling_fare || 0;
        this.ticketFareForm.net_fare = t.net_fare || 0;
        this.ticketFareForm.non_refundable = !t.is_refundable;
        this.ticketFareForm.non_exchangeable = !t.is_exchangeable;
        this.ticketFareForm.baggage_inbound = t.baggage_inbound || '';
        this.ticketFareForm.baggage_outbound = t.baggage_outbound || '';
        this.ticketFareForm.outbound_pending = t.outbound_pending || false;
    } else if (row.ticket_fare) {
        // Populate from pre-configured fare (existing logic)
        // ... keep existing logic
    } else {
        // Reset to defaults (existing logic)
        // ... keep existing logic
    }

    this.handleTicketFareRouteTypeChange();
    this.handleTicketTypeChange();
    this.isTicketFareModalOpen = true;
},

// Add helper
getToday() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
},
```

---

## Phase 10: "Add New Ticket" — Inline Form

### 10a. Trigger Button

After the Ticket `select` dropdown (after closing `</select>` tag):

```blade
<template x-if="ticketFareForm.ticket_type !== 'group'">
    <div class="mt-1">
        <button @click="showAddNewTicket = !showAddNewTicket" type="button"
                class="text-xs text-blue-600 hover:text-blue-800 font-medium">
            <span x-text="showAddNewTicket ? '− Hide' : '+ Add New Ticket'"></span>
        </button>
    </div>
</template>
```

### 10b. Alpine State for Add New Ticket

Add to the `bookingIndexApp()` return object:

```javascript
showAddNewTicket: false,

addNewTicketForm: {
    route_type: '',
    flight_type: '',
    airline_id: '',
    airline_classes_id: '',
    route_id: '',
    ticket_type: 'regular',
    effective_from: '',
    effective_to: '',
    with_meal: false,
    net_fare: 0,
    selling_fare: 0,
    offer_price: 0,
    child_fare_percentage: 70,
    infant_fare_percentage: 30,
    pnr: '',
    ticket_qty: 1,
    inbound_date: '',
    outbound_date: '',
    is_non_refundable: false,
    is_non_exchangable: false,
    inbound_adult: 30,
    inbound_child: 30,
    inbound_infant: 10,
    outbound_adult: 50,
    outbound_child: 50,
    outbound_infant: 10,
},

addNewTicketSaving: false,

// Sub-modal states
routeModalOpen: false,
airlineModalOpen: false,
classModalOpen: false,
// ... copy all sub-modal data/save/append methods from create.blade.php
```

### 10c. Section Display Logic

Use `x-show` for conditional sections:
- Offer Price: `x-show="addNewTicketForm.ticket_type === 'offer'"`
- Group Ticket section: `x-show="addNewTicketForm.ticket_type === 'group'"`
- Baggage section: `x-show="addNewTicketForm.route_type !== ''"`
- Inbound/outbound date/baggage: filter by route_type

### 10d. Filtering Functions

```javascript
get addNewFilteredRoutes() {
    const rt = this.addNewTicketForm.route_type;
    const ft = this.addNewTicketForm.flight_type;
    if (!rt || !ft) return this.routesList;
    return this.routesList.filter(r => r.route_type === rt && r.flight_type === ft);
},

get addNewFilteredClasses() {
    const airlineId = parseInt(this.addNewTicketForm.airline_id);
    if (!airlineId) return this.classesList;
    const airline = this.airlinesList.find(a => a.id === airlineId);
    if (!airline) return this.classesList;
    return this.classesList.filter(c => airline.class_ids.includes(c.id));
},
```

### 10e. Submit Handler

```javascript
handleAddNewTicketCreate() {
    this.addNewTicketSaving = true;

    fetch('/api/ticket-fares/quick-create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(this.addNewTicketForm)
    })
    .then(r => r.json())
    .then(res => {
        this.addNewTicketSaving = false;
        if (res.success) {
            const fare = res.ticket_fare;
            const routeDisplay = this.buildRouteDisplay(fare.route);
            const airlineName = fare.airline?.name || '';
            const className = fare.airlineClass?.class?.name || '';
            const optionValue = routeDisplay + '||' + airlineName + '||' + className;

            // Auto-select in the Ticket dropdown
            this.ticketFareForm.ticket_option = optionValue;
            this.handleTicketOptionChange();

            this.showAddNewTicket = false;
            this.showToast('Ticket fare created successfully');
        } else {
            alert(res.message || 'Failed to create ticket fare');
        }
    })
    .catch(err => {
        this.addNewTicketSaving = false;
        console.error('Add new ticket error:', err);
        alert('Failed to create ticket fare');
    });
},

buildRouteDisplay(route) {
    if (!route) return '';
    const rt = route.route_type?.value || route.route_type;
    if (rt === 'multi_city') {
        const seg = route.multi_segments?.[0] || route.multiSegments?.[0];
        if (seg) {
            const fromCode = seg.from_city?.code || seg.fromCity?.code || '?';
            const toCode = seg.to_city?.code || seg.toCity?.code || '?';
            return fromCode + '-' + toCode;
        }
        return 'Multi City';
    }
    const fromCode = route.from_city?.code || route.fromCity?.code || '?';
    const toCode = route.to_city?.code || route.toCity?.code || '?';
    const returnCode = route.return_city?.code || route.returnCity?.code || '';
    return rt === 'round' && returnCode
        ? fromCode + '-' + toCode + '-' + returnCode
        : fromCode + '-' + toCode;
},
```

### 10f. Inline HTML Structure

```blade
<template x-if="showAddNewTicket">
    <div class="mt-4 p-4 border border-blue-200 rounded-lg bg-blue-50">
        <h4 class="text-sm font-semibold text-blue-800 mb-3">New Ticket Fare</h4>

        <!-- Section 1: Basic Information -->
        <div class="mb-4">
            <h5 class="text-xs font-medium text-blue-600 mb-2 uppercase tracking-wide">Basic Information</h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Route Type *</label>
                    <select x-model="addNewTicketForm.route_type" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        <option value="">Select</option>
                        <option value="oneway_inbound">One Way - Inbound</option>
                        <option value="oneway_outbound">One Way - Outbound</option>
                        <option value="round">Round</option>
                        <option value="multi_city">Multi City</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Flight Type *</label>
                    <select x-model="addNewTicketForm.flight_type" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        <option value="">Select</option>
                        <option value="direct">Direct</option>
                        <option value="transit">Transit</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Airline *</label>
                    <select x-model="addNewTicketForm.airline_id" required
                            @change="onAirlineSelectChange($event)"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        <option value="">Select Airline</option>
                        <option value="__add_new__">+ Add New Airline</option>
                        <template x-for="a in airlinesList" :key="a.id">
                            <option :value="a.id" x-text="a.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Class *</label>
                    <select x-model="addNewTicketForm.airline_classes_id" required
                            @change="onClassSelectChange($event)"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        <option value="">Select Class</option>
                        <option value="__add_new__">+ Add New Class</option>
                        <template x-for="c in addNewFilteredClasses" :key="c.id">
                            <option :value="c.id" x-text="c.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Route *</label>
                    <select x-model="addNewTicketForm.route_id" required
                            @change="onRouteSelectChange($event)"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        <option value="">Select Route</option>
                        <option value="__add_new__">+ Add New Route</option>
                        <template x-for="r in addNewFilteredRoutes" :key="r.id">
                            <option :value="r.id" x-text="r.display"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Type *</label>
                    <select x-model="addNewTicketForm.ticket_type"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                        <option value="regular">Regular</option>
                        <option value="offer">Offer</option>
                        <option value="group">Group</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Effective From *</label>
                    <input type="date" x-model="addNewTicketForm.effective_from"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Effective To *</label>
                    <input type="date" x-model="addNewTicketForm.effective_to"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div class="flex items-center">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" x-model="addNewTicketForm.with_meal"
                               class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-500">
                        <span class="ml-2 text-sm text-slate-700">With Meal</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Section 2: Fare Information -->
        <div class="mb-4">
            <h5 class="text-xs font-medium text-blue-600 mb-2 uppercase tracking-wide">Fare Information</h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Net Fare (SAR) *</label>
                    <input type="number" x-model="addNewTicketForm.net_fare" step="0.01" min="0" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Selling Fare (SAR) *</label>
                    <input type="number" x-model="addNewTicketForm.selling_fare" step="0.01" min="0" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div x-show="addNewTicketForm.ticket_type === 'offer'">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Offer Price (SAR)</label>
                    <input type="number" x-model="addNewTicketForm.offer_price" step="0.01" min="0"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Child Fare (%)</label>
                    <input type="number" x-model="addNewTicketForm.child_fare_percentage" step="0.01" min="0" max="100"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Infant Fare (%)</label>
                    <input type="number" x-model="addNewTicketForm.infant_fare_percentage" step="0.01" min="0" max="100"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
            </div>
        </div>

        <!-- Section 3: Group Ticket Details (conditional) -->
        <div x-show="addNewTicketForm.ticket_type === 'group'" class="mb-4">
            <h5 class="text-xs font-medium text-blue-600 mb-2 uppercase tracking-wide">Group Ticket Details</h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">PNR</label>
                    <input type="text" x-model="addNewTicketForm.pnr"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ticket Quantity</label>
                    <input type="number" x-model="addNewTicketForm.ticket_qty" min="1"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div x-show="addNewTicketForm.route_type !== 'oneway_outbound'">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Inbound Date</label>
                    <input type="date" x-model="addNewTicketForm.inbound_date"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div x-show="addNewTicketForm.route_type !== 'oneway_inbound'">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Outbound Date</label>
                    <input type="date" x-model="addNewTicketForm.outbound_date"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
            </div>
            <div class="flex gap-4 mt-3">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" x-model="addNewTicketForm.is_non_refundable"
                           class="w-4 h-4 text-slate-600 border-slate-300 rounded">
                    <span class="ml-2 text-sm text-slate-700">Non-Refundable</span>
                </label>
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" x-model="addNewTicketForm.is_non_exchangable"
                           class="w-4 h-4 text-slate-600 border-slate-300 rounded">
                    <span class="ml-2 text-sm text-slate-700">Non-Exchangable</span>
                </label>
            </div>
        </div>

        <!-- Section 4: Baggage Allowances (conditional on route_type) -->
        <div x-show="addNewTicketForm.route_type !== ''" class="mb-4">
            <h5 class="text-xs font-medium text-blue-600 mb-2 uppercase tracking-wide">Baggage Allowances (KG)</h5>
            <div x-show="addNewTicketForm.route_type !== 'oneway_outbound'" class="mb-3">
                <h6 class="text-sm font-medium text-slate-600 mb-2">Inbound</h6>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Adult</label>
                        <input type="number" x-model="addNewTicketForm.inbound_adult" min="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Child</label>
                        <input type="number" x-model="addNewTicketForm.inbound_child" min="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Infant</label>
                        <input type="number" x-model="addNewTicketForm.inbound_infant" min="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                </div>
            </div>
            <div x-show="addNewTicketForm.route_type !== 'oneway_inbound'">
                <h6 class="text-sm font-medium text-slate-600 mb-2">Outbound</h6>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Adult</label>
                        <input type="number" x-model="addNewTicketForm.outbound_adult" min="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Child</label>
                        <input type="number" x-model="addNewTicketForm.outbound_child" min="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Infant</label>
                        <input type="number" x-model="addNewTicketForm.outbound_infant" min="0"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 mt-4 pt-3 border-t border-blue-200">
            <button @click="handleAddNewTicketCreate()" type="button"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm"
                    :disabled="addNewTicketSaving">
                <span x-text="addNewTicketSaving ? 'Creating...' : 'Create Ticket'"></span>
            </button>
            <button @click="showAddNewTicket = false" type="button"
                    class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 font-medium text-sm">
                Cancel
            </button>
        </div>
    </div>
</template>
```

### 10g. Sub-Modals (Option A)

The partials `route-form-modal`, `airline-form-modal`, and `class-form-modal` currently use `x-data="ticketFareForm()"`. They must be updated to omit their own `x-data` and instead reference `bookingIndexApp()` properties directly.

**Changes to `partials/route-form-modal.blade.php`:**
- Remove `x-data="ticketFareForm()"` (or change to reference the parent)
- All bindings must point to `bookingIndexApp()` properties:
  - `x-show="routeModalOpen"` (was `showRouteModal`)
  - `route.*` → `addNewTicketRoute.*` (keep same field names, namespaced)
  - Save method: `saveAddNewTicketRoute()` (was `saveRoute()`)
  - On success: `appendRouteToSelect(route)` → `appendAddNewRouteToSelect(route)`

**Changes to `partials/airline-form-modal.blade.php`:**
- Remove `x-data`
- `x-show="airlineModalOpen"`
- `airlineData.*` → `addNewAirlineData.*`
- Save method: `saveAddNewAirline()`
- On success: `appendAirlineToSelect(airline)` → `appendAddNewAirlineToSelect(airline)`

**Changes to `partials/class-form-modal.blade.php`:**
- Remove `x-data`
- `x-show="classModalOpen"`
- `classData.*` → `addNewClassData.*`
- Save method: `saveAddNewClass()`
- On success: `appendClassToSelect(ac)` → `appendAddNewClassToSelect(ac)`

All sub-modal data objects, save methods, and append methods must be added to the `bookingIndexApp()` return object.

---

## Phase 11: Backfill Command

**File:** `app/Console/Commands/BackfillIssuedTickets.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\IssuedTicket;
use App\Models\Passenger;
use Illuminate\Console\Command;

class BackfillIssuedTickets extends Command
{
    protected $signature = 'tickets:backfill-issued';
    protected $description = 'Create issued_tickets records for all existing passengers';

    public function handle()
    {
        $count = 0;

        Passenger::with('booking')->chunk(100, function ($passengers) use (&$count) {
            foreach ($passengers as $passenger) {
                if (IssuedTicket::where('passenger_id', $passenger->id)->exists()) {
                    continue;
                }

                $currentStatus = $passenger->ticket_status?->value;
                $mapStatus = match ($currentStatus) {
                    'issued'     => 'issued',
                    're-issued'  => 'issued',
                    'refunded'   => 'refunded',
                    default      => 'pending',
                };

                IssuedTicket::create([
                    'passenger_id'    => $passenger->id,
                    'booking_id'      => $passenger->booking_id,
                    'user_id'         => $passenger->booking?->user_id ?? 1,
                    'ticket_fare_id'  => $passenger->ticket_fare_id,
                    'issue_type'      => null,
                    'status'          => $mapStatus,
                    'is_refundable'   => false,
                    'is_exchangeable' => false,
                    'outbound_pending'=> false,
                ]);

                if ($currentStatus === null) {
                    $passenger->update(['ticket_status' => 'pending']);
                }

                $count++;
            }
        });

        $this->info("Created {$count} issued_ticket records.");
    }
}
```

**Register** in `App\Console\Kernel.php`:
```php
protected $commands = [
    Commands\BackfillIssuedTickets::class,
];
```

**Run:**
```bash
php artisan tickets:backfill-issued
```

---

## Phase 12: Deferred Workflows (Future)

These are not implemented now, but the schema and controller structure support them:

| Workflow | `issue_type` | `status` | `outbound_pending` |
|----------|-------------|----------|-------------------|
| Re-issue (via show page) | `regular` | `re-issued` | preserved |
| Additional Ticket (via show page) | `additional` | `issued` | preserved |
| Pending Outbound (via show page) | `pending_outbound` | `issued` | `true` |

---

## Complete Implementation Order

| # | Owner | Task | Files |
|---|-------|------|-------|
| 1 | @Mostafiz | Migration: `issued_tickets` table | New migration |
| 2 | @Mostafiz | Migration: `issued_ticket_logs` table | New migration |
| 3 | @Mostafiz | Model: `IssuedTicket` | `app/Models/IssuedTicket.php` |
| 4 | @Mostafiz | Model: `IssuedTicketLog` | `app/Models/IssuedTicketLog.php` |
| 5 | @Mostafiz | Add `issuedTickets()` + `latestIssuedTicket()` to Passenger | `app/Models/Passenger.php` |
| 6 | @Mostafiz | Auto-creation in `BookingController@store` + `@addPassenger` | `app/Http/Controllers/BookingController.php` |
| 7 | @Mostafiz | `TicketIssueController` with `issue()` + `edit()` | `app/Http/Controllers/TicketIssueController.php` |
| 8 | @Mostafiz | Routes for ticket issue/edit | `routes/web.php` |
| 9 | @Mostafiz | `TicketFareController@quickStore` | `app/Http/Controllers/TicketFareController.php` |
| 10 | @Mostafiz | Backfill command | `app/Console/Commands/BackfillIssuedTickets.php` |
| 11 | @Mostafiz | Run `php artisan tickets:backfill-issued` | Terminal |
| 12 | @Mostafiz | Add eager loads to `BookingController@index` | `app/Http/Controllers/BookingController.php` |
| 13 | @Shanto | Update `$passengersTicketData` PHP prep | `resources/views/bookings/index.blade.php` |
| 14 | @Shanto | Replace 6 baggage fields → 2 string fields | `resources/views/bookings/index.blade.php` |
| 15 | @Shanto | Baggage auto-suggest in `handleTicketOptionChange()` | `resources/views/bookings/index.blade.php` |
| 16 | @Shanto | Add "Outbound Ticket Pending" checkbox | `resources/views/bookings/index.blade.php` |
| 17 | @Shanto | "Add New Ticket" button + inline form (all 4 sections + sub-modals) | `resources/views/bookings/index.blade.php` |
| 18 | @Mostafiz | Rewrite `handleTicketFareSubmit()` → API call | `resources/views/bookings/index.blade.php` |
| 19 | @Mostafiz | Conditional Issue/Edit button visibility | `resources/views/bookings/index.blade.php` |
| 20 | @Mostafiz | Update `openTicketFareModal()` to load from `latest_issued_ticket` | `resources/views/bookings/index.blade.php` |

---

## Testing Checklist

- [ ] `issued_tickets` record auto-created on booking creation and passenger add
- [ ] Issue button visible only when status is `pending` or null; Edit only when `issued`/`re-issued`
- [ ] Issue API call succeeds → `status` → `issued`, `user_id` updated, `issued_ticket_log` created
- [ ] Edit API call succeeds → fields updated, `user_id` NOT changed, log with action `edited`
- [ ] Baggage auto-suggests from `baggage_allowances` matching `passenger_type`
- [ ] "Outbound Ticket Pending" checkbox sets `outbound_pending = true`
- [ ] "Add New Ticket" creates fare via API, auto-selects in Ticket dropdown
- [ ] "+ Add New" sub-modals (Route/Airline/Class) create records and populate selects
- [ ] Group ticket `ticket_qty` decremented on issue
- [ ] Page refresh: all issued ticket data persists and loads correctly
- [ ] Backfill command: creates records for all existing passengers (idempotent)
