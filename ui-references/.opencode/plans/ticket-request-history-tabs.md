# Ticket Request History Tabs - Implementation Plan

This file tracks the implementation plans for the 3 history tabs in the
Invoice Details (bookings/show) page:

1. **Re Issue History** (planned)
2. **Additional Ticket History** (planned)
3. **Refund History** (pending)

---

## 1. Re Issue History Tab

### Goal

Show all re-issued tickets for a booking, sourced directly from the
`re_issued_tickets` table. Each re-issue = one row. Tracks how many times
each ticket has been re-issued.

### Data Source

`re_issued_tickets` table, linked to booking via:
```
re_issued_tickets.issued_ticket_id -> issued_tickets.booking_id
```

Both entry points create records in `re_issued_tickets`:
- `ReIssueController::store` (from passenger index)
- `TicketRequestController::processReIssue` (from booking show)

### Table Columns (8)

| # | Column              | Source (`r` = re_issued_ticket)                    |
|---|---------------------|----------------------------------------------------|
| 1 | Date                | `r.re_issue_date`                                  |
| 2 | Passenger Name      | `r.issued_ticket.passenger.first_name + last_name` |
| 3 | Passport No.        | `r.issued_ticket.passenger.passport_no`            |
| 4 | PNR                 | `r.pnr`                                            |
| 5 | Agent               | `r.ticket_agent?.name`                             |
| 6 | Total Reissue Cost  | `r.re_issue_charge + r.fare_difference + r.other_costs` |
| 7 | Total Customer Payment | `r.total_customer_payment`                       |
| 8 | Profit              | `total_customer_payment - total_cost`               |

No Status column. No Payment Method column. No Action column.

### Files to Modify

#### 1. `routes/web.php` - Add new route

```php
Route::get('/bookings/{booking}/re-issued-tickets', [ReIssueController::class, 'byBooking'])
    ->name('re-issued-tickets.by-booking');
```

Place this inside the existing middleware group that already has the
`ReIssueController` routes (around line 540).

#### 2. `app/Http/Controllers/ReIssueController.php` - Add `byBooking()` method

```php
public function byBooking(Booking $booking)
{
    $reIssuedTickets = ReIssuedTicket::whereHas('issuedTicket', function ($q) use ($booking) {
            $q->where('booking_id', $booking->id);
        })
        ->with([
            'ticketAgent',
            'ticketFare.airline',
            'ticketFare.airlineClass.class',
            'ticketFare.route.fromCity',
            'ticketFare.route.toCity',
            'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity',
            'ticketFare.route.multiSegments.toCity',
            'reason',
            'issuedTicket.passenger',
        ])
        ->orderBy('re_issue_date', 'desc')
        ->get();

    return response()->json($reIssuedTickets);
}
```

#### 3. `resources/views/bookings/show.blade.php` - HTML + JS changes

**HTML (table header ~line 334-347):**

Replace the existing `<thead>` with 8 columns:

```html
<thead class="bg-slate-50 text-slate-600">
    <tr>
        <th class="px-3 py-2 text-left font-medium">Date</th>
        <th class="px-3 py-2 text-left font-medium">Passenger Name</th>
        <th class="px-3 py-2 text-left font-medium">Passport No.</th>
        <th class="px-3 py-2 text-left font-medium">PNR</th>
        <th class="px-3 py-2 text-left font-medium">Agent</th>
        <th class="px-3 py-2 text-right font-medium">Total Reissue Cost</th>
        <th class="px-3 py-2 text-right font-medium">Total Customer Payment</th>
        <th class="px-3 py-2 text-right font-medium">Profit</th>
    </tr>
</thead>
```

Remove the empty state div id stays: `reissueHistoryEmpty`.

**JS - Rewrite `renderReissueHistory()` (~line 1405-1442):**

