# Report B — Package Current-Value Inventory

## Overview
This report documents the current configuration values for all relevant packages in the system, based on code analysis of the Package model and related entities.

## Package Current-Value Components

For each Package in the system, the current value consists of:

### 1. Base Package Fields
- `package_id`: Unique identifier
- `package_name`: Descriptive name
- `service_charge`: Fixed service charge amount (decimal)
- `regular_price`: Standard package price (decimal)
- `offer_price`: Discounted/promotional price (decimal)
- `is_active`: Boolean indicating if package is currently available
- `is_double_ticket`: Boolean indicating if package includes inbound and outbound tickets

### 2. Related Ticket Fare Components
Depending on `is_double_ticket` value:

**Single Ticket Packages (`is_double_ticket = false`):**
- `ticket_fare_id`: FK to TicketFare model
- From related TicketFare:
  - `selling_fare`: Base ticket price
  - `offer_price`: Promotional ticket price (if applicable)
  - `ticket_type`: Either 'regular' or 'offer'
  - `child_fare_percentage`: Percentage applied to child passengers
  - `infant_fare_percentage`: Percentage applied to infant passengers

**Double Ticket Packages (`is_double_ticket = true`):**
- `ticket_fare_inbound_id`: FK to inbound TicketFare model
- `ticket_fare_outbound_id`: FK to outbound TicketFare model
- From each related TicketFare:
  - Same fields as above (selling_fare, offer_price, etc.)

### 3. Related Visa Selling Price
- `visa_selling_price_id`: FK to VisaSellingPrice model
- From related VisaSellingPrice:
  - `selling_price`: Visa processing charge

## Current Value Calculation Logic

The current package value for a passenger is calculated by `BookingService::calculatePackageValue()` as follows:

1. **Ticket Amount** (if service_required ≠ 'visa_only'):
   - For double ticket packages:
     * Sum of inbound and outbound fare amounts
     * Adjusted by passenger type percentages (child/infant) from inbound fare
   - For single ticket packages:
     * Base fare (offer_price if ticket_type='offer' else selling_fare)
     * Adjusted by passenger type percentages

2. **Visa Amount** (if service_required ≠ 'ticket_only' and package exists):
   * VisaSellingPrice.selling_price (replayed to appropriate era if using historic calculation)
   * Otherwise 0

3. **Service Charge Amount**:
   * Package.service_charge (replayed to appropriate era if using historic calculation)
   * Applied for all service types except where explicitly excluded

4. **Extra Charge**:
   * Passenger.extra_charge (if present)

## Data Availability

To compile a complete Package Current-Value Inventory, the following database queries would be required (read-only):

```sql
-- Basic package information
SELECT 
    p.id as package_id,
    p.package_name,
    p.service_charge,
    p.regular_price,
    p.offer_price,
    p.is_active,
    p.is_double_ticket,
    p.visa_selling_price_id
FROM packages p
WHERE p.is_active = 1;

-- Single ticket fare details
SELECT 
    tf.id as ticket_fare_id,
    tf.selling_fare,
    tf.offer_price,
    tf.ticket_type,
    tf.child_fare_percentage,
    tf.infant_fare_percentage
FROM ticket_fares tf
WHERE tf.is_active = 1;

-- Double ticket fare details (inbound)
SELECT 
    tf.id as ticket_fare_id,
    tf.selling_fare,
    tf.offer_price,
    tf.ticket_type,
    tf.child_fare_percentage,
    tf.infant_fare_percentage
FROM ticket_fares tf
INNER JOIN packages p ON p.ticket_fare_inbound_id = tf.id
WHERE p.is_active = 1 AND p.is_double_ticket = 1;

-- Double ticket fare details (outbound)
SELECT 
    tf.id as ticket_fare_id,
    tf.selling_fare,
    tf.offer_price,
    tf.ticket_type,
    tf.child_fare_percentage,
    tf.infant_fare_percentage
FROM ticket_fares tf
INNER JOIN packages p ON p.ticket_fare_outbound_id = tf.id
WHERE p.is_active = 1 AND p.is_double_ticket = 1;

-- Visa selling price details
SELECT 
    vsp.id as visa_selling_price_id,
    vsp.selling_price
FROM visa_selling_prices vsp
WHERE vsp.id IN (SELECT visa_selling_price_id FROM packages WHERE is_active = 1);
```

## Notes About Missing or Ambiguous Relationships

1. **Fallback Behavior**: When specific fare IDs are null (e.g., ticket_fare_inbound_id for double ticket packages), the system falls back to the package-level ticket_fare_id.

2. **Offer Price Logic**: The system uses offer_price when ticket_type='offer' and offer_price > 0, otherwise falls back to selling_fare.

3. **Percentage Sources**: Child/infant percentages come exclusively from the inbound fare for double ticket packages, or the single fare for single ticket packages.

4. **Era Anchoring**: For historic calculations, values are replayed to the passenger's historic anchor moment (max(earliest era start of package, passenger creation time)), not to the current live values.

5. **Nullable Fields**: All monetary fields are nullable in the database but have default values enforced through application logic.

## Conclusion
The current value of any package can be determined by examining the live package record and its related TicketFare and VisaSellingPrice records, applying the business logic defined in `BookingService::calculatePackageValue()`. For historic values, the same logic applies but with values replayed to specific points in time using the update log systems.