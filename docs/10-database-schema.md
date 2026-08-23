# Database Schema Reference

> Part of the [Development Handbook](README.md) · **Mode:** Reference
>
> This document contains the complete table-by-table column reference for all
> 42 tables in the Umrah App database. For architectural context, see
> [Architecture](02-architecture.md). For entity relationships and model
> associations, see [Domain Reference](08-domain-reference.md).

---

## Table of Contents

- [Core Business Tables](#core-business-tables)
  - [users](#users)
  - [roles](#roles)
  - [user_roles](#user_roles)
  - [branches](#branches)
  - [districts](#districts)
  - [customers](#customers)
  - [bookings](#bookings)
  - [passengers](#passengers)
  - [passenger_statuses](#passenger_statuses)
  - [documents](#documents)
  - [booking_conditions](#booking_conditions)
  - [stay_duration_limits](#stay_duration_limits)
- [Packages & Fares](#packages--fares)
  - [packages](#packages)
  - [package_configurations](#package_configurations)
  - [ticket_fares](#ticket_fares)
  - [baggage_allowances](#baggage_allowances)
  - [group_tickets](#group_tickets)
  - [routes](#routes)
  - [route_multi_segments](#route_multi_segments)
  - [route_transits](#route_transits)
  - [airlines](#airlines)
  - [classes](#classes)
  - [airline_classes](#airline_classes)
  - [airline_cities](#airline_cities)
  - [city_codes](#city_codes)
  - [travel_classes](#travel_classes)
- [Fingerprint Tables](#fingerprint-tables)
  - [fingerprint_charges](#fingerprint_charges)
  - [fingerprints](#fingerprints)
  - [fingerprint_details](#fingerprint_details)
  - [fingerprint_detail_logs](#fingerprint_detail_logs)
  - [fingerprint_cost_logs](#fingerprint_cost_logs)
  - [rescheduled_fingerprints](#rescheduled_fingerprints)
- [Visa Tables](#visa-tables)
  - [visa_agents](#visa_agents)
  - [visa_agent_costs](#visa_agent_costs)
  - [visa_selling_prices](#visa_selling_prices)
  - [commission_agents](#commission_agents)
  - [visa_submissions](#visa_submissions)
  - [visa_update_logs](#visa_update_logs)
  - [cancelled_submissions](#cancelled_submissions)
- [Ticket Tables](#ticket-tables)
  - [ticket_agents](#ticket_agents)
  - [issued_tickets](#issued_tickets)
  - [issued_ticket_logs](#issued_ticket_logs)
  - [re_issued_tickets](#re_issued_tickets)
  - [refunded_tickets](#refunded_tickets)
  - [ticket_requests](#ticket_requests)
  - [re_issue_refund_reasons](#re_issue_refund_reasons)
- [Financial Tables](#financial-tables)
  - [invoices](#invoices)
  - [payments](#payments)
  - [vouchers](#vouchers)
  - [transaction_types](#transaction_types)
  - [currency_rates](#currency_rates)
  - [banks](#banks)
- [Audit Log Tables](#audit-log-tables)
  - [booking_update_logs](#booking_update_logs)
  - [passenger_update_logs](#passenger_update_logs)
  - [invoice_update_logs](#invoice_update_logs)
- [Other Tables](#other-tables)
  - [flight_date_gaps](#flight_date_gaps)
  - [offices](#offices)
- [Laravel Default](#laravel-default)

> **Note on column ordering:** Columns are listed in the order they appear in
> the final migration state (after all incremental migrations are applied).
> Some columns were added via `ALTER TABLE` migrations and may not appear in
> the original `Schema::create` block.

---

## Core Business Tables

### `users`

User accounts for the application.

| Column               | Type         | Nullable | Default | Description                         |
|----------------------|--------------|----------|---------|-------------------------------------|
| id                   | bigint       | NO       | —       | Primary key, auto-increment         |
| name                 | varchar(255) | NO       | —       | Full name                           |
| email                | varchar(255) | NO       | —       | Unique email                        |
| email_verified_at    | timestamp    | YES      | NULL    | Email verification timestamp        |
| password             | varchar(255) | NO       | —       | Password hash                       |
| branch_id            | bigint       | YES      | NULL    | Nullable FK → branches.id (null = global admin) |
| remember_token       | varchar(100) | YES      | NULL    | Session token                       |
| is_active            | boolean      | NO       | 1       | Active/inactive flag                |
| created_at           | timestamp    | YES      | NULL    |                                     |
| updated_at           | timestamp    | YES      | NULL    |                                     |

**FKs:** branch_id → branches(id) (onDelete: SET NULL)

### `roles`

Predefined system roles.

| Column       | Type         | Nullable | Default | Description                |
|--------------|--------------|----------|---------|----------------------------|
| id           | bigint       | NO       | —       | Primary key                |
| name         | varchar(50)  | NO       | —       | Unique role name           |
| created_at   | timestamp    | YES      | NULL    |                            |
| updated_at   | timestamp    | YES      | NULL    |                            |

### `user_roles`

Pivot table linking users to roles.

| Column       | Type         | Nullable | Default | Description                |
|--------------|--------------|----------|---------|----------------------------|
| id           | bigint       | NO       | —       | Primary key                |
| user_id      | bigint       | NO       | —       | FK → users.id              |
| role_id      | bigint       | NO       | —       | FK → roles.id              |
| created_at   | timestamp    | YES      | NULL    |                            |
| updated_at   | timestamp    | YES      | NULL    |                            |

**UK:** user_id + role_id (unique)

### `branches`

Physical office locations (KSA and BD).

| Column                 | Type          | Nullable | Default | Description                          |
|------------------------|---------------|----------|---------|--------------------------------------|
| id                     | bigint        | NO       | —       | Primary key                          |
| name                   | varchar(255)  | NO       | —       | Branch name                          |
| address                | text          | YES      | NULL    | Full address                         |
| contacts               | text          | YES      | NULL    | Contact persons                      |
| location               | enum          | NO       | —       | `KSA` or `BD`                        |
| fingerprint_operation  | boolean      | NO       | 0       | Whether branch handles fingerprint ops |
| branch_code            | varchar(10)   | NO       | —       | Auto-uppercased code (unique)       |
| created_at             | timestamp     | YES      | NULL    |                                      |
| updated_at             | timestamp     | YES      | NULL    |                                      |

**UK:** branch_code
**Note:** `branch_code` is auto-uppercased on save via the model's `setBranchCodeAttribute` mutator.

### `districts`

Geographic districts for fingerprint charge assignment.

| Column       | Type         | Nullable | Default | Description                |
|--------------|--------------|----------|---------|----------------------------|
| id           | bigint       | NO       | —       | Primary key                |
| name         | varchar(255) | NO       | —       | District name              |
| division     | varchar(255) | YES      | NULL    | Division (e.g., Dhaka)     |
| created_at   | timestamp    | YES      | NULL    |                            |
| updated_at   | timestamp    | YES      | NULL    |                            |

### `customers`

Travel agency customers (umrah travelers' contacts).

| Column              | Type          | Nullable | Default | Description                          |
|---------------------|---------------|----------|---------|--------------------------------------|
| id                  | bigint        | NO       | —       | Primary key                          |
| name                | varchar(255)  | NO       | —       | Customer name                        |
| iqama_type          | enum          | NO       | —       | `none`, `self`, `referral`           |
| passport_no         | varchar(50)   | YES      | NULL    |                                      |
| iqama_no            | varchar(50)   | YES      | NULL    |                                      |
| mobile_no           | varchar(50)   | YES      | NULL    |                                      |
| ref_iqama_no        | varchar(50)   | YES      | NULL    | Referral iqama number                |
| ref_mobile_no       | varchar(50)   | YES      | NULL    | Referral mobile                      |
| ref_iqama_doc       | text          | YES      | NULL    | Referral iqama doc text              |
| address             | text          | YES      | NULL    |                                      |
| created_at          | timestamp     | YES      | NULL    |                                      |
| updated_at          | timestamp     | YES      | NULL    |                                      |

### `bookings`

The central entity of the system. One booking generates exactly one invoice.

| Column                    | Type          | Nullable | Default | Description |
|---------------------------|---------------|----------|---------|-------------|
| id                        | bigint        | NO       | —       | PK |
| customer_id               | bigint        | NO       | —       | FK → customers |
| package_id                | bigint        | YES      | NULL    | FK → packages |
| booking_branch_id         | bigint        | YES      | NULL    | FK → branches (who created the booking) |
| fingerprint_branch_id     | bigint        | YES      | NULL    | FK → branches (who handles fingerprints) |
| fingerprint_charge_id     | bigint        | YES      | NULL    | FK → fingerprint_charges |
| district_id               | bigint        | YES      | NULL    | FK → districts |
| date_gap_id               | bigint        | YES      | NULL    | FK → flight_date_gaps |
| invoice_id                | string(50)    | YES      | NULL    | Branch-prefixed invoice number (e.g., `BRNC-XXXX26`) |
| fingerprint_location      | enum          | NO       | —       | `home`, `office` |
| date_from                 | date          | NO       | —       | Umrah start date |
| date_to                   | date          | NO       | —       | Umrah end date |
| pax_qty                   | integer       | NO       | —       | Passenger count |
| adult_qty                 | integer       | NO       | 0       | Adult passenger count |
| child_qty                 | integer       | NO       | 0       | Child passenger count |
| infant_qty                | integer       | NO       | 0       | Infant passenger count |
| ticket_fare_id            | bigint        | YES      | NULL    | FK → ticket_fares (denormalized snapshot) |
| ticket_fare_inbound_id    | bigint        | YES      | NULL    | FK → ticket_fares (double-ticket inbound) |
| ticket_fare_outbound_id   | bigint        | YES      | NULL    | FK → ticket_fares (double-ticket outbound) |
| visa_selling_price_id     | bigint        | YES      | NULL    | FK → visa_selling_prices |
| discount_type             | enum          | YES      | NULL    | `fixed_amount`, `percentage` |
| discount_value            | decimal(10,2) | YES      | NULL    | Discount percentage or fixed amount |
| discount_amount           | decimal(10,2) | NO       | 0.00    | Calculated discount amount |
| total_value               | decimal(10,2) | NO       | —       | Total booking value (SAR) |
| bdt_amount                | decimal(12,2) | YES      | NULL    | Total value in BDT (SAR × rate) |
| currency_rate_id          | bigint        | YES      | NULL    | FK → currency_rates (snapshot of rate at creation) |
| fingerprint_cost          | decimal(14,6) | NO       | 0.00    | Total fingerprint cost for the booking |
| is_cancelled              | boolean       | NO       | 0       | Cancellation flag |
| created_by                | bigint        | YES      | NULL    | FK → users (who created the booking) |
| updated_by                | bigint        | YES      | NULL    | FK → users (who last modified) |
| created_at                | timestamp     | YES      | NULL    | |
| updated_at                | timestamp     | YES      | NULL    | |

**Note:** `fingerprint_cost` is a denormalized snapshot — the actual per-booking
fingerprint cost is computed from `fingerprint_charges` × `pax_qty` and stored
here for audit traceability.

### `passengers`

Travelers (one or more per booking).

| Column                    | Type          | Nullable | Default | Description |
|---------------------------|---------------|----------|---------|-------------|
| id                        | bigint        | NO       | —       | PK |
| booking_id                | bigint        | NO       | —       | FK → bookings |
| first_name                | varchar(255)  | NO       | —       | |
| last_name                 | varchar(255)  | NO       | —       | |
| passport_no               | varchar(50)   | YES      | NULL    | |
| mobile_no                 | varchar(50)   | YES      | NULL    | |
| date_of_birth             | date          | YES      | NULL    | |
| gender                    | enum          | NO       | —       | `male`, `female` |
| passenger_type            | enum          | NO       | —       | `adult`, `child`, `infant` (calculated by age) |
| passport_expiry           | date          | YES      | NULL    | |
| stay_duration             | integer       | YES      | NULL    | Umrah duration in days |
| service_required          | enum          | NO       | —       | `all`, `visa_only`, `ticket_only` |
| flight_date_from          | date          | YES      | NULL    | Outbound date |
| flight_date_to            | date          | YES      | NULL    | Inbound date |
| actual_flight_date        | date          | YES      | NULL    | |
| address                   | text          | YES      | NULL    | |
| ticket_fare_id            | bigint        | YES      | NULL    | FK → ticket_fares (snapshot) |
| ticket_fare_inbound_id    | bigint        | YES      | NULL    | FK → ticket_fares (double ticket) |
| ticket_fare_outbound_id   | bigint        | YES      | NULL    | FK → ticket_fares (double ticket) |
| package_value             | decimal(14,6) | NO       | 0.00    | Calculated package value for this passenger |
| is_ticket_held            | boolean       | NO       | 0       | |
| ticket_held_by            | bigint        | YES      | NULL    | FK → users |
| ticket_held_at            | timestamp     | YES      | NULL    | |
| ticket_remarks            | text          | YES      | NULL    | |
| refund_payable            | decimal(14,6) | YES      | NULL    | |
| ticket_status             | enum          | YES      | NULL    | `pending`, `issued`, `re-issued`, `refunded`, `awaiting-group` |
| passenger_status_id       | bigint        | YES      | NULL    | FK → passenger_statuses |
| created_at                | timestamp     | YES      | NULL    | |
| updated_at                | timestamp     | YES      | NULL    | |

**Note:** `passenger_type` is automatically calculated by `BookingService` based
on `date_of_birth`. Rules: infant = < 19 months, child = < 139 months, adult =
≥ 139 months.

### `passenger_statuses`

Reference data for passenger statuses.

| Column       | Type         | Nullable | Default | Description |
|--------------|--------------|----------|---------|-------------|
| id           | bigint       | NO       | —       | PK |
| name         | varchar(255) | NO       | —       | Unique status name (e.g., "Hold", "Cancel") |
| description  | varchar(255) | YES      | NULL    | |
| created_at   | timestamp    | YES      | NULL    | |
| updated_at   | timestamp    | YES      | NULL    | |

### `documents`

Polymorphic documents (passports, iqamas, etc.) for customers and passengers.

| Column         | Type          | Nullable | Default | Description |
|----------------|---------------|----------|---------|-------------|
| id             | bigint        | NO       | —       | PK |
| owner_type     | enum          | NO       | —       | `customer`, `passenger` (polymorph base) |
| owner_id       | bigint        | NO       | —       | Polymorphic FK to owner |
| file_path      | varchar(512)  | NO       | —       | Path in storage |
| display_name   | varchar(255)  | NO       | —       | Human-readable name |
| created_at     | timestamp     | YES      | NULL    | |
| updated_at     | timestamp     | YES      | NULL    | |

**Note:** The `owner_type` enum is constrained to `customer` and `passenger`.
The `Booking` model also declares `morphMany(Document::class, 'owner')` but the
enum constraint on the table restricts actual usage. If you need to attach
documents to a new model type, you must modify this enum.

### `booking_conditions`

Terms and conditions shown on booking forms.

| Column        | Type          | Nullable | Default | Description |
|---------------|---------------|----------|---------|-------------|
| id            | bigint        | NO       | —       | PK |
| title         | varchar(255)  | NO       | —       | |
| description   | text          | YES      | NULL    | |
| is_active     | boolean       | NO       | 1       | |
| sort_order    | integer       | YES      | NULL    | |
| created_at    | timestamp     | YES      | NULL    | |
| updated_at    | timestamp     | YES      | NULL    | |

### `stay_duration_limits`

Global configuration for minimum/maximum umrah stay duration.

| Column      | Type          | Nullable | Default | Description |
|-------------|---------------|----------|---------|-------------|
| id          | bigint        | NO       | —       | PK |
| min_days    | integer       | NO       | 1       | Minimum stay (days) |
| max_days    | integer       | NO       | 85      | Maximum stay (days) |
| created_at  | timestamp     | YES      | NULL    | |
| updated_at  | timestamp     | YES      | NULL    | |

---

## Packages & Fares

### `packages`

Umrah packages bundling ticket fares and visa prices.

| Column                | Type          | Nullable | Default | Description |
|-----------------------|---------------|----------|---------|-------------|
| id                    | bigint        | NO       | —       | PK |
| package_name          | varchar(255)  | NO       | —       | |
| ticket_fare_id        | bigint        | NO       | —       | FK → ticket_fares (main fare) |
| ticket_fare_inbound_id| bigint        | YES      | NULL    | FK → ticket_fares (double ticket) |
| ticket_fare_outbound_id| bigint        | YES      | NULL    | FK → ticket_fares (double ticket) |
| visa_selling_price_id | bigint        | NO       | —       | FK → visa_selling_prices |
| regular_price         | decimal(10,2) | NO       | —       | |
| offer_price           | decimal(10,2) | YES      | NULL    | |
| service_charge        | decimal(10,2) | NO       | —       | |
| is_active             | boolean       | NO       | 1       | |
| is_double_ticket      | boolean       | NO       | 0       | |
| created_at            | timestamp     | YES      | NULL    | |
| updated_at            | timestamp     | YES      | NULL    | |

**Constraints:** CHECK (regular_price ≥ 0), CHECK (offer_price ≥ 0)

### `package_configurations`

Package templates for configuration.

| Column             | Type          | Nullable | Default | Description |
|--------------------|---------------|----------|---------|-------------|
| id                 | bigint        | NO       | —       | PK |
| name               | varchar(255)  | NO       | —       | |
| regular_price      | decimal(10,2) | NO       | 0.00    | |
| offer_price        | decimal(10,2) | YES      | NULL    | |
| service_charge     | decimal(10,2) | NO       | 0.00    | |
| is_active          | boolean       | NO       | 1       | |
| ticket_fare_id     | bigint        | NO       | —       | FK → ticket_fares |
| visa_selling_price_id | bigint     | NO       | —       | FK → visa_selling_prices |
| ticket_fare_inbound_id | bigint    | YES      | NULL    | FK → ticket_fares |
| ticket_fare_outbound_id | bigint   | YES      | NULL    | FK → ticket_fares |
| is_double_ticket   | boolean      | NO       | 0       | |
| created_at         | timestamp    | YES      | NULL    | |
| updated_at         | timestamp    | YES      | NULL    | |

### `ticket_fares`

Fare rates per route/travel class.

| Column                  | Type          | Nullable | Default | Description |
|-------------------------|---------------|----------|---------|-------------|
| id                      | bigint        | NO       | —       | PK |
| route_id                | bigint        | NO       | —       | FK → routes |
| airline_class_id        | bigint        | NO       | —       | FK → airline_classes |
| travel_class_id         | bigint        | YES      | NULL    | FK → classes (model: TravelClass) |
| ticket_type             | enum          | NO       | —       | `regular`, `offer`, `group` |
| effective_from          | date          | YES      | NULL    | |
| effective_to            | date          | YES      | NULL    | |
| net_fare                | decimal(14,6) | NO       | 0.00    | |
| selling_fare            | decimal(14,6) | NO       | 0.00    | |
| offer_price             | decimal(14,6) | YES      | NULL    | |
| child_fare_percentage   | decimal(5,2)  | YES      | NULL    | Percentage off adult fare for children |
| infant_fare_percentage  | decimal(5,2)  | YES      | NULL    | Percentage off adult fare for infants |
| with_meal               | boolean       | NO       | 0       | |
| is_active               | boolean       | NO       | 1       | |
| created_at              | timestamp     | YES      | NULL    | |
| updated_at              | timestamp     | YES      | NULL    | |

### `baggage_allowances`

Per-fare baggage rules.

| Column          | Type         | Nullable | Default | Description |
|-----------------|--------------|----------|---------|-------------|
| id              | bigint       | NO       | —       | PK |
| ticket_fare_id  | bigint       | NO       | —       | FK → ticket_fares |
| passenger_type  | enum         | NO       | —       | `adult`, `child`, `infant` |
| travel_direction| enum         | NO       | —       | `inbound`, `outbound` |
| allowance       | varchar(255) | NO       | —       | e.g., "30kg" |
| created_at      | timestamp    | YES      | NULL    | |
| updated_at      | timestamp    | YES      | NULL    | |

**UK:** ticket_fare_id + passenger_type + travel_direction

### `group_tickets`

Group ticket bookings.

| Column               | Type          | Nullable | Default | Description |
|----------------------|---------------|----------|---------|-------------|
| id                   | bigint        | NO       | —       | PK |
| ticket_fare_id       | bigint        | NO       | —       | FK → ticket_fares |
| inbound_date         | date          | YES      | NULL    | |
| outbound_date        | date          | YES      | NULL    | |
| pnr                  | varchar(50)   | YES      | NULL    | |
| ticket_qty           | integer       | NO       | —       | CHECK: ≥ 1 |
| is_refundable        | boolean       | NO       | 0       | |
| is_exchangable       | boolean       | NO       | 0       | |
| created_at           | timestamp     | YES      | NULL    | |
| updated_at           | timestamp     | YES      | NULL    | |

### `routes`

Flight routes (origin → destination, possibly multi-city with transits).

| Column               | Type          | Nullable | Default | Description |
|----------------------|---------------|----------|---------|-------------|
| id                   | bigint        | NO       | —       | PK |
| airline_id           | bigint        | NO       | —       | FK → airlines |
| from_city_id         | bigint        | NO       | —       | FK → city_codes (departure) |
| to_city_id           | bigint        | NO       | —       | FK → city_codes (destination) |
| return_city_id       | bigint        | YES      | NULL    | FK → city_codes (return point) |
| route_type           | enum          | NO       | —       | `oneway_inbound`, `oneway_outbound`, `round`, `multi_city` |
| flight_type          | enum          | NO       | —       | `direct`, `transit` |
| additional_gap       | integer       | YES      | NULL    | Days between flights (for multi-city) |
| created_at           | timestamp     | YES      | NULL    | |
| updated_at           | timestamp     | YES      | NULL    | |

### `route_multi_segments`

Segments for multi-city routes.

| Column          | Type         | Nullable | Default | Description |
|-----------------|--------------|----------|---------|-------------|
| id              | bigint       | NO       | —       | PK |
| route_id        | bigint       | NO       | —       | FK → routes |
| from_city_id    | bigint       | NO       | —       | FK → city_codes |
| to_city_id      | bigint       | NO       | —       | FK → city_codes |
| segment_direction | enum       | NO       | —       | `inbound`, `outbound` |
| created_at      | timestamp    | YES      | NULL    | |
| updated_at      | timestamp    | YES      | NULL    | |

### `route_transits`

Transit cities for routes with stops.

| Column         | Type         | Nullable | Default | Description |
|----------------|--------------|----------|---------|-------------|
| id             | bigint       | NO       | —       | PK |
| route_id       | bigint       | NO       | —       | FK → routes |
| transit_city_id| bigint       | NO       | —       | FK → city_codes |
| transit_time   | integer      | NO       | —       | Minutes |
| created_at     | timestamp    | YES      | NULL    | |
| updated_at     | timestamp    | YES      | NULL    | |

### `airlines`

Airlines serving routes.

| Column       | Type         | Nullable | Default | Description |
|--------------|--------------|----------|---------|-------------|
| id           | bigint       | NO       | —       | PK |
| name         | varchar(255) | NO       | —       | Airline name |
| code         | varchar(10)  | YES      | NULL    | IATA/ICAO code |
| created_at   | timestamp    | YES      | NULL    | |
| updated_at   | timestamp    | YES      | NULL    | |

### `classes`

Travel classes (table name: `classes`, model: `TravelClass`).

| Column       | Type         | Nullable | Default | Description |
|--------------|--------------|----------|---------|-------------|
| id           | bigint       | NO       | —       | PK |
| name         | varchar(255) | NO       | —       | e.g., "Economy", "Business" |
| created_at   | timestamp    | YES      | NULL    | |
| updated_at   | timestamp    | YES      | NULL    | |

### `airline_classes`

Pivot table: airlines × travel classes.

| Column        | Type    | Nullable | Default | Description |
|---------------|---------|----------|---------|-------------|
| id            | bigint  | NO       | —       | PK |
| airline_id    | bigint  | NO       | —       | FK → airlines |
| class_id      | bigint  | NO       | —       | FK → classes |
| created_at    | timestamp | YES    | NULL    | |
| updated_at    | timestamp | YES    | NULL    | |

**UK:** airline_id + class_id

### `airline_cities`

Pivot table: airlines × cities they serve.

| Column       | Type         | Nullable | Default | Description |
|--------------|--------------|----------|---------|-------------|
| id           | bigint       | NO       | —       | PK |
| airline_id   | bigint       | NO       | —       | FK → airlines |
| city_code_id | bigint       | NO       | —       | FK → city_codes |
| created_at   | timestamp    | YES      | NULL    | |
| updated_at   | timestamp    | YES      | NULL    | |

**UK:** airline_id + city_code_id

### `city_codes`

Airport/city reference data.

| Column      | Type         | Nullable | Default | Description |
|-------------|--------------|----------|---------|-------------|
| id          | bigint       | NO       | —       | PK |
| city_name   | varchar(255) | NO       | —       | e.g., "Dhaka", "Jeddah" |
| code        | varchar(10)  | NO       | —       | IATA code (indexed) |
| country     | varchar(100) | NO       | —       | Country name |
| created_at  | timestamp    | YES      | NULL    | |
| updated_at  | timestamp    | YES      | NULL    | |

### `travel_classes`

Alias/compat table for `classes`. In practice, the model `TravelClass` maps to
table `classes` — this table may exist for legacy compatibility.

---

## Fingerprint Tables

### `fingerprint_charges`

Per-district fingerprint service costs.

| Column            | Type          | Nullable | Default | Description |
|-------------------|---------------|----------|---------|-------------|
| id                | bigint        | NO       | —       | PK |
| district_id       | bigint        | NO       | —       | FK → districts (UK: district_id) |
| fingerprint_charge| decimal(10,6) | NO       | —       | Cost per fingerprint in BDT |
| user_id           | bigint        | NO       | —       | FK → users (who set the charge) |
| created_at        | timestamp     | YES      | NULL    | |
| updated_at        | timestamp     | YES      | NULL    | |

### `fingerprints`

One fingerprint record per booking (HasOne).

| Column            | Type          | Nullable | Default | Description |
|-------------------|---------------|----------|---------|-------------|
| id                | bigint        | NO       | —       | PK |
| booking_id        | bigint        | NO       | —       | FK → bookings (UK: booking_id) |
| deadline          | date          | NO       | —       | Fingerprint deadline |
| cost              | decimal(14,6) | NO       | 0.00    | Total fingerprint cost (SAR snapshot) |
| assigned_staff_id | bigint        | YES      | NULL    | FK → users (staff assigned) |
| created_at        | timestamp     | YES      | NULL    | |
| updated_at        | timestamp     | YES      | NULL    | |

### `fingerprint_details`

One fingerprint detail record per passenger per booking's fingerprint.

| Column       | Type         | Nullable | Default | Description |
|--------------|--------------|----------|---------|-------------|
| id           | bigint       | NO       | —       | PK |
| fingerprint_id | bigint     | NO       | —       | FK → fingerprints |
| passenger_id   | bigint     | NO       | —       | FK → passengers (UK: fingerprint_id + passenger_id) |
| status         | enum        | NO       | —       | `none`, `processing`, `approved`, `cancelled`, `done` |
| created_at     | timestamp  | YES      | NULL    | |
| updated_at     | timestamp  | YES      | NULL    | |

**Note:** `status` is NOT a database enum — it's enforced via the `FingerprintStatus`
enum cast in the model. The migration stores it as a string column.

### `fingerprint_detail_logs`

Audit log of fingerprint detail status changes.

| Column                    | Type          | Nullable | Default | Description |
|---------------------------|---------------|----------|---------|-------------|
| id                        | bigint        | NO       | —       | PK |
| fingerprint_detail_id     | bigint        | NO       | —       | FK → fingerprint_details (indexed) |
| user_id                   | bigint        | YES      | NULL    | FK → users |
| action                    | varchar(50)   | NO       | —       | e.g., "status_changed" |
| old_values                | json          | YES      | NULL    | |
| new_values                | json          | YES      | NULL    | |
| created_at                | timestamp     | NO       | —       | (no updated_at — append-only) |

### `fingerprint_cost_logs`

Audit log of fingerprint cost changes.

| Column           | Type          | Nullable | Default | Description |
|------------------|---------------|----------|---------|-------------|
| id               | bigint        | NO       | —       | PK |
| fingerprint_id   | bigint        | NO       | —       | FK → fingerprints (indexed) |
| cost             | decimal(14,6) | NO       | —       | New cost value |
| cost_updated_by  | bigint        | NO       | —       | FK → users |
| created_at       | timestamp     | YES      | NULL    | |
| updated_at       | timestamp     | YES      | NULL    | |

### `rescheduled_fingerprints`

Tracking of rescheduled fingerprint appointments.

| Column                | Type         | Nullable | Default | Description |
|-----------------------|--------------|----------|---------|-------------|
| id                    | bigint       | NO       | —       | PK |
| fingerprint_detail_id | bigint       | NO       | —       | FK → fingerprint_details |
| reason                | enum         | NO       | —       | `rescheduled_by_client`, `rescheduled_by_bmt`, `nfc_problem`, `others` |
| other_reason          | varchar(255) | YES      | NULL    | Free-text when reason = `others` |
| next_date             | date         | NO       | —       | Next scheduled date |
| occurrence            | integer      | NO       | —       | CHECK: ≥ 1 |
| remarks               | text         | YES      | NULL    | |
| created_at            | timestamp    | YES      | NULL    | |
| updated_at            | timestamp    | YES      | NULL    | |

---

## Visa Tables

### `visa_agents`

Visa processing agents.

| Column       | Type          | Nullable | Default | Description |
|--------------|---------------|----------|---------|-------------|
| id           | bigint        | NO       | —       | PK |
| name         | varchar(255)  | NO       | —       | |
| address      | text          | YES      | NULL    | |
| contacts     | text          | YES      | NULL    | |
| created_at   | timestamp     | YES      | NULL    | |
| updated_at   | timestamp     | YES      | NULL    | |

### `visa_agent_costs`

Per-agent visa processing costs.

| Column          | Type          | Nullable | Default | Description |
|-----------------|---------------|----------|---------|-------------|
| id              | bigint        | NO       | —       | PK |
| visa_agent_id   | bigint        | NO       | —       | FK → visa_agents (UK: visa_agent_id) |
| user_id         | bigint        | NO       | —       | FK → users |
| visa_agent_cost | decimal(14,6) | NO       | —       | Cost in BDT |
| created_at      | timestamp     | YES      | NULL    | |
| updated_at      | timestamp     | YES      | NULL    | |

### `visa_selling_prices`

Selling prices for visa services (per package).

| Column           | Type          | Nullable | Default | Description |
|------------------|---------------|----------|---------|-------------|
| id               | bigint        | NO       | —       | PK |
| package_id       | bigint        | YES      | NULL    | FK → packages (nullable — can be standalone) |
| selling_price    | decimal(14,6) | NO       | —       | |
| user_id          | bigint        | NO       | —       | FK → users |
| is_locked        | boolean       | NO       | 0       | When locked, price can't be changed |
| created_at       | timestamp     | YES      | NULL    | |
| updated_at       | timestamp     | YES      | NULL    | |

### `commission_agents`

Commission agents under visa agents.

| Column        | Type         | Nullable | Default | Description |
|---------------|--------------|----------|---------|-------------|
| id            | bigint       | NO       | —       | PK |
| visa_agent_id | bigint       | NO       | —       | FK → visa_agents |
| name          | varchar(255) | NO       | —       | |
| address       | text         | YES      | NULL    | |
| contacts      | text         | YES      | NULL    | |
| created_at    | timestamp    | YES      | NULL    | |
| updated_at    | timestamp    | YES      | NULL    | |

### `visa_submissions`

Visa submissions (one per passenger, latestOfMany).

| Column                  | Type          | Nullable | Default | Description |
|-------------------------|---------------|----------|---------|-------------|
| id                      | bigint        | NO       | —       | PK |
| passenger_id            | bigint        | NO       | —       | FK → passengers (UK: passenger_id) |
| visa_agent_id           | bigint        | YES      | NULL    | FK → visa_agents |
| commission_agent_id     | bigint        | YES      | NULL    | FK → commission_agents |
| visa_selling_price_id   | bigint        | YES      | NULL    | FK → visa_selling_prices |
| visa_number             | varchar(100)  | YES      | NULL    | |
| agent_commission        | decimal(14,6) | YES      | NULL    | Commission amount |
| net_visa_cost           | decimal(14,6) | NO       | 0.00    | Base cost in BDT |
| additional_cost         | decimal(14,6) | NO       | 0.00    | Extra fees |
| final_cost              | decimal(14,6) | NO       | 0.00    | net + additional |
| status                  | enum          | NO       | —       | `pending`, `submitted`, `issued`, `cancelled` |
| is_cancelled            | boolean       | NO       | 0       | |
| remarks                 | text          | YES      | NULL    | |
| created_at              | timestamp     | YES      | NULL    | |
| updated_at              | timestamp     | YES      | NULL    | |

### `visa_update_logs`

Audit log of visa submission status changes.

| Column           | Type         | Nullable | Default | Description |
|------------------|--------------|----------|---------|-------------|
| id               | bigint       | NO       | —       | PK |
| visa_submission_id | bigint    | NO       | —       | FK → visa_submissions |
| user_id          | bigint       | YES      | NULL    | FK → users |
| action           | varchar(50)  | NO       | —       | e.g., "status_changed" |
| old_values       | json         | YES      | NULL    | |
| new_values       | json         | YES      | NULL    | |
| created_at       | timestamp    | NO       | —       | (no updated_at) |

### `cancelled_submissions`

Visa submission cancellations.

| Column            | Type          | Nullable | Default | Description |
|-------------------|---------------|----------|---------|-------------|
| id                | bigint        | NO       | —       | PK |
| visa_submission_id | bigint       | NO       | —       | FK → visa_submissions (UK: visa_submission_id) |
| cancellation_fee  | decimal(10,2) | YES      | NULL    | CHECK: NULL or ≥ 0 |
| created_at        | timestamp     | YES      | NULL    | |
| updated_at        | timestamp     | YES      | NULL    | |

---

## Ticket Tables

### `ticket_agents`

Ticket-selling agents.

| Column       | Type         | Nullable | Default | Description |
|--------------|--------------|----------|---------|-------------|
| id           | bigint       | NO       | —       | PK |
| name         | varchar(255) | NO       | —       | |
| address      | text         | YES      | NULL    | |
| contacts     | text         | YES      | NULL    | |
| created_at   | timestamp    | YES      | NULL    | |
| updated_at   | timestamp    | YES      | NULL    | |

### `issued_tickets`

Issued airline tickets (soft-deleted on re-issue/refund).

| Column                | Type          | Nullable | Default | Description |
|-----------------------|---------------|----------|---------|-------------|
| id                    | bigint        | NO       | —       | PK |
| passenger_id          | bigint        | NO       | —       | FK → passengers |
| booking_id            | bigint        | YES      | NULL    | FK → bookings |
| user_id               | bigint        | NO       | —       | FK → users |
| ticket_agent_id       | bigint        | YES      | NULL    | FK → ticket_agents |
| ticket_fare_id        | bigint        | YES      | NULL    | FK → ticket_fares |
| group_ticket_id       | bigint        | YES      | NULL    | FK → group_tickets |
| ticket_number         | varchar(100)  | YES      | NULL    | |
| pnr                   | varchar(50)   | YES      | NULL    | |
| issued_date           | date          | YES      | NULL    | |
| inbound_date          | date          | YES      | NULL    | |
| outbound_date         | date          | YES      | NULL    | |
| selling_fare          | decimal(14,6) | NO       | 0.00    | |
| net_fare              | decimal(14,6) | NO       | 0.00    | |
| offer_price           | decimal(14,6) | NO       | 0.00    | |
| is_refundable         | boolean       | NO       | 0       | |
| is_exchangeable       | boolean       | NO       | 0       | |
| baggage_inbound       | varchar(255)  | YES      | NULL    | |
| baggage_outbound      | varchar(255)  | YES      | NULL    | |
| outbound_pending      | boolean       | NO       | 0       | |
| issue_type            | enum          | YES      | NULL    | `regular`, `additional`, `pending_outbound` |
| status                | enum          | NO       | —       | `pending`, `issued`, `re-issued`, `refunded`, `awaiting-group` |
| remarks               | text          | YES      | NULL    | |
| deleted_at            | timestamp     | YES      | NULL    | Soft delete |
| created_at            | timestamp     | YES      | NULL    | |
| updated_at            | timestamp     | YES      | NULL    | |

### `issued_ticket_logs`

Audit log for issued ticket actions.

| Column           | Type         | Nullable | Default | Description |
|------------------|--------------|----------|---------|-------------|
| id               | bigint       | NO       | —       | PK |
| issued_ticket_id | bigint       | NO       | —       | FK → issued_tickets |
| user_id          | bigint       | YES      | NULL    | FK → users |
| action           | varchar(50)  | NO       | —       | e.g., "issued", "re_issued", "refunded" |
| old_values       | json         | YES      | NULL    | |
| new_values       | json         | YES      | NULL    | |
| created_at       | timestamp    | NO       | —       | (no updated_at) |

### `re_issued_tickets`

Re-issued tickets (soft-deleted, keeps original issuance record).

| Column                  | Type          | Nullable | Default | Description |
|-------------------------|---------------|----------|---------|-------------|
| id                      | bigint        | NO       | —       | PK |
| user_id                 | bigint        | NO       | —       | FK → users |
| ticket_agent_id         | bigint        | YES      | NULL    | FK → ticket_agents |
| ticket_fare_id          | bigint        | YES      | NULL    | FK → ticket_fares |
| group_ticket_id         | bigint        | YES      | NULL    | FK → group_tickets |
| issued_ticket_id        | bigint        | YES      | NULL    | FK → issued_tickets |
| ticket_number           | varchar(100)  | YES      | NULL    | |
| pnr                     | varchar(50)   | YES      | NULL    | |
| re_issue_date           | date          | YES      | NULL    | |
| inbound_date            | date          | YES      | NULL    | |
| outbound_date           | date          | YES      | NULL    | |
| selling_fare          | decimal(14,6) | NO       | 0.00    | |
| net_fare                | decimal(14,6) | NO       | 0.00    | |
| offer_price             | decimal(14,6) | NO       | 0.00    | |
| is_refundable           | boolean       | NO       | 0       | |
| is_exchangeable         | boolean       | NO       | 0       | |
| baggage_inbound         | varchar(255)  | YES      | NULL    | |
| baggage_outbound        | varchar(255)  | YES      | NULL    | |
| re_issue_charge         | decimal(14,6) | NO       | 0.00    | |
| fare_difference         | decimal(14,6) | NO       | 0.00    | |
| other_costs             | decimal(14,6) | NO       | 0.00    | |
| service_charge          | decimal(14,6) | NO       | 0.00    | |
| payment_by              | enum          | YES      | NULL    | `customer`, `airline`, `employee` |
| reason_id               | bigint        | YES      | NULL    | FK → re_issue_refund_reasons |
| remarks                 | text          | YES      | NULL    | |
| deleted_at              | timestamp     | YES      | NULL    | Soft delete |
| created_at              | timestamp     | YES      | NULL    | |
| updated_at              | timestamp     | YES      | NULL    | |

### `refunded_tickets`

Refunded tickets (soft-deleted).

| Column                | Type          | Nullable | Default | Description |
|-----------------------|---------------|----------|---------|-------------|
| id                    | bigint        | NO       | —       | PK |
| user_id               | bigint        | NO       | —       | FK → users |
| ticket_agent_id       | bigint        | YES      | NULL    | FK → ticket_agents |
| ticket_fare_id        | bigint        | YES      | NULL    | FK → ticket_fares |
| group_ticket_id       | bigint        | YES      | NULL    | FK → group_tickets |
| issued_ticket_id      | bigint        | YES      | NULL    | FK → issued_tickets |
| ticket_number         | varchar(100)  | YES      | NULL    | |
| pnr                   | varchar(50)   | YES      | NULL    | |
| refund_date           | date          | YES      | NULL    | |
| inbound_date          | date          | YES      | NULL    | |
| outbound_date         | date          | YES      | NULL    | |
| selling_fare          | decimal(14,6) | NO       | 0.00    | |
| net_fare              | decimal(14,6) | NO       | 0.00    | |
| offer_price           | decimal(14,6) | NO       | 0.00    | |
| is_refundable         | boolean       | NO       | 0       | |
| is_exchangeable       | boolean       | NO       | 0       | |
| baggage_inbound       | varchar(255)  | YES      | NULL    | |
| baggage_outbound      | varchar(255)  | YES      | NULL    | |
| iata_refunded_amount  | decimal(14,6) | NO       | 0.00    | Amount refunded by airline |
| refund_to_customer    | decimal(14,6) | NO       | 0.00    | Amount refunded to customer |
| service_charge        | decimal(14,6) | NO       | 0.00    | |
| payment_by            | enum          | YES      | NULL    | `customer`, `airline`, `employee`, `company` |
| reason_id             | bigint        | YES      | NULL    | FK → re_issue_refund_reasons |
| remarks               | text          | YES      | NULL    | |
| deleted_at            | timestamp     | YES      | NULL    | Soft delete |
| created_at            | timestamp     | YES      | NULL    | |
| updated_at            | timestamp     | YES      | NULL    | |

### `ticket_requests`

Re-issue, refund, or additional ticket requests.

| Column              | Type          | Nullable | Default | Description |
|---------------------|---------------|----------|---------|-------------|
| id                  | bigint        | NO       | —       | PK |
| user_id             | bigint        | NO       | —       | FK → users |
| request_branch_id   | bigint        | YES      | NULL    | FK → branches |
| booking_id          | bigint        | YES      | NULL    | FK → bookings |
| passenger_id        | bigint        | YES      | NULL    | FK → passengers |
| issued_ticket_id    | bigint        | YES      | NULL    | FK → issued_tickets |
| request_type        | enum          | NO       | —       | `re_issue`, `refund`, `additional` |
| status              | enum          | NO       | —       | `pending`, `processed`, `rejected` |
| outbound_date       | date          | YES      | NULL    | |
| inbound_date        | date          | YES      | NULL    | |
| reason              | text          | YES      | NULL    | |
| remark              | text          | YES      | NULL    | |
| requested_at        | timestamp     | YES      | NULL    | |
| processed_at        | timestamp     | YES      | NULL    | |
| rejected_at         | timestamp     | YES      | NULL    | |
| result_re_issued_ticket_id  | bigint | YES      | NULL    | FK → re_issued_tickets |
| result_refunded_ticket_id   | bigint | YES      | NULL    | FK → refunded_tickets |
| result_issued_ticket_id      | bigint | YES      | NULL    | FK → issued_tickets |
| created_at          | timestamp     | YES      | NULL    | |
| updated_at          | timestamp     | YES      | NULL    | |

### `re_issue_refund_reasons`

Lookup table for re-issue and refund reasons.

| Column       | Type         | Nullable | Default | Description |
|--------------|--------------|----------|---------|-------------|
| id           | bigint       | NO       | —       | PK |
| reason_of    | enum         | NO       | —       | `re-issue`, `refund` |
| name         | varchar(255) | NO       | —       | Reason name |
| default_payment_by | enum   | YES      | NULL    | `customer`, `airline`, `employee`, `company` |
| created_at   | timestamp    | YES      | NULL    | |
| updated_at   | timestamp    | YES      | NULL    | |

---

## Financial Tables

### `invoices`

One invoice per booking.

| Column       | Type          | Nullable | Default | Description |
|--------------|---------------|----------|---------|-------------|
| id           | bigint        | NO       | —       | PK |
| booking_id   | bigint        | NO       | —       | FK → bookings (UK: booking_id, but also invoice_id string on bookings) |
| branch_id    | bigint        | NO       | —       | FK → branches |
| user_id      | bigint        | YES      | NULL    | FK → users |
| invoice_no   | string(50)    | YES      | NULL    | Matches bookings.invoice_id (e.g., `BRNC-XXXX26`) |
| total_amount | decimal(12,2) | NO       | —       | Total invoice amount (SAR) |
| paid_amount  | decimal(12,2) | NO       | 0.00    | Amount paid (SAR) |
| balance      | decimal(12,2) | NO       | —       | total - paid |
| status       | enum (cast)   | NO       | —       | InvoiceStatus: `pending`, `partial`, `paid`, `cancelled`, `refunded` |
| notes        | text          | YES      | NULL    | |
| audit_reason | —             | —        | —       | Transient property (not a DB column) — set in model, used by InvoiceObserver |
| created_at   | timestamp     | YES      | NULL    | |
| updated_at   | timestamp     | YES      | NULL    | |

**Note:** `audit_reason` is NOT a database column — it's a public property set
on the model instance before save, used by `InvoiceObserver` to record context
in the audit log.

### `payments`

Financial transactions (customer payments, agent payouts, refunds, deductions).

| Column                   | Type          | Nullable | Default | Description |
|--------------------------|---------------|----------|---------|-------------|
| id                       | bigint        | NO       | —       | PK |
| invoice_id               | bigint        | YES      | NULL    | FK → invoices |
| booking_id               | bigint        | YES      | NULL    | FK → bookings |
| branch_id                | bigint        | YES      | NULL    | FK → branches |
| user_id                  | bigint        | YES      | NULL    | FK → users |
| currency_rate_id         | bigint        | YES      | NULL    | FK → currency_rates |
| bank_id                  | bigint        | YES      | NULL    | FK → banks (receiver bank for customer payments) |
| sender_bank_id           | bigint        | YES      | NULL    | FK → banks (sender bank) |
| other_sender_bank        | varchar(255)  | YES      | NULL    | Free-text bank name (when sender_bank_id is null) |
| receiver_bank            | varchar(255)  | YES      | NULL    | Free-text receiver bank name |
| ticket_agent_id          | bigint        | YES      | NULL    | FK → ticket_agents (for agent payouts) |
| visa_agent_id            | bigint        | YES      | NULL    | FK → visa_agents (for agent payouts) |
| commission_agent_id      | bigint        | YES      | NULL    | FK → commission_agents (for agent payouts) |
| cancelled_booking_id     | bigint        | YES      | NULL    | FK → cancelled_bookings |
| passenger_id             | bigint        | YES      | NULL    | FK → passengers (added via migration) |
| refunded_ticket_id       | bigint        | YES      | NULL    | FK → refunded_tickets (added via migration) |
| re_issued_ticket_id      | bigint        | YES      | NULL    | FK → re_issued_tickets (added via migration) |
| payment_date             | date          | NO       | —       | |
| payment_method           | enum          | NO       | —       | `cash`, `bank` |
| transaction_id           | varchar(255)  | YES      | NULL    | Bank transaction reference |
| amount                   | decimal(12,2) | NO       | —       | Amount in SAR |
| bdt_amount               | decimal(12,2) | NO       | 0.00    | Amount in BDT (SAR × rate) |
| notes                    | text          | YES      | NULL    | |
| remarks                  | text          | YES      | NULL    | |
| payment_referral         | varchar(255)  | YES      | NULL    | |
| created_at               | timestamp     | YES      | NULL    | |
| updated_at               | timestamp     | YES      | NULL    | |

### `vouchers`

Ledger entries — 1:1 with payments.

| Column                   | Type          | Nullable | Default | Description |
|--------------------------|---------------|----------|---------|-------------|
| id                       | bigint        | NO       | —       | PK |
| voucher_id               | varchar(50)   | NO       | —       | UK: format `VCH-YYYYMMDD-XXXX` |
| invoice_id               | bigint        | YES      | NULL    | FK → invoices |
| booking_id               | bigint        | YES      | NULL    | FK → bookings |
| payment_id               | bigint        | NO       | —       | FK → payments (UK: payment_id) |
| branch_id                | bigint        | YES      | NULL    | FK → branches |
| user_id                  | bigint        | YES      | NULL    | FK → users |
| currency_rate_id         | bigint        | YES      | NULL    | FK → currency_rates |
| bank_id                  | bigint        | YES      | NULL    | FK → banks |
| ticket_agent_id          | bigint        | YES      | NULL    | FK → ticket_agents |
| visa_agent_id            | bigint        | YES      | NULL    | FK → visa_agents |
| commission_agent_id      | bigint        | YES      | NULL    | FK → commission_agents |
| transaction_type_id      | bigint        | NO       | —       | FK → transaction_types |
| cancelled_booking_id     | bigint        | YES      | NULL    | FK → cancelled_bookings |
| payment_date             | date          | NO       | —       | |
| payment_method           | enum          | NO       | —       | `cash`, `bank` |
| transaction_id           | varchar(255)  | YES      | NULL    | |
| amount                   | decimal(12,2) | NO       | —       | Amount in SAR |
| bdt_amount               | decimal(12,2) | NO       | 0.00    | Amount in BDT |
| notes                    | text          | YES      | NULL    | |
| created_at               | timestamp     | YES      | NULL    | |
| updated_at               | timestamp     | YES      | NULL    | |

### `transaction_types`

9 types controlling voucher debit/credit direction.

| Column   | Type         | Nullable | Default | Description |
|----------|--------------|----------|---------|-------------|
| id       | bigint       | NO       | —       | PK |
| name     | varchar(255) | NO       | —       | UK: type name |
| type     | enum         | NO       | —       | `debit` or `credit` |
| created_at | timestamp  | YES      | NULL    | |
| updated_at | timestamp  | YES      | NULL    | |

| Transaction Type              | Direction | Context                          |
|------------------------------|-----------|----------------------------------|
| Initial Payment              | credit    | Customer pays upfront            |
| Due Collection              | credit    | Customer pays remaining balance  |
| Customer Refund             | debit     | Refund to customer               |
| Ticket Refund - Payment     | debit     | Refund from ticket payment       |
| Ticket Refund - Re-issue    | debit     | Refund from re-issued ticket     |
| Ticket Agent Payment        | debit     | Payout to ticket agent           |
| Visa Agent Payment          | debit     | Payout to visa agent             |
| Commission Agent Payment    | debit     | Payout to commission agent       |
| Service Charge Deduction    | credit    | Agency service fee revenue       |

### `currency_rates`

SAR→BDT exchange rates.

| Column    | Type          | Nullable | Default | Description |
|-----------|---------------|----------|---------|-------------|
| id        | bigint        | NO       | —       | PK |
| user_id   | bigint        | NO       | —       | FK → users (who set the rate) |
| rate      | decimal(10,4) | NO       | —       | SAR → BDT conversion rate |
| created_at| timestamp     | YES      | NULL    | Used for "effective on date" resolution |
| updated_at| timestamp     | YES      | NULL    | |

### `banks`

Banks for bank payments.

| Column       | Type         | Nullable | Default | Description |
|--------------|--------------|----------|---------|-------------|
| id           | bigint       | NO       | —       | PK |
| name         | varchar(255) | NO       | —       | Bank name |
| description  | text         | YES      | NULL    | |
| currency     | enum         | NO       | —       | `SAR`, `BDT` |
| location     | enum         | NO       | —       | `KSA`, `BD` |
| branch_id    | bigint       | YES      | NULL    | FK → branches |
| created_at   | timestamp    | YES      | NULL    | |
| updated_at   | timestamp    | YES      | NULL    | |

---

## Audit Log Tables

### `booking_update_logs`

| Column               | Type    | Nullable | Description |
|----------------------|---------|----------|-------------|
| id                   | bigint  | NO       | PK |
| booking_id           | bigint  | NO       | FK → bookings (indexed) |
| user_id              | bigint  | NO       | FK → users |
| action               | string  | NO       | `created`, `updated`, `deleted` |
| booking_invoice_id   | string  | YES      | Snapshot of invoice_id |
| old_values           | json    | YES      | |
| new_values           | json    | YES      | |
| created_at           | timestamp | NO    | (no updated_at — append-only) |

### `passenger_update_logs`

| Column               | Type    | Nullable | Description |
|----------------------|---------|----------|-------------|
| id                   | bigint  | NO       | PK |
| passenger_id         | bigint  | NO       | FK → passengers (indexed) |
| user_id              | bigint  | NO       | FK → users |
| action               | string  | NO       | |
| passport_no          | string  | YES      | Snapshot of passport number |
| old_values           | json    | YES      | |
| new_values           | json    | YES      | |
| created_at           | timestamp | NO    | (no updated_at) |

### `invoice_update_logs`

| Column               | Type    | Nullable | Description |
|----------------------|---------|----------|-------------|
| id                   | bigint  | NO       | PK |
| invoice_id           | bigint  | NO       | FK → invoices |
| user_id              | bigint  | NO       | FK → users |
| action               | string  | NO       | |
| reason               | string  | YES      | Audit reason from Invoice::audit_reason |
| old_values           | json    | YES      | |
| new_values           | json    | YES      | |
| created_at           | timestamp | NO    | (no updated_at) |

---

## Other Tables

### `flight_date_gaps`

Date gap configuration (used in passenger flight date forms).

| Column       | Type         | Nullable | Default | Description |
|--------------|--------------|----------|---------|-------------|
| id           | bigint       | NO       | —       | PK |
| gap          | integer      | NO       | —       | Days (e.g., 7, 10, 14, 30) |
| created_at   | timestamp    | YES      | NULL    | |
| updated_at   | timestamp    | YES      | NULL    | |

### `offices`

**Deprecated table.** Offices were merged into `branches` via
`MergeOfficesIntoBranchesSeeder`. This table may exist in schemas that were not
migrated from `branches`-first setup. Do not use the `Office` model in new code.

---

## Laravel Default Tables

These are standard Laravel framework tables, not part of the Umrah App domain:

| Table                    | Purpose |
|--------------------------|---------|
| `cache`, `cache_locks`   | Caching (CACHE_STORE=database in local dev) |
| `failed_jobs`            | Failed queue jobs |
| `jobs`, `job_batches`    | Queue jobs |
| `password_reset_tokens`  | Password reset flow |
| `sessions`               | Session storage (SESSION_DRIVER=database in local dev) |

---

## Indexes

Key indexes across the schema (beyond primary keys and foreign key indexes):

| Table                    | Index columns                    |
|--------------------------|----------------------------------|
| `branches`               | branch_code (unique)             |
| `bookings`               | booking_branch_id, fingerprint_branch_id, district_id, fingerprint_charge_id, package_id, date_from |
| `passengers`             | booking_id, booking_id + first_name, booking_id + passport_no |
| `invoices`               | booking_id (unique), branch_id |
| `payments`               | invoice_id, booking_id, branch_id, payment_date |
| `vouchers`               | voucher_id (unique), payment_id (unique), transaction_type_id, branch_id |
| `visa_submissions`       | passenger_id (unique) |
| `visa_update_logs`       | visa_submission_id |
| `fingerprints`           | booking_id (unique) |
| `fingerprint_details`    | fingerprint_id + passenger_id (unique), status |
| `fingerprint_detail_logs`| fingerprint_detail_id |
| `fingerprint_cost_logs`  | fingerprint_id |
| `rescheduled_fingerprints` | fingerprint_detail_id |
| `issued_tickets`         | passenger_id |
| `issued_ticket_logs`     | issued_ticket_id |
| `ticket_requests`        | booking_id, passenger_id, status, request_type |
| `re_issued_tickets`      | issued_ticket_id |
| `refunded_tickets`       | issued_ticket_id |
| `currency_rates`         | created_at |
| `transaction_types`      | name (unique) |
| `roles`                  | name (unique) |
| `city_codes`             | code (index) |
| `fingerprint_charges`    | district_id (unique) |
| `visa_agent_costs`       | visa_agent_id (unique) |
| `booking_update_logs`    | booking_id |
| `passenger_update_logs`  | passenger_id |
| `cancelled_submissions`  | visa_submission_id (unique) |
| `group_tickets`          | pnr |
| `documents`              | owner_type + owner_id (index) |
| `baggage_allowances`     | ticket_fare_id + passenger_type + travel_direction (unique) |

---

## Conventions

### Migration Naming

Migrations follow `YYYY_MM_DD_HHMMSS_create_<table>_table.php`. Incremental
schema changes use additional migrations (e.g.,
`2026_06_25_110600_modify_payments_table.php`) rather than modifying the
original create migration.

### Column Conventions

- All tables have `id` (bigint, auto-increment) as primary key
- All tables have `created_at` / `updated_at` timestamps, except:
  - Audit log tables: `created_at` only (no `updated_at`, via `const UPDATED_AT = null`)
  - `stay_duration_limits`: both timestamps (seeded with fixed data)
- All monetary values stored as `decimal(14,6)` or `decimal(10,2)` or `decimal(12,2)`
- Currency conversion stored as `decimal(10,4)` in `currency_rates.rate`
- Enum columns are stored as strings (not MySQL native ENUM) — the enum casting
  happens in the model via `$casts`
- Soft-deleted models use a `deleted_at` timestamp column

### FK Constraint Conventions

- New FK constraints use `restrictOnDelete()` (no cascade) for parent entities
  like ticket_fares, visa_agents, etc.
- `restrictOnUpdate()` + `onUpdate('cascade')` are used for lookup-type FKs
  (city_codes, airlines, classes)
- `nullOnDelete()` is used for nullable FK columns in payments/vouchers
  (e.g., ticket_agent_id, refunded_ticket_id)
- `cascadeOnDelete()` is used for audit log tables (deleting a parent deletes its logs)

---

## Navigation

Previous: [Architecture](02-architecture.md) ·
Next: [Domain Reference](08-domain-reference.md) ·
Full index: [README](README.md)
