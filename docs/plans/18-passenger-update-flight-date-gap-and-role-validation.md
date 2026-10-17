# Plan: Passenger Update — Flight Date Gap Skip + Protected-Field Role Validation

**Status: PLAN ONLY — DO NOT IMPLEMENT until explicitly approved.**

## Goal

Fix two defects in the passenger update flow (`PUT /passengers/{passenger}`) around the **Flight Date Range** field, and tighten the role gate on the two admin-only fields:

1. **Gap check no longer applies on update (changed or unchanged).** A Super Admin / Co Admin editing *any* other field on a passenger whose stored `flight_date_from` has fallen behind today's gap floor currently gets a `422 "Flight date range is too early…"`. The gap rule must be skipped on update — regardless of whether the pair changed — while the slot **shape** rule stays enforced.
2. **Role validation for `flight_date_from` / `flight_date_to` / `extra_charge`.** Only **Super Admin** and **Co Admin** may set these fields. The existing pre-validation strip only removes them from the request body, so values smuggled via the query string survive into validation and persist.
3. **Hide those fields from non-admin roles in the passenger edit form** (defence in depth on top of #2).

Out of scope by decision: booking create / add-passenger keep the full gap rule (`BookingController`, `StoreBookingRequest`, `StorePassengerRequest`).

## Confirmed decisions

- **Gap skip scope: `PassengerController::update()` only.** The gap portion of `FlightDateSlot` is made *opt-out* via a constructor flag, defaulting to enforced, so the four create-path call sites are untouched.
- **Enforcement style: A — silently ignore.** Strip the three keys from **all** input sources (`$request->request`, `$request->query`, `$request->json()` when JSON). Non-admins' values are dropped; the update still succeeds and the fields stay unchanged. This matches the existing behavioural contract (`tests/Feature/PassengerExtraChargeTotalsTest.php:321-339` asserts *ignored*, not *rejected*) and the current UI, which always posts stored values for every role. Rejected alternatives: presence-based `403` (would break every non-admin save, because the edit form always includes these three keys — see finding C2) and diff-based `403` (weaker guarantee, still rewrites the existing test).
- **Allowed roles: `Super Admin`, `Co Admin`** — the existing predicate `PassengerController::isFlightDateAdmin()` (`app/Http/Controllers/PassengerController.php:62-67`) now covers **both** field groups (`flight_date_from`/`flight_date_to` and `extra_charge`).
- **UI:** for non-admins, render the Flight Date Range blocks as read-only text (no `<select>`, no Edit button). Extra Charge inputs are already hidden from non-admins (`@if($canEditExtraCharge)`), so no change there.

## Findings (analysis of current behaviour)

### Server side — `PassengerController::update()` (`app/Http/Controllers/PassengerController.php:657-705`)

Current flow:

1. Branch / ownership gates (`:657-663`).
2. Role strip for non-admins — two duplicated blocks (`:665-672`) calling `$request->request->remove('flight_date_from'|'flight_date_to')` and `$request->request->remove('extra_charge')`.
3. `$mergedFrom = $request->input('flight_date_from', $passenger->flight_date_from?->format('Y-m-d'))` (`:678`) — closes a `to`-only payload bypass (covered by `test_update_rejects_to_only_payload`).
4. Gap inputs: `$fareId` from client input, `FlightDateGap::first()?->gap ?? 30`, route `additional_gap` (`:679-684`).
5. Rules (`:686-705`): admins → `flight_date_from` = `required|date`, `flight_date_to` = `required|date` + `new FlightDateSlot($mergedFrom, $defaultGap, $additionalGap)`; everyone else → both `nullable|date`.
6. `$passenger->update($validated)` (`:714`).

**S1 — gap check runs against the unchanged stored pair (high).** `FlightDateSlot::minimumFromDate()` (`app/Rules/FlightDateSlot.php:94-117`) = first day of the slot containing `today + defaultGap + additionalGap`, so the floor advances daily while the stored pair is fixed. Any passenger booked `≈ slot(bookingDay + gap)` falls behind within ~1–11 days and then *every* admin edit 422s — including edits to unrelated fields (name, address, service, extra charge) and any passenger whose flight already departed.

Verified by running `FlightDateSlot::minimumFromDate()` for a pair "booked" 2026-10-14 with `defaultGap = 30` (stored `2026-11-11 → 2026-11-20`):

| today | minFrom | admin save |
|---|---|---|
| 2026-10-14 | 2026-11-11 | OK |
| 2026-10-21 | 2026-11-21 | **422 "too early"** |
| 2026-11-11 | 2026-12-11 | 422 |
| 2026-12-02 | 2027-01-01 | 422 |

The form always submits the stored pair (`resources/views/passengers/edit.blade.php:1185-1186`), so `$mergedFrom` = stored value and the gap rule validates data the user never touched.

**S2 — role strip misses the query bag (medium).** `Request::all()` / `input()` merge `$this->query` on top of the body bag (`vendor/laravel/framework/src/Illuminate/Http/Concerns/InteractsWithInput.php:86-101` and `:110-115`), and `$request->request->remove()` only touches the body bag. (For JSON requests the body bag *is* the JSON bag — `vendor/laravel/framework/src/Illuminate/Http/Request.php:537-539` — so `?flight_date_from=…&flight_date_to=…` on the URL is the only surviving vector.) Those values then pass the non-admin `nullable|date` / `nullable|numeric|min:0` rules, land in `$validated`, and are persisted → a non-admin can change admin-only fields, with no slot/gap validation, via a crafted URL. No UI does this today; it is an API-level hole.

**S3 — gap source trusts client input (low, disappears with this plan).** `:679-680` prefers `$request->input('ticket_fare_id', …)` over the stored FK, even though the controller deliberately does **not** accept fare-FK changes (`:702-703`). Skipping the gap removes this code entirely.

**S4 — no test coverage** for (a) an unchanged/stale pair, (b) non-admin attempts at either field via body or query, or (c) query-string bypass. `tests/Feature/FlightDateSlotEnforcementTest.php:336-393` only exercises shape/gap failures with a Super Admin.

### Client side — `resources/views/passengers/edit.blade.php`

- Flight Date Range is a `disabled` `<select id="passengerFlightDateRange">` (`:176-186`) plus a second one for double-ticket (`:218-228`); the Edit button is already `@if`-gated to Super Admin / Co Admin (`:182-184`, `:224-226`) — parity with `isFlightDateAdmin()`.
- Options come from `calculateFlightDateRange()` → `GET /api/ticket-fares/flight-date-gap` (`:798-829`; endpoint `app/Http/Controllers/TicketFareController.php:553-572`) → `generateFlightDateRangeOptions()` (`:831-882`). Its day buckets (1-5→1-10, 6-15→11-20, 16-25→21-last, 26-31→next month 1-10) are **identical** to `FlightDateSlot::minimumFromDate()`.
- The modal (`saveFlightDateModal()`, `:1063-1094`) validates DD-MMM-YY format, `from <= to` and slot shape — **never the gap floor** — so it can produce a pair only the server rejected. With the gap skipped this mismatch disappears.
- `savePassenger()` (`:1138-1218`) hard-requires a non-empty pair (`:1165-1168`) and always includes the three protected keys (`:1185-1186`, `:1193`); errors are surfaced as `alert(data.message)` only (the 422 `message` is the first error plus "(and N more errors)"), `data.errors` is ignored and `response.ok` is never checked.
- **C1 — double-ticket has no option list:** `calculateFlightDateRange()` is only reached from the single-ticket branch / `onTicketChange` / `onServiceRequiredChange`, so the double-ticket select only ever holds the appended stored range (free-text via the modal).
- **C2 — the form always sends the protected keys**, for every role. This is why a presence-based server `403` was rejected: it would break every non-admin save.
- **C3 — legacy `NULL` dates block non-admins:** `:1165-1168` fails client-side, but the server marks both fields `nullable|date` for non-admins.

## Steps (implementation order, TDD-first)

### 1. Failing tests first

Add to `tests/Feature/FlightDateSlotEnforcementTest.php` (or a sibling test file) — see §Tests below. Run and confirm they fail for the right reason (stale pair 422s; query-string values persist).

### 2. `app/Rules/FlightDateSlot.php` — opt-out gap flag

- Constructor (`:24-29`) becomes:

  ```php
  public function __construct(
      private ?string $from = null,
      private int $defaultGap = 30,
      private int $additionalGap = 0,
      private ?Carbon $today = null,
      private bool $enforceGap = true,
  ) {}
  ```

  Defaults added so the new call site reads `new FlightDateSlot(from: $mergedFrom, enforceGap: false)` and the existing positional call sites (`BookingController.php:195`, `BookingController.php:2523`, `app/Http/Requests/StoreBookingRequest.php:92`, `app/Http/Requests/StorePassengerRequest.php:40`) are unaffected.

- In `validate()` (`:31-52`), after the pair-present check and `self::isValidSlot()` shape check, return early before the gap comparison:

  ```php
  if (! $this->enforceGap) {
      return;
  }
  ```

- Update the class docblock (`:10-21`) to state the gap portion is optional and enforced by default (`enforceGap: true`).

### 3. `app/Http/Controllers/PassengerController.php::update()`

- Compute the role flag once at the top: `$canEditProtectedFields = $this->isFlightDateAdmin();` (`:62-67`; Super Admin / Co Admin).
- Replace both strip blocks (`:665-672`) with a single helper that removes the three keys from every input source:

  ```php
  if (! $canEditProtectedFields) {
      foreach (['flight_date_from', 'flight_date_to', 'extra_charge'] as $protected) {
          $request->request->remove($protected);
          $request->query->remove($protected);
          if ($request->isJson()) {
              $request->json()->remove($protected);
          }
      }
  }
  ```

  (`request` and `json()` are the same `InputBag` for JSON requests — the third call is defensive; `query` is what closes S2.)
- Delete the now-dead `$fareId` / `$defaultGap` / `$additionalGap` block (`:679-684`) and the unused `use App\Models\FlightDateGap;` import (`:11`) — also removes S3.
- Keep `$mergedFrom` (`:678`) — it still feeds the shape check and blocks `to`-only payloads.
- Rules (`:695-698`) become:

  ```php
  'flight_date_from' => $canEditProtectedFields ? ['required', 'date'] : ['nullable', 'date'],
  'flight_date_to' => $canEditProtectedFields
      ? ['required', 'date', new FlightDateSlot(from: is_string($mergedFrom) ? $mergedFrom : null, enforceGap: false)]
      : ['nullable', 'date'],
  ```

- `extra_charge` keeps `nullable|numeric|min:0` (`:704`) and the null→0 normalization (`:707-709`); both are effectively admin-only via the strip.
- Leave `isValidFlightDateGroup()` (`:69-72`) in place — `tests/Feature/PassengerFlightDateValidationTest.php` reflects on it.
- Optional cosmetic: rename `isFlightDateAdmin()` → e.g. `canEditProtectedFields()` (the name now covers extra charge too); do it only if the diff stays clean.

### 4. `resources/views/passengers/edit.blade.php` — hide fields from non-admins

- Wrap the single-ticket Flight Date Range block (`:176-186`) and the double-ticket one (`:218-228`) in the same role check the Edit button uses (`auth()->user()?->hasRole('Super Admin') || …('Co Admin')`).
  - For admins: unchanged (`disabled` select + Edit button).
  - For non-admins: render a read-only text line with the stored range (e.g. `{{ $passenger->flight_date_display }}`, `app/Models/Passenger.php:262`) instead of the `<select>`.
  - If the product prefers "nothing at all" instead of read-only text, it is a one-line swap — flag for review before implementing.
- Removing the `<select>` is null-safe: every consumer guards for absence — `populateFlightDateRangeOptions()` (`:884-898`), `generateFlightDateRangeForEdit()` / `generateDoubleTicketFlightDateRange()` (`:900-952`, inside `$nextTick`), `saveFlightDateModal()`'s `forEach` (`:1081-1092`).
- Add an early return in `calculateFlightDateRange()` (`:798-808`) for non-admins so the gap API is not called for a field they cannot see.
- No change needed to the Extra Charge block (`:118-127`) — already `@if($canEditExtraCharge)`.

### 5. `resources/views/passengers/edit.blade.php` — payload + guard alignment

- Expose the role flag alongside `window.__bookingServerData` (`:4-13`), e.g. `canEditProtectedFields`, computed with the same `hasRole` check.
- In `savePassenger()` (`:1176-1194`) include `flight_date_from`, `flight_date_to` and `extra_charge` only when that flag is true (belt-and-braces under enforcement style A; also stops posting a field the role cannot set).
- Relax the pair-presence guard (`:1165-1168`) to admins only, so a non-admin can save a passenger whose dates are legacy `NULL` (the server already treats them as `nullable` for that role) — fixes C3.

## Tests

### New (write first)

- **Gap skipped on update, unchanged pair:** Super Admin `putJson` with a well-shaped pair *earlier* than today's floor (e.g. `FlightDateSlot::validPairForTesting()` shifted back one slot, or a fixed past pair such as `2026-01-01`/`2026-01-10` — both shape-valid) → `200`, DB row updated. Today this returns `422`.
- **Shape still enforced with the gap off:** misaligned/cross-month pair (e.g. `2026-09-04` → `2026-09-18`) → `422`.
- **Non-admin cannot set protected fields via query string:** Branch Staff `put` to `/passengers/{id}?flight_date_from=…&flight_date_to=…&extra_charge=999` with otherwise-valid body → `200`/redirect, and all three columns unchanged (today `extra_charge` becomes `999` and the dates change).

### Must stay green

- `tests/Feature/PassengerExtraChargeTotalsTest.php:321-339` — non-admin body ignore (form PUT, expects redirect).
- `tests/Feature/FlightDateSlotEnforcementTest.php` update tests (`:336-393`): `test_update_rejects_bad_range` (shape), `test_update_rejects_to_only_payload` (cross-month via merged `from`), `test_update_accepts_valid_range`, plus the branch-gating tests (`:548-597`).
- `tests/Feature/FlightDateSlotEnforcementTest.php:159-191` — rule-level gap tests, which construct the rule positionally and therefore keep `enforceGap = true`.
- `tests/Feature/PassengerExtraChargeCurrencyToggleTest.php:106-128` — extra charge hidden for non-admins.
- `tests/Feature/PassengerFlightDateEditModalTest.php:45-62` — Super Admin still sees the select + Edit button.

### Must be updated

- `tests/Feature/PassengerFlightDateEditModalTest.php::test_edit_button_hidden_for_non_admin_users` (`:64-93`) — line `:91` asserts `id="passengerFlightDateRange"` **is present** for a non-admin. Flip to assert the select is **absent** (and the read-only range text is present, no Edit button).

## Verification

```bash
php artisan test --filter=FlightDateSlot
php artisan test --filter=PassengerExtraCharge
php artisan test --filter=PassengerFlightDate
php artisan test
vendor/bin/pint
npm run build
docker compose -f docker-compose.yml config --quiet
docker compose -f docker-compose.prod.yml config --quiet
```

Manual smoke (Super Admin and a Branch Staff account): open `passengers/{id}/edit` for a passenger booked more than a week ago; confirm the admin can save an unrelated field (address) without a "too early" alert; confirm a non-admin sees the range as read-only text with no Edit button and can still save; confirm the double-ticket view behaves the same.

## Out of scope

- Gap enforcement on booking create / add-passenger (`BookingController.php:195`, `:2523`, `StoreBookingRequest.php:92`, `StorePassengerRequest.php:40`) — unchanged.
- `C1` (double-ticket gap-derived option list) and `C2`/client error rendering improvements (`data.errors`, `response.ok`) — noted, not part of this change.
- Any `ui-references/` file.
