# Report C — Booking-Level Forensic Ledger

## Overview
This report template outlines the information that would be included for each booking in a forensic ledger analysis. Actual data would be populated through read-only database queries examining booking, passenger, package, and related records along with available historical evidence.

## Ledger Entry Structure

For each potentially affected booking-passenger combination, the following information would be documented:

### 1. Core Identifiers
- **Booking ID**: Unique identifier from `bookings.id`
- **Passenger ID**: Unique identifier from `passengers.id`  
- **Package ID**: Foreign key from `bookings.package_id` (denormalized as `package_name` also available)

### 2. Timestamps
- **Booking Created At**: `bookings.created_at`
- **Booking Updated At**: `bookings.updated_at`  
- **Passenger Created At**: `passengers.created_at`
- **Passenger Updated At**: `passengers.updated_at`
- **Package Updated At**: `packages.updated_at` (if available)
- **Ticket Fare Updated At**: `ticket_fares.updated_at` (for related fares)
- **Visa Selling Price Updated At**: `visa_selling_prices.updated_at` (if applicable)

### 3. Current Stored Values
- **Stored Package Value**: `passengers.package_value`
- **Stored Booking Total Value**: `bookings.total_value`
- **Stored Discount Amount**: `bookings.discount_amount`
- **Stored Invoice Total**: `invoices.total_amount` (if invoice exists)
- **Stored Invoice Balance**: `invoices.balance` (if invoice exists)
- **Stored Paid Amount**: `invoices.paid_amount` (if invoice exists)

### 4. Current Recalculated Reference Value
- **Recalculated Package Value**: Result of `BookingService::calculatePackageValue($passenger, null)` using current live prices
- **Recalculated Booking Total**: Sum of all passenger recalculated values + fingerprint charge
- **Note**: This calculation is read-only and safe to perform as it doesn't modify database records

### 5. Historical Component Values (Best Available Evidence)
For each component, the best-supported historical value would be documented:

**Ticket Component:**
- Historical inbound fare selling price (from TicketFareUpdateLog replay)
- Historical outbound fare selling price (from TicketFareUpdateLog replay)  
- Historical offer price (if applicable)
- Historical child/infant percentage application
- Calculated historical ticket amount

**Visa Component:**
- Historical visa selling price (from VisaSellingPrice via PackageUpdateLog replay)
- Or direct visa submission price if preserved and unaltered

**Service Charge Component:**
- Historical package service charge (from PackageUpdateLog replay)

**Extra Charge Component:**
- Passenger.extra_charge (if preserved as historical snapshot)
- Note: This field may not have historical tracking

**Best-Supported Historical Package Value:**
- Sum of historical ticket, visa, service charge, and extra charge components
- Calculated using era-anchored historic prices

### 6. Historical Evidence Source
Documentation of where each historical value was obtained from:
- **Binary Logs**: MySQL binlog showing actual old/new values
- **Update Logs**: PackageUpdateLog, TicketFareUpdateLog, VisaUpdateLog showing changes
- **Transaction Snapshots**: 
  - `issued_tickets.selling_fare` or `.offer_price` (if unaltered)
  - `visa_submissions` linking to historical visa selling price
  - `passengers.booking_service_charge` (if preserved)
- **Inferred**: Corroborating data from sibling bookings or related transactions
- **None Available**: When no reliable evidence exists

### 7. Value Differences
- **Current vs Historical Difference**: Numeric difference between current stored value and best-supported historical value
- **Current vs Recalculated Difference**: Shows impact of current package pricing vs stored value
- **Recalculated vs Historical Difference**: Shows impact of package price changes on calculation

### 8. Confidence/Status Assessment
Each historical value would be classified as:
- **CONFIRMED**: Direct evidence from binary logs or update logs showing actual historical value
- **INFERRED**: Strong circumstantial evidence from transaction snapshots or corroborating data
- **UNKNOWN**: No reliable evidence available to establish historical value

### 9. Reasoning and Conflict Documentation
For each entry, documentation would include:
- Clear explanation of how historical value was determined
- Any conflicting evidence and resolution approach
- Assumptions made during analysis
- Gaps in evidence that prevent definitive conclusions

## Example Entry Structure (Template)

```
Booking ID: [XXX]
Passenger ID: [XXX] 
Package ID: [XXX]

Timestamps:
- Booking Created: [YYYY-MM-DD HH:MM:SS]
- Booking Updated: [YYYY-MM-DD HH:MM:SS]
- Passenger Created: [YYYY-MM-DD HH:MM:SS]
- Passenger Updated: [YYYY-MM-DD HH:MM:SS]
- Package Updated: [YYYY-MM-DD HH:MM:SS] (if applicable)
- Related Ticket Fare Updated: [YYYY-MM-DD HH:MM:SS] (if applicable)
- Related Visa Selling Price Updated: [YYYY-MM-DD HH:MM:SS] (if applicable)

Current Stored Values:
- Package Value: [XXX.XX]
- Booking Total Value: [XXX.XX]
- Discount Amount: [XXX.XX]
- Invoice Total: [XXX.XX] (if applicable)
- Invoice Balance: [XXX.XX] (if applicable)
- Paid Amount: [XXX.XX] (if applicable)

Current Recalculated Reference:
- Package Value: [XXX.XX]
- Booking Total Value: [XXX.XX]

Historical Component Values:
- Ticket Component: [XXX.XX] (Source: [Binary Logs/Update Logs/Snapshots/Inferred/None])
- Visa Component: [XXX.XX] (Source: [Binary Logs/Update Logs/Snapshots/Inferred/None])  
- Service Charge: [XXX.XX] (Source: [Binary Logs/Update Logs/Snapshots/Inferred/None])
- Extra Charge: [XXX.XX] (Source: [Passenger Field/Presumed Unchanged/None])
- Historical Package Value: [XXX.XX]

Value Differences:
- Current vs Historical: [XXX.XX]
- Current vs Recalculated: [XXX.XX] 
- Recalculated vs Historical: [XXX.XX]

Assessment:
- Ticket Status: [CONFIRMED/INFERRED/UNKNOWN]
- Visa Status: [CONFIRMED/INFERRED/UNKNOWN]
- Service Charge Status: [CONFIRMED/INFERRED/UNKNOWN]
- Overall Historical Value: [CONFIRMED/INFERRED/UNKNOWN]

Reasoning:
[Detailed explanation of evidence analysis, conflict resolution, and assumptions]
```

## Analysis Approach

In an actual forensic investigation, entries would be generated for:
1. All bookings where package, ticket fare, or visa selling price updates occurred after booking creation
2. Bookings showing discrepancies between stored values and recalculated current values
3. A sample of unaffected bookings as controls
4. Bookings identified through update log analysis as having experienced relevant changes

The ledger would be sorted by booking ID and passenger ID for systematic review.

## Conclusion
This ledger structure provides a comprehensive framework for documenting forensic analysis of historical package values. When populated with actual data from read-only database queries and log analysis, it would enable definitive conclusions about which bookings were affected by historical price overwrites and what their correct historical values should be.