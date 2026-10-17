# Phase 2 — Read-Only Historical Package Value Forensics

## Step 1 — Review Existing Evidence

After reviewing all six Phase 1 reports and SUMMARY_FINDINGS.md, I can distinguish the following types of conclusions:

### 1. Verified Code Behavior
- The historic anchoring mechanism in `PackageEras::anchorAt()` correctly calculates the max(earliest era start of package, passenger creation time)
- The `LogReplay::valueFromEvents()` function properly replays update logs to reconstruct historical values
- `BookingService::calculatePackageValue()` implements the correct business logic for calculating package values from package, ticket fare, and visa selling price components
- Financial synchronization in `BookingService::applyFinancials()` properly handles updates with options to recalculate passenger values or just update totals
- All update log tables (`package_update_logs`, `ticket_fare_update_logs`, `visa_update_logs`, `passenger_update_logs`, `booking_update_logs`) exist and follow the expected schema

### 2. Verified Database Evidence
- **NONE** - Direct database access was unavailable for this audit. All database-related analysis in Phase 1 was based on code inspection rather than actual data examination.

### 3. Inferences
- Based on the system architecture, historical package value overwrites would be difficult to occur silently due to the historic anchoring mechanism
- If overwrites did occur, they would be detectable by comparing stored `passengers.package_value` with `BookingService::calculatePackageValue($passenger, $passenger->historicAnchor())`
- The system contains built-in recovery mechanisms to reconstruct correct historical values using the same update log replay technology
- Financial system provides additional validation through delta-based invoice updates that would make discrepancies visible in payment application patterns

### 4. Unknowns and Unavailable Evidence
- Whether any historical package value overwrites actually occurred in the live system
- Which specific bookings/passengers, if any, have incorrect historical values
- Magnitude of any historical value discrepancies
- Root cause of any discrepancies (if they exist)
- Timeline precision of when discrepancies were introduced or could be detected
- Actual content of update log tables (package_update_logs, ticket_fare_update_logs, etc.)
- Actual values stored in core tables (passengers, bookings, packages, ticket_fares, visa_selling_prices)
- MySQL binary logging status and retention settings
- Application logs showing financial synchronization events

## Step 2 — Assess Evidence Availability

Through investigation of the available environment, I determined the following evidence source availability:

### Available Evidence Sources
1. **Source Code**: Complete access to all application code for analysis
2. **Database Schema**: Full schema visible through migration files in `database/migrations/`
3. **Seeders**: Complete seeder classes available in `database/seeders/` showing what sample data would be loaded
4. **Application Logs**: Laravel logs available in `storage/logs/laravel.log` (though primarily showing cache-related errors)
5. **File System**: Full access to the application directory structure

### Unavailable or Limited Evidence Sources
1. **Live Database Connection**: 
   - Despite `.env` configuring MySQL (`DB_CONNECTION=mysql`), the application is actually using SQLite in memory (`Connection: sqlite, Database: :memory:`)
   - This indicates the system is running in a testing/development environment rather than against production data
   - No ability to connect to or query the MySQL database configured in `.env`

2. **Database Content**:
   - Unable to examine actual table contents for:
     - `packages`, `ticket_fares`, `visa_selling_prices`
     - `passengers`, `bookings`, `invoices`
     - All update log tables (`*_update_logs`)
   - Unable to verify if seeders have been run or what data exists

3. **Binary Logs**:
   - Unable to check MySQL binary logging status (`SHOW VARIABLES LIKE 'log_bin'`)
   - Unable to check binary log format (`SHOW VARIABLES LIKE 'binlog_format'`)
   - Unable to check binary log retention (`SHOW VARIABLES LIKE 'binlog_expire_logs_seconds'`)
   - Unable to list available binary log files (`SHOW BINARY LOGS`)

4. **Application Logs for Financial Events**:
   - Laravel logs show primarily cache-related errors, not financial synchronization events
   - No visible evidence of `BookingService::applyFinancials()` calls or related financial operations

5. **External System Evidence**:
   - No access to payment gateway records, accounting system entries, or backup archives
   - No change management records for approved price updates

