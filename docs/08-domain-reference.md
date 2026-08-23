# Domain Reference

> Part of the [Development Handbook](README.md) · **Mode:** Reference
>
> This is the definitive reference for the project's domain entities, roles,
> enums, services, and UI components. See [Architecture](02-architecture.md)
> for the full layered architecture and database schema.

---

## Role System

Access control is enforced via `CheckRole` middleware
(`app/Http/Middleware/CheckRole.php`). Routes are protected by passing allowed
roles as comma-separated values:

```php
Route::resource('bookings', BookingController::class)
    ->middleware('role:Super Admin,Co Admin');
```

### All 12 Roles

| #  | Role              | Scope                                                      |
|----|-------------------|------------------------------------------------------------|
| 1  | Super Admin       | Full access — all modules, all branches                    |
| 2  | Co Admin          | All modules, all branches                                   |
| 3  | Auditor           | Read-only access to reports, invoices, payments            |
| 4  | Ticket Admin      | Manage fares, routes, airlines, ticket agents                 |
| 5  | Visa Admin        | Manage visa agents, visa submissions, visa rates            |
| 6  | Ticket Staff      | Issue tickets, manage ticket agents' customers              |
| 7  | Visa Staff        | Process visa submissions for assigned branches              |
| 8  | Fingerprint Admin | Configure fingerprint charges, manage fingerprint staff       |
| 9  | Fingerprint Staff | Process passenger fingerprints                              |
| 10 | Branch Manager    | Manage bookings and finances for own branch                 |
| 11 | Branch Staff      | Create bookings, issue documents for own branch             |
| 12 | Delivery Staff    | Receive passport/document handoffs                            |

Roles are seeded by `RoleSeeder`. Users get roles via the `user_roles` pivot table
(managed by the `UserRole` model). The `HandlesBranchAccess` trait (in
`app/Concerns/`) provides programmatic role checks with in-request caching.

### Checking Roles in Code

```php
// In middleware: 'role:Super Admin,Co Admin'
// In code:
$user = auth()->user();
if ($user->hasRole('Super Admin')) { ... }

// Check multiple roles (cached in-request):
$user->roles()->whereIn('name', ['Super Admin', 'Co Admin'])->exists();
$this->hasAnyRole(['Super Admin', 'Co Admin']);  // via HandlesBranchAccess trait
```

### Permission Matrix (HandlesBranchAccess trait)

| Method                      | Allowed Roles                                    |
|-----------------------------|--------------------------------------------------|
| `isAdmin()`                 | Super Admin, Co Admin                            |
| `isBranchScoped()`          | Has branch_id AND not admin                      |
| `isGlobalNonAdmin()`        | No branch_id AND not admin                       |
| `canEditVisa()`             | Super Admin, Co Admin, Visa Admin                |
| `canEditFingerprint()`      | Super Admin, Co Admin, Fingerprint Admin, Delivery Staff |
| `canEditTickets()`          | Super Admin, Co Admin, Ticket Admin              |
| `canFilterByAgent()`        | Super Admin, Co Admin, Visa Admin, Ticket Admin  |
| `canDeleteBooking()`        | Super Admin                                      |
| `canViewSummaryCards()`     | Super Admin, Co Admin, Auditor, Ticket Admin, Visa Admin, Branch Manager, Fingerprint Admin |
| `canViewProfitCards()`      | Super Admin, Co Admin, Auditor                   |
| `canViewTicketRequests()`   | Super Admin, Ticket Admin                        |
| `ensureBranchAccess()`      | Branch-scoped users can only access their branch's bookings |
| `ensureEditWindow()`        | Admin → always; others → 12h from booking creation |
| `ensureCancellationAccess()`| Blocks Fingerprint Admins without a branch; others → own branch only |

---

## Key Models (56 total)

All models are in `app/Models/`. Tables use plural snake_case. Some models
override `$table` — note the following:

| Model              | Table name (if overridden)  |
|--------------------|------------------------------|
| `AirlineClass`     | `airline_classes`            |
| `AirlineCity`      | `airline_cities`             |
| `Route`            | `routes`                     |
| `TravelClass`      | `classes`                    |
| `TransactionType`  | `transaction_types`          |
| `UserRole`         | `user_roles`                 |
| `RescheduledFingerprint` | `rescheduled_fingerprints` |

