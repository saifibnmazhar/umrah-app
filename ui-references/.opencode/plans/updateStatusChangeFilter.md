# Update Status Change Filter — Add 3 New Options

## Goal

Add **Visa Submitted**, **Visa Issued**, and **Ticket Issued** to the existing
Status Change filter dropdown on the Bookings index page. These query
`visa_update_logs` and `issued_ticket_logs` respectively, rather than
`passenger_update_logs`. Passengers whose current `passenger_status_id` is
Cancel, Delivered, or Hold are excluded from the new filters.

---

## Files to Modify

| # | File | What Changes |
|---|------|-------------|
| 1 | `app/Http/Controllers/BookingController.php` | Extend `$statusChangeOptions` + rewrite the `->when('status_change_action')` filter block |
| 2 | `resources/views/bookings/index.blade.php` | **No changes** — dropdown loop is already generic |

---

## Change 1: Extend `$statusChangeOptions`

**File:** `app/Http/Controllers/BookingController.php` — lines 622-624

### Current Code

```php
$passengerStatuses = PassengerStatus::all();
$statusChangeOptions = $passengerStatuses->filter(fn ($s) => in_array($s->name, ['Cancel', 'Delivered', 'Hold'])
)->values();
```

### New Code

```php
$passengerStatuses = PassengerStatus::all();
$statusChangeOptions = $passengerStatuses->filter(fn ($s) => in_array($s->name, ['Cancel', 'Delivered', 'Hold'])
)->values();
$statusChangeOptions = $statusChangeOptions->concat(collect([
    (object) ['id' => 'visa_submitted', 'name' => 'Visa Submitted'],
    (object) ['id' => 'visa_issued', 'name' => 'Visa Issued'],
    (object) ['id' => 'ticket_issued', 'name' => 'Ticket Issued'],
]));
```

### Why `(object)` cast works

The Blade template iterates with `$status->id` and `$status->name`, which works
identically on both Eloquent models and plain PHP objects.

---

## Change 2: Rewrite the Filter Block

**File:** `app/Http/Controllers/BookingController.php` — lines 417-451

### Current Code

```php
->when($request->filled('status_change_action'), function ($q) use ($request) {
    $action = $request->input('status_change_action');
    $dateFrom = $request->input('status_change_from');
    $dateTo = $request->input('status_change_to');

    $q->where('passenger_status_id', $action);

    if ($dateFrom || $dateTo) {
        $q->where(function ($query) use ($action, $dateFrom, $dateTo) {
            $query->where(function ($q) use ($action, $dateFrom, $dateTo) {
                $q->whereHas('updateLogs', function ($logQ) use ($action, $dateFrom, $dateTo) {
                    $logQ->where('action', 'updated')
                        ->where('new_values->passenger_status_id', $action);
                    if ($dateFrom) {
                        $logQ->whereDate('created_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $logQ->whereDate('created_at', '<=', $dateTo);
                    }
                });
            })->orWhere(function ($q) use ($action, $dateFrom, $dateTo) {
                $q->whereDoesntHave('updateLogs', function ($logQ) use ($action) {
                    $logQ->where('action', 'updated')
                        ->where('new_values->passenger_status_id', $action);
                });
                if ($dateFrom) {
                    $q->whereDate('updated_at', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->whereDate('updated_at', '<=', $dateTo);
                }
            });
        });
    }
})
```

### New Code

