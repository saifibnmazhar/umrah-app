# Report F — Repair Candidate Plan (NO EXECUTION)

## Overview
This report outlines a systematic approach for identifying and planning correction of historical package value discrepancies, WITHOUT executing any repairs. All proposed actions require explicit authorization and separate implementation phases.

## Repair Philosophy

Any correction plan must adhere to these core principles:

### 1. Do No Further Harm
- Never modify data without comprehensive backup and rollback plan
- Preserve all existing evidence of what occurred
- Ensure repairs don't destroy future investigative capability

### 2. Authorization-First Approach
- No repair action proceeds without explicit, documented authorization
- Authorization must specify exact records, values, and methods
- Consider regulatory, audit, and stakeholder notification requirements

### 3. Evidence-Based Repairs
- Repairs must be grounded in verifiable historical evidence
- When evidence is insufficient, recommend investigation not repair
- Document the evidentiary basis for each proposed change

### 4. Auditable Implementation
- All repair actions must leave clear audit trails
- Repairs should be implementable through standard financial correction channels
- Prefer incremental, verifiable steps over bulk operations

## Repair Candidate Identification Process

### Step 1: Definitive Classification Threshold
Only consider for repair those cases meeting ALL criteria:
- **Confirmed Historical Value**: Direct evidence from binary/logs showing actual historical value
- **Documented Discrepancy**: Clear, quantified difference between stored and historical values
- **Causal Link**: Evidence the discrepancy resulted from silent historical price overwrite
- **Material Impact**: Difference exceeds defined threshold (e.g., >$0.01 currency unit)
- **Actionable Context**: Record is modifiable within business rules and system constraints

### Step 2: Repair Classification System
Candidates are classified by repair complexity and risk:

**Class A - Straightforward Component Correction**
- Single component (ticket/visa/service charge/extra charge) has confirmed historical value
- Other components either match or have insufficient evidence
- Repair involves updating one stored field to correct value
- Lower risk as affects only specific financial element

**Class B - Multi-Component Correction**  
- Multiple components have confirmed historical values
- Requires coordinated update of several stored fields
- Higher complexity but still component-focused
- Risk of inconsistent state if not executed atomically

**Class C - Full Record Reconstruction**
- Complete historical value confirmed through multiple evidence lines
- Requires setting package_value to correct historic total
- May require corresponding booking.total_value and invoice.total_amount adjustments
- Highest impact but most complete correction

**Class D - Investigative Required**
- Insufficient evidence to confirm historical value
- Discrepancy exists but root cause unclear
- NOT a repair candidate - requires further investigation
- Repair would be speculative and potentially incorrect

### Step 3: Impact Analysis Requirements
For each candidate, document:

**Direct Financial Impact:**
- Change to passenger package_value
- Change to booking total_value  
- Change to invoice total_amount (if applicable)
- Change to applied discounts (if percentage-based)
- Change to due/paid balance calculations

**Secondary Impact Analysis:**
- Effect on profit calculations (if visa/ticket effective)
- Impact on financial reporting periods
- Potential tax implications
- Effect on customer statements or reconciliations
- Influence on audit or compliance reporting

**Systemic Impact Assessment:**
- Whether correction creates inconsistencies with related records
- Impact on historical trend analysis
- Effect on reconciliation with external systems (payment gateways, accounting)
- Potential to mask or reveal other issues

## Safe Repair Methodology Framework

### Phase 0: Authorization and Preparation
```
[REQUIRED] Explicit written authorization for specific repair set
[REQUIRED] Complete backup of affected tables/records
[REQUIRED] Rollback procedure tested and documented
[REQUIRED] Maintenance window scheduled and communicated
[REQUIRED] Monitoring and verification procedures established
```

### Phase 1: Isolation and Verification
```
[REQUIRED] Repair executed in staging environment first
[REQUIRED] Results validated against evidence requirements
[REQUIRED] No impact on live system during verification
[REQUIRED] Staging repair mirrors planned production approach exactly
```

### Phase 2: Controlled Implementation
```
[REQUIRED] Repair executed in smallest possible batches
[REQUIRED] Each batch independently verified before proceeding
[REQUIRED] Real-time monitoring for unintended side effects
[REQUIRED] Ability to halt and rollback at any batch boundary
[REQUIRED] Detailed logging of each record modified
```

### Phase 3: Verification and Validation
```
[REQUIRED] Post-repair financial cross-checks performed
[REQUIRED] Component-level validation against evidence sources
[REQUIRED] Confirmation audit trail entries created correctly
[REQUIRED] Comparison with authorization specifications
[REQUIRED] Stakeholder sign-off obtained
```

### Phase 4: Documentation and Reporting
```
[REQUIRED] Complete repair log maintained (what, when, who, why)
[REQUIRED] Evidence package assembled and archived
[REQUIRED] Lessons learned document created
[REQUIRED] Recommendations for prevention developed
```

