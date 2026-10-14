# Report D — Financial Cross-Check

## Overview
This report documents financial consistency checks that would be performed to validate the historical package value analysis. These checks help distinguish between genuine historical price overwrites and other legitimate financial adjustments.

## Financial Relationships in the System

Understanding the correct financial relationships is essential for accurate analysis:

### 1. Passenger-Level Relationships
```
passengers.package_value = 
    ticket_amount + 
    visa_amount + 
    service_charge_amount + 
    extra_charge
```

Where:
- **ticket_amount**: Calculated from ticket fares based on passenger type and package configuration
- **visa_amount**: Visa selling price (when service_required ≠ 'ticket_only')  
- **service_charge_amount**: Package service charge (when service_required ≠ 'ticket_only')
- **extra_charge**: Passenger-specific additional charges

### 2. Booking-Level Relationships
```
bookings.total_value = 
    SUM(all passengers.package_value) + 
    fingerprint_charge
```

Where:
- **Fingerprint charge**: Based on district and location (office vs home)

### 3. Invoice-Level Relationships
Upon invoice creation:
```
invoices.total_amount = bookings.total_value
invoices.balance = bookings.total_value  
invoices.paid_amount = 0
```

After payments and adjustments:
```
invoices.balance = invoices.total_amount - invoices.paid_amount - due_adjustments
```

Where **due_adjustments** are specific refund-related adjustments.

### 4. Profit Relationships
```
booking.profit = 
    SUM(effective passenger profits) + 
    fingerprint_profit - 
    discount_amount
```

Where effectiveness depends on visa/ticket issuance status.

## Cross-Check Validation Points

For each booking-passenger combination under investigation, the following financial relationships would be validated:

### A. Internal Consistency Checks
1. **Packet Component Sum**: Verify that stored package_value equals the sum of its documented components (ticket + visa + service_charge + extra_charge)
2. **Booking Total Sum**: Verify that stored total_value equals sum of all passenger package_values plus fingerprint charge
3. **Invoice Baseline**: For newly created bookings, verify invoice.total_amount equals booking.total_value at creation time

### B. Historical Consistency Checks
1. **Component Reconstruction**: Verify that historically reconstructed components sum to the historically reconstructed package value
2. **Timeline Consistency**: Ensure historical values correspond to appropriate time periods (pre-dating package/fare/visa updates)
3. **Mutation Detection**: Identify whether invoice total_amount changes correspond to documented financial events

### C. External Validation Checks
1. **Payment Correlation**: Verify that payment amounts align with invoice balances over time
2. **Discount Application**: Confirm discount_amount is correctly applied to calculate final due amounts
3. **Profit Consistency**: Validate that profit calculations align with revenue and cost documentation

## Specific Financial Discrepancies to Investigate

### 1. Package Value Mismatches
| Check | Expected | Potential Issue if Mismatch |
|-------|----------|-----------------------------|
| Stored package_value vs sum of components | Equal | Component values incorrectly calculated or stored |
| Stored package_value vs historic reconstruction | May differ | Historic price overwrite or legitimate update |
| Stored package_value vs current recalculation | May differ | Package price changed after storage |

### 2. Booking Total Mismatches  
| Check | Expected | Potential Issue if Mismatch |
|-------|----------|-----------------------------|
| Stored total_value vs SUM(passenger.package_value) + fingerprint | Equal | Passenger values incorrectly aggregated |
| Stored total_value vs invoice.total_amount (at creation) | Equal | Invoice not initialized correctly |

### 3. Invoice Balance Mismatches
| Check | Expected | Potential Issue if Mismatch |
|-------|----------|-----------------------------|
| Invoice balance vs total_amount - paid_amount - adjustments | Equal | Financial records corrupted |
| Historical invoice total vs booking total at time | May differ | Post-creation financial modification |

### 4. Payment Application Issues
| Check | Expected | Potential Issue if Mismatch |
|-------|----------|-----------------------------|
| Sum of payments <= invoice total_amount | Always | Over-payment or recording error |
| Payment dates vs invoice status changes | Logical sequence | Premature or delayed payment application |