### Core Entities

| Model | Table | Key Fields | Relationships |
|-------|-------|-----------|---------------|
| **User** | users | name, email, password, branch_id, is_active | branch (belongsTo), roles (belongsToMany via user_roles), userRoles (hasMany) |
| **Branch** | branches | name, address, contacts, location (KSA/BD), fingerprint_operation, branch_code | users (hasMany), bookings (hasMany via booking_branch_id), fingerprintCharges (hasMany via district) |
| **Booking** | bookings | customer_id, package_id, booking_branch_id, fingerprint_branch_id, invoice_id (string), date_gap_id, fingerprint_location, pax_qty, discount_type/value/amount, total_value, is_cancelled, currency_rate_id | customer (belongsTo), package (belongsTo), bookingBranch & fingerprintBranch (belongsTo Branch ×2), fingerprintCharge (belongsTo), district (belongsTo), dateGap (belongsTo), currencyRate (belongsTo), passengers (hasMany), invoice (hasOne), fingerprint (hasOne), payments (hasMany), documents (morphMany), cancelledBooking (hasOne), invoice (hasOne) |
| **Passenger** | passengers | booking_id, passenger_status_id, first_name, last_name, passport_no, mobile_no, date_of_birth, gender, passenger_type, passport_expiry, stay_duration, service_required, flight_date_from/to, actual_flight_date, ticket_status, address, ticket_fare_id, ticket_fare_inbound_id, ticket_fare_outbound_id, package_value, is_ticket_held, ticket_held_by/at, ticket_remarks, refund_payable | booking (belongsTo), status (belongsTo PassengerStatus), ticketFare/ticketFareInbound/ticketFareOutbound (belongsTo ×3), visaSubmission (hasOne latestOfMany), fingerprintDetail (hasOne), latestIssuedTicket (hasOne), issuedTickets/allIssuedTickets (hasMany), refundedTickets (hasManyThrough), documents (morphMany), updateLogs (hasMany) |
| **Customer** | customers | name, iqama_type, passport_no, iqama_no, mobile_no, ref_iqama_no, ref_mobile_no, ref_iqama_doc, address | bookings (hasMany), documents (morphMany) |
| **Package** | packages | package_name, ticket_fare_id, visa_selling_price_id, regular_price, offer_price, service_charge, is_active, ticket_fare_inbound_id, ticket_fare_outbound_id, is_double_ticket | ticketFare (belongsTo), visaSellingPrice (belongsTo), ticketFareInbound/Outbound (belongsTo ×2), bookings (hasMany) |

### Financial Entities

| Model | Table | Key Fields | Notes |
|-------|-------|-----------|-------|
| **Invoice** | invoices | booking_id, branch_id, user_id, total_amount, paid_amount, balance, status (InvoiceStatus), notes, audit_reason | One per booking (via InvoiceService::createForBooking). `audit_reason` is a transient property set before save. |
| **Payment** | payments | invoice_id, booking_id, branch_id, user_id, currency_rate_id, bank_id, sender_bank_id, other_sender_bank, receiver_bank, ticket_agent_id, visa_agent_id, commission_agent_id, payment_date, payment_method (cash/bank), transaction_id, amount, bdt_amount, notes, remarks, payment_referral, cancelled_booking_id, passenger_id, refunded_ticket_id, re_issued_ticket_id | Each Payment has exactly 1 Voucher (hasOne voucher via latestOfMany). |
| **Voucher** | vouchers | voucher_id (unique, VCH-YYYYMMDD-XXXX), invoice_id, booking_id, payment_id, branch_id, user_id, currency_rate_id, bank_id, ticket_agent_id, visa_agent_id, commission_agent_id, transaction_type_id, payment_date, payment_method, transaction_id, amount, bdt_amount, notes, cancelled_booking_id | 1:1 with Payment. `voucher_id` format: `VCH-YYYYMMDD-XXXX` (4-digit zero-padded sequence per day). |
| **CurrencyRate** | currency_rates | user_id, rate (decimal 10,4) | Resolved via three-tier: explicit relation → effective on date → first/oldest. In-request cache keyed by date. |
| **Bank** | banks | name, description, currency, location (KSA/BD), branch_id | Sender/receiver in payments |
| **TransactionType** | transaction_types | name (unique), type (debit/credit) | 9 seeded types see financial flow section |