### Critical Limitation
The most significant limitation is that the system is not actually using the MySQL database configured in `.env`. Instead, it's using an SQLite in-memory database that appears to be missing essential tables (evidenced by cache table errors in the logs). This means there is no persistent data available for forensic analysis in the current environment.

## Step 3 — Design and Run Safe Validation

Since authorized read-only database access to the MySQL production database is not available in this environment, I cannot execute validation queries directly. However, I can design the exact read-only SQL queries that would be run by an authorized database operator with access to the production MySQL database.

### Preliminary Schema Validation

If database access were available, the first step would be to validate the schema and check record counts:

```sql
-- Check if we're connected to the correct database
SELECT DATABASE() as current_database;

-- Check core table existence and record counts
SELECT 
    'packages' as table_name, COUNT(*) as record_count FROM packages
UNION ALL
    SELECT 'ticket_fares', COUNT(*) FROM ticket_fares
UNION ALL
    SELECT 'visa_selling_prices', COUNT(*) FROM visa_selling_prices
UNION ALL
    SELECT 'passengers', COUNT(*) FROM passengers
UNION ALL
    SELECT 'bookings', COUNT(*) FROM bookings
UNION ALL
    SELECT 'invoices', COUNT(*) FROM invoices;
```

### Update Log Table Validation

```sql
-- Check update log table existence and record counts
SELECT 
    'package_update_logs' as table_name, COUNT(*) as record_count FROM package_update_logs
UNION ALL
    SELECT 'ticket_fare_update_logs', COUNT(*) FROM ticket_fare_update_logs
UNION ALL
    SELECT 'visa_update_logs', COUNT(*) FROM visa_update_logs
UNION ALL
    SELECT 'passenger_update_logs', COUNT(*) FROM passenger_update_logs
UNION ALL
    SELECT 'booking_update_logs', COUNT(*) FROM booking_update_logs
UNION ALL
    SELECT 'invoice_update_logs', COUNT(*) FROM invoice_update_logs;
```

### Sample Data Inspection (Limited to First 10 Records)

If tables contain data, inspect sample records to understand structure:

```sql
-- Sample package records
SELECT 
    id, package_name, service_charge, regular_price, offer_price,
    ticket_fare_id, ticket_fare_inbound_id, ticket_fare_outbound_id,
    visa_selling_price_id, is_active, is_double_ticket,
    created_at, updated_at
FROM packages 
LIMIT 10;

-- Sample ticket fare records
SELECT 
    id, selling_fare, offer_price, ticket_type,
    child_fare_percentage, infant_fare_percentage,
    created_at, updated_at
FROM ticket_fares 
LIMIT 10;

-- Sample visa selling price records
SELECT 
    id, selling_price, created_at, updated_at
FROM visa_selling_prices 
LIMIT 10;
```

### Historic Value Reconstruction Validation

To validate the historic anchoring logic against a sample of bookings, we would:

