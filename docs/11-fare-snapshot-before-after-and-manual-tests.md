# Fare Snapshot System — Before / After & Manual Test Cases

Implements `ui-references/.opencode/plans/packageEditWithTicketEditPlan.md`:
open edit access for in-use packages & ticket fares, with fare snapshots
preserved on `issued_tickets` for profit calculations.

## 1. Scenario BEFORE the plan

- **Locked packages** (any bookings exist): `edit` / `update` redirected with
  "cannot be edited because it has existing bookings." The only workaround
  was creating a brand-new package.
- **In-use ticket fares** (linked to packages): `update` allowed only
  `effective_to`. Price corrections required a new fare + a new package.
- **Profit** was computed from **live** `ticket_fares.selling_fare` /
  `offer_price` and live `packages.service_charge`. Editing a fare or the
  service charge **retroactively changed historic profit** of already-issued
  tickets.
- `ticket_fares.net_fare` was a required rate-card field, duplicated at
  issuance. Agents could also overwrite `selling_fare` / `offer_price` in
  issue / re-issue forms, rewriting financial history.
- No audit trail for package / fare edits.

## 2. Scenario AFTER the plan

- **Locked packages**: `package_name` + `service_charge` are editable, plus
  an optional bump of `visa_selling_price_id` to the latest via the
  `use_current_visa` checkbox. Fare references and the `is_double_ticket`
  toggle stay locked. Deletes are still blocked.
- **In-use fares**: `selling_fare`, `offer_price`, `effective_from`,
  `effective_to`, `child_fare_percentage` and `infant_fare_percentage` are
  editable (rate-card corrections; future snapshots pick the new values,
  issued tickets keep their frozen copies). Everything else (airline, class,
  route, type, meal, group fields) is locked.
  `net_fare` is always `0` on the fare; the real airline cost is entered
  per-ticket at issuance time.
- **Snapshots**: at passenger creation (and on booking package change,
  `passenger_type` change, `visa_only` → ticket change), the
  passenger-type-adjusted fare is frozen into
  `issued_tickets.selling_fare` / `offer_price`, and
  `passengers.booking_service_charge` is frozen from the package.
  **Profit reads snapshots**, so later fare / service-charge edits do not
  rewrite history. Booking package changes still prospectively recalculate
  `package_value` / invoice totals.
- **Issuance flows**: issue / re-issue forms show fares as readonly snapshots;
  only `net_fare` is entered. Re-issue / additional endpoints fall back to
  snapshots server-side. Additional tickets take `net_fare` raw from the form
  (no child/infant scaling); selling / offer are scaled.
- **Audit**: new `package_update_logs` and `ticket_fare_update_logs` tables
  record create / update / delete with old/new values and the acting user.

## 3. Key differences at a glance

| Area | Before | After |
|------|--------|-------|
| Locked package edit | Blocked (redirect) | Name + charge (+ optional visa bump) |
| In-use fare edit | `effective_to` only | Selling, offer, effective dates, child/infant % |
| Profit source | Live `ticket_fares` / `packages` | Frozen `issued_tickets` snapshot + `booking_service_charge` |
| Historic profit on fare edit | Mutated | Immutable |
| Service-charge edit on locked package | Blocked | Allowed, historic profit unchanged |
| `ticket_fares.net_fare` | Required rate-card field | Always `0`, hidden from forms |
| Issue / re-issue fare fields | Editable (history rewritable) | Readonly snapshots, server-enforced |
| Additional ticket net | Scaled by child/infant % | Raw agent input, unscaled |
| Edit audit | None | `*_update_logs` tables |

## 4. Manual test cases

### A. Locked package edit

1. Open a package WITH bookings → `Edit` opens (no redirect). Fare
   dropdowns + double-ticket checkbox are disabled, prices readonly, charge
   editable, `use_current_visa` checkbox visible.
2. Change `service_charge` → save → success. Open a passenger of that
   booking → profit / `service_charge` **unchanged** (snapshot holds the old
   value).
3. Forge fare fields via devtools (re-enable `ticket_fare_id`, submit) →
   ignored; package fare unchanged.
4. Package WITHOUT bookings → full edit still works (fare change, double
   toggle, prices).

### B. In-use fare edit

5. Open a fare used by a package → `Edit` shows `selling_fare` /
   `offer_price`, `effective_from` / `effective_to` and child / infant
   percentages editable, rest readonly, no net-fare widget, submit button
   reads *Update Fare Price*.
6. Change `selling_fare` → save → existing issued tickets keep the old
   `selling_fare` (check the passenger ticket row); newly created passengers
   get the new snapshot.
7. Create a new fare (full form and inline quick-create on the bookings
   page) → no `net_fare` field; DB row has `net_fare = 0`.

### C. Snapshot creation

8. Create a booking (single-ticket, one adult + one child) → check
   `issued_tickets`: child row = fare × child % (rounded to 6dp);
   `passengers.booking_service_charge` = package charge.
9. Create a booking (double-ticket) → two tickets: regular (NULL type) from
   the inbound fare, `pending_outbound` from the outbound fare.
10. Create a `visa_only` passenger → ticket row `0` / `0`, no fare snapshot.

### D. Snapshot updates

11. Change a booking's package → passenger `booking_service_charge` + both
    snapshots update; invoice `package_value` recalculates (prospective
    change — expected to move).
12. Change `passenger_type` adult → child → snapshots rescale; additional
    tickets rescale too.
13. Change `service_required` `visa_only` → `all` → tickets are created with
    current fare snapshots (or refreshed if rows already exist).

### E. Issuance flows

14. Issue modal: selling / offer shown readonly (= snapshot); enter `net_fare`
    → issued; snapshot columns unchanged in DB.
15. Re-issue modal + `re-issues/confirmation` page: all fare fields readonly;
    the created `ReIssuedTicket` inherits snapshot fares.
16. Additional ticket (`tickets/add-confirmation`): select a fare →
    selling / offer auto-filled (scaled for child/infant), `net_fare` stays
    as typed (not scaled). After confirm, profit = snapshot selling −
    entered net.

### F. Profit & reports

17. Profit report for a passenger before vs after editing the underlying
    fare → profit **identical** (snapshot). Only `net_fare` edits or new
    passengers move it.
18. Service-charge edit on a locked package → historic passenger profit
    unchanged.
19. Refund flow → unchanged behavior (reads from the source ticket).

### G. Audit logs

20. Edit a package / fare → row appears in `package_update_logs` /
    `ticket_fare_update_logs` with `action = updated`, old/new values, and
    your user id. Deletes log `action = deleted` with old values.