### Reference Data

| Model | Table | Purpose |
|-------|-------|---------|
| **Package** | packages | Umrah packages (fares, pricing) |
| **Route** | routes | Flight routes (origin, destination, direction, multi-city, transits) |
| **TicketFare** | ticket_fares | Fare rates per route/class (net, selling, offer) |
| **Airline** | airlines | Airlines (name, code) |
| **TravelClass** | classes | Travel classes (Economy, Business — model table: classes) |
| **AirlineClass** | airline_classes | Pivot: airline × class |
| **AirlineCity** | airline_cities | Pivot: airline × city |
| **CityCode** | city_codes | Airport/city codes |
| **RouteMultiSegment** | route_multi_segments | Multi-city route segments |
| **RouteTransit** | route_transits | Transit cities with time |
| **VisaAgent** | visa_agents | Visa processing agents |
| **TicketAgent** | ticket_agents | Ticket-selling agents |
| **CommissionAgent** | commission_agents | Commission agents (under visa agents) |
| **VisaAgentCost** | visa_agent_costs | Per-agent visa costs |
| **VisaSellingPrice** | visa_selling_prices | Selling prices (is_locked appended) |
| **District** | districts | Geographic districts (name, division) |
| **FingerprintCharge** | fingerprint_charges | Per-district fingerprint cost |
| **FlightDateGap** | flight_date_gaps | Date gap config (7/10/14/30 days) |
| **BookingCondition** | booking_conditions | Terms/conditions for booking forms |
| **PassengerStatus** | passenger_statuses | Status labels (Hold, Cancel, etc.) |
| **StayDurationLimit** | stay_duration_limits | min/max stay days (default 1-85) |

### Ticketing Entities

| Model | Table | Key Fields | Notes |
|-------|-------|-----------|-------|
| **IssuedTicket** | issued_tickets | passenger_id, booking_id, user_id, ticket_agent_id, ticket_fare_id, group_ticket_id, ticket_number, pnr, issued_date, inbound/outbound_date, selling_fare, net_fare, offer_price, is_refundable, is_exchangeable, baggage_inbound/outbound, outbound_pending, issue_type (regular/additional/pending_outbound), status (pending/issued/re-issued/refunded) | Soft deletes. Latest re-issue via latestReIssuedTicket. |
| **ReIssuedTicket** | re_issued_tickets | user_id, ticket_agent_id, ticket_fare_id, group_ticket_id, issued_ticket_id, ticket_number, pnr, re_issue_date, dates, fares, re_issue_charge, fare_difference, other_costs, service_charge, payment_by (customer/airline/employee), payment_option (customer_payment/refund_adjustment), refund_adjustment_amount, total_customer_payment, reason_id, remarks | Soft deletes. Belongs to IssuedTicket. |
| **RefundedTicket** | refunded_tickets | user_id, ticket_agent_id, ticket_fare_id, group_ticket_id, issued_ticket_id, ticket_number, pnr, refund_date, dates, fares, iata_refunded_amount, refund_to_customer, service_charge, payment_by, reason_id, remarks | Soft deletes. Belongs to IssuedTicket. |
| **TicketRequest** | ticket_requests | user_id, request_branch_id, booking_id, passenger_id, issued_ticket_id, request_type (re_issue/refund/additional), status (pending/processed/rejected), dates, remark, requested/processed/rejected_at, result_re_issued_ticket_id, result_refunded_ticket_id, result_issued_ticket_id | Links requests to results. |
| **ReIssueRefundReason** | re_issue_refund_reasons | reason_of (re-issue/refund), name, default_payment_by | Lookup table. |
| **GroupTicket** | group_tickets | ticket_fare_id, inbound/outbound_date, pnr, ticket_qty, is_refundable, is_exchangable | CHECK: ticket_qty ≥ 1 |
| **BaggageAllowance** | baggage_allowances | ticket_fare_id, passenger_type, travel_direction, allowance | Unique: fare + type + direction |

