# Report E — Evidence and Timeline

## Overview
This report documents the evidence gathering approach, timeline reconstruction methodology, and sources of information that would be consulted during a forensic investigation of historical package values in the Umrah App.

## Evidence Sources Consulted

In a complete forensic investigation, the following evidence sources would be examined (all read-only):

### 1. Primary Database Evidence (Read-Only Queries)
- **Package Records**: Current and historical values through update logs
- **Ticket Fare Records**: Current and historical values through update logs  
- **Visa Selling Price Records**: Current and historical values through update logs
- **Booking Records**: Creation/update timestamps, package associations, financial values
- **Passenger Records**: Creation/update timestamps, service requirements, package values
- **Invoice Records**: Creation timestamps, total amounts, payment applications
- **Update Log Tables**: 
  - `package_update_logs`
  - `ticket_fare_update_logs` 
  - `visa_update_logs`
  - `passenger_update_logs`
  - `booking_update_logs`
  - `invoice_update_logs`

### 2. Application and System Logs
- **Laravel Application Logs**: Financial synchronization events, recalculation triggers
- **Database Logs**: Query logs (if enabled), connection information
- **Web Server Logs**: API requests triggering financial updates
- **Artisan Command Logs**: Scheduled financial synchronization runs
- **Queue Worker Logs**: Background job processing records

### 3. External System Evidence (if accessible)
- **Payment Gateway Records**: Transaction timing and amounts
- **Accounting System Entries**: Financial posting timestamps
- **Backup Archives**: Historical database snapshots (if available and permitted)
- **Change Management Records**: Documentation of approved price updates

### 4. Code Artifacts
- **Source Code Version History**: Git commits showing when calculation logic changed
- **Configuration Files**: Price update procedures and validation rules
- **Database Schema Migrations**: Evidence of when tracking capabilities were added

## Timeline Reconstruction Methodology

### Phase 1: Establish Baseline Period
Determine the suspected incident timeframe through:
- User reports of discrepancies
- Audit findings or anomalies
- System change records
- Customer complaint timelines

### Phase 2: Identify Relevant Update Events
For each entity type, timestamp all modifications:

**Package Modifications:**
```sql
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
WHERE pul.created_at BETWEEN [start_date] AND [end_date]
ORDER BY pul.created_at;
```

**Ticket Fare Modifications:**
```sql
SELECT 
    tful.id,
    tful.ticket_fare_id,
    tful.created_at as update_time,
    tful.action,
    JSON_EXTRACT(tful.old_values, '$.selling_fare') as old_selling_fare,
    JSON_EXTRACT(tful.new_values, '$.selling_fare') as new_selling_fare,
    JSON_EXTRACT(tful.old_values, '$.offer_price') as old_offer_price,
    JSON_EXTRACT(tful.new_values, '$.offer_price') as new_offer_price
FROM ticket_fare_update_logs tful
WHERE tful.created_at BETWEEN [start_date] AND [end_date]
ORDER BY tful.created_at;
```

**Visa Selling Price Modifications:**
```sql
SELECT 
    vul.id,
    vul.visa_selling_price_id,
    vul.created_at as update_time,
    vul.action,
    JSON_EXTRACT(vul.old_values, '$.selling_price') as old_selling_price,
    JSON_EXTRACT(vul.new_values, '$.selling_price') as new_selling_price
FROM visa_update_logs vul
WHERE vul.created_at BETWEEN [start_date] AND [end_date]
ORDER BY vul.created_at;
```

### Phase 3: Map Update Events to Affected Bookings
For each update event, determine which bookings could be affected:

**Direct Impact Analysis:**
- Package updates affect all bookings using that package (regardless of timing)
- Ticket fare updates affect bookings using that fare
- Visa selling price updates affect bookings linked to that price

**Temporal Filtering (More Precise):**
Only bookings where:
- Booking created BEFORE the update (potentially affected by silent overwrite)
- Passenger created BEFORE the update (for historic anchoring considerations)
- Financial synchronization occurred AFTER the update (opportunity for overwrite to propagate)

### Phase 4: Correlate with Financial Synchronization Events
Identify when financial recalculation likely occurred:

**Passenger-Level Triggers:**
```sql
-- Passenger updates that would trigger financial recalculation
SELECT 
    plu.id,
    plu.passenger_id,
    plu.created_at as update_time,
    plu.action,
    JSON_EXTRACT(plu.old_values, '$.package_value') as old_package_value,
    JSON_EXTRACT(plu.new_values, '$.package_value') as new_package_value
FROM passenger_update_logs plu
WHERE plu.action IN ('updated', 'recalculated')
AND plu.created_at BETWEEN [start_date] AND [end_date]
ORDER BY plu.created_at;
```

**Booking-Level Triggers:**
```sql
-- Booking updates that would trigger financial recalculation  
SELECT 
    bul.id,
    bul.booking_id,
    bul.created_at as update_time,
    bul.action,
    JSON_EXTRACT(bul.old_values, '$.total_value') as old_total_value,
    JSON_EXTRACT(bul.new_values, '$.total_value') as new_total_value
FROM booking_update_logs bul
WHERE bul.action IN ('updated', 'financial_sync')
AND bul.created_at BETWEEN [start_date] AND [end_date]
ORDER BY bul.created_at;
```

