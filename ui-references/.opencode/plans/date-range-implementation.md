# Date Range Feature Implementation

## Overview

This implementation adds dynamic Flight Date Range calculation and handling to the booking passenger form. The feature allows automatic calculation of flight date ranges based on:

1. **Settings → Flight Date Gap**: Default gap value stored in the `flight_date_gaps` table
2. **Ticket Admin → Routes → Additional Gap (Days)**: Per-route additional gap stored in the `routes` table

The calculated date range is then stored as `flight_date_from` and `flight_date_to` in the passengers table when a booking is saved.

## Implementation Goals

### Functional Requirements
- Calculate flight date ranges dynamically based on settings and route configuration
- Display date range options in the passenger form's Travel Details section
- Parse selected date range and populate database fields on save

### UX Goals
- Auto-populate Route Type and Flight Type when ticket is selected
- Filter available tickets based on Route Type and Flight Type selection
- Auto-select ticket based on package selection in Create Booking form
- Load date range options dynamically without page reload

### Technical Goals
- Use existing project architecture (Alpine.js, Laravel)
- Maintain backward compatibility with existing functionality
- Follow existing coding conventions
- Modular and scalable implementation

## Existing System Analysis

### Previous Implementation State
- No dynamic date range calculation existed
- No Flight Date Gap settings or Additional Gap field in routes
- Tickets were displayed without proper filtering

### Limitations/Problems Discovered
1. **Value Mismatch Issue**: Route Type dropdown used "One Way-Inbound" but ticket data stored "oneway_inbound" - comparison failed
2. **Wrong Property Reference**: `this.package_id` was used instead of `this.bookingData.package_id` in `openPassengerModal()`
3. **Missing Route Data**: Package model only had `ticket_fare_id`, not `route_type`/`flight_type` - needed to fetch from associated ticket
4. **Duplicate Ranges**: Nested loop caused duplicate date ranges in dropdown
5. **Missing Field Population**: `flight_date_from` and `flight_date_to` were never populated from selected range

### Related Components/Files
- `app/Http/Controllers/BookingController.php` - Server data preparation
- `app/Models/FlightDateGap.php` - Settings model
- `app/Models/Route.php` - Route model with additional_gap
- `resources/js/booking.js` - Frontend logic
- `resources/views/bookings/create.blade.php` - Booking form UI
- `resources/views/fares/admin.blade.php` - Route configuration (Additional Gap field)

## Planning and Design Decisions

### Implementation Strategy
1. Use Alpine.js for reactive state management in booking form
2. Pass server-side data via `window.__bookingServerData` global variable
3. Use database-driven gap values instead of hardcoded logic
4. Parse date strings in JavaScript before saving to database

### Why This Approach
- **Alpine.js Integration**: Project already uses Alpine.js, maintaining consistency
- **Client-side Parsing**: Option A chosen - parse in JavaScript before save for better UX
- **Single Loop Fix**: Changed nested loops to single loop (i < 16) to prevent duplicate ranges

### Alternatives Considered
- Option B: Add hidden fields in blade template and update with Alpine.js
- Option C: Parse in Laravel controller before saving to database

### Tradeoffs
- Client-side parsing provides immediate feedback
- Need to maintain month name mapping in JS (also used in PHP for consistency)

## Architecture and Data Flow

### Backend → Frontend Data Flow

```
1. BookingController.create() loads:
   - $ticketFares (with additional_gap from routes)
   - $packages (with ticket_fare_id)
   - $defaultFlightDateGap (from FlightDateGap model)

2. Blade template serializes to window.__bookingServerData:
   - ticketFares: [...]
   - packages: [...]
   - preSelectedPackageId: null
   - defaultFlightDateGap: 30
```

### Frontend State Flow

```
1. User selects Package in Create Booking form
   → bookingData.package_id updated

2. User clicks "Add Passenger"
   → openPassengerModal() reads bookingData.package_id
   → Finds associated ticket_fare from packages array
   → Gets route_type/flight_type from ticket data
   → Filters tickets by route_type/flight_type
   → Auto-selects ticket_fare_id if package has one

3. User selects Ticket dropdown
   → onTicketChange() triggered
   → Populates route, airline, class, route_type, flight_type
   → Calls calculateFlightDateRange()
   → Date range options populated in dropdown
```