### Fingerprint Entities

| Model | Table | Key Fields | Notes |
|-------|-------|-----------|-------|
| **Fingerprint** | fingerprints | booking_id (unique), deadline, cost, assigned_staff_id | One per booking. hasOne FingerpringDetail per passenger. |
| **FingerprintDetail** | fingerprint_details | fingerprint_id, passenger_id (unique), status (none/processing/approved/cancelled/done) | Unique: fingerprint + passenger |
| **FingerprintCharge** | fingerprint_charges | district_id (unique), user_id, fingerprint_charge | Per-district cost |
| **FingerprintDetailLog** | fingerprint_detail_logs | fingerprint_detail_id, user_id, action, old_values, new_values | Audit log (no updated_at) |
| **FingerprintCostLog** | fingerprint_cost_logs | fingerprint_id, cost, cost_updated_by | Cost change history |
| **RescheduledFingerprint** | rescheduled_fingerprints | fingerprint_detail_id, reason, other_reason, next_date, occurrence, remarks | CHECK: occurrence ≥ 1 |

### Visa Entities

| Model | Table | Key Fields | Notes |
|-------|-------|-----------|-------|
| **VisaSubmission** | visa_submissions | passenger_id (unique), visa_agent_id, commission_agent_id, agent_commission, visa_selling_price_id, visa_number, is_cancelled, net_visa_cost, additional_cost, final_cost, remarks, status (pending/submitted/issued/cancelled) | One per passenger (latestOfMany). |
| **VisaAgent** | visa_agents | name, address, contacts | HasMany visaSubmissions, cancelledSubmissions, commissionAgents; hasOne visaAgentCost |
| **VisaAgentCost** | visa_agent_costs | visa_agent_id (unique), user_id, visa_agent_cost | |
| **VisaSellingPrice** | visa_selling_prices | selling_price, user_id | is_locked appended; HasMany packages, visaSubmissions |
| **CommissionAgent** | commission_agents | visa_agent_id, name, address, contacts | BelongsTo VisaAgent |
| **VisaUpdateLog** | visa_update_logs | visa_submission_id, user_id, action, old_values, new_values | Audit log (no updated_at) |
| **CancelledSubmission** | cancelled_submissions | visa_submission_id (unique), cancellation_fee | CHECK: cancellation_fee ≥ 0 (nullable) |

### Audit Log Entities

| Model | Table | Foreign key | Purpose |
|-------|-------|-------------|---------|
| **BookingUpdateLog** | booking_update_logs | booking_id | Logs booking created/updated/deleted with dirty field diffs |
| **PassengerUpdateLog** | passenger_update_logs | passenger_id | Logs passenger changes (includes passport_no) |
| **InvoiceUpdateLog** | invoice_update_logs | invoice_id | Logs invoice changes (includes reason for audit context) |
| **VisaUpdateLog** | visa_update_logs | visa_submission_id | Logs visa status changes (queries new_values→status) |
| **FingerprintDetailLog** | fingerprint_detail_logs | fingerprint_detail_id | Logs fingerprint status changes |
| **IssuedTicketLog** | issued_ticket_logs | issued_ticket_id | Logs issued ticket actions (issued/edited/re-issued/refunded) |
| **FingerprintCostLog** | fingerprint_cost_logs | fingerprint_id | Logs fingerprint cost changes (cost, cost_updated_by) |

All audit log tables are append-only (`const UPDATED_AT = null`). They store
JSON `old_values`/`new_values` columns. The `InvoiceUpdateLog` also has a
`reason` column set from the model's `audit_reason` transient property.

---

## Enums (26 total)

All in `app/Enums/`. These are PHP backed enums (string or int) used in model
`$casts` arrays.

### Booking / Invoice Related

| Enum | Values |
|------|--------|
| `DiscountType` | `fixed_amount`, `percentage` |
| `InvoiceStatus` | `pending`, `partial`, `paid`, `cancelled`, `refunded` |
| `TransactionType` (enum) | `debit`, `credit` |
| `OwnerType` | `customer`, `passenger` |
| `CancelledBookingStatus` | `cancellation processing`, `cancelled` |

### Passenger / Visa Related