## Distinguishing Legitimate vs Illegitimate Changes

Not all financial discrepancies indicate historical price overwrites. Legitimate reasons for value changes include:

### Legitimate Causes:
1. **Explicit Financial Corrections**: Manual adjustments documented in audit logs
2. **Error Corrections**: Fixing data entry mistakes with proper documentation
3. **Policy Changes**: Applying new pricing rules retroactively with authorization
4. **Refund Processing**: Legitimate refunds or adjustments to original charges
5. **Currency Corrections**: Fixing exchange rate applications

### Illegitimate Causes (Target of Investigation):
1. **Silent Overwrites**: Changing historical values without documentation or audit trail
2. **Unauthorized Modifications**: Changes made outside approved financial workflows
3. **Systematic Errors**: Bugs causing incorrect historical value calculations
4. **Audit Trail Suppression**: Modifications that avoid logging mechanisms

## Evidence Requirements for Financial Discrepancy Classification

To classify a financial discrepancy as a historical price overwrite incident, the following evidence would be needed:

### Required Evidence:
1. **Timing Correlation**: Discrepancy coincides with package/fare/visa price updates
2. **Lack of Documentation**: No corresponding financial adjustment records or audit entries
3. **Component Specificity**: Discrepancy aligns with specific updated components (ticket/visa/service charge)
4. **Pattern Recognition**: Multiple bookings show similar discrepancy patterns
5. **Absence of Legitimate Explanation**: No valid business reason for the change

### Evidence Against Classification:
1. **Documented Adjustments**: Corresponding entries in financial adjustment logs or audit trails
2. **Timing Mismatch**: Discrepancy occurs long after relevant price updates
3. **Systematic Pattern**: Affects all bookings uniformly suggesting system-wide correction
4. **Authorized Changes**: Evidence of approval for financial reconciliation process
5. **Invoice-Level Only**: Discrepancy only appears at invoice level, not component level

## Methodology for Financial Cross-Check Analysis

### Step 1: Establish Baseline Financial Relationships
Verify core financial equations hold true for unaffected bookings as controls.

### Step 2: Identify Discrepancy Candidates
Find bookings where:
- Stored values don't equal component sums
- Historical reconstructions differ from stored values
- Invoice totals don't match booking totals at creation time

### Step 3: Trace Financial Mutation Path
For each discrepancy candidate:
1. Identify when the discrepancy first appeared (using available timestamps)
2. Correlate with package/fare/visa update timestamps
3. Check for intervening financial synchronization events
4. Look for documentation explaining the change

### Step 4: Validate Through Independent Channels
Cross-reference findings with:
- Payment gateway records (if available)
- Accounting system entries
- Manual financial adjustment logs
- Customer communication records

### Step 5: Classify Root Cause
Determine whether discrepancy represents:
- Historical price overwrite (investigation target)
- Legitimate financial correction
- System error or bug
- Insufficient evidence to determine

## Limitations of Financial Cross-Check Alone

Financial cross-checking has important limitations for this investigation:

1. **Cannot Prove Causation**: Shows discrepancies exist but not why
2. **Legitimate Changes Mimic Issues**: Proper corrections look identical to improper ones
3. **Component Attribution Difficult**: Invoice-level disputes don't reveal which component changed
4. **Timing Ambiguity**: When records show only when, not why or how
5. **Requires Corroboration**: Must be combined with component-level evidence

## Conclusion
Financial cross-checking provides essential context for validating historical package value analysis but cannot standalone prove historical price overwrites occurred. It serves best as:
1. A filtering mechanism to identify suspicious cases
2. A validation tool to confirm component-level findings
3. A way to rule out alternative explanations for observed discrepancies
4. A method to understand the financial impact of any confirmed historical value changes

When combined with the Booking-Level Forensic Ledger (Report C) and evidence analysis (Report E), financial cross-checks significantly strengthen the investigative conclusions.