## Repair Candidate Template

For each authorized repair candidate, the following documentation would be required:

```
Repair Candidate ID: [UUID or sequential number]
Authorization Reference: [Document/ticket number]
Related Investigation Case: [Link to Report C entry if applicable]

## Identification
- Booking ID: [XXX]
- Passenger ID: [XXX] 
- Package ID: [XXX]
- Detected Discrepancy: [XXX.XX] currency units
- Classification: [Class A/B/C]

## Evidentiary Basis for Historical Value
**Ticket Component:**
- Historical Value: [XXX.XX]
- Evidence Source: [Binary Logs/Update Logs/Transaction Snapshot]
- Specific Evidence: [Log ID, timestamp, old/new values]
- Confidence: [CONFIRMED]

**Visa Component:**  
- Historical Value: [XXX.XX]
- Evidence Source: [Binary Logs/Update Logs/Transaction Snapshot]
- Specific Evidence: [Log ID, timestamp, old/new values]
- Confidence: [CONFIRMED/INFERRED]

**Service Charge Component:**
- Historical Value: [XXX.XX]  
- Evidence Source: [Binary Logs/Update Logs/Transaction Snapshot]
- Specific Evidence: [Log ID, timestamp, old/new values]
- Confidence: [CONFIRMED]

**Extra Charge Component:**
- Historical Value: [XXX.XX]
- Evidence Source: [Passenger Field Analysis/Change Log]
- Specific Evidence: [Field value at relevant time, or logs showing no change]
- Confidence: [CONFIRMED/INFERRED]

**Historical Package Value:**
- Calculated Total: [XXX.XX]
- Validation Method: [Component sum / direct calculation]
- Cross-Check: [Alternative calculation method if available]

## Current State
- Stored Package Value: [XXX.XX]
- Stored Booking Total Value: [XXX.XX]
- Stored Invoice Total Amount: [XXX.XX] (if applicable)
- Stored Invoice Balance: [XXX.XX] (if applicable)

## Proposed Repair Values
**To Be Set:**
- Passenger.package_value: [XXX.XX]
- Booking.total_value: [XXX.XX] (if requiring update)
- Invoice.total_amount: [XXX.XX] (if requiring update)
- Invoice.balance: [XXX.XX] (if requiring update, recalculated)

**Not To Be Modified:**
- Passenger.extra_charge: [Current value to be preserved]
- Passenger.[other protected fields]: [Current values]
- Booking.[non-financial fields]: [Current values to preserve]
- Invoice.[non-financial fields]: [Current values to preserve]

## Impact Analysis
**Direct Financial Effects:**
- Package Value Delta: [XXX.XX]
- Booking Total Value Delta: [XXX.XX]  
- Invoice Total Amount Delta: [XXX.XX] (if applicable)
- Discount Amount Change: [XXX.XX] (if percentage-based)
- Balance Due Change: [XXX.XX] (if applicable)

**Secondary Effects:**
- Profit Impact: [XXX.XX] (if visa/ticket effective)
- Reporting Period Effect: [Specify affected periods]
- Tax Implication: [None/minor/significant - explain]
- Customer Statement Effect: [Will/will not show on customer facing documents]

**Risk Assessment:**
- Correction Risk: [Low/Medium/High] - explain
- Rollback Complexity: [Low/Medium/High] - explain
- Verification Difficulty: [Easy/Medium/Hard] - explain
- Concurrent Modification Risk: [None/Low/Medium/High] - explain

## Implementation Specification
**Atomicity Requirements:**
- Single transaction required: [Yes/No]
- Related records that must update together: [List if any]
- Intermediate states to avoid: [Describe problematic states]

**Field-Level Specifications:**
- Exact SQL UPDATE statements (for review only):
  ```sql
  -- PASSENGER TABLE - DO NOT EXECUTE WITHOUT AUTHORIZATION
  -- UPDATE passengers SET package_value = [XXX.XX] WHERE id = [XXX];
  
  -- BOOKING TABLE - DO NOT EXECUTE WITHOUT AUTHORIZATION  
  -- UPDATE bookings SET total_value = [XXX.XX] WHERE id = [XXX];
  
  -- INVOICE TABLE - DO NOT EXECUTE WITHOUT AUTHORIZATION
  -- UPDATE invoices SET total_amount = [XXX.XX], balance = [XXX.XX] WHERE booking_id = [XXX];
  ```

**Validation Checkpoints:**
- [ ] Pre-repair backup verified restorable
- [ ] Staging environment repair successful
- [ ] Batch size limits respected ([N] records max)
- [ ] Each batch independently verified
- [ ] Rollback procedure tested and documented
- [ ] Monitoring alerts configured and tested
- [ ] Post-repair financial validation passed
- [ ] Component-level evidence match confirmed
- [ ] Audit trail entries verified correct
- [ ] Authorization limits not exceeded

## Authorization Requirements
This repair candidate requires:
- [ ] Explicit written authorization for specific record modifications
- [ ] Separate authorization for each table to be modified (passenger, booking, invoice)
- [ ] Authorization specifying exact old and new values
- [ ] Authorization acknowledging understanding of rollback procedures
- [ ] Authorization from appropriate authority level (specify: [Tech Lead/DBA/Audit Committee/etc.])
- [ ] Authorization valid for specific time window: [start] to [end]
- [ ] Authorization requiring post-repair verification sign-off

## Alternatives Considered
1. **No Action**: Accept current state if impact immaterial or evidence insufficient
2. **Partial Repair**: Correct only highest-confidence components
3. **Adjustment Approach**: Record discrepancy as adjustment rather than correcting history
4. **Supplemental Documentation**: Add explanatory notes without changing data
5. **Future-Proofing**: Focus on preventing recurrence rather than correcting past

## Prevention Recommendations
To reduce likelihood of similar issues:
[ ] Enable/enhance binary logging for critical financial tables
[ ] Extend update log retention periods  
[ ] Add checksums or validation checks to financial synchronization
[ ] Implement alerts for unexpected financial value changes
[ ] Create read-only replicas of critical tables for comparison
[ ] Add automated reconciliation reports to regular financial close
[ ] Enhance documentation of financial update procedures
[ ] Implement approval workflows for mass financial updates
```