| Enum | Values |
|------|--------|
| `Gender` | `male`, `female` |
| `IqamaType` | `none`, `self`, `referral` |
| `PassengerType` | `adult`, `child`, `infant` |
| `ServiceRequired` | `all`, `visa_only`, `ticket_only` |
| `TicketType` | `regular`, `offer`, `group` |
| `TicketStatus` | `pending`, `issued`, `re-issued`, `refunded`, `awaiting-group` |
| `VisaStatus` | `pending`, `submitted`, `issued`, `cancelled` |
| `ReasonOf` | `re-issue`, `refund` |

### Fingerprint Related

| Enum | Values |
|------|--------|
| `FingerprintStatus` | `none`, `processing`, `approved`, `cancelled`, `done` |
| `FingerprintLocation` | `home`, `office` |

### Route / Flight Related

| Enum | Values |
|------|--------|
| `FlightType` | `direct`, `transit` |
| `RouteType` | `oneway_inbound`, `oneway_outbound`, `round`, `multi_city` |
| `RouteDirection` | `inbound`, `outbound` |
| `SegmentDirection` | `inbound`, `outbound` |
| `TravelDirection` | `inbound`, `outbound` |
| `Location` | `KSA`, `BD` |

### Financial Related

| Enum | Values |
|------|--------|
| `PaymentMethod` | `cash`, `bank` |
| `PaymentBy` | `customer`, `airline`, `employee`, `company` |
| `Currency` | `SAR`, `BDT` |
| `ReIssuePaymentOption` | `customer_payment`, `refund_adjustment` |
| `RescheduleReason` | `rescheduled_by_client`, `rescheduled_by_bmt`, `nfc_problem`, `others` |

### Using Enums

```php
// Check:
if ($booking->discount_type === DiscountType::FIXED_AMOUNT) { ... }

// Create:
$booking->discount_type = DiscountType::PERCENTAGE;

// In Blade:
@if($booking->discount_type->value === 'fixed_amount')
```

---

## Services (7 total)

All in `app/Services/`. Injected via constructor DI.

| Service | Purpose |
|---------|---------|
| **BookingService** | Booking creation, passenger type calculation (age-based: <19 months = infant, <139 months = child, else adult), discount calculation (fixed_amount or percentage), package value calculation (ticket fare + visa selling price + service charge, child/infant % discounts, double-ticket support), booking total recalculation, `syncFinancials()` (recalculates + updates invoice), `generateInvoiceId()` (branch-prefixed random format: `BRNC-XXXX26`), fingerprint charge lookup |
| **InvoiceService** | Invoice creation for bookings (`createForBooking`), payment status updates (pending/partial/paid/cancelled/refunded based on balance), `canAcceptPayment()` (allows 50 SAR overpayment buffer), `calculateBalance()`, `updateTotals()` (with audit_reason) |
| **PaymentService** | Customer payment creation (`createCustomerPayment` — DB transaction with payment + voucher + invoice update), agent payments (`createAgentPayment` — ticket/agent/commission, invoice_id=null), currency conversion via `CurrencyRateService` |
| **VoucherService** | Voucher number generation (`VCH-YYYYMMDD-XXXX` sequential with 4-digit zero-padded sequence per day), voucher creation with transaction_type linking |
| **CancellationService** | Booking cancellation workflow — calculates refund_amount = total_paid - total_cost - service_charge_deduction, creates CancelledBooking record (status: 'cancellation processing'), creates deduction + refund Payment/Voucher pairs, links via foreign keys. Uses CostTrackingService for cost calculation. |
| **CostTrackingService** | Per-passenger cost breakdown: fingerprint cost (total cost / passenger count) + visa cost (visaSubmission final_cost if issued) + ticket cost (issuedTickets net_fare sum). Booking cost summary for profit calculation. |
| **CurrencyRateService** | In-request cache keyed by date string, three-tier rate resolution (explicit CurrencyRate relation → effective on date → first/oldest rate), `convertSarToBdt()`, `convertBdtToSar()`, `convert()` unified method, `getCurrentRate()`/`getFirstRate()`/`getFirstRateValue()` |

### Financial Flow Summary