```javascript
function renderReissueHistory() {
    const tbody = document.getElementById('reissueHistoryBody');
    const emptyEl = document.getElementById('reissueHistoryEmpty');
    if (!tbody) return;

    fetch('/bookings/{{ $booking->id }}/re-issued-tickets', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(res => res.json())
    .then(tickets => {
        if (!tickets.length) {
            tbody.innerHTML = '';
            if (emptyEl) emptyEl.classList.remove('hidden');
            return;
        }
        if (emptyEl) emptyEl.classList.add('hidden');
        tbody.innerHTML = '';
        tickets.forEach(r => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';
            const p = r.issued_ticket?.passenger || {};
            const totalCost = (parseFloat(r.re_issue_charge) || 0)
                + (parseFloat(r.fare_difference) || 0)
                + (parseFloat(r.other_costs) || 0);
            const customerPayment = parseFloat(r.total_customer_payment) || 0;
            const profit = customerPayment - totalCost;
            tr.innerHTML = `
                <td class="px-3 py-2 text-slate-600">${r.re_issue_date || '-'}</td>
                <td class="px-3 py-2 text-slate-800">${escapeHtml(p.first_name ? p.first_name + ' ' + p.last_name : '-')}</td>
                <td class="px-3 py-2 text-slate-600">${escapeHtml(p.passport_no || '-')}</td>
                <td class="px-3 py-2 text-slate-600">${escapeHtml(r.pnr || '-')}</td>
                <td class="px-3 py-2 text-slate-800">${escapeHtml(r.ticket_agent?.name || '-')}</td>
                <td class="px-3 py-2 text-slate-800 text-right font-medium">${totalCost.toFixed(2)}</td>
                <td class="px-3 py-2 text-slate-800 text-right font-medium">${customerPayment.toFixed(2)}</td>
                <td class="px-3 py-2 text-right font-medium ${profit >= 0 ? 'text-green-600' : 'text-red-600'}">${profit.toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
        });
    });
}
```

### What Does NOT Change

- Database / migrations (no schema changes)
- TicketRequestController (untouched)
- Re-Issue Request modal
- Confirmation page (`/re-issues/{id}/confirm`)
- Rejected ticket handling (not shown in this tab)

---

## 2. Additional Ticket History Tab

### Goal

Show all additional tickets for a booking, sourced directly from the
`issued_tickets` table where `issue_type = 'additional'`. Each additional
ticket = one row.

### Data Source

`issued_tickets` table directly:
```
issued_tickets.booking_id = {booking}
issued_tickets.issue_type = 'additional'
```

Created by `TicketRequestController::processAdditional()`.

### Price Logic

| Scenario | Display Price | Profit |
|---|---|---|
| `offer_price` exists (> 0) | `offer_price` | `offer_price - net_fare` |
| `offer_price` is null/0 | `selling_fare` | `selling_fare - net_fare` |

### Table Columns (9)

| # | Column              | Source (`t` = issued_ticket)                            |
|---|---------------------|----------------------------------------------------------|
| 1 | Date                | `t.issued_date`                                          |
| 2 | Passenger Name      | `t.passenger.first_name + last_name`                     |
| 3 | Passport No.        | `t.passenger.passport_no`                                |
| 4 | Price (Selling/Offer) | `t.offer_price > 0 ? t.offer_price : t.selling_fare`  |
| 5 | Net Fare            | `t.net_fare`                                             |
| 6 | PNR                 | `t.pnr`                                                  |
| 7 | Ticket Num          | `t.ticket_number`                                        |
| 8 | Route               | `t.ticket_fare.route` (fromCity-toCity)                  |
| 9 | Profit              | `display_price - t.net_fare`                             |

No Status column. No Payment Method column. No Action column.

### Files to Modify

#### 1. `routes/web.php` - Add new route

```php
Route::get('/bookings/{booking}/additional-tickets', [TicketRequestController::class, 'additionalTicketsByBooking'])
    ->name('bookings.additional-tickets');
