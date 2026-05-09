# Booking + Passengers Module Implementation Plan

## Overview

This document outlines the implementation plan for integrating the new Bookings + Passengers module in the Laravel Umrah application. The module consists of two tightly coupled components: **Booking** (header-level entity) and **Passenger** (line items belonging to a booking).

## Current State Analysis

### Existing Models and Database
- **Booking Model**: `app/Models/Booking.php` - Already exists with relationships to Customer, Office, District, Package, FingerprintCharge, Branch, FlightDateGap, Passenger
- **Passenger Model**: `app/Models/Passenger.php` - Already exists with relationships to Booking, PassengerStatus, Documents (MorphMany)
- **Customer Model**: `app/Models/Customer.php` - Already exists with fields: name, iqama_type, passport_no, iqama_no, mobile_no, ref_iqama_no, ref_mobile_no, ref_iqama_doc, address
- **Invoice Model**: `app/Models/Invoice.php` - Already exists
- **Package Model**: `app/Models/Package.php` - Already exists

### Existing Routes (routes/web.php:67-70)
```php
Route::get('/bookings', fn() => view('bookings.index'))->name('booking.index');
Route::post('/bookings', function () {
    return redirect()->route('booking.index')->with('success', 'Booking created successfully!');
})->name('booking.store');
```

### Existing View
- `resources/views/bookings/index.blade.php` - Basic placeholder with hardcoded data

### Enums Available
- `PassengerType` (adult, child, infant)
- `ServiceRequired` (All, Visa Only, Ticket Only)
- `TicketStatus` (various statuses)
- `VisaStatus` (various statuses)
- `FingerprintLocation` (Office, Home)
- `DiscountType` (Fixed, Percentage)

---

## Phase 1: Backend Infrastructure (Priority: HIGH)

### 1.1 Create BookingController
**File**: `app/Http/Controllers/BookingController.php`

**Methods to implement**:
- `index()` - Display booking index with tabs (Booking Index / Passenger Index)
- `create()` - Show create form (can be inline with index)
- `store(Request $request)` - Create booking with passengers
- `show(Booking $booking)` - Show booking details
- `edit(Booking $booking)` - Show edit form
- `update(Request $request, Booking $booking)` - Update booking
- `destroy(Booking $booking)` - Delete booking
- `getPassengerDetails($id)` - API for passenger modal
- `searchCustomer(Request $request)` - API for customer autocomplete

**Dependencies**:
- Uses existing Customer, Booking, Passenger models
- Uses District, Package, Office, FingerprintCharge models

### 1.2 Create PassengerController
**File**: `app/Http/Controllers/PassengerController.php`

**Methods to implement**:
- `store(Request $request)` - Add passenger to booking
- `update(Request $request, Passenger $passenger)` - Update passenger
- `destroy(Passenger $passenger)` - Remove passenger
- `calculateAge(Request $request)` - API for passenger type calculation
- `search(Request $request)` - API for passenger search

**Dependencies**:
- Uses existing Passenger model

### 1.3 Create Form Request Validation Classes
**File**: `app/Http/Requests/StoreBookingRequest.php`
```php
- customer_id: required|exists:customers,id
- district_id: required|exists:districts,id
- fingerprint_location: required|in:Office,Home
- fingerprint_office: required|string
- package_id: nullable|exists:packages,id
- pax_qty: required|integer|min:1
- discount_type: nullable|in:fixed,percentage
- discount_value: nullable|numeric|min:0
- remarks: nullable|string|max:1000
- passengers: required|array|min:1
- passengers.*.first_name: required|string|max:255
- passengers.*.last_name: required|string|max:255
- passengers.*.passport_no: required|string|max:50
- passengers.*.date_of_birth: required|date|before:today
- passengers.*.passenger_type: required|in:adult,child,infant
- passengers.*.mobile_no: nullable|string|max:20
- passengers.*.passport_expiry: nullable|date
- passengers.*.service_required: required|in:All,Visa Only,Ticket Only
- passengers.*.stay_duration: required|integer|min:1
- passengers.*.flight_date_from: nullable|date
- passengers.*.flight_date_to: nullable|date
- passengers.*.address: nullable|string|max:500
```

