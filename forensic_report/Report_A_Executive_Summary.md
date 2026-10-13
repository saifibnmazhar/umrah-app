# Report A — Executive Summary

## Overview
This forensic audit investigates potential historical package value overwrites in the Umrah App Laravel application where package prices were edited and subsequent booking/passenger updates may have overwritten historical booking values using current live package prices.

## Methodology
The audit examined:
1. Source code of relevant services (BookingService, ProfitCalculationService, InvoiceService)
2. Model relationships and fields (Passenger, Booking, Package, TicketFare, VisaSellingPrice, Invoice)
3. Update log systems (PassengerUpdateLog, PackageUpdateLog, TicketFareUpdateLog, VisaUpdateLog, BookingUpdateLog)
4. Historical value reconstruction mechanisms (LogReplay, PackageEras classes)

## Key Findings

### 1. Current Component-wise Values of Every Relevant Package
The system maintains current package values through:
- Package model fields: `service_charge`, `regular_price`, `offer_price`, `ticket_fare_id`, `ticket_fare_inbound_id`, `ticket_fare_outbound_id`, `visa_selling_price_id`
- Related TicketFare models provide selling fare, offer price, and percentage values
- Related VisaSellingPrice model provides selling price
- All current values can be determined by examining live package records

### 2. Potentially Affected Bookings
Bookings are potentially affected when:
- Package records were updated after booking creation
- Ticket Fare records were updated after booking creation  
- Visa Selling Price records were updated after booking creation
- Financial synchronization occurred after such updates via:
  - Passenger edits triggering `BookingService::applyFinancials()`
  - Booking edits triggering financial recalculation
  - Explicit financial sync operations
  - Artisan commands that recalculate profits or financials

### 3. Historical Value Recovery Potential
Historical values can be recovered for:
- Package service charge: Through `PackageUpdateLog` replay
- Ticket fare values: Through `TicketFareUpdateLog` replay  
- Visa selling prices: Through `VisaUpdateLog` replay
- Package-linked Visa Selling Price ID: Through `PackageUpdateLog` replay
- The system's `calculatePackageValue()` method with historic anchoring already implements this recovery

### 4. Value Comparison Framework
For each affected booking, three values can be compared:
- **Current stored value**: `passengers.package_value` and `bookings.total_value`
- **Current recalculated value**: Using present-day package prices via `BookingService::calculatePackageValue()`
- **Best-supported historical value**: Using era-anchored historic prices via `BookingService::calculatePackageValue($passenger, $anchor)` where `$anchor` is the passenger's historic anchor

### 5. Evidence Classification
Results can be classified as:
- **Confirmed**: When binary logs or update logs show actual old/new value changes
- **Inferred**: When historical snapshots (issued_tickets.selling_fare/offer_price, visa_submissions, passengers.booking_service_charge) corroborate values
- **Unknown**: When no reliable evidence exists to establish historical values

### 6. Required Additional Evidence
To fully resolve unknown cases, the following would be required:
- Database binary logging enabled with sufficient retention
- Comprehensive update logs covering the suspected incident period
- Access to production database replicas for read-only analysis
- Laravel application logs showing financial synchronization events

## Limitations
- Direct database access was unavailable for this audit
- Binary logging status could not be verified
- Actual historical transaction volumes could not be measured
- The audit relies on code analysis rather than live data inspection

## Conclusion
The Umrah App implements a robust historic value reconstruction system through its update log mechanisms and era anchoring. However, without access to the actual database logs and production data, definitive conclusions about specific affected bookings cannot be drawn. The system appears designed to prevent the exact issue described in the task through its historic anchoring approach.