## Summary Statistics Template

After investigation, summary statistics would be provided:

```
Total Bookings Examined: [XXX]
Total Passengers Examined: [XXX]

Repair Candidates Identified:
- Class A (Straightforward): [XXX]
- Class B (Multi-Component): [XXX] 
- Class C (Full Reconstruction): [XXX]
- Class D (Investigation Required): [XXX]

By Evidence Level:
- Fully Confirmed Historical Values: [XXX]
- Partially Confirmed (Some Components): [XXX]
- Inferred Values Only: [XXX]
- No Usable Evidence: [XXX]

Financial Impact Range:
- Minimum Individual Repair: [XXX.XX]
- Maximum Individual Repair: [XXX.XX]
- Estimated Total Repair Amount: [XXX.XX]
- Median Repair Amount: [XXX.XX]

Authorization Status:
- Fully Authorized for Repair: [XXX]
- Requires Additional Authorization: [XXX]
- Awaiting Evidence Completion: [XXX]
- Deemed Inappropriate for Repair: [XXX]
```

## Critical Warnings and Constraints

### Absolute Prohibitions
Under NO circumstances should the following be attempted without separate, explicit authorization:

1. **Bulk Undifferentiated Updates**: "Fix all passengers for package X"
2. **Override of Protected Fields**: Modifying created_at, audit trails, or system fields
3. **Bypass of Validation**: Disabling model events, observers, or validation rules
4. **Suppression of Audit Trail**: Preventing creation of update log entries
5. **Production Testing**: Trying repair approaches on live data first
6. **Ignoring Relationships**: Modifying records without considering related table impacts
7. **Violation of Business Rules**: Creating states that violate model validation

### Required Safeguards
Any authorized repair MUST include:

1. **Pre-Deployment Validation**: Staging environment success required
2. **Incremental Execution**: Small batches with intermediate verification
3. **Real-Time Monitoring**: Active observation during execution
4. **Rollback Capability**: Tested ability to return to exact pre-repair state
5. **Post-Deployment Verification**: Comprehensive validation after completion
6. **Audit Trail Preservation**: Ensuring repair actions are properly logged
7. **Authorization Adherence**: Strict compliance with what was authorized

## Conclusion

This repair candidate plan provides a structured, cautious approach to addressing historical package value discrepancies when sufficient evidence exists. The emphasis throughout is on:
1. **Evidence Requirements**: Never repair without confirmation of correct historical value
2. **Authorization Discipline**: Never act without explicit permission for specific actions
3. **Methodological Care**: Never compromise safety for expediency
4. **Transparency Imperative**: Never obscure what was done or why it was necessary

The most important output of any forensic investigation is not the repair itself, but the understanding of how the discrepancy occurred and how to prevent recurrence. Repair candidates should only be pursued when:
1. The evidence clearly supports a specific historical value
2. The discrepancy represents a genuine error requiring correction  
3. The repair approach is well-understood and safely controllable
4. Proper authorization has been obtained for the specific, limited action proposed

When in doubt, the correct approach is to recommend further investigation rather than proceed with repair. The Umrah App's historic value reconstruction capabilities mean that many discrepancies may resolve themselves once the underlying evidence trails are restored or understood, making active repair less frequently necessary than it might initially appear.