**File**: `app/Http/Requests/StorePassengerRequest.php`
```php
- booking_id: required|exists:bookings,id
- first_name: required|string|max:255
- last_name: required|string|max:255
- passport_no: required|string|max:50
- date_of_birth: required|date
- passenger_type: required|in:adult,child,infant
- mobile_no: nullable|string|max:20
- passport_expiry: nullable|date
- service_required: required|in:All,Visa Only,Ticket Only
- stay_duration: required|integer|min:1
- flight_date_from: nullable|date
- flight_date_to: nullable|date
- address: nullable|string|max:500
```

**File**: `app/Http/Requests/UpdateBookingRequest.php`
```php
- Same as StoreBookingRequest but with unique passenger validation for updates
- booking_id not required (implicit from route)
```

### 1.4 Create Service Layer
**File**: `app/Services/BookingService.php`

**Methods**:
- `calculatePassengerType($dateOfBirth)` - Auto-calculate adult/child/infant based on DOB
- `calculateDiscount($total, $type, $value)` - Calculate discount amount
- `calculateTotal($booking)` - Calculate booking total
- `generateInvoiceNumber()` - Generate unique invoice number
- `getFingerprintCharge($districtId, $location)` - Get fingerprint charge
- `validatePassengerCount($max)` - Validate booking passenger count
- `processBookingWithPassengers($data)` - Full booking creation with passengers
- `updateBookingTotals($booking)` - Recalculate all totals

### 1.5 Update Routes
**File**: `routes/web.php`

Replace current placeholder routes with:
```php
Route::resource('bookings', BookingController::class);
Route::post('/bookings/{booking}/passengers', [BookingController::class, 'addPassenger'])->name('bookings.passengers.store');
Route::delete('/bookings/{booking}/passengers/{passenger}', [BookingController::class, 'removePassenger'])->name('bookings.passengers.destroy');
Route::get('/passengers/search', [PassengerController::class, 'search'])->name('passengers.search');
Route::get('/passengers/{passenger}', [PassengerController::class, 'show'])->name('passengers.show');
// Booking-specific routes
Route::get('/bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');
// API routes for AJAX
Route::get('/api/customers/search', [CustomerController::class, 'search'])->name('api.customers.search');
Route::post('/api/bookings/calculate-type', [BookingController::class, 'calculateType'])->name('api.bookings.calculate-type');
```

### 1.6 Database Relationship Analysis
**Booking Relationships** (already defined in app/Models/Booking.php):
- `user()` -> BelongsTo(User::class)
- `customer()` -> BelongsTo(Customer::class)
- `office()` -> BelongsTo(Office::class)
- `district()` -> BelongsTo(District::class)
- `package()` -> BelongsTo(Package::class)
- `fingerprintCharge()` -> BelongsTo(FingerprintCharge::class)
- `branch()` -> BelongsTo(Branch::class)
- `dateGap()` -> BelongsTo(FlightDateGap::class, 'date_gap_id')
- `passengers()` -> HasMany(Passenger::class)

**Passenger Relationships** (already defined in app/Models/Passenger.php):
- `booking()` -> BelongsTo(Booking::class)
- `status()` -> BelongsTo(PassengerStatus::class, 'passenger_status_id')
- `documents()` -> MorphMany(Document::class, 'owner')

**Required Implementation Note**: Ensure Customer model has inverse relationship:
```php
// In app/Models/Customer.php - add:
public function bookings(): HasMany
{
    return $this->hasMany(Booking::class);
}
```

---

## Phase 2: Frontend - Blade Views (Priority: HIGH)

