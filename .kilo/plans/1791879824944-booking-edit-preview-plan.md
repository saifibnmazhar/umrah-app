# Booking Edit Preview Plan

## Problem
When editing a booking, the preview in the summary card should show:
- Historical values (current booking state) when the currently selected package matches the original package
- Live/current values (what it would be with new package) when the currently selected package differs from the original package

## Solution
Modify the booking edit form's JavaScript to:
1. Track the original package ID from when the edit form loaded
2. Determine whether to show historical or live preview based on whether currently selected package ID equals original package ID
3. Calculate preview values accordingly

## Implementation Details

### Files to Modify
- `resources/js/booking.js` (createBookingApp section)

### Changes

#### 1. Add Properties
In the `createBookingApp` data section, add:
```javascript
originalPackageId: null,
originalPassengerValues: {}
```

#### 2. Initialize Properties
In the `init()` method, after loading server data:
```javascript
// Store original package ID
this.originalPackageId = serverData.preSelectedPackageId ? String(serverData.preSelectedPackageId) : null;

// Store original passenger historical values from database
this.originalPassengerValues = {};
if (serverData.existingPassengers) {
    serverData.existingPassengers.forEach((passenger, index) => {
        this.originalPassengerValues[index] = passenger.package_value || 0;
    });
}
```

#### 3. Add Preview Calculation Method
Add a method to recalculate preview values:
```javascript
recalculatePreviewValues() {
    const useHistoricalPreview = this.bookingData.package_id === this.originalPackageId;
    const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
    
    this.passengers.forEach((p, index) => {
        if (useHistoricalPreview) {
            // Use historical value from database
            this.passengerPackageValues[index] = this.originalPassengerValues[index] || 0;
        } else {
            // Calculate with current package data and current passenger data
            this.passengerPackageValues[index] = this.calculatePackageValue(p, pkg);
        }
    });
}
```

#### 4. Update Package Change Handler
Modify `onPackageChange()` to:
```javascript
onPackageChange() {
    // ... existing code that sets up passenger data based on new package ...
    
    // Update preview values
    this.recalculatePreviewValues();
}
```

#### 5. Update Initial Preview
In the `init()` method's `$nextTick` callback, after setting package_id:
```javascript
// Show initial preview (should be historical since we're viewing original booking)
this.recalculatePreviewValues();
```

#### 6. Update Passenger Change Handlers
Methods that recalculate individual passenger values (like `recalculateCurrentPassenger` if it exists) should use the same logic:
```javascript
recalculateCurrentPassenger(index) {
    if (index === null || index === undefined || !this.passengers[index]) {
        return;
    }
    
    const useHistoricalPreview = this.bookingData.package_id === this.originalPackageId;
    const pkg = this.allPackages.find(p => String(p.id) === String(this.bookingData.package_id));
    
    this.passengerPackageValues[index] = useHistoricalPreview 
        ? (this.originalPassengerValues[index] || 0) 
        : this.calculatePackageValue(this.passengers[index], pkg);
}
```

### How It Works
- **Case A-A (A → A)**: Selected A, Original A → historical values (package unchanged)
- **Case A-B (A → B)**: Selected B, Original A → live values (package changed)
- **Case B-A (B → A)**: Selected A, Original B → live values (package changed from original B to A)
- **Case C (A → B → A)**: Selected A, Original A → historical values (package unchanged from original, even after temporary B selection)

### Notes
- This approach assumes that the sum of original passenger package_values correctly represents the historical total for preview purposes
- For more accurate preview that also responds to passenger data changes when on the original package, historical price calculation would be needed in the frontend
- The `calculatePackageValue` method used here should be the existing one in createBookingApp that calculates values with current prices

### Validation
1. Verify that when loading the edit form, the preview shows current booking values (historical)
2. Verify that changing to a different package updates preview to show what values would be with new package
3. Verify that changing back to original package shows live preview (since package changed from original)
4. Verify that changing passenger data while on a changed package updates preview accordingly
5. Verify that the summary card values update correctly based on preview values