### Event Flow
- `@change="filterTickets()"` on Route Type/Flight Type dropdowns
- `@change="onTicketChange()"` on Ticket dropdown
- `@change="calculateFlightDateRange()"` triggered within onTicketChange()

### Reactive Update Behavior
- Alpine.js x-model binds passengerData fields
- Changing ticket selection triggers multiple reactive updates
- Date range dropdown populated dynamically via DOM manipulation

### Validation Flow
- Ticket selection validates ticket exists in allTickets array
- Date range parsing validates string format before populating fields

## Frontend Changes

### Components Modified

#### createBookingApp Alpine Component (booking.js)

**State Variables Added/Changed:**

```javascript
passengerData: {
    route_type: '',
    flight_type: '',
    flight_date_range: '',
    // Flight date range fields populated on save:
    flight_date_from: '',
    flight_date_to: '',
}
```

**Methods/Functions Added:**

1. **filterTickets()** (lines 824-806)
   - Purpose: Filter available tickets based on Route Type and Flight Type
   - Logic: Maps dropdown values to database values before comparing

   ```javascript
   const routeTypeMap = {
       'One Way-Inbound': 'oneway_inbound',
       'One Way-Outbound': 'oneway_outbound',
       'Round': 'round',
       'Multi City': 'multi_city'
   };
   const flightTypeMap = {
       'Transit': 'transit',
       'Direct': 'direct'
   };
   const routeTypeValue = routeTypeMap[this.passengerData.route_type] || this.passengerData.route_type;
   const flightTypeValue = flightTypeMap[this.passengerData.flight_type] || this.passengerData.flight_type;
   ```

2. **onTicketChange()** (lines 808-843)
   - Purpose: Handle ticket selection - populate fields and trigger calculations
   - Key addition: Auto-sets route_type and flight_type from ticket data

   ```javascript
   const routeTypeMap = {
       'oneway_inbound': 'One Way-Inbound',
       'oneway_outbound': 'One Way-Outbound',
       'round': 'Round',
       'multi_city': 'Multi City'
   };
   this.passengerData.route_type = routeTypeMap[ticket.route_type] || '';
   this.passengerData.flight_type = flightTypeMap[ticket.flight_type] || '';
   ```

3. **getTicketDisplayText(ticket)** (lines 845-858)
   - Purpose: Format ticket display in dropdown
   - Format varies by ticket_type (offer/group/regular)

   ```javascript
   const price = ticket.selling_fare ? ticket.selling_fare + ' SAR' : '';
   const type = ticket.ticket_type.charAt(0).toUpperCase() + ticket.ticket_type.slice(1);
   switch (ticket.ticket_type) {
       case 'offer':
           const offer = ticket.offer_price ? ' | ' + ticket.offer_price + ' SAR' : '';
           return `${ticket.route} | ${type} | ${price}${offer}`;
       case 'group':
           const seats = ticket.available_seats ? ' | ' + ticket.available_seats + ' seats' : '';
           return `${ticket.route} | ${type} | ${price}${seats}`;
       default:
           return `${ticket.route} | ${type} | ${price}`;
   }
   ```

4. **calculateFlightDateRange()** (lines 869-944)
   - Purpose: Generate date range options based on gap calculation
   - Key logic:

   ```javascript
   const defaultGap = serverData.defaultFlightDateGap || 30;
   const additionalGap = ticket?.additional_gap ?? 0;
   const finalGap = defaultGap + parseInt(additionalGap);
   const calculatedDate = new Date();
   calculatedDate.setDate(calculatedDate.getDate() + finalGap);
   ```
   - Cutoff logic for 5-day intervals:
     - 1-5 → 1-10
     - 6-10 → 11-20
     - 11-15 → 11-20
     - 16-20 → 21-last day
     - 21-25 → 21-last day
     - 26-31 → next month 1-10
   - Dynamic range generation: 1-10, 11-20, 21-last day of month
   - Single loop (i < 16) to avoid duplicate ranges

5. **parseFlightDateRange(rangeString)** (lines 1319-1346)
   - Purpose: Parse selected date range string to extract from/to dates

   ```javascript
   parseFlightDateRange(rangeString) {
       // Input: "May 1, 2026 - May 10, 2026"
       // Output: { from: "2026-05-01", to: "2026-05-10" }
       if (!rangeString) return null;
       const parts = rangeString.split(' - ');
       if (parts.length !== 2) return null;
       const months = {
           'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5,
           'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11
       };
       const parseDate = (dateStr) => {
           const match = dateStr.trim().match(/^(\w+)\s+(\d+),\s+(\d{4})$/);
           if (!match) return null;
           const month = months[match[1]];
           const day = parseInt(match[2]);
           const year = parseInt(match[3]);
           if (month === undefined) return null;
           return new Date(year, month, day).toISOString().split('T')[0];
       };
       const fromDate = parseDate(parts[0]);
       const toDate = parseDate(parts[1]);
       if (!fromDate || !toDate) return null;
       return { from: fromDate, to: toDate };
   }
   ```