```
Booking created
    → InvoiceService::createForBooking()
    → Invoice created (status: pending, total_amount = booking.total_value, balance = total)

Customer payment received
    → PaymentService::createCustomerPaymentAndUpdateInvoice()
    → [DB Transaction]
      1. Payment::create() (SAR amount, bdt_amount = SAR × rate)
      2. VoucherService::createVoucher() → Voucher (1:1, transaction_type: Initial Payment)
      3. InvoiceService::updatePaymentStatus() → paid_amount += amount, balance -= amount
         → status: pending → partial → paid (when balance ≤ 0)

Due collection (later payment)
    → Same flow, transaction_type: Due Collection

Agent payout (ticket/agent/commission)
    → PaymentService::createAgentPayment()
    → Payment (invoice_id = null) + Voucher (debit transaction_type)
    → No invoice update

Booking cancellation
    → CancellationService::initiateCancellation()
    → CancelledBooking created (status: 'cancellation processing')
    → refund_amount = total_paid - total_cost - service_charge_deduction
    → Payment + Voucher for deduction (Service Charge Deduction, credit)
    → Payment + Voucher for refund (Customer Refund, debit) — when confirmed

Currency rates updated
    → CurrencyRate::create() (new rate, timestamps)
    → Affects all future conversions until a newer rate is added
    → Past bookings retain their original currency_rate_id
```

---

## Blade Components (13 total)

All in `resources/views/components/`.

| Component | Usage |
|-----------|-------|
| `action-button` | Standard action buttons (edit, delete, view) |
| `data-table` | Paginated, sortable data table |
| `empty-state` | Empty state illustration + message |
| `error-state` | Error display for failed loads |
| `loading-state` | Spinner + loading message |
| `modal` | Modal dialog with Alpine.js show/hide |
| `page-header` | Page title + action buttons |
| `search-input` | Search/filter input field |
| `skeleton` | Skeleton loaders for content |
| `stat-card` | Dashboard summary stat card |
| `status-badge` | Status indicator badge |
| `tab-button` | Tab navigation buttons |
| `toast` | Toast notification component |

### Custom Blade Directives

#### `@currency`

Renders multi-currency display (SAR primary, BDT secondary):

```blade
@currency($booking->total_value, 2, $currencyRate?->rate, $booking->bdt_amount)
@endcurrency
```

Renders as:
```html
<span class="currency-display" data-sar="1234.560000" data-dec="2"
      data-rate="12.00" data-bdt="14814.720000">1,234.56</span>
```

The `@endcurrency` directive returns empty string (pairing only).

#### Registered in `AppServiceProvider::boot()`

To add a new directive:
```php
Blade::directive('myDirective', function ($expression) {
    return "<?php echo '...' . {$expression} . '...' ?>";
});
```

---

## Observers (6 total)

All in `app/Observers/`. Registered in `AppServiceProvider::boot()`.

| Model | Observer | What it logs | Log table |
|-------|----------|-------------|-----------|
| Booking | BookingObserver | Created: full attributes; Updated: dirty field diffs (old + new); Deleted: stripped attributes | `booking_update_logs` |
| Passenger | PassengerObserver | Same pattern as Booking; includes `passport_no` column | `passenger_update_logs` |
| Invoice | InvoiceObserver | Created/Updated: dirty field tracking; Deleted: stripped attributes; includes `reason` from `audit_reason` | `invoice_update_logs` |
| FingerprintDetail | FingerprintDetailObserver | Fingerprint status changes | `fingerprint_detail_logs` |
| VisaSubmission | VisaSubmissionObserver | Visa status changes (old + new values as JSON) | `visa_update_logs` |
| IssuedTicket | IssuedTicketObserver | Ticket actions (issued/edited/re-issued/refunded) | `issued_ticket_logs` |

**Pattern:**
```php
public function updated(MyModel $model): void
{
    $dirty = $model->getDirty();
    if (empty($dirty)) {
        return;  // Skip if nothing changed
    }
    $original = $model->getOriginal();
    $oldValues = [];
    $newValues = [];
    foreach ($dirty as $key => $newValue) {
        $oldValues[$key] = $original[$key] ?? null;
        $newValues[$key] = $newValue;
    }
    MyUpdateLog::create([
        'model_id' => $model->id,
        'user_id' => auth()->id() ?? null,
        'action' => 'updated',
        'old_values' => $oldValues,
        'new_values' => $newValues,
    ]);
}
```