**Artifact Evidence:**
- Check for logs of Artisan commands like `app:recalculate-profits` or `app:sync-financials`
- Review scheduled task executions (cron jobs, queue workers)
- Examine HTTP endpoints that might trigger bulk financial updates

### Phase 5: Reconstruct Historical Values at Key Moments
For potentially affected bookings, calculate values at significant points:

**At Booking Creation:**
- What package value should have been set initially?

**At Passenger Creation:**  
- What anchored historic value should apply?

**After Each Relevant Update:**
- How should the value have changed if using proper historic anchoring?

**At Present Time:**
- What is the current stored value?
- What does current pricing produce?
- What does proper historic anchoring produce?

## Evidence Gaps and Limitations

### Common Evidence Gaps:
1. **Insufficient Log Retention**: Update logs purged before investigation period
2. **Missing Binary Logging**: MySQL binlog not enabled or expired
3. **Selective Logging**: Certain updates bypassing logging mechanisms
4. **Incomplete Application Logging**: Financial sync events not recorded at sufficient detail
5. **Aggregated Updates**: Bulk operations losing individual record specificity

### Evidence Quality Issues:
1. **Lossy Snapshots**: Transaction snapshots (issued_tickets, etc.) may have been overwritten
2. **Circumstantial Only**: Inferential evidence without direct proof
3. **Conflicting Timelines**: Different systems showing inconsistent timestamps
4. **Technical Artifacts**: Database reproduction inconsistencies mimicking actual changes

### Investigative Limitations:
1. **No Modification Permission**: Cannot insert test records to verify logging
2. **Performance Constraints**: Unable to run expensive scans on production
3. **Access Restrictions**: Some logs may be inaccessible due to security policies
4. **Interpretation Ambiguity**: Log entries may not clearly indicate business intent

## Assumptions Made During Analysis

Any forensic investigation must document its assumptions:

### Technical Assumptions:
1. **Logging Fidelity**: Update logs accurately record all relevant changes
2. **Timestamp Accuracy**: System clocks are synchronized and correct
3. **Log Completeness**: No gaps in logging during investigation period
4. **Code-Log Consistency**: Application behavior matches documented logic

### Business Logic Assumptions:
1. **Static Rules**: Pricing calculation rules didn't change during investigation period
2. **Consistent Application**: All instances of the application behave identically
3. **Canonical Sources**: Package/TicketFare/VisaSellingPrice tables are truth sources
4. **Historic Anchoring Correct**: PackageEras and LogReplay implementations are accurate

### Evidence Interpretation Assumptions:
1. **Silent Failure Absence**: Lack of evidence doesn't indicate evidence of absence
2. **Correlation Implies Causation**: Temporal proximity suggests relationship
3. **Pattern Significance**: Repeated patterns indicate systematic issues
4. **Conservatism in Doubt**: When uncertain, classify as unknown rather than inferred

## Evidence Collection Checklist

For a thorough investigation, verify the following were examined:

### [ ] Database State
- [ ] Package current values documented
- [ ] Ticket fare current values documented
- [ ] Visa selling price current values documented
- [ ] Update log tables present and populated
- [ ] Binary logging status checked
- [ ] Log retention policies documented

### [ ] Package Update Evidence
- [ ] All package_update_logs entries reviewed for period
- [ ] Service charge changes identified and quantified
- [ ] Visa selling price ID changes tracked
- [ ] Correlation with affected packages established

### [ ] Ticket Fare Update Evidence  
- [ ] All ticket_fare_update_logs entries reviewed for period
- [ ] Selling/offer price changes identified
- [ ] Percentage change analysis completed
- [ ] Impact on specific routes/fares assessed

### [ ] Visa Selling Price Evidence
- [ ] All visa_update_logs entries reviewed for period
- [ ] Selling price changes documented
- [ ] Linked package updates traced
- [ ] Visa submission price preservation checked

### [ ] Financial Synchronization Evidence
- [ ] Passenger update logs checked for recalculation triggers
- [ ] Booking update logs checked for financial sync events
- [ ] Invoice update logs reviewed for manual adjustments
- [ ] Artisan command execution traces examined
- [ ] HTTP endpoint access logs reviewed (if available)

### [ ] Corroborating Evidence
- [ ] Issued ticket snapshots reviewed for price preservation
- [ ] Visa submission links checked for price fidelity
- [ ] Passenger service charge fields examined for historical preservation
- [ ] Sibling booking analysis performed for pattern detection
- [ ] Payment timeline analysis completed

### [ ] Documentation Review
- [ ] System change records examined for price update approvals
- [ ] Customer complaint timelines correlated with evidence
- [ ] Internal audit reports reviewed for prior findings
- [ ] Procedure documents checked for financial update policies

## Conclusion

The evidence and timeline analysis forms the critical bridge between suspected incidents and provable conclusions. By systematically mapping:
1. When price updates occurred (via update logs)
2. When bookings were created and passengers added
3. When financial synchronization could propagate changes
4. What the correct historical values should have been (via replay mechanisms)
5. What values are actually stored
6. What independent evidence exists to support historical values

This approach enables transformation of suspicion into evidence-based findings. The key insight from the Umrah App architecture is that while the system contains sophisticated historic value reconstruction mechanisms, their effectiveness depends entirely on the completeness and accuracy of the underlying evidence trails. When those trails are intact, the system can self-correct; when they are broken, external investigation becomes necessary.