# Package Selection Behavior Change Plan

## Goal
Modify package selection behavior in booking edit so that:
- When user selects the originally loaded package → shows/historical uses historical values
- When user selects any other package → shows/uses live/current values
- When selection remains unchanged → no value change, keeps historical

## Current Behavior
- Uses historical values if package was ever used before on this booking (based on PackageEras logs)
- Uses live values if package is new to this booking

## Required Changes

### 1. BookingController::edit()
Add tracking of originally loaded package ID to pass to view

### 2. BookingController::swapPreview()
Modify logic to compare selected package against originally loaded package:
- If selected package ID == originally loaded package ID → use historical anchor
- If selected package ID != originally loaded package ID → use live anchor (now())

### 3. BookingController::update()
No changes needed - update logic already handles package changes correctly

## Implementation Details

### In BookingController::edit():
1. Store original package ID: `$originalPackageId = $booking->package_id ?? null;`
2. Pass to view: add `'originalPackageId' => $originalPackageId` to compact()

### In BookingController::swapPreview():
1. Get originalPackageId from request (add to validation)
2. Replace `$restored` logic with:
   ```php
   $useHistorical = (int) $validated['package_id'] === (int) $originalPackageId;
   $anchor = $useHistorical
       ? PackageEras::anchorAt($booking->created_at, $package->id, $eraLogs, $passenger->created_at)
       : now();
   ```

### Validation Addition
Add `original_package_id` to swapPreview validation:
```php
'original_package_id' => 'required|integer|exists:packages,id',
```

## Files to Modify
- `app/Http/Controllers/BookingController.php`

## Testing Approach
1. Create test case for A→B→A scenario showing second A uses live values
2. Verify unchanged selection (A→A) keeps historical values
3. Verify new package selection (A→B) uses live values
4. Verify package never used before uses live values
5. Verify package used before but not original uses live values

## Edge Cases
- Null package_id handling (visa_only bookings)
- Original package ID null when no package initially selected
- Ensure originalPackageId is passed correctly in all edit scenarios