### 2.1 Complete Rewrite of bookings/index.blade.php
**File**: `resources/views/bookings/index.blade.php`

**Structure Based on ui-references/booking.html**:

1. **Tab Navigation** (lines 131-137 in booking.html):
   - Booking Index tab (default)
   - Passenger Index tab

2. **Booking Index View** (lines 138-171):
   - Search input (by Mobile or Invoice No)
   - Table with columns:
     - Invoice No, Booking Date, Customer, Mobile, Passenger Count
     - Fingerprint Location, Office, District
     - Re-issue Tkt, Re-issue Cost, Refund Tkt, Refund Amount
     - Total, Paid, Due, Status, Actions

3. **Passenger Index View** (lines 173-207):
   - Table with columns (per passenger):
     - Booking Date, Invoice, Pax Qty, Guardian, Mobile
     - Passenger Name, Passport, Route, Current Status
     - Ticket Fare, Visa, Visa Agent, Visa Status
     - Required Flight Date, Actual Flight Date, F. Cost
     - Package, Package Value, Total Cost, Markup, Due

4. **Add Booking Form** (lines 212-402):
   - Customer Search with autocomplete (based on passport/iqama)
   - Selected Customer display card
   - Booking Fields:
     - Fingerprint Location (Office/Home)
     - Fingerprint Office (dropdown)
     - District (dropdown with fingerprint charge data attributes)
     - Package (dropdown with price data attributes)
     - PAX QTY (readonly, calculated from passengers)
     - Remarks
   - Document Upload area
   - "Add Passenger" button
   - Passenger List (dynamic cards)
   - "+ Add More" button
   - Live Summary Card:
     - Package, Fingerprint Charge, Pax Qty
     - Discount, Total Before Discount, Total Package Value
   - Form Actions: Submit, Clear, Cancel

### 2.2 Modal Components (to be included in index.blade.php or as separate partials)

1. **Customer Modal** (lines 440-512):
   - Fields: Name, Iqama Type, Referral Iqama/Mobile/Doc
   - Iqama No, Passport No, Mobile No
   - Document Upload

2. **Passenger Modal** (lines 514-698):
   - Basic Info: First Name, Last Name, Passport, Mobile, DOB, Gender (for adults), Passenger Type, Passport Expiry
   - Service Info: Stay Duration, Service Required
   - Travel Details: Route Type, Flight Type, Route, Airline, Class, Flight Date Range
   - Baggage Info: Baggage Weight (readonly)
   - Location: Address
   - Ticket Options: With Offer, Refundable checkboxes
   - Documents Upload

3. **Discount Modal** (lines 404-438):
   - Original Total (readonly)
   - Discount Type (Fixed/Percentage)
   - Discount Value
   - Discount Amount (calculated)
   - New Total (calculated)

4. **Ticket Fare Modal** (lines 716-846):
   - Ticket Type (Regular/Group)
   - Ticket Information: Inbound/Outbound Date, PNR, Ticket Number, Date, Agent
   - Travel Details (readonly): Route, Airline, Class, Flight Date Range, Route Type, Flight Type, Passenger Type
   - Fare Calculation: Selling Fare, Net Fare
   - Options: With Offer, Refundable

5. **Visa Cost Modal** (lines 848-891):
   - Visa Agent, Commission Agent
   - Visa Selling Price (readonly)
   - Agent Commission, Net Visa Cost
   - Final Visa Cost (calculated)

6. **Visa Issue Modal** (lines 893-...):
   - Visa Agent (readonly)
   - Visa Number
   - Selling Price (readonly)

7. **Custom Duration Modal** (lines 700-714):
   - Duration (days) input (30-89 range)

---

## Phase 3: Frontend - JavaScript (Priority: HIGH)

### 3.1 Create booking.js
**File**: `resources/js/booking.js` (or add to existing app.js)