```php
->when($request->filled('status_change_action'), function ($q) use ($request) {
    $action = $request->input('status_change_action');
    $dateFrom = $request->input('status_change_from');
    $dateTo = $request->input('status_change_to');

    if (in_array($action, ['visa_submitted', 'visa_issued', 'ticket_issued'])) {
        // New filters: exclude passengers whose current status is Cancel/Delivered/Hold
        $excludeIds = PassengerStatus::whereIn('name', ['Cancel', 'Delivered', 'Hold'])
            ->pluck('id')
            ->toArray();

        $q->where(function ($sub) use ($excludeIds) {
            $sub->whereNull('passenger_status_id')
                ->orWhereNotIn('passenger_status_id', $excludeIds);
        });

        match ($action) {
            'visa_submitted' => $q->whereHas(
                'visaSubmission',
                fn ($vs) => $vs->whereHas('logs', function ($log) use ($dateFrom, $dateTo) {
                    $log->where('action', 'submitted');
                    if ($dateFrom) $log->whereDate('created_at', '>=', $dateFrom);
                    if ($dateTo) $log->whereDate('created_at', '<=', $dateTo);
                })
            ),
            'visa_issued' => $q->whereHas(
                'visaSubmission',
                fn ($vs) => $vs->whereHas('logs', function ($log) use ($dateFrom, $dateTo) {
                    $log->where('action', 'issued');
                    if ($dateFrom) $log->whereDate('created_at', '>=', $dateFrom);
                    if ($dateTo) $log->whereDate('created_at', '<=', $dateTo);
                })
            ),
            'ticket_issued' => $q->whereHas(
                'issuedTickets',
                fn ($it) => $it->whereHas('logs', function ($log) use ($dateFrom, $dateTo) {
                    $log->where('action', 'issued');
                    if ($dateFrom) $log->whereDate('created_at', '>=', $dateFrom);
                    if ($dateTo) $log->whereDate('created_at', '<=', $dateTo);
                })
            ),
        };
    } else {
        // Existing Cancel/Delivered/Hold logic — unchanged
        $q->where('passenger_status_id', $action);

        if ($dateFrom || $dateTo) {
            $q->where(function ($query) use ($action, $dateFrom, $dateTo) {
                $query->where(function ($q) use ($action, $dateFrom, $dateTo) {
                    $q->whereHas('updateLogs', function ($logQ) use ($action, $dateFrom, $dateTo) {
                        $logQ->where('action', 'updated')
                            ->where('new_values->passenger_status_id', $action);
                        if ($dateFrom) {
                            $logQ->whereDate('created_at', '>=', $dateFrom);
                        }
                        if ($dateTo) {
                            $logQ->whereDate('created_at', '<=', $dateTo);
                        }
                    });
                })->orWhere(function ($q) use ($action, $dateFrom, $dateTo) {
                    $q->whereDoesntHave('updateLogs', function ($logQ) use ($action) {
                        $logQ->where('action', 'updated')
                            ->where('new_values->passenger_status_id', $action);
                    });
                    if ($dateFrom) {
                        $q->whereDate('updated_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $q->whereDate('updated_at', '<=', $dateTo);
                    }
                });
            });
        }
    }
})
```

---

## Import Required

Add at the top of `BookingController.php` if not already present:

```php
use App\Models\PassengerStatus;
```

Check existing imports — `PassengerStatus` may already be imported since
`$passengerStatuses = PassengerStatus::all()` is already used on line 622.

---

## No Changes Required in Other Files

| File | Reason |
|------|--------|
| `resources/views/bookings/index.blade.php` | Dropdown uses `@foreach($statusChangeOptions as $status)` with `$status->id` / `$status->name` — works for both models and `(object)` |
| Alpine.js `x-data` (line 3186) | `selectedStatusChangeAction` accepts any string from URL params |
| `onStatusChangeActionChange()` (line 3514) | Sets `status_change_action` URL param — string or numeric, both work |
| `onStatusChangeDateChange()` (line 3527) | Unchanged — just sets `status_change_from` / `status_change_to` |
| `clearPassengerFilters()` (line 3554) | Already clears `status_change_action`, `status_change_from`, `status_change_to` |

---

## Data Flow

### Visa Submitted / Visa Issued

```
Passenger
  → visaSubmission (hasOne, latestOfMany — latest visa submission)
    → logs (hasMany → visa_update_logs)
      → filter by action = 'submitted' or 'issued'
      → filter by created_at within date range
```

### Ticket Issued

```
Passenger
  → issuedTickets (hasMany → issued_tickets)
    → logs (hasMany → issued_ticket_logs)
      → filter by action = 'issued'
      → filter by created_at within date range
```

---

## Edge Cases

| Scenario | Behavior |
|----------|----------|
| No date range provided | Finds any passenger with at least one matching log (ever) |
| Passenger has no visa submission | `whereHas('visaSubmission', ...)` returns false — excluded |
| Passenger has no issued tickets | `whereHas('issuedTickets', ...)` returns false — excluded |
| Passenger's current status is Cancel/Delivered/Hold | Excluded by the `excludeIds` subquery |
| Passenger's current status is null | Included (`whereNull('passenger_status_id')` passes) |
| Multiple visa submissions | `latestOfMany()` ensures only the latest is checked |
| Multiple issued tickets | Any ticket with an 'issued' log matches |

---

## Testing Checklist

- [ ] Existing Cancel/Delivered/Hold filters still work identically
- [ ] Visa Submitted filter: dropdown shows "Visa Submitted"
- [ ] Visa Submitted filter: with date range, finds passengers with matching `visa_update_logs` entry
- [ ] Visa Submitted filter: without date range, finds all passengers with any 'submitted' log
- [ ] Visa Issued filter: works same as above with 'issued' action
- [ ] Ticket Issued filter: works same as above with 'issued' action on `issued_ticket_logs`
- [ ] Exclusion: passengers with current status Cancel/Delivered/Hold do NOT appear in new filters
- [ ] Exclusion: passengers with null status DO appear in new filters
- [ ] Date range: From and To inputs appear when a new filter is selected
- [ ] Clear filters: clicking clear removes all status change params