**Watchers/Event Handlers:**
- `@change="filterTickets()"` on Route Type dropdown
- `@change="filterTickets()"` on Flight Type dropdown
- `@change="onTicketChange()"` on Ticket dropdown

**UI Behavior:**
- Date range dropdown populated on ticket selection
- No auto-selection after user selects ticket (removed auto-select logic)
- Route Type/Flight Type auto-populate when ticket is selected

### Form UI Changes (create.blade.php)

**Line 5** - Added defaultFlightDateGap to server data:

```html
<script>window.__bookingServerData = { ticketFares: @json($ticketFares ?? []), packages: @json($packages ?? []), preSelectedPackageId: {{ $preSelectedPackageId ?? 'null' }}, defaultFlightDateGap: {{ $defaultFlightDateGap ?? 30 }} };</script>
```

**Lines 346-349** - Flight Date Range dropdown:

```html
<select id="passengerFlightDateRange" x-model="passengerData.flight_date_range" class="...">
    <option value="">Select Date Range</option>
</select>
```

## Backend Changes

### Controllers Modified

#### BookingController.php

**Changes in create() method:**
- Added FlightDateGap model query to get default gap
- Included additional_gap in ticket data from routes
- Updated package loading with visaSellingPrice relation

```php
$flightDateGap = \App\Models\FlightDateGap::first();
$defaultFlightDateGap = $flightDateGap?->gap ?? 30;

// In ticketFares mapping:
'additional_gap' => $fare->route?->additional_gap ?? 0,
```

### Database Changes (Previously Implemented)

- `flight_date_gaps` table: Stores default Flight Date Gap
- `routes` table: Added `additional_gap` column via migration

### Request/Response Handling

- No new API endpoints created
- Existing passenger store validation accepts flight_date_from and flight_date_to

## File-Level Change Breakdown

| File | Changes | Reason |
|------|---------|--------|
| `app/Http/Controllers/BookingController.php` | Added `$defaultFlightDateGap` variable; included `additional_gap` in ticket data; enhanced package loading with visaSellingPrice | Pass gap configuration to frontend |
| `resources/js/booking.js` | Multiple method additions/modifications: filterTickets(), onTicketChange(), getTicketDisplayText(), calculateFlightDateRange(), parseFlightDateRange(), openPassengerModal() fix, savePassenger() update | Core implementation logic |
| `resources/views/bookings/create.blade.php` | Added defaultFlightDateGap to server data script; added x-init="init()" | Pass configuration and initialize Alpine component |

## Step-by-Step Implementation Timeline

### 1. Initial Analysis
- Identified need for dynamic flight date range based on settings and route configuration
- Understood the gap calculation: Final Gap = Flight Date Gap + Additional Gap
- Understood fallback: Additional Gap = 0 if not set

### 2. Implementation Steps

**Step 1**: Add defaultFlightDateGap to BookingController
- Added query to get FlightDateGap model
- Passed to view as $defaultFlightDateGap

**Step 2**: Update Blade template
- Added defaultFlightDateGap to server data script
- Flight Date Range dropdown already existed in form

**Step 3**: Implement calculateFlightDateRange() function
- Calculated final gap from settings + additional gap
- Generated date range options dynamically
- Implemented 5-day cutoff logic

**Step 4**: Fix ticket auto-selection on package selection
- Fixed: changed this.package_id to this.bookingData.package_id
- Fixed: get route_type/flight_type from ticket_fare, not package

**Step 5**: Fix ticket filtering
- Added value mapping in filterTickets() to handle dropdown vs database value mismatch

**Step 6**: Fix onTicketChange() to auto-populate route_type/flight_type
- Added mapping from database values to display values

**Step 7**: Remove auto-selection of date range
- User wanted options loaded but not auto-selected

**Step 8**: Update ticket display text format
- Changed to show type, price, offer/group seats