**State Management** (from booking.js lines 8-46):
```javascript
const state = {
    customers: [],
    customerSearchTerm: '',
    filteredCustomers: [],
    selectedCustomer: null,
    passengers: [],
    editingPassengerIndex: null,
    isCustomerModalOpen: false,
    isPassengerModalOpen: false,
    passengerDocFiles: [],
    bookingDocFiles: [],
    customerDocFiles: [],
    editingBookingIndex: null,
    currentIndexTab: 'booking',
    discountType: 'fixed',
    discountValue: 0,
};
```

**Key Functions to Implement**:

1. **Customer Search** (lines 512-576):
   - `handleCustomerSearch(e)` - Debounced search on input
   - `renderSuggestions(customers)` - Show dropdown
   - `showAddNewCustomerLink()` - Show "Add New Customer" option
   - `selectCustomer(customer)` - Select from suggestions
   - `clearSelectedCustomer()` - Clear selection

2. **Customer Modal** (lines 606-707):
   - `openCustomerModal()` / `closeCustomerModal()`
   - `toggleReferralFields()` - Show/hide referral fields
   - `toggleCustomerIqamaField()` - Show/hide iqama field
   - `handleCustomerSubmit(e)` - Form submission
   - `handleCustomerDocSelect(event)` - Document upload

3. **Passenger Modal** (lines 782-988):
   - `openPassengerModal(index)` / `closePassengerModal()`
   - `generateFlightDateRangeOptions()` - Dynamic date range generation
   - `calculatePassengerType(dob)` - Date of birth to type conversion
   - `handlePassengerSubmit(e)` - Form submission
   - `updateCheckboxState()` - Handle offer/refundable interplay

4. **Live Summary** (lines 993-1029):
   - `updateLiveSummary()` - Real-time calculation of totals
   - Pull values from: Package, District (fingerprint charge), Pax Qty

5. **Discount Modal** (lines 1034-1091):
   - `openDiscountModal()` / `closeDiscountModal()`
   - `calculateDiscount()` - Real-time discount calculation
   - `applyDiscount()` - Apply discount to state

6. **Passenger List** (lines 1096-1160):
   - `renderPassengerList()` - Render passenger cards
   - `deletePassenger(index)` - Remove passenger

7. **Form Control** (lines 1185-1636):
   - `showForm()` / `hideForm()` - Toggle form visibility
   - `clearForm()` - Reset form
   - `submitForm()` - Form submission
   - `renderBookingIndex()` / `renderPassengerIndex()` - Tab rendering

8. **Search Functions** (lines 1673-1681):
   - `searchBookingIndex()` - Filter booking index

### 3.2 Integration with app.js
Add to `resources/js/app.js`:
```javascript
import './booking';
```

---

## Phase 4: Supporting Components (Priority: MEDIUM)

### 4.1 Update existing views to use new booking structure

**Customer Index**: Add link to create new customer from booking form
**Invoice Views**: Link to bookings
**Dashboard**: Link to booking index

### 4.2 Create Partial Views
Consider creating:
- `resources/views/bookings/partials/customer-search.blade.php`
- `resources/views/bookings/partials/passenger-list.blade.php`
- `resources/views/bookings/partials/summary-card.blade.php`
- `resources/views/bookings/modals/*.blade.php`

### 4.3 Add JavaScript Assets
Run asset build after creating JS files:
```bash
npm run build
```

---

## Phase 5: Testing Considerations (Priority: MEDIUM)

### 5.1 Backend Tests
- Unit tests for BookingService calculations
- Feature tests for BookingController CRUD
- Feature tests for PassengerController CRUD
- Request validation tests

### 5.2 Frontend Tests
- Manual testing of:
  - Customer search/autocomplete
  - Passenger modal form validation
  - Discount calculation
  - Live summary updates
  - Tab switching
  - Form submission