Note: Observers that reference `auth()->user()` or `auth()->id()` return early
or get null when running outside a request context (e.g., in tests or queue
jobs without a user). The `BookingObserver` and `PassengerObserver` explicitly
check `if (! $user) { return; }`.

---

## Query Repositories (6 total)

All in `app/Queries/`. Constructor takes a `Request`, applies filters, exposes
`getQuery()`, `paginate()`, and `getSummary()`.

| Query class | For |
|-------------|-----|
| `BookingIndexQuery` | Booking index page + Livewire `BookingIndexTable` data endpoint |
| `PassengerIndexQuery` | Passenger index Livewire table data endpoint |
| `FingerprintReportQuery` | Fingerprint report filtering + eager loading |
| `TicketAgentReportQuery` | Ticket agent report data (payable, paid, refunds, reissues) |
| `VisaAgentReportQuery` | Visa agent report data (submitted, issued, cancelled, payments) |
| `BranchWiseReportQuery` | Branch-wise report + dashboard summary (`summary()`) + payment history print (`paymentHistory()`) |

### BranchWiseReportQuery Detail

This is the most complex query class. It computes the dashboard summary with:
- **Visa stats:** visaSubmitted, visaIssued, visaPending (via VisaUpdateLog and VisaSubmission queries)
- **Fingerprint stats:** fingerprintApproved, fingerprintDone, fingerprintProcessing (via FingerprintDetailLog)
- **Invoice stats:** invoiceCount, invoiceTotalAmount (SAR + BDT), totalDue (SAR + BDT)
- **Ticket stats:** inboundTicket, outboundTicket, pendingTicket (via IssuedTicketLog and IssuedTicket)
- **Payment aggregates:** totalReceiving, totalCashPayment, totalBankPayment (broken down by Initial Payment vs Due Collection, cash vs bank) — single query with CASE WHEN for method splits
- **Refund aggregates:** totalRefund, totalTicketRefund (via voucher transaction types)
- **Fingerprint profit:** SUM(fingerprint_charges.fingerprint_charge - fingerprints.cost) × rate
- **Service charge deduction:** via voucher transaction type 'Service Charge Deduction'
- **Total profit:** iterates bookings (Booking::with cost loading) × CostTrackingService — the only N+1 pattern, kept from original controller
- **Branch scoping:** 'central' (branch_id IS NULL), specific branch_id, or global (no filter)
- **Rate resolution:** uses `CurrencyRateService::getFirstRateValue()` as fallback for all BDT conversions

### Payment History (print view)

`BranchWiseReportQuery::paymentHistory()` returns voucher line items with:
- invoice_id, voucher_no, method (cash/bank), transaction_type, trx_id,
- receive_by (user name), receive_at (branch name or 'Central'),
- receive_branch_id, receive_branch_location (KSA/BD),
- amount (SAR), bdt_amount, currency_rate,
- bank name + bank_id

---

## Entity-Relationship Quick Reference

### Core Chain: Branch → Booking → Passenger → [Visa/Ticket/Fingerprint] → Invoice → Payment → Voucher