**Step 9**: Fix date range convention
- Changed to 1-10, 11-20, 21-last day of month format

**Step 10**: Fix duplicate ranges issue
- Changed from nested loops to single loop (i < 16)

**Step 11**: Add parseFlightDateRange() for database
- Created function to parse "May 1, 2026 - May 10, 2026" format
- Updated savePassenger() to populate flight_date_from and flight_date_to

### 3. Debugging
- Multiple iterations to identify why ticket filtering wasn't working
- Root cause: value format mismatch between dropdown (display) vs database
- Multiple fixes needed: onTicketChange, filterTickets, openPassengerModal

### 4. Fixes/Refactoring
- Removed auto-selection after user feedback
- Updated date range format per user requirements
- Fixed duplicate ranges by changing loop structure

### 5. Final Integration
- Date ranges load on ticket selection
- Route Type/Flight Type auto-populate
- Filtering works correctly
- Database fields populate on save

## Bugs and Issues Encountered

### Issue 1: Ticket filtering not working
**Root Cause**: `filterTickets()` compared dropdown values ("One Way-Inbound") with database values ("oneway_inbound")
**Fix**: Added routeTypeMap and flightTypeMap to normalize values before comparison

### Issue 2: Ticket not auto-selecting on package selection
**Root Cause**: Used `this.package_id` which didn't exist; should be `this.bookingData.package_id`
**Fix**: Corrected property reference in openPassengerModal()

### Issue 3: Route Type/Flight Type not auto-populating on ticket selection
**Root Cause**: onTicketChange() only set route, airline, class - not route_type/flight_type
**Fix**: Added mapping logic to populate these fields

### Issue 4: Date range auto-selected on ticket selection
**User Feedback**: Wanted options loaded but not auto-selected
**Fix**: Removed the auto-selection logic from calculateFlightDateRange()

### Issue 5: Duplicate date ranges in dropdown
**Root Cause**: Nested loop (i < 4, week < 4) caused week=3 to default to day 1, creating duplicates
**Fix**: Changed to single loop (i < 16) with weekIndex = i % 3

### Issue 6: Date range not being saved to database
**Root Cause**: flight_date_from and flight_date_to fields never populated from selected range string
**Fix**: Added parseFlightDateRange() function and updated savePassenger()

## Edge Cases Handled

1. **Additional Gap = null/empty**: Falls back to 0 (using `?? 0`)
2. **Default Flight Date Gap = null**: Falls back to 30 days (using `|| 30`)
3. **Date range string format**: Validates format before parsing, returns null if invalid
4. **Month-end handling**: Uses `setMonth(month + 1, 0)` to get last day of month dynamically
5. **Day 26-31 cutoff**: Maps to next month's 1-10 range
6. **Ticket not found**: Gracefully handles with early return in onTicketChange()

## Testing Notes

### Manual Testing Scenarios
1. ✅ Select package → Add Passenger → Ticket auto-selected
2. ✅ Select ticket → Route Type and Flight Type auto-populate
3. ✅ Change Route Type → Ticket dropdown filters correctly
4. ✅ Change Flight Type → Ticket dropdown filters further
5. ✅ Select ticket → Date range options populate with correct format
6. ✅ Save passenger → flight_date_from and flight_date_to saved to database

### Regression Considerations
- Old functionality preserved: customer selection, passenger management, payment flow
- Date range parsing added without breaking existing passenger save
- Ticket filtering only affects filteredTickets, not original allTickets

## Future Improvements

1. **Pre-select date range**: Could re-add auto-selection logic if user changes mind
2. **Validation**: Add required validation for flight_date_range field
3. **Date range in other forms**: Similar logic may be needed in edit passenger form
4. **Unit tests**: Add JavaScript tests for parseFlightDateRange() function
5. **Error handling**: Add user feedback if date range parsing fails

## Summary

The date range feature implementation successfully adds dynamic flight date range calculation to the booking passenger form. The implementation:

- ✅ Calculates final gap from Settings (Flight Date Gap) + Route (Additional Gap)
- ✅ Generates date range options dynamically based on calculated date
- ✅ Auto-populates ticket details on selection
- ✅ Filters tickets based on Route Type/Flight Type
- ✅ Parses selected range and saves to database fields
- ✅ Handles all edge cases and fallback scenarios
- ✅ Maintains backward compatibility with existing functionality

The feature required fixes to multiple existing functions that were broken or incomplete, ensuring proper integration throughout the booking flow.