### 5.3 Browser Testing Checklist
- [ ] Customer search returns results
- [ ] New customer can be created from modal
- [ ] Passenger type auto-calculates from DOB
- [ ] Discount modal calculates correctly for Fixed type
- [ ] Discount modal calculates correctly for Percentage type
- [ ] Live summary updates when passengers added
- [ ] Form clears properly
- [ ] Booking index search works
- [ ] Tab switching between Booking/Passenger index
- [ ] Edit booking loads data correctly

---

## Implementation Dependencies & Order

### Task Dependencies
1. **Must Complete First**:
   - Phase 1.1: BookingController (basic CRUD)
   - Phase 1.2: PassengerController (basic CRUD)
   - Phase 1.3: Form Request Validations
   - Phase 1.4: BookingService (calculation methods)
   - Phase 1.5: Route updates

2. **Must Complete Second**:
   - Phase 1.6: Add Customer.booking() relationship
   - Phase 2.1: Complete index blade view
   - Phase 2.2: Modal components

3. **Must Complete Third**:
   - Phase 3.1: JavaScript interactions
   - Phase 3.2: App.js integration

4. **Should Complete**:
   - Phase 4: Supporting components
   - Phase 5: Testing

### Unknowns Requiring Clarification

1. **Authentication**: How is the current user tracked? Should booking record user_id from auth?

2. **Office Data**: What are the exact office names/enum values? (Currently hardcoded in UI as BMT-Dhaka, BMT-Chattogram, etc.)

3. **Customer Search API**: Is there an existing API endpoint for customer search, or should we create one?

4. **Status Workflow**: What are the exact passenger status values/routes through the workflow?

5. **File Upload Storage**: Are documents stored locally or in cloud? Current references use localStorage in JS demo.

6. **Route Data**: Where are the routes stored (Route model)? What format for dropdown options?

7. **Airline Data**: Where are airlines stored? Use existing Airline model?

8. **Ticket Fare Integration**: How do we fetch current ticket fares? From TicketFare model?

9. **Visa Price Integration**: How do we fetch current visa prices? From VisaSellingPrice model?

---

## Refactoring Opportunities

1. **Current Placeholder Routes**: Replace function closures with proper controller methods

2. **Hardcoded UI Data**: Replace hardcoded dropdown options with database-driven data

3. **Customer Model**: Add missing `bookings()` relationship

4. **Customer Search**: Currently client-side; consider server-side for large datasets

5. **Document Upload**: Implementation needed - currently just UI placeholder

6. **Payment Integration**: Link to existing Payment model/controller

7. **Invoice Integration**: Link to existing Invoice model for invoice generation

---

## Risk Assessment

| Risk | Impact | Likelihood | Mitigation |
|------|--------|-----------|------------|
| Customer search performance with large dataset | High | Medium | Implement server-side search with pagination |
| Complex form validation | High | High | Thorough testing, clear error messages |
| Passenger type auto-calculation logic | Medium | Low | Use established age thresholds |
| Multi-passenger booking creation | Medium | Medium | Wrap in transaction |
| File upload size/format | Low | Low | Configure validation and storage |
| Discount calculation edge cases | Medium | Low | Test various scenarios |

---

## File Structure Changes Summary

### New Files to Create
```
app/Http/Controllers/BookingController.php
app/Http/Controllers/PassengerController.php
app/Http/Requests/StoreBookingRequest.php
app/Http/Requests/UpdateBookingRequest.php
app/Http/Requests/StorePassengerRequest.php
app/Services/BookingService.php
resources/views/bookings/index.blade.php (complete rewrite)
resources/js/booking.js
```

### Files to Modify
```
routes/web.php
app/Models/Customer.php (add bookings relationship)
app/Models/Booking.php (verify relationships)
```

### Existing Files (No Changes)
```
app/Models/Passenger.php (existing, correct)
app/Models/Booking.php (existing, correct)
app/Models/Customer.php (add relationship only)
resources/views/bookings/index.blade.php (will be rewritten)
```

---

*Implementation Plan Version: 1.0*
*Created: 2026-05-09*