1. Select a sample of bookings with passengers
2. For each passenger, calculate:
   - Stored package_value
   - Current recalculated value (using present-day prices)
   - Historic-anchored recalculated value (using prices at passenger's historic anchor)
3. Compare these values to identify discrepancies

The SQL for this would be complex due to the need to replicate the PHP logic in SQL, but conceptually it would involve:

```sql
-- This is a conceptual representation - actual implementation would require
-- replicating the PHP logic for historic anchoring and value replay in SQL
SELECT 
    p.id as passenger_id,
    p.package_value as stored_package_value,
    -- Current recalculated value (would need to join to current package/fare/visa values)
    -- Historic recalculated value (would need to join to historic package/fare/visa values via update logs)
    -- Differences between these values
FROM passengers p
JOIN bookings b ON p.booking_id = b.id
JOIN packages pkg ON b.package_id = pkg.id
WHERE p.is_cancelled = 0  -- Exclude cancelled passengers
LIMIT 100;  -- Limit sample size for performance
```

### Update Log Analysis for Changes

To identify when package, ticket fare, and visa selling price values were modified:

```sql
-- Package updates
SELECT 
    pul.id,
    pul.package_id,
    pul.created_at as update_time,
    pul.action,
    JSON_EXTRACT(pul.old_values, '$.service_charge') as old_service_charge,
    JSON_EXTRACT(pul.new_values, '$.service_charge') as new_service_charge,
    JSON_EXTRACT(pul.old_values, '$.visa_selling_price_id') as old_visa_price_id,
    JSON_EXTRACT(pul.new_values, '$.visa_selling_price_id') as new_visa_price_id
FROM package_update_logs pul
WHERE pul.created_at BETWEEN '2026-09-01 00:00:00' AND '2026-10-13 23:59:59'
ORDER BY pul.created_at;

-- Ticket fare updates
SELECT 
    tful.id,
    tful.ticket_fare_id,
    tful.created_at as update_time,
    tful.action,
    JSON_EXTRACT(tful.old_values, '$.selling_fare') as old_selling_fare,
    JSON_EXTRACT(tful.new_values, '$.selling_fare') as new_selling_fare,
    JSON_EXTRACT(tful.old_values, '$.offer_price') as old_offer_price,
    JSON_EXTRACT(tful.new_values, '$.offer_price') = new_offer_price
FROM ticket_fare_update_logs tful
WHERE tful.created_at BETWEEN '2026-09-01 00:00:00' AND '2026-10-13 23:59:59'
ORDER BY tful.created_at;

-- Visa selling price updates
SELECT 
    vul.id,
    vul.visa_selling_price_id,
    vul.created_at as update_time,
    vul.action,
    JSON_EXTRACT(vul.old_values, '$.selling_price') as old_selling_price,
    JSON_EXTRACT(vul.new_values, '$.selling_price') as new_selling_price
FROM visa_update_logs vul
WHERE vul.created_at BETWEEN '2026-09-01 00:00:00' AND '2026-10-13 23:59:59'
ORDER BY vul.created_at;
```

### Financial Synchronization Event Analysis

To identify when financial recalculation likely occurred:

```sql
-- Passenger-level triggers (would trigger full value recalculation)
SELECT 
    plu.id,
    plu.passenger_id,
    plu.created_at as update_time,
    plu.action,
    JSON_EXTRACT(plu.old_values, '$.package_value') as old_package_value,
    JSON_EXTRACT(plu.new_values, '$.package_value') = new_package_value
FROM passenger_update_logs plu
WHERE plu.action IN ('updated', 'recalculated')
AND plu.created_at BETWEEN '2026-09-01 00:00:00' AND '2026-10-13 23:59:59'
ORDER BY plu.created_at;

-- Booking-level triggers (would trigger financial sync)
SELECT 
    bul.id,
    bul.booking_id,
    bul.created_at as update_time,
    bul.action,
    JSON_EXTRACT(bul.old_values, '$.total_value') = old_total_value,
    JSON_EXTRACT(bul.new_values, '$.total_value') = new_total_value
FROM booking_update_logs bul
WHERE bul.action IN ('updated', 'financial_sync')
AND bul.created_at BETWEEN '2026-09-01 00:00:00' AND '2026-10-13 23:59:59'
ORDER BY bul.created_at;
```

### Expected Results Format

If database access were available and data existed, the validation would produce:

1. **Record Counts**: Showing how many records exist in each table
2. **Sample Data**: Showing the structure and sample values of key fields
3. **Update Log Entries**: Showing when and what values were changed
4. **Discrepancy Identification**: For each passenger, showing:
   - Stored package_value
   - Current recalculated value (live prices)
   - Historic-anchored recalculated value (correct historical value)
   - Differences between these values
5. **Event Correlation**: Showing temporal relationships between value updates and financial synchronization events

Since I cannot execute these queries in the current environment, I will not claim to have executed them. Instead, I provide them as the exact read-only SQL that an authorized operator would run.

## Step 4 — Evaluate Recoverability

Without access to actual database contents, I cannot definitively evaluate recoverability for specific discrepancies. However, based on the system architecture, I can provide a framework for how recoverability would be assessed:

### Recoverability Classification Framework

For each discrepancy identified (difference between stored and historic-anchored values):

1. **Reconstructed with Sufficient Evidence**:
   - Direct evidence from binary logs showing actual old/new values
   - OR complete update log chains showing all modifications to relevant fields
   - OR transaction snapshots (issued_tickets.selling_fare/offer_price, visa_submissions) that preserve unaltered historical values
   - OR multiple corroborating evidence sources that independently confirm the same historical value

2. **Partially Reconstructed**:
   - Some components have sufficient evidence while others are ambiguous
   - Evidence shows historical value range but not exact value
   - Update logs show changes but with gaps in the chain
   - Transaction snapshots exist but may have been altered

3. **Ambiguous**:
   - Conflicting evidence from different sources
   - Update logs show changes but timestamps don't align with expected effect
   - Transaction snapshots exist but show values that don't reconstruct to a coherent total
   - Insufficient evidence to rule out legitimate explanations

4. **Not Recoverable**:
   - No evidence trails exist for the relevant time period
   - Update logs purged or missing
   - Binary logs expired or not enabled
   - Transaction snapshots overwritten or corrupted
   - Multiple conflicting evidence sources with no resolution possible

### Evidence Requirements for Each Component

For a passenger's package value, recoverability would be assessed per component:

**Ticket Component**:
- Sufficient: TicketFareUpdateLog showing historical selling/offer prices + percentage applications
- Partial: Issued ticket snapshots showing preserved selling/offer prices
- Ambiguous: Conflicting ticket fare values from different sources
- None: No ticket fare update logs or transaction snapshots for relevant period

**Visa Component**:
- Sufficient: VisaUpdateLog showing historical selling prices OR PackageUpdateLog showing historical visa_selling_price_id
- Partial: Visa submission linking to historical visa selling price (if price not altered)
- Ambiguous: Visa submission exists but linked price history unclear
- None: No visa selling price update logs and no visa submission or submission price altered

**Service Charge Component**:
- Sufficient: PackageUpdateLog showing historical service_charge values
- Partial: N/A (service charge primarily tracked through package update logs)
- Ambiguous: Conflicting service charge values from different sources
- None: No package update logs for service charge during relevant period

**Extra Charge Component**:
- Sufficient: Passenger.update logs showing historical extra_charge values
- Partial: Passenger.extra_charge field if known to be immutable after setting
- Ambiguous: N/A (either clear evidence or none)
- None: No passenger update logs for extra_charge and no reason to believe it's preserved

## Step 5 — Deliverables

### Evidence Sources Inspected
- Source code of all relevant services and models
- Database schema through migration files
- Seeder files showing expected data structure
- Application logs (showing primarily cache-related errors, not financial events)
- File system structure

### Evidence Sources Unavailable
- Live MySQL database connection (system using SQLite in memory instead)
- Actual table contents for core entities and update logs
- MySQL binary logging status and content
- Application logs showing financial synchronization events
- External system evidence (payment gateways, accounting systems, backups)

### Checks Performed
1. Verified code implementation of historic anchoring and value replay mechanisms
2. Confirmed existence of all necessary database tables through migration inspection
3. Validated that update log tables follow expected schema for historical reconstruction
4. Examined application logs for evidence of database activity (found primarily cache errors)
5. Confirmed system is not using the MySQL database configured in .env

### Booking-Level Discrepancy Ledger
Since no database access was available, no actual ledger entries could be populated. However, the ledger structure from Report C would be used when data becomes available. The ledger would contain entries for each potentially affected booking-passenger combination with:

- Core identifiers (Booking ID, Passenger ID, Package ID)
- Timestamps (creation/update times for all relevant entities)
- Current stored values (package_value, total_value, discount_amount, invoice fields)
- Current recalculated reference values (using present-day prices)
- Historical component values (ticket, visa, service charge, extra charge) with evidence sources
- Value differences (current vs historical, current vs recalculated, recalculated vs historical)
- Confidence/status assessment (CONFIRMED/INFERRED/UNKNOWN for each component and overall)
- Reasoning and conflict documentation

### Financial Cross-Check Results
Since no database access was available, no actual financial cross-checks could be performed. However, the validation points from Report D would be applied when data becomes available, including:

1. Internal consistency checks (component sums, booking totals, invoice baselines)
2. Historical consistency checks (component reconstruction, timeline consistency, mutation detection)
3. External validation checks (payment correlation, discount application, profit consistency)

### Confidence and Limitations for Each Finding

All findings from this Phase 2 investigation are subject to the following limitations:

**High Confidence Findings**:
- Code analysis conclusions about system design and mechanisms
- Schema verification through migration files
- Determination that system is not using configured MySQL database

**Low Confidence Findings**:
- Any conclusions about actual data content or historical discrepancies
- Assumptions about what data might exist based on seeders
- Inferences about system behavior without actual data verification

**Fundamental Limitation**:
- No actual database contents were inspected, so no conclusions can be drawn about whether historical package value overwrites actually occurred

### Read-Only SQL Used or Proposed
No SQL was executed in this environment due to lack of database access. The exact read-only SQL queries that would be run by an authorized operator with production database access are documented in Step 3 above.

### Repair Candidates Listed Separately
Since no discrepancies were identified (due to lack of data access), no repair candidates were identified. The repair candidate framework from Report F would be used if discrepancies were found with sufficient evidence.

### Explicit Recommendations for Next Step

Based on the findings of this Phase 2 investigation, the explicit recommendation for the next authorized step is:

**Gain authorized read-only access to the production MySQL database configured in the application's .env file, then execute the validation queries designed in Step 3 to begin actual forensic analysis.**

Specifically:
1. Verify database connectivity to the MySQL instance configured in .env
2. Execute the schema validation and record count queries
3. If data exists, proceed with sample data inspection and historic value reconstruction validation
4. Analyze update logs for changes to pricing tables
5. Correlate pricing changes with financial synchronization events
6. Identify passengers with discrepancies between stored and historic-anchored values
7. Classify discrepancies by recoverability and evidence strength
8. Populate the Booking-Level Forensic Ledger (Report C) with findings
9. Perform financial cross-checks (Report D) to validate findings
10. Develop repair candidates (Report F) only for cases with sufficient evidence and proper authorization

## Conclusion

### Confirmed Overwrites
**NONE** - No evidence was available to confirm that any historical package value overwrites actually occurred.

### Confirmed Absence of Overwrites Within Investigated Scope
**NONE** - Due to lack of access to actual database contents, it is not possible to confirm the absence of overwrites. The investigated scope (code and schema only) does not include the data where such overwrites would be evident.

### Unresolved Cases
**ALL POTENTIAL CASES** - Without access to the actual database containing passenger, booking, package, and update log records, all cases remain unresolved. It is impossible to determine:
- Whether any historical package value overwrites occurred
- Which specific bookings/passengers are affected
- The magnitude of any discrepancies
- The root causes of any discrepancies

### Cases That Could Not Be Investigated
**ALL CASES** - Due to the fundamental limitation of no database access, no cases could be investigated at the data level. The investigation was limited to:
- Code analysis (verified the mechanisms exist to prevent and detect overwrites)
- Schema analysis (confirmed the necessary tables exist)
- Environmental assessment (determined the system is not using the configured MySQL database)

### Final Assessment
The Umrah App architecture includes sophisticated mechanisms designed to prevent and detect historical package value overwrites through historic anchoring and update log replay. However, without access to the actual database contents, it is impossible to determine whether these mechanisms are functioning correctly in production or whether any historical overwrites have occurred.

The most critical finding from this Phase 2 investigation is that the system is not actually using the MySQL database configured in its .env file. Instead, it is using an SQLite in-memory database that appears to be missing essential tables (based on cache-related errors in the logs). This suggests the system is running in a testing or development environment rather than against production data.

**The explicit next step is to obtain authorized read-only access to the production MySQL database and begin the validation process outlined in Step 3 of this report.**