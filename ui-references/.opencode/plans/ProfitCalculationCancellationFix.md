  # Profit Calculation Cancellation Fix

## Problem

Cancelled bookings and cancelled passengers are not properly excluded from profit
calculations. The profit report only filters at the booking level
(`bookings.is_cancelled = false`), but individual passenger cancellations within
active bookings are completely ignored.

### Specific Gaps

1. **Report layer**: `ProfitLossReportController` passenger queries lack
   `passengers.is_cancelled = false` filter
2. **Calculation layer**: `ProfitCalculationService` iterates all passengers
   without checking `is_cancelled`
3. **Zeroing layer**: Neither `PassengerCancellationService` nor
   `CancellationService` zeros out `profit` on cancellation
4. **Observer layer**: No observer triggers profit recalculation when
   `is_cancelled` changes
5. **Backfill layer**: `backfillAllBookings()` processes all bookings including
   cancelled ones

---

## Fix Plan

### Step 1: Add `passengers.is_cancelled` filter to report queries

**File:** `app/Http/Controllers/ProfitLossReportController.php`

- **`summary()` passenger query (line ~128):** Add
  `->where('passengers.is_cancelled', false)` after the existing
  `bookings.is_cancelled` filter
- **`data()` passenger tab (line ~215):** Add
  `->where('passengers.is_cancelled', false)` after the existing
  `bookings.is_cancelled` filter
- **`mapPassengers()` (line ~182):** Filter out cancelled passengers:
  `$booking->passengers->where('is_cancelled', false)`

### Step 2: Skip cancelled passengers in ProfitCalculationService

**File:** `app/Services/ProfitCalculationService.php`

- **`recalculateBookingProfit()` (line ~35):** Add early continue in the
  passenger loop:
  ```php
  foreach ($booking->passengers as $passenger) {
      if ($passenger->is_cancelled) {
          $passenger->profit = 0;
          $passenger->saveQuietly();
          continue;
      }
      $this->recalculatePassengerProfit($passenger);
  }
  ```
- **`getCustomerProfitBreakdown()` (line ~122):** Skip cancelled passengers in
  the loop
- **`getPassengerProfitBreakdownForPassengers()` (line ~195):** Skip cancelled
  passengers in the loop
- **`backfillAllBookings()` (line ~224):** Add `->where('is_cancelled', false)`
  to the base query

### Step 3: Zero out profit on cancellation confirmation

**File:** `app/Services/PassengerCancellationService.php`

- **`confirmCancellation()` (line ~289):** Add profit zeroing when setting
  permanent status:
  ```php
  $passenger->update([
      'passenger_status_id' => $cancelStatus->id,
      'profit' => 0,  // ← add this
  ]);
  ```
- **`initiateCancellation()` (line ~109):** Optionally zero out profit here
  too for immediate consistency

**File:** `app/Services/CancellationService.php`

- **`confirmCancellation()`:** After line ~170 where status is set to
  `CANCELLED`, add:
  ```php
  // Zero out profit for all passengers in this booking
  $booking->passengers()->update(['profit' => 0]);
  $booking->update(['profit' => 0]);
  ```

### Step 4: Trigger profit recalculation on cancellation status change

**File:** `app/Observers/BookingObserver.php`

- **`updated()` (line ~33):** Add `is_cancelled` to the list of fields that
  trigger profit recalculation:
  ```php
  if (! empty(array_intersect_key($dirty, array_flip([
      'discount_amount', 'discount_value', 'is_cancelled',
  ])))) {
      app(ProfitCalculationService::class)->recalculateBookingProfit($booking);
  }
  ```

**File:** `app/Observers/PassengerObserver.php`

- **`updated()`:** Add profit recalculation when `is_cancelled` changes:
  ```php
  if (array_key_exists('is_cancelled', $dirty)) {
      app(ProfitCalculationService::class)->recalculateBookingProfit(
          $passenger->booking
      );
  }
  ```

### Step 5: Add tests

**File:** `tests/Feature/ProfitCalculationCancellationTest.php` (new)

Test cases:
1. Cancelled booking excluded from profit report summary
2. Cancelled passenger excluded from profit report passenger tab
3. Cancelled passenger profit zeroed in booking total
4. Profit recalculated when passenger is cancelled
5. Profit recalculated when booking is cancelled
6. Backfill excludes cancelled bookings

---

## Verification

After applying fixes:

```bash
# Run existing tests to ensure no regressions
php artisan test

# Run Pint for code style
vendor/bin/pint

# Verify the specific profit test
php artisan test --filter=ProfitCalculationCancellationTest
```

---

## Risk Assessment

- **Low risk:** All changes are additive filters and guards
- **Data consistency:** The `profit:backfill` command should be re-run after
  deployment to recalculate all profit values with the new logic
- **No migration needed:** No schema changes required