```

Place this inside the existing middleware group with ticket request routes.

#### 2. `app/Http/Controllers/TicketRequestController.php` - Add method

```php
public function additionalTicketsByBooking(Booking $booking)
{
    $tickets = IssuedTicket::where('booking_id', $booking->id)
        ->where('issue_type', 'additional')
        ->with([
            'passenger',
            'ticketFare.route.fromCity',
            'ticketFare.route.toCity',
            'ticketFare.route.returnCity',
            'ticketFare.route.multiSegments.fromCity',
            'ticketFare.route.multiSegments.toCity',
            'ticketFare.airline',
            'ticketFare.airlineClass.class',
        ])
        ->orderBy('issued_date', 'desc')
        ->get();

    return response()->json($tickets);
}
```

#### 3. `resources/views/bookings/show.blade.php` - HTML + JS changes

**HTML (table header ~line 359-375):**

Replace the existing `<thead>` with 9 columns:

```html
<thead class="bg-slate-50 text-slate-600">
    <tr>
        <th class="px-3 py-2 text-left font-medium">Date</th>
        <th class="px-3 py-2 text-left font-medium">Passenger Name</th>
        <th class="px-3 py-2 text-left font-medium">Passport No.</th>
        <th class="px-3 py-2 text-right font-medium">Price (Selling/Offer)</th>
        <th class="px-3 py-2 text-right font-medium">Net Fare</th>
        <th class="px-3 py-2 text-left font-medium">PNR</th>
        <th class="px-3 py-2 text-left font-medium">Ticket Num</th>
        <th class="px-3 py-2 text-left font-medium">Route</th>
        <th class="px-3 py-2 text-right font-medium">Profit</th>
    </tr>
</thead>
```

**JS - Rewrite `renderAdditionalTicketHistory()` (~line 1734-1772):**

```javascript
function renderAdditionalTicketHistory() {
    const tbody = document.getElementById('additionalTicketHistoryBody');
    const emptyEl = document.getElementById('additionalTicketHistoryEmpty');
    if (!tbody) return;

    fetch('/bookings/{{ $booking->id }}/additional-tickets', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(res => res.json())
    .then(tickets => {
        if (!tickets.length) {
            tbody.innerHTML = '';
            if (emptyEl) emptyEl.classList.remove('hidden');
            return;
        }
        if (emptyEl) emptyEl.classList.add('hidden');
        tbody.innerHTML = '';
        tickets.forEach(t => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';
            const p = t.passenger || {};
            const offerPrice = parseFloat(t.offer_price) || 0;
            const displayPrice = offerPrice > 0 ? offerPrice : (parseFloat(t.selling_fare) || 0);
            const netFare = parseFloat(t.net_fare) || 0;
            const profit = displayPrice - netFare;
            const route = t.ticket_fare?.route || {};
            const routeType = route.route_type || '';
            let routeDisplay = '';
            if (routeType === 'multi_city' && route.multi_segments) {
                routeDisplay = route.multi_segments.map(s =>
                    (s.from_city?.code || '?') + '-' + (s.to_city?.code || '?')
                ).join(', ');
            } else {
                const from = route.from_city?.code || '?';
                const to = route.to_city?.code || '?';
                const ret = route.return_city?.code || '';
                routeDisplay = (routeType === 'round' && ret)
                    ? from + '-' + to + '-' + ret
                    : from + '-' + to;
            }
            tr.innerHTML = `
                <td class="px-3 py-2 text-slate-600">${t.issued_date || '-'}</td>
                <td class="px-3 py-2 text-slate-800">${escapeHtml(p.first_name ? p.first_name + ' ' + p.last_name : '-')}</td>
                <td class="px-3 py-2 text-slate-600">${escapeHtml(p.passport_no || '-')}</td>
                <td class="px-3 py-2 text-slate-800 text-right font-medium">${displayPrice.toFixed(2)}</td>
                <td class="px-3 py-2 text-slate-800 text-right font-medium">${netFare.toFixed(2)}</td>
                <td class="px-3 py-2 text-slate-600">${escapeHtml(t.pnr || '-')}</td>
                <td class="px-3 py-2 text-slate-600">${escapeHtml(t.ticket_number || '-')}</td>
                <td class="px-3 py-2 text-slate-600">${escapeHtml(routeDisplay || '-')}</td>
                <td class="px-3 py-2 text-right font-medium ${profit >= 0 ? 'text-green-600' : 'text-red-600'}">${profit.toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
        });
    });
}
```

### What Does NOT Change

- Database / migrations (no schema changes)
- ReIssueController (untouched)
- Additional Ticket Request modal
- Additional Ticket confirmation page

---

## 3. Refund History

> **Plan pending** - to be added later.
