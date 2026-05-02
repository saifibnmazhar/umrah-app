# BM Umrah - Database Design Plan

## 1. Application Overview

**BM Umrah** is a travel agency booking system for Umrah pilgrimage packages. The system manages:
- Customer registration and management
- Booking creation with multiple passengers per invoice
- Ticket/fare management with agent tracking
- Visa processing and tracking
- Fingerprint registration and location tracking
- Payment recording and due tracking
- Multiple reporting modules

---

## 2. Entity Relationship Diagram

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│  Customers  │──1:N──│  Bookings   │──1:N──│ Passengers │
└─────────────┘       └─────────────┘       └─────────────┘
                             │                      │
                             │                      │
                        ┌────┴────┐          ┌────┴────┐
                        │         │          │         │
                   ┌────▼────┐  │    ┌─────▼────┐  │
                   │Payments │  │    │ Tickets  │  │
                   └─────────┘  │    └──────────┘  │
                                │         │
                           ┌─────▼────────┐   │
                           │   Visas    │   │
                           └────────────┘   │
                                      │
                                 ┌─────▼────┐
                                 │Fingerprints│
                                 └──────────┘
```

---

## 3. Core Tables Design

### 3.1 Customers Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| customer_code | VARCHAR(20) | UNIQUE, NOT NULL | Customer ID (Iqama/Passport) |
| name | VARCHAR(255) | NOT NULL | Full name |
| iqama_no | VARCHAR(50) | NULL | Saudi Iqama number |
| passport_no | VARCHAR(50) | NULL | Passport number |
| passport_expiry | DATE | NULL | Passport expiration date |
| mobile_no | VARCHAR(20) | NOT NULL | Primary mobile |
| bangladeshi_mobile | VARCHAR(20) | NULL | Bangladesh contact |
| email | VARCHAR(255) | NULL | Email address |
| address | TEXT | NULL | Detailed address |
| district | VARCHAR(100) | NULL | District |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 3.2 Bookings Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| invoice_no | VARCHAR(50) | UNIQUE, NOT NULL | Invoice number (INV-XXXX) |
| booking_date | DATE | NOT NULL | Booking date |
| customer_id | UUID | FK → customers(id) | Associated customer |
| total_amount | DECIMAL(12,2) | DEFAULT 0 | Total booking amount |
| paid_amount | DECIMAL(12,2) | DEFAULT 0 | Amount paid |
| due_amount | DECIMAL(12,2) | DEFAULT 0 | Outstanding due |
| status | ENUM | DEFAULT 'pending' | pending/confirmed/cancelled |
| notes | TEXT | NULL | Additional notes |
| created_by | UUID | FK → users(id) | Created by user |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 3.3 Passengers Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| booking_id | UUID | FK → bookings(id) | Parent booking |
| customer_id | UUID | FK → customers(id) | Guardian/customer |
| name | VARCHAR(255) | NOT NULL | Passenger name |
| passport_no | VARCHAR(50) | NOT NULL | Passport number |
| passport_expiry | DATE | NULL | Passport expiration |
| date_of_birth | DATE | NULL | DOB (for type calc) |
| mobile_no | VARCHAR(20) | NULL | Passenger mobile |
| route | VARCHAR(20) | NOT NULL | Flight route (DAC-JED-DAC) |
| airline | VARCHAR(50) | NOT NULL | Airline name |
| travel_class | ENUM | NOT NULL | economy/business |
| passenger_type | ENUM | NOT NULL | adult/child/infant |
| package_type | ENUM | NOT NULL | 14 days/85 days |
| service_type | ENUM | NOT NULL | all/visa_only/ticket_only |
| flight_date_from | DATE | NULL | Departure date |
| flight_date_to | DATE | NULL | Return date |
| with_offer | BOOLEAN | DEFAULT FALSE | Offer pricing applied |
| refundable | BOOLEAN | DEFAULT TRUE | Refundable ticket |
| re_issueable | BOOLEAN | DEFAULT TRUE | Re-issueable ticket |
| status | ENUM | DEFAULT 'none' | Processing status |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 3.4 Tickets Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| passenger_id | UUID | FK → passengers(id) | Associated passenger |
| booking_id | UUID | FK → bookings(id) | Parent booking |
| ticket_agent_id | UUID | FK → agents(id) | Ticket agent |
| fare_date | DATE | NOT NULL | Fare date |
| gross_fare | DECIMAL(10,2) | NOT NULL | Ticket fare before discount |
| discount_type | ENUM | NULL | amount/percentage |
| discount_value | DECIMAL(10,2) | NULL | Discount amount |
| net_fare | DECIMAL(10,2) | NOT NULL | Net fare after discount |
| ticket_status | ENUM | DEFAULT 'pending' | pending/issued/reissued/refunded |
| issued_date | DATE | NULL | Issue date |
| pnr_no | VARCHAR(20) | NULL | PNR/Booking reference |
| ticket_no | VARCHAR(50) | NULL | Ticket number |
| with_offer | BOOLEAN | DEFAULT FALSE | Offer applied |
| refundable | BOOLEAN | DEFAULT TRUE | Refundable |
| re_issueable | BOOLEAN | DEFAULT TRUE | Re-issueable |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 3.5 Visas Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| passenger_id | UUID | FK → passengers(id) | Associated passenger |
| booking_id | UUID | FK → bookings(id) | Parent booking |
| visa_agent_id | UUID | FK → agents(id) | Visa processing agent |
| package_type | ENUM | NOT NULL | 14 days/85 days |
| visa_package_cost | DECIMAL(10,2) | NOT NULL | Base visa cost |
| profit_margin | DECIMAL(10,2) | DEFAULT 0 | Profit margin |
| final_visa_cost | DECIMAL(10,2) | NOT NULL | Final cost (cost + margin) |
| selling_price | DECIMAL(10,2) | NOT NULL | Price to customer |
| agent_commission | DECIMAL(10,2) | DEFAULT 0 | Agent commission |
| visa_number | VARCHAR(50) | NULL | Official visa number |
| issued_date | DATE | NULL | Visa issue date |
| visa_status | ENUM | DEFAULT 'pending' | pending/submitted/issued |
| application_date | DATE | NULL | Application submission date |
| additional_cost | DECIMAL(10,2) | DEFAULT 0 | Extra fees |
| remarks | TEXT | NULL | Notes |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 3.6 Fingerprints Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| passenger_id | UUID | FK → passengers(id) | Associated passenger |
| booking_id | UUID | FK → bookings(id) | Parent booking |
| location | VARCHAR(100) | NOT NULL | Fingerprint location |
| division | VARCHAR(100) | NULL | Bangladesh division |
| district | VARCHAR(100) | NULL | District name |
| finger_charge | DECIMAL(10,2) | DEFAULT 0 | Charge to customer |
| finger_cost | DECIMAL(10,2) | DEFAULT 0 | Company cost |
| deadline | DATE | NULL | Submission deadline |
| status | ENUM | DEFAULT 'pending' | pending/processing/done/nfc_problem |
| assigned_staff_id | UUID | FK → staff(id) | Assigned staff |
| completed_date | DATE | NULL | Completion date |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 3.7 Payments Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| booking_id | UUID | FK → bookings(id) | Parent booking |
| passenger_id | UUID | FK → passengers(id) | Associated passenger (nullable) |
| payment_date | DATE | NOT NULL | Payment date |
| voucher_no | VARCHAR(50) | UNIQUE | Payment voucher number |
| payment_method | ENUM | NOT NULL | cash/bank_transfer/card/online |
| transaction_id | VARCHAR(100) | NULL | Bank trx ID |
| amount | DECIMAL(10,2) | NOT NULL | Payment amount |
| payment_type | ENUM | NOT NULL | booking/ticket/visa/fingerprint |
| reference_no | VARCHAR(50) | NULL | Reference (invoice/ticket/visa) |
| received_by | UUID | FK → users(id) | Received by user |
| notes | TEXT | NULL | Payment notes |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |

---

## 4. Reference Tables

### 4.1 Agents Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| agent_code | VARCHAR(20) | UNIQUE | Agent code |
| name | VARCHAR(255) | NOT NULL | Agent name |
| agent_type | ENUM | NOT NULL | ticket/visa/both |
| mobile_no | VARCHAR(20) | NULL | Contact number |
| email | VARCHAR(255) | NULL | Email address |
| commission_rate | DECIMAL(5,2) | DEFAULT 0 | Commission percentage |
| is_active | BOOLEAN | DEFAULT TRUE | Active status |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 4.2 Staff Table (Fingerprint Staff)
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| staff_code | VARCHAR(20) | UNIQUE | Staff code |
| name | VARCHAR(255) | NOT NULL | Staff name |
| division | VARCHAR(100) | NOT NULL | Assigned division |
| district | VARCHAR(100) | NULL | Assigned district |
| mobile_no | VARCHAR(20) | NULL | Contact number |
| is_active | BOOLEAN | DEFAULT TRUE | Active status |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |

### 4.3 Users Table (System Users)
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| username | VARCHAR(50) | UNIQUE, NOT NULL | Login username |
| password_hash | VARCHAR(255) | NOT NULL | Hashed password |
| full_name | VARCHAR(255) | NOT NULL | Full name |
| role | ENUM | NOT NULL | admin/manager/staff |
| is_active | BOOLEAN | DEFAULT TRUE | Active status |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| last_login | TIMESTAMP | NULL | Last login time |

### 4.4 Fare Configuration Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| route | VARCHAR(20) | NOT NULL | Flight route |
| airline | VARCHAR(50) | NOT NULL | Airline name |
| travel_class | ENUM | NOT NULL | economy/business |
| passenger_type | ENUM | NOT NULL | adult/child/infant |
| regular_price | DECIMAL(10,2) | NOT NULL | Regular fare |
| offer_price | DECIMAL(10,2) | NULL | Promotional fare |
| effective_from | DATE | NOT NULL | Start date |
| effective_to | DATE | NULL | End date |
| is_active | BOOLEAN | DEFAULT TRUE | Active status |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 4.5 Visa Packages Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| package_type | ENUM | NOT NULL | 14 days/85 days |
| base_cost | DECIMAL(10,2) | NOT NULL | Base visa cost |
| effective_from | DATE | NOT NULL | Start date |
| effective_to | DATE | NULL | End date |
| is_active | BOOLEAN | DEFAULT TRUE | Active status |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 4.6 Fingerprint Locations Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| location_name | VARCHAR(100) | NOT NULL | Location name |
| division | VARCHAR(100) | NOT NULL | Division |
| district | VARCHAR(100) | NULL | District |
| charge_amount | DECIMAL(10,2) | DEFAULT 0 | Customer charge |
| cost_amount | DECIMAL(10,2) | DEFAULT 0 | Company cost |
| is_active | BOOLEAN | DEFAULT TRUE | Active status |
| created_at | TIMESTAMP | DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

### 4.7 Settings Table
| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| id | UUID | PK, AUTO | Unique identifier |
| setting_key | VARCHAR(100) | UNIQUE, NOT NULL | Setting key |
| setting_value | TEXT | NULL | Setting value |
| description | VARCHAR(255) | NULL | Description |
| updated_at | TIMESTAMP | ON UPDATE NOW() | Last update |

---

## 5. Indexes for Performance

| Table | Index Name | Columns | Type |
|-------|------------|--------|------|
| customers | idx_customer_code | customer_code | UNIQUE |
| customers | idx_mobile | mobile_no | INDEX |
| bookings | idx_invoice_no | invoice_no | UNIQUE |
| bookings | idx_booking_date | booking_date | INDEX |
| bookings | idx_customer | customer_id | INDEX |
| passengers | idx_passport | passport_no | INDEX |
| passengers | idx_booking | booking_id | INDEX |
| passengers | idx_status | status | INDEX |
| tickets | idx_passenger | passenger_id | INDEX |
| tickets | idx_ticket_status | ticket_status | INDEX |
| visas | idx_passenger | passenger_id | INDEX |
| visas | idx_visa_status | visa_status | INDEX |
| fingerprints | idx_passenger | passenger_id | INDEX |
| fingerprints | idx_location | location | INDEX |
| fingerprints | idx_status | status | INDEX |
| payments | idx_booking | booking_id | INDEX |
| payments | idx_voucher | voucher_no | UNIQUE |

---

## 6. Business Logic & Dependencies

### 6.1 Invoice Number Generation
- Format: `INV-XXXX` (auto-incrementing)
- All passengers in same booking share same invoice number
- Unique constraint prevents duplicates

### 6.2 Due Calculation
```
due_amount = total_amount - SUM(payments.amount)
```
- Trigger on payment insert/update/delete to recalculate

### 6.3 Passenger Type Auto-Detection
Based on date_of_birth:
- Adult: 12+ years
- Child: 2-12 years
- Infant: <2 years

### 6.4 Fare Calculation
```
net_fare = gross_fare - discount
```
- If discount_type = 'percentage': discount = gross_fare * (discount_value / 100)
- If discount_type = 'amount': discount = discount_value

### 6.5 Visa Cost Calculation
```
final_visa_cost = base_cost + profit_margin
selling_price = final_visa_cost + agent_commission
```

### 6.6 Individual Passenger Cost
```
individual_cost = ticket.net_fare + visa.final_visa_cost + fingerprint.finger_charge
```

---

## 7. Recommended Database

**PostgreSQL** is recommended for this application because:
- Strong ACID compliance for financial transactions
- JSON support for flexible configuration
- Excellent performance for complex queries
- Robust role-based security
- Easy migrations with tools like Flyway or Alembic

---

## 8. Implementation Notes

### Phase 1: Core Tables
- customers, bookings, passengers, payments

### Phase 2: Transaction Tables
- tickets, visas, fingerprints

### Phase 3: Reference Data
- agents, staff, users, fare_config, visa_packages, fingerprint_locations

### Phase 4: Migration Strategy
1. Export current localStorage data to JSON
2. Create migration scripts
3. Import data with new UUIDs
4. Update frontend to use API endpoints

---

## 9. API Endpoint Structure (Future)

```
/api/v1/
├── /customers
│   ├── GET/POST /customers
│   ├── GET/PUT/DELETE /customers/:id
├── /bookings
│   ├── GET/POST /bookings
│   ├── GET/PUT/DELETE /bookings/:id
├── /passengers
│   ├── GET/POST /passengers
│   ├── GET/PUT/DELETE /passengers/:id
├── /tickets
├── /visas
├── /fingerprints
├── /payments
├── /reports
│   ├── /reports/statement
│   ├── /reports/profit-loss
│   ├── /reports/due
│   └── ...
└── /settings
```

---

*Document Version: 1.0*
*Created: April 2026*