```
Branch
  └─(1:M)─ users (branch_id)
  └─(1:M)─ bookings (booking_branch_id)  [who created the booking]
  └─(1:M)─ bookings (fingerprint_branch_id)  [who handles fingerprints]
  └─(1:M)─ invoices (branch_id)
  └─(1:M)─ payments (branch_id)
  └─(1:M)─ vouchers (branch_id)
  └─(1:1)─ fingerprint_charges (via district_id)

Customer
  └─(1:M)─ bookings (customer_id)
  └─(M:M)─ documents (morphMany, owner: customer)

Booking
  ├─(1:1)─ invoice
  ├─(1:1)─ fingerprint
  ├─(1:1)─ cancelledBooking
  ├─(1:M)─ passengers
  ├─(1:M)─ payments
  ├─(1:M)─ vouchers
  ├─(M)─ documents (morphMany)
  └─(M)─ bookingUpdateLogs

Passenger
  ├─(1:1)─ visaSubmission (latest)
  ├─(1:1)─ fingerprintDetail
  ├─(M)─ issuedTickets
  ├─(M)─ documents (morphMany)
  ├─(M)─ passengerUpdateLogs
  ├─(M:M)─ refundedTickets (via issuedTickets)
  └─(M)─ reIssueSettlements (payments with 'Ticket Refund - Re-issue' vouchers)

Invoice
  ├─(M)─ payments
  └─(M)─ vouchers

Payment
  └─(1:1)─ voucher (latest, via latestOfMany)

Fingerprint (1:1 with Booking)
  ├─(1:M)─ fingerprintDetails (1 per passenger)
  ├─(1:M)─ fingerprintCostLogs
  └─(1:1)─ firstCostLog

VisaSubmission (1:1 with Passenger)
  ├─(1:1)─ cancelledSubmission
  └─(M)─ visaUpdateLogs

IssuedTicket (M per Passenger, belongs to Booking)
  ├─(M)─ issuedTicketLogs
  ├─(1:1)─ latestReIssuedTicket
  ├─(M)─ reIssuedTickets (soft-deleted)
  ├─(M)─ refundedTickets (soft-deleted)
  └─(M)─ ticketRequests (pending re_issue/refund)

TransactionType (1) ←─(M)─ vouchers (transaction_type_id)
  [Initial Payment, Due Collection = credit (receiving money)]
  [Customer Refund, Ticket Refund-*, Agent Payments = debit (paying out)]
  [Service Charge Deduction = credit (company revenue)]

CurrencyRate (1) ←─(M)─ payments, vouchers, bookings
  [Rate stored as SAR→BDT. Three-tier resolution for conversion.]
```

---

## Livewire Components Summary

List tables extend `BaseListTable` (abstract, `app/Livewire/BaseListTable.php`):
- Uses `WithPagination` trait
- Provides `resetSearch()` and `applySearch()` (supports column names + custom `[operator, value]` pairs)

| Component | Namespace | For |
|-----------|-----------|-----|
| `BookingIndexTable` | `App\Livewire\Booking` | Booking list Livewire table |
| `PassengerIndexTable` | `App\Livewire\Booking` | Passenger list Livewire table |
| `PackageListTable` | `App\Livewire\Package` | Package list |
| `BranchListTable` | `App\Livewire\Branch` | Branch list |
| `PaymentListTable` | `App\Livewire\Payment` | Payment list |
| `UserListTable` | `App\Livewire\User` | User list |
| `TicketFareTable` | `App\Livewire\TicketFare` | Ticket fare list |
| `TicketFareIndexTable` | `App\Livewire\TicketFare` | Ticket fare index |
| `FingerprintChargeTable` | `App\Livewire\Settings` | Fingerprint charge settings |
| `FingerprintChargeList` | `App\Livewire\Fingerprint` | Fingerprint charge list (report) |
| `DashboardSummary` | `App\Livewire\Dashboard` | Dashboard summary stats + totals |
| `DashboardRequestTabs` | `App\Livewire\Dashboard` | Pending ticket requests (re-issue, additional, refund) |
| `DashboardPackageSlider` | `App\Livewire\Dashboard` | Active package carousel |
| `ProfitLossReportTable` | `App\Livewire\Report` | Profit/loss report table |
| `FingerprintReportTable` | `App\Livewire\Report` | Fingerprint report table |
| `VisaAgentReportTable` | `App\Livewire\Report` | Visa agent report table |
| `TicketAgentReportTable` | `App\Livewire\Report` | Ticket agent report table |
| `BookingCancellationReportTable` | `App\Livewire\Report` | Booking cancellation report |
| `DueReportTable` | `App\Livewire\Report` | Due report table |
| `BranchWiseReportFilters` | `App\Livewire\Report` | Branch-wise report filters |
| `VisaReportTable` | `App\Livewire\Report` | Visa report table |
| `IndexDataTable` | `App\Livewire` | Shared table primitive |

---

## Navigation

Previous: [Architecture](02-architecture.md) ·
Next: [Development Environment](03-dev-environment.md) ·
Full index: [README](README.md)
