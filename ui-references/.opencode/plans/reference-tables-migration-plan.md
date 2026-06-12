# Database Migration Plan: Reference Tables

> **Note**: This file contains plans for multiple independent migration phases. Scroll to relevant sections.

## Overview

This plan outlines the database migrations for 9 reference tables with proper ordering based on foreign key dependencies.

---

## Dependency Analysis

### Tables with NO dependencies (create first):
- `branches`, `offices`, `banks`, `districts`, `city_codes`, `airlines`, `classes`

### Tables with dependencies (create last):
- `airline_cities` → depends on `airlines`, `city_codes`
- `airline_classes` → depends on `airlines`, `classes`

---

## Migration Order (Phase 1: Independent Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | branches | none | `php artisan make:migration create_branches_table` |
| 2 | offices | none | `php artisan make:migration create_offices_table` |
| 3 | banks | none | `php artisan make:migration create_banks_table` |
| 4 | districts | none | `php artisan make:migration create_districts_table` |
| 5 | city_codes | none | `php artisan make:migration create_city_codes_table` |
| 6 | airlines | none | `php artisan make:migration create_airlines_table` |
| 7 | classes | none | `php artisan make:migration create_classes_table` |

## Migration Order (Phase 2: Pivot Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 8 | airline_cities | airlines, city_codes | `php artisan make:migration create_airline_cities_table` |
| 9 | airline_classes | airlines, classes | `php artisan make:migration create_airline_classes_table` |

---

## Design Decisions

### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `unsignedBigInteger()` to match

### 2. Foreign Key Constraints
- `onDelete('cascade')` on all pivot table foreign keys
- `onUpdate('cascade')` on all foreign keys for referential integrity
- No cascading on independent tables (branches, offices, banks, etc.) to prevent accidental data loss

### 3. Unique Constraints (Pivot Tables)
- **airline_cities**: Composite unique on `(airline_id, city_code_id)` to prevent duplicate airline-city combinations
- **airline_classes**: Composite unique on `(airline_id, class_id)` to prevent duplicate airline-class combinations

### 4. Timestamps
- **Independent tables**: Include `timestamps()` (created_at, updated_at)
- **Pivot tables**: Include `timestamps()` per user request for audit purposes

### 5. Indexes
- Index on `airlines.code` for faster airline lookups
- Index on `city_codes.code` for faster city code lookups
- Index on foreign keys in pivot tables for join optimization

---

## Migration File Details

### 1. branches table
```php
Schema::create('branches', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('address');
    $table->string('contacts');
    $table->timestamps();
});
```

### 2. offices table
```php
Schema::create('offices', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('address');
    $table->string('contacts');
    $table->timestamps();
});
```

### 3. banks table
```php
Schema::create('banks', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('description')->nullable();
    $table->timestamps();
});
```

**Latest Update**: `currency` field was added via a new migration (see [Banks Schema Update](#banks-schema-update) section).

### 4. districts table
```php
Schema::create('districts', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('division');
    $table->timestamps();
});
```

### 5. city_codes table
```php
Schema::create('city_codes', function (Blueprint $table) {
    $table->id();
    $table->string('city_name');
    $table->string('code');
    $table->string('country');
    $table->index('code'); // Index for faster lookups
    $table->timestamps();
});
```

### 6. airlines table
```php
Schema::create('airlines', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code');
    $table->index('code'); // Index for faster lookups
    $table->timestamps();
});
```

### 7. classes table
```php
Schema::create('classes', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

### 8. airline_cities table (Pivot)
```php
Schema::create('airline_cities', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('airline_id');
    $table->unsignedBigInteger('city_code_id');
    $table->foreign('airline_id')->references('id')->on('airlines')->onDelete('cascade')->onUpdate('cascade');
    $table->foreign('city_code_id')->references('id')->on('city_codes')->onDelete('cascade')->onUpdate('cascade');
    $table->unique(['airline_id', 'city_code_id']); // Prevent duplicates
    $table->index('airline_id');
    $table->index('city_code_id');
    $table->timestamps();
});
```

### 9. airline_classes table (Pivot)
```php
Schema::create('airline_classes', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('airline_id');
    $table->unsignedBigInteger('class_id');
    $table->foreign('airline_id')->references('id')->on('airlines')->onDelete('cascade')->onUpdate('cascade');
    $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade')->onUpdate('cascade');
    $table->unique(['airline_id', 'class_id']); // Prevent duplicates
    $table->index('airline_id');
    $table->index('class_id');
    $table->timestamps();
});
```

---

## Safe Execution Plan

### Option 1: Full Migration Run (Recommended)
Run all migrations at once after creating all files:
```bash
php artisan migrate
```

### Option 2: Partial/Step-by-Step Execution
If you need to test incrementally:

```bash
# Step 1: Create all migration files first
php artisan make:migration create_branches_table
php artisan make:migration create_offices_table
php artisan make:migration create_banks_table
php artisan make:migration create_districts_table
php artisan make:migration create_city_codes_table
php artisan make:migration create_airlines_table
php artisan make:migration create_classes_table
php artisan make:migration create_airline_cities_table
php artisan make:migration create_airline_classes_table

# Step 2: Verify migration files content

# Step 3: Run migrations
php artisan migrate

# Step 4: Verify tables created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('airlines');
```

### Option 3: Rollback Plan
If something goes wrong:
```bash
# Rollback last migration
php artisan migrate:rollback

# Rollback all (if needed)
php artisan migrate:fresh
```

---

## Rollback Considerations

- **Pivot tables**: Safe to drop since they only contain mappings
- **Independent tables**: Consider data before dropping
- The cascade delete on pivot tables will automatically clean up related records

---

## Summary

| Category | Count |
|----------|-------|
| Total Tables | 9 |
| Independent Tables | 7 |
| Pivot Tables | 2 |
| Foreign Keys | 4 |
| Unique Constraints | 2 |
| Indexes | 5 |

---

## Phase 3: Users Table Modification

### Overview
Add `branch_id` and `office_id` foreign keys to the existing `users` table.

### Migration File Name
`2026_05_03_XXXXXX_add_branch_id_and_office_id_to_users_table.php`

### Artisan Command
```bash
php artisan make:migration add_branch_id_and_office_id_to_users_table
```

### Schema Structure

#### UP Method
```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->foreignId('branch_id')
            ->nullable()
            ->constrained('branches')
            ->nullOnDelete()
            ->onUpdate('cascade')
            ->after('password');

        $table->foreignId('office_id')
            ->nullable()
            ->constrained('offices')
            ->nullOnDelete()
            ->onUpdate('cascade')
            ->after('branch_id');
    });
}
```

#### DOWN Method
```php
public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropForeign(['branch_id']);
        $table->dropForeign(['office_id']);
        $table->dropColumn(['branch_id', 'office_id']);
    });
}
```

### Design Decisions

| Decision | Justification |
|----------|---------------|
| Nullable fields | Existing users may not have branch/office assigned |
| `nullOnDelete()` | If branch/office deleted, user is preserved with NULL values |
| `onUpdate('cascade')` | Auto-update FK if referenced ID changes |
| Use `foreignId()` | Creates unsignedBigInteger + FK constraint + index |
| Column placement | Use `after('password')` for logical grouping |

### Safe Execution Steps

```bash
# Step 1: Create migration
php artisan make:migration add_branch_id_and_office_id_to_users_table

# Step 2: Run migration
php artisan migrate

# Step 3: Verify columns
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('users');
```

### Risks & Edge Cases

| Risk | Mitigation |
|------|------------|
| Existing users without branch/office | Both fields are nullable |
| Branch/office deleted | `nullOnDelete()` preserves user record |

---

## Additional Reference Tables (Phase 2)

### Overview

This phase adds 7 new reference tables with proper ordering based on foreign key dependencies.

---

## Dependency Analysis

### Tables with NO dependencies (create first):
- `visa_agents`, `ticket_agents`, `customers`

### Tables with dependencies (create last):
- `commission_agents` → depends on `visa_agents`
- `fingerprint_charges` → depends on `districts`, `users`
- `visa_agent_costs` → depends on `visa_agents`, `users`
- `visa_selling_prices` → depends on `users`

---

## Migration Order (Phase 2A: Independent Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | visa_agents | none | `php artisan make:migration create_visa_agents_table` |
| 2 | ticket_agents | none | `php artisan make:migration create_ticket_agents_table` |
| 3 | customers | none | `php artisan make:migration create_customers_table` |

## Migration Order (Phase 2B: Dependent Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 4 | commission_agents | visa_agents | `php artisan make:migration create_commission_agents_table` |
| 5 | fingerprint_charges | districts, users | `php artisan make:migration create_fingerprint_charges_table` |
| 6 | visa_agent_costs | visa_agents, users | `php artisan make:migration create_visa_agent_costs_table` |
| 7 | visa_selling_prices | users | `php artisan make:migration create_visa_selling_prices_table` |

---

## Design Decisions

### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `foreignId()->constrained()` for proper constraints

### 2. Foreign Key Constraints
- **commission_agents**: `restrictOnDelete()` on `visa_agent_id` - prevents visa_agent deletion if commission agents exist
- **fingerprint_charges**: `restrictOnDelete()` on both `district_id` and `user_id` - prevents district/user deletion if charges exist
- **visa_agent_costs**: `restrictOnDelete()` on both `visa_agent_id` and `user_id` - prevents visa_agent/user deletion if costs exist
- **visa_selling_prices**: `restrictOnDelete()` on `user_id` - prevents user deletion if selling prices exist
- `onUpdate('cascade')` on all foreign keys for referential integrity

### 3. Enum Handling (Laravel 9.3+)
- **customers.iqama_type**: Uses PHP 8.1 backed enum defined in `app/Enums/IqamaType.php`:
```php
<?php

namespace App\Enums;

enum IqamaType: string
{
    case SELF = 'self';
    case REFERRAL = 'referral';
}
```
- Database column type: `enum('self', 'referral')` or `string` with enum cast

### 4. Monetary Fields
- **fingerprint_charges.fingerprint_charge**: `decimal(10, 2)` with `CHECK (fingerprint_charge >= 0)` constraint
- **visa_agent_costs.visa_agent_cost**: `decimal(10, 2)` with `CHECK (visa_agent_cost >= 0)` constraint
- **visa_selling_prices.selling_price**: `decimal(10, 2)` with `CHECK (selling_price >= 0)` constraint
- Prevents negative monetary values at database level
- Justification: User preference for cleaner display + data integrity protection

### 5. Timestamps
- **All tables**: Include `timestamps()` (created_at, updated_at) for audit purposes
- Independent tables: track entity lifecycle
- Dependent tables: track when charges/costs/prices were set

### 6. Indexes
- **customers**: Index on `iqama_no`, `passport_no` for frequent lookups
- Note: Foreign key constraints auto-create indexes; explicit index calls removed for FK columns

### 7. Unique Constraints
- **customers**: Unique on `iqama_no`, unique on `passport_no`
- **fingerprint_charges**: Unique on `district_id` (one charge per district)
- **visa_agent_costs**: Unique on `visa_agent_id` (one cost per visa agent)
- **visa_selling_prices**: No unique constraint required

### 8. Nullable Fields
- **customers**: `ref_iqama_no`, `ref_mobile_no`, `ref_iqama_doc` are nullable (referral info not always required)
- Other nullable fields follow the pattern established in Phase 1

---

## Migration File Details

### 1. visa_agents table
```php
Schema::create('visa_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('address');
    $table->string('contacts');
    $table->timestamps();
});
```

### 2. ticket_agents table
```php
Schema::create('ticket_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('address');
    $table->string('contacts');
    $table->timestamps();
});
```

### 3. customers table
```php
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('iqama_type', ['self', 'referral']);
    $table->string('passport_no');
    $table->string('iqama_no');
    $table->string('mobile_no');
    $table->string('ref_iqama_no')->nullable();
    $table->string('ref_mobile_no')->nullable();
    $table->string('ref_iqama_doc', 512)->nullable();
    $table->string('address');
    $table->unique('iqama_no');
    $table->unique('passport_no');
    $table->timestamps();
});
```

### 4. commission_agents table
```php
Schema::create('commission_agents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('visa_agent_id')
        ->constrained('visa_agents')
        ->restrictOnDelete()
        ->onUpdate('cascade');
    $table->string('name');
    $table->string('address');
    $table->string('contacts');
    $table->timestamps();
});
```

### 5. fingerprint_charges table
```php
Schema::create('fingerprint_charges', function (Blueprint $table) {
    $table->id();
    $table->foreignId('district_id')
        ->constrained('districts')
        ->restrictOnDelete()
        ->onUpdate('cascade');
    $table->foreignId('user_id')
        ->constrained('users')
        ->restrictOnDelete()
        ->onUpdate('cascade');
    $table->decimal('fingerprint_charge', 10, 2);
    $table->unique('district_id');
    $table->timestamps();
});
// Note: Add CHECK constraint via DB statement after table creation
DB::statement('ALTER TABLE fingerprint_charges ADD CONSTRAINT fingerprint_charge_positive CHECK (fingerprint_charge >= 0)');
```

### 6. visa_agent_costs table
```php
Schema::create('visa_agent_costs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('visa_agent_id')
        ->constrained('visa_agents')
        ->restrictOnDelete()
        ->onUpdate('cascade');
    $table->foreignId('user_id')
        ->constrained('users')
        ->restrictOnDelete()
        ->onUpdate('cascade');
    $table->decimal('visa_agent_cost', 10, 2);
    $table->unique('visa_agent_id');
    $table->timestamps();
});
// Note: Add CHECK constraint via DB statement after table creation
DB::statement('ALTER TABLE visa_agent_costs ADD CONSTRAINT visa_agent_cost_positive CHECK (visa_agent_cost >= 0)');
```

### 7. visa_selling_prices table
```php
Schema::create('visa_selling_prices', function (Blueprint $table) {
    $table->id();
    $table->decimal('selling_price', 10, 2);
    $table->foreignId('user_id')
        ->constrained('users')
        ->restrictOnDelete()
        ->onUpdate('cascade');
    $table->timestamps();
});
// Note: Add CHECK constraint via DB statement after table creation
DB::statement('ALTER TABLE visa_selling_prices ADD CONSTRAINT selling_price_positive CHECK (selling_price >= 0)');
```

---

## Safe Execution Plan

### Option 1: Full Migration Run (Recommended)
Run all Phase 2 migrations at once after creating all files:
```bash
php artisan migrate
```

### Option 2: Partial/Step-by-Step Execution
If you need to test incrementally:

```bash
# Step 1: Create all migration files first
php artisan make:migration create_visa_agents_table
php artisan make:migration create_ticket_agents_table
php artisan make:migration create_customers_table
php artisan make:migration create_commission_agents_table
php artisan make:migration create_fingerprint_charges_table
php artisan make:migration create_visa_agent_costs_table
php artisan make:migration create_visa_selling_prices_table

# Step 2: Verify migration files content

# Step 3: Run migrations
php artisan migrate

# Step 4: Verify tables created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('customers');
```

### Option 3: Execute by Path
If you need to run only Phase 2 migrations:

```bash
# Run only Phase 2 migrations (adjust date range as needed)
php artisan migrate --path=/database/migrations/2026_05_04_*
```

### Option 4: Rollback Plan
If something goes wrong:
```bash
# Rollback last migration
php artisan migrate:rollback

# Rollback all (if needed)
php artisan migrate:fresh
```

---

## Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|---------------|--------------|
| visa_agents | Independent | Low - no dependencies |
| ticket_agents | Independent | Low - no dependencies |
| customers | Independent | Low - no dependencies |
| commission_agents | restrictOnDelete | Medium - prevents visa_agent deletion |
| fingerprint_charges | restrictOnDelete | Medium - prevents district/user deletion |
| visa_agent_costs | restrictOnDelete | Medium - prevents visa_agent/user deletion |
| visa_selling_prices | restrictOnDelete | Medium - prevents user deletion |

**Note**: The `restrictOnDelete()` constraints mean you cannot delete parent records (districts, users, visa_agents) if child records exist. You must first delete the child records or modify them to allow parent deletion.

---

## Summary (Phase 2)

| Category | Count |
|----------|-------|
| Total Tables | 7 |
| Independent Tables | 3 |
| Dependent Tables | 4 |
| Foreign Keys | 8 |
| Unique Constraints | 4 |
| Indexes | 2 |

---

### Combined Summary (All Phases)

| Category | Count |
|----------|-------|
| Total Tables | 16 |
| Total Foreign Keys | 12 |
| Total Unique Constraints | 6 |
| Total Indexes | 7 |

---

## Enum Definition Required

Create the following enum before running migrations:

**File**: `app/Enums/IqamaType.php`

```php
<?php

namespace App\Enums;

enum IqamaType: string
{
    case SELF = 'self';
    case REFERRAL = 'referral';
}
```

**Model Cast** (in Customer model):

```php
protected $casts = [
    'iqama_type' => IqamaType::class,
];
```

---

## Route Tables (Phase 3)

### Overview

This phase adds 3 route-related tables with proper ordering based on foreign key dependencies.

---

### Dependency Analysis

### Tables with dependencies (create in order):
- **routes** → depends on `airlines`, `city_codes`
- **route_multi_segments** → depends on `routes`, `city_codes`
- **route_transits** → depends on `routes`, `city_codes`

---

### Migration Order (Phase 3: Route Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | routes | airlines, city_codes | `php artisan make:migration create_routes_table` |
| 2 | route_multi_segments | routes, city_codes | `php artisan make:migration create_route_multi_segments_table` |
| 3 | route_transits | routes, city_codes | `php artisan make:migration create_route_transits_table` |

---

### Design Decisions

#### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `unsignedBigInteger()` to match

#### 2. Foreign Key Constraints
| Table | Column | References | Delete Behavior |
|-------|--------|------------|------------------|
| routes | airline_id | airlines.id | `restrictOnDelete()` |
| routes | from_city_id | city_codes.id | `restrictOnDelete()` |
| routes | to_city_id | city_codes.id | `restrictOnDelete()` |
| routes | return_city_id | city_codes.id | `restrictOnDelete()` |
| route_multi_segments | route_id | routes.id | `restrictOnDelete()` |
| route_multi_segments | from_city_id | city_codes.id | `restrictOnDelete()` |
| route_multi_segments | to_city_id | city_codes.id | `restrictOnDelete()` |
| route_transits | route_id | routes.id | `restrictOnDelete()` |
| route_transits | transit_city_id | city_codes.id | `restrictOnDelete()` |

- `onUpdate('cascade')` on all foreign keys
- `restrictOnDelete()` prevents accidental deletion of parent records with existing children

#### 3. Conditional Nullability Rules (IMPORTANT)

| Column | Nullable in Schema | Business Logic Requirement |
|--------|-------------------|----------------------------|
| `from_city_id` | YES | REQUIRED when `route_type != multi_city`; NOT used when `route_type = multi_city` |
| `to_city_id` | YES | REQUIRED when `route_type != multi_city`; NOT used when `route_type = multi_city` |
| `return_city_id` | YES | REQUIRED ONLY when `route_type = round` |

**Note**: These conditional requirements CANNOT be fully enforced at database level. Must be documented and enforced in:
- Application validation layer
- Form/Request validation
- Model observers

#### 4. Enum Handling

**Database Enums:**
```php
// routes.route_type
$table->enum('route_type', ['oneway_inbound', 'oneway_outbound', 'round', 'multi_city']);

// routes.flight_type
$table->enum('flight_type', ['direct', 'transit']);

// route_multi_segments.segment_direction
$table->enum('segment_direction', ['inbound', 'outbound']);
```

**Recommended PHP Enums** (for model casting):

**File**: `app/Enums/RouteType.php`
```php
<?php

namespace App\Enums;

enum RouteType: string
{
    case ONEWAY_INBOUND = 'oneway_inbound';
    case ONEWAY_OUTBOUND = 'oneway_outbound';
    case ROUND = 'round';
    case MULTI_CITY = 'multi_city';
}
```

**File**: `app/Enums/FlightType.php`
```php
<?php

namespace App\Enums;

enum FlightType: string
{
    case DIRECT = 'direct';
    case TRANSIT = 'transit';
}
```

**File**: `app/Enums/SegmentDirection.php`
```php
<?php

namespace App\Enums;

enum SegmentDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
```

#### 5. transit_time Data Type Decision

**Decision**: Use `unsignedInteger` (minutes)

| Option | Pros | Cons |
|--------|------|------|
| `unsignedInteger` (minutes) | Simple math operations, timezone-agnostic, easy aggregation in queries | Requires conversion for display |
| `time` type | Native time representation | Database-specific behavior, timezone issues, harder to aggregate |
| `varchar` | Flexible | No validation, harder to query |

**Justification**: Integer minutes is the industry standard for flight durations. It allows:
- Easy sorting/filtering (e.g., flights under 4 hours)
- Simple aggregation (total transit time)
- Cleaner comparison operations
- No timezone complications

#### 6. Business Logic Notes

| Route Type | Use Fields | Multi-Segments |
|------------|------------|----------------|
| `oneway_inbound` | from_city_id, to_city_id | No |
| `oneway_outbound` | from_city_id, to_city_id | No |
| `round` | from_city_id, to_city_id, return_city_id | No |
| `multi_city` | from_city_id/to_city_id NOT used | YES - use route_multi_segments |

| Flight Type | Transit Records |
|-------------|-----------------|
| `direct` | No entries in route_transits |
| `transit` | One or more entries in route_transits |

#### 7. Indexes
- Foreign key constraints auto-create indexes
- No redundant manual indexes needed

---

### Migration File Details

#### 1. routes table

```php
// UP
public function up(): void
{
    Schema::create('routes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('airline_id');
        $table->enum('route_type', ['oneway_inbound', 'oneway_outbound', 'round', 'multi_city']);
        $table->enum('flight_type', ['direct', 'transit']);
        $table->unsignedBigInteger('from_city_id')->nullable();
        $table->unsignedBigInteger('to_city_id')->nullable();
        $table->unsignedBigInteger('return_city_id')->nullable();
        
        $table->foreign('airline_id')
            ->references('id')
            ->on('airlines')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        
        $table->foreign('from_city_id')
            ->references('id')
            ->on('city_codes')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        
        $table->foreign('to_city_id')
            ->references('id')
            ->on('city_codes')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        
        $table->foreign('return_city_id')
            ->references('id')
            ->on('city_codes')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        
        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    if (Schema::hasTable('routes')) {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropForeign(['airline_id']);
            $table->dropForeign(['from_city_id']);
            $table->dropForeign(['to_city_id']);
            $table->dropForeign(['return_city_id']);
        });
    }

    Schema::dropIfExists('routes');
}
```

**Important**: This schema makes ALL city fields nullable. Business logic enforcement must happen at application level.

#### 2. route_multi_segments table

```php
// UP
public function up(): void
{
    Schema::create('route_multi_segments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('route_id');
        $table->unsignedBigInteger('from_city_id');
        $table->unsignedBigInteger('to_city_id');
        $table->enum('segment_direction', ['inbound', 'outbound']);
        
        $table->foreign('route_id')
            ->references('id')
            ->on('routes')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        
        $table->foreign('from_city_id')
            ->references('id')
            ->on('city_codes')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        
        $table->foreign('to_city_id')
            ->references('id')
            ->on('city_codes')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        
        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    if (Schema::hasTable('route_multi_segments')) {
        Schema::table('route_multi_segments', function (Blueprint $table) {
            $table->dropForeign(['route_id']);
            $table->dropForeign(['from_city_id']);
            $table->dropForeign(['to_city_id']);
        });
    }

    Schema::dropIfExists('route_multi_segments');
}
```

#### 3. route_transits table

```php
// UP
public function up(): void
{
    Schema::create('route_transits', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('route_id');
        $table->unsignedBigInteger('transit_city_id');
        $table->unsignedInteger('transit_time'); // Minutes
        
        $table->foreign('route_id')
            ->references('id')
            ->on('routes')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        
        $table->foreign('transit_city_id')
            ->references('id')
            ->on('city_codes')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        
        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    if (Schema::hasTable('route_transits')) {
        Schema::table('route_transits', function (Blueprint $table) {
            $table->dropForeign(['route_id']);
            $table->dropForeign(['transit_city_id']);
        });
    }

    Schema::dropIfExists('route_transits');
}
```

---

### Safe Execution Plan

#### Option 1: Full Migration Run (Recommended)
Run all Phase 3 migrations after creating all files:
```bash
php artisan migrate
```

#### Option 2: Partial/Step-by-Step Execution
If you need to test incrementally:

```bash
# Step 1: Create all migration files
php artisan make:migration create_routes_table
php artisan make:migration create_route_multi_segments_table
php artisan make:migration create_route_transits_table

# Step 2: Verify migration files content

# Step 3: Run migrations
php artisan migrate

# Step 4: Verify tables created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('routes');
```

#### Option 3: Rollback Plan
If something goes wrong:
```bash
# Rollback last migration
php artisan migrate:rollback

# Rollback all (if needed)
php artisan migrate:fresh
```

---

### Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|-----------------|---------------|
| routes | restrictOnDelete | Medium - prevents airline deletion if routes exist |
| route_multi_segments | restrictOnDelete | Medium - prevents route deletion if segments exist |
| route_transits | restrictOnDelete | Medium - prevents route deletion if transits exist |

**Note**: The `restrictOnDelete()` constraints mean you cannot delete parent records (airlines, routes, city_codes) if child records exist. You must first delete the child records or modify them to allow parent deletion.

---

### Summary (Phase 3)

| Category | Count |
|----------|-------|
| Total Tables | 3 |
| Foreign Keys | 9 |
| Enum Columns | 4 |
| Indexes | Auto-created by FK |

---

### Combined Summary (All Phases)

| Category | Count |
|----------|-------|
| Total Tables | 19 |
| Total Foreign Keys | 21 |
| Total Unique Constraints | 6 |
| Total Indexes | 7+ (auto from FK) |

---

### Enum Definitions Required

Create the following enums before running migrations:

**File**: `app/Enums/RouteType.php`
```php
<?php

namespace App\Enums;

enum RouteType: string
{
    case ONEWAY_INBOUND = 'oneway_inbound';
    case ONEWAY_OUTBOUND = 'oneway_outbound';
    case ROUND = 'round';
    case MULTI_CITY = 'multi_city';
}
```

**File**: `app/Enums/FlightType.php`
```php
<?php

namespace App\Enums;

enum FlightType: string
{
    case DIRECT = 'direct';
    case TRANSIT = 'transit';
}
```

**File**: `app/Enums/SegmentDirection.php`
```php
<?php

namespace App\Enums;

enum SegmentDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
```

**Model Cast Example** (in Route model):
```php
protected $casts = [
    'route_type' => RouteType::class,
    'flight_type' => FlightType::class,
];
```

**Model Cast Example** (in RouteMultiSegment model):
```php
protected $casts = [
    'segment_direction' => SegmentDirection::class,
];
```

---

### Validation Layer Requirements

Since database constraints cannot enforce conditional nullability, implement validation at:

1. **Form Requests**: Validate based on route_type
2. **Model Observers**: Validate before save
3. **Service Layer**: Centralized validation logic

**Example validation logic**:
```php
// In Form Request
public function rules()
{
    $routeType = $this->route_type;
    
    $rules = [
        'airline_id' => 'required|exists:airlines,id',
        'route_type' => 'required|in:oneway_inbound,oneway_outbound,round,multi_city',
        'flight_type' => 'required|in:direct,transit',
    ];
    
    if ($routeType !== 'multi_city') {
        $rules['from_city_id'] = 'required|exists:city_codes,id';
        $rules['to_city_id'] = 'required|exists:city_codes,id';
    }
    
    if ($routeType === 'round') {
        $rules['return_city_id'] = 'required|exists:city_codes,id';
    }
    
    return $rules;
}
```

---

---

## Ticket Fare Tables (Phase 4)

### Overview

This phase adds 3 ticket fare-related tables with proper ordering based on foreign key dependencies.

---

### Dependency Analysis

### Tables with dependencies (create in order):
- **ticket_fares** → depends on `airlines`, `airline_classes`, `routes`, `users`
- **group_tickets** → depends on `ticket_fares`
- **baggage_allowances** → depends on `ticket_fares`

---

### Migration Order (Phase 4: Ticket Fare Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | ticket_fares | airlines, airline_classes, routes, users | `php artisan make:migration create_ticket_fares_table` |
| 2 | group_tickets | ticket_fares | `php artisan make:migration create_group_tickets_table` |
| 3 | baggage_allowances | ticket_fares | `php artisan make:migration create_baggage_allowances_table` |

---

### Design Decisions

#### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `unsignedBigInteger()` to match

#### 2. Foreign Key Constraints

| Table | Column | References | Delete Behavior |
|-------|--------|------------|------------------|
| ticket_fares | airline_id | airlines.id | `restrictOnDelete()` |
| ticket_fares | airline_classes_id | airline_classes.id | `restrictOnDelete()` |
| ticket_fares | route_id | routes.id | `restrictOnDelete()` |
| ticket_fares | user_id | users.id | `restrictOnDelete()` |
| group_tickets | ticket_fare_id | ticket_fares.id | `restrictOnDelete()` |
| baggage_allowances | ticket_fare_id | ticket_fares.id | `restrictOnDelete()` |

- `onUpdate('cascade')` on all foreign keys
- `restrictOnDelete()` prevents accidental deletion of parent records with existing children

#### 3. Nullable Rules

| Column | Nullable | Justification |
|--------|----------|---------------|
| `offer_price` | YES | Only used when ticket_type = offer |

#### 4. Unique Constraints

| Table | Constraint | Justification |
|-------|------------|---------------|
| ticket_fares | route_id (unique) | One ticket fare per route |
| group_tickets | pnr (NOT unique) | Same PNR can appear in multiple records |
| baggage_allowances | (ticket_fare_id, passenger_type, travel_direction) | One allowance per fare/ passenger type/direction |

#### 5. Enum Handling

**Database Enums:**
```php
// ticket_fares.ticket_type
$table->enum('ticket_type', ['regular', 'offer', 'group']);

// baggage_allowances.passenger_type
$table->enum('passenger_type', ['adult', 'child', 'infant']);

// baggage_allowances.travel_direction
$table->enum('travel_direction', ['inbound', 'outbound']);
```

**Recommended PHP Enums** (for model casting):

**File**: `app/Enums/TicketType.php`
```php
<?php

namespace App\Enums;

enum TicketType: string
{
    case REGULAR = 'regular';
    case OFFER = 'offer';
    case GROUP = 'group';
}
```

**File**: `app/Enums/PassengerType.php`
```php
<?php

namespace App\Enums;

enum PassengerType: string
{
    case ADULT = 'adult';
    case CHILD = 'child';
    case INFANT = 'infant';
}
```

**File**: `app/Enums/TravelDirection.php`
```php
<?php

namespace App\Enums;

enum TravelDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
```

#### 6. Monetary & Numeric Constraints

All monetary/percentage fields must be **non-negative** (>= 0):

| Field | Type | Constraint |
|-------|------|------------|
| net_fare | decimal(10,2) | CHECK >= 0 |
| selling_fare | decimal(10,2) | CHECK >= 0 |
| offer_price | decimal(10,2) | CHECK >= 0 (nullable) |
| child_fare_percentage | decimal(5,2) | CHECK >= 0 |
| infant_fare_percentage | decimal(5,2) | CHECK >= 0 |
| ticket_qty | integer | CHECK >= 1 |

**Justification**:
- `decimal(10,2)` provides sufficient precision for fares (up to 99,999,999.99)
- Percentage fields use `decimal(5,2)` (max 999.99%) for child/infant discounts
- CHECK constraints at DB level prevent negative values
- Business logic should also validate at application layer

#### 7. Indexes
- Foreign key constraints auto-create indexes
- No redundant manual indexes needed

---

### Business Logic Notes

| Condition | Field Usage |
|-----------|-------------|
| ticket_type = regular | use net_fare, selling_fare |
| ticket_type = offer | use net_fare, selling_fare, offer_price |
| ticket_type = group | use net_fare, selling_fare, group_tickets records |
| baggage_allowances | varies by passenger_type and travel_direction |
| effective_from/effective_to | defines fare validity date range |

---

### Migration File Details

#### 1. ticket_fares table

```php
// UP
public function up(): void
{
    Schema::create('ticket_fares', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('airline_id');
        $table->unsignedBigInteger('airline_classes_id');
        $table->unsignedBigInteger('route_id');
        $table->enum('ticket_type', ['regular', 'offer', 'group']);
        $table->date('effective_from');
        $table->date('effective_to');
        $table->decimal('net_fare', 10, 2);
        $table->decimal('selling_fare', 10, 2);
        $table->decimal('offer_price', 10, 2)->nullable();
        $table->decimal('child_fare_percentage', 5, 2);
        $table->decimal('infant_fare_percentage', 5, 2);
        $table->boolean('with_meal');
        $table->unsignedBigInteger('user_id');

        // Foreign keys with restrictOnDelete
        $table->foreign('airline_id')
            ->references('id')
            ->on('airlines')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('airline_classes_id')
            ->references('id')
            ->on('airline_classes')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('route_id')
            ->references('id')
            ->on('routes')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('user_id')
            ->references('id')
            ->on('users')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        // Unique constraint: one fare per route
        $table->unique('route_id');

        $table->timestamps();
    });

    // Add CHECK constraints
    DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT net_fare_positive CHECK (net_fare >= 0)');
    DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT selling_fare_positive CHECK (selling_fare >= 0)');
    DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT offer_price_positive CHECK (offer_price >= 0)');
    DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT child_fare_percentage_positive CHECK (child_fare_percentage >= 0)');
    DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT infant_fare_percentage_positive CHECK (infant_fare_percentage >= 0)');
}

// DOWN
public function down(): void
{
    try {
        DB::statement('ALTER TABLE ticket_fares DROP CONSTRAINT net_fare_positive');
    } catch (\Exception $e) {
        // ignore if constraint does not exist
    }
    try {
        DB::statement('ALTER TABLE ticket_fares DROP CONSTRAINT selling_fare_positive');
    } catch (\Exception $e) {
        // ignore if constraint does not exist
    }
    try {
        DB::statement('ALTER TABLE ticket_fares DROP CONSTRAINT offer_price_positive');
    } catch (\Exception $e) {
        // ignore if constraint does not exist
    }
    try {
        DB::statement('ALTER TABLE ticket_fares DROP CONSTRAINT child_fare_percentage_positive');
    } catch (\Exception $e) {
        // ignore if constraint does not exist
    }
    try {
        DB::statement('ALTER TABLE ticket_fares DROP CONSTRAINT infant_fare_percentage_positive');
    } catch (\Exception $e) {
        // ignore if constraint does not exist
    }

    if (Schema::hasTable('ticket_fares')) {
        Schema::table('ticket_fares', function (Blueprint $table) {
            $table->dropForeign(['airline_id']);
            $table->dropForeign(['airline_classes_id']);
            $table->dropForeign(['route_id']);
            $table->dropForeign(['user_id']);
        });
    }

    Schema::dropIfExists('ticket_fares');
}
```

#### 2. group_tickets table

```php
// UP
public function up(): void
{
    Schema::create('group_tickets', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('ticket_fare_id');
        $table->date('inbound_date');
        $table->date('outbound_date');
        $table->string('pnr'); // NOT unique
        $table->integer('ticket_qty');
        $table->boolean('is_refundable');
        $table->boolean('is_exchangable');

        // Foreign keys
        $table->foreign('ticket_fare_id')
            ->references('id')
            ->on('ticket_fares')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->timestamps();
    });

    // Add CHECK constraint for positive ticket_qty
    DB::statement('ALTER TABLE group_tickets ADD CONSTRAINT ticket_qty_positive CHECK (ticket_qty >= 1)');
}

// DOWN
public function down(): void
{
    try {
        DB::statement('ALTER TABLE group_tickets DROP CONSTRAINT ticket_qty_positive');
    } catch (\Exception $e) {
        // ignore if constraint does not exist
    }

    if (Schema::hasTable('group_tickets')) {
        Schema::table('group_tickets', function (Blueprint $table) {
            $table->dropForeign(['ticket_fare_id']);
        });
    }

    Schema::dropIfExists('group_tickets');
}
```

#### 3. baggage_allowances table

```php
// UP
public function up(): void
{
    Schema::create('baggage_allowances', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('ticket_fare_id');
        $table->enum('passenger_type', ['adult', 'child', 'infant']);
        $table->enum('travel_direction', ['inbound', 'outbound']);
        $table->string('allowance');

        // Foreign keys
        $table->foreign('ticket_fare_id')
            ->references('id')
            ->on('ticket_fares')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        // Unique constraint: one allowance per fare/passenger_type/direction
        $table->unique(['ticket_fare_id', 'passenger_type', 'travel_direction']);

        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    if (Schema::hasTable('baggage_allowances')) {
        Schema::table('baggage_allowances', function (Blueprint $table) {
            $table->dropForeign(['ticket_fare_id']);
        });
    }

    Schema::dropIfExists('baggage_allowances');
}
```

---

### Safe Execution Plan

#### Option 1: Full Migration Run (Recommended)
Run all Phase 4 migrations after creating all files:
```bash
php artisan migrate
```

#### Option 2: Partial/Step-by-Step Execution
If you need to test incrementally:

```bash
# Step 1: Create all migration files
php artisan make:migration create_ticket_fares_table
php artisan make:migration create_group_tickets_table
php artisan make:migration create_baggage_allowances_table

# Step 2: Verify migration files content

# Step 3: Run migrations
php artisan migrate

# Step 4: Verify tables created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('ticket_fares');
```

#### Option 3: Rollback Plan
If something goes wrong:
```bash
# Rollback last migration
php artisan migrate:rollback

# Rollback all (if needed)
php artisan migrate:fresh
```

---

### Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|-----------------|---------------|
| ticket_fares | restrictOnDelete | Medium - prevents route/airline deletion if fares exist |
| group_tickets | restrictOnDelete | Medium - prevents ticket_fare deletion if group tickets exist |
| baggage_allowances | restrictOnDelete | Medium - prevents ticket_fare deletion if allowances exist |

**Note**: The `restrictOnDelete()` constraints mean you cannot delete parent records (routes, airlines, ticket_fares) if child records exist. You must first delete the child records or modify them to allow parent deletion.

---

### Summary (Phase 4)

| Category | Count |
|----------|-------|
| Total Tables | 3 |
| Foreign Keys | 6 |
| Unique Constraints | 2 |
| CHECK Constraints | 6 |
| Enum Columns | 4 |
| Indexes | Auto-created by FK |

---

### Combined Summary (All Phases)

| Category | Count |
|----------|-------|
| Total Tables | 22 |
| Total Foreign Keys | 27 |
| Total Unique Constraints | 8 |
| Total CHECK Constraints | 6 |
| Total Indexes | 10+ (auto from FK) |

---

### Enum Definitions Required

Create the following enums before running migrations:

**File**: `app/Enums/TicketType.php`
```php
<?php

namespace App\Enums;

enum TicketType: string
{
    case REGULAR = 'regular';
    case OFFER = 'offer';
    case GROUP = 'group';
}
```

**File**: `app/Enums/PassengerType.php`
```php
<?php

namespace App\Enums;

enum PassengerType: string
{
    case ADULT = 'adult';
    case CHILD = 'child';
    case INFANT = 'infant';
}
```

**File**: `app/Enums/TravelDirection.php`
```php
<?php

namespace App\Enums;

enum TravelDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
```

**Model Cast Examples**:
```php
// In TicketFare model
protected $casts = [
    'ticket_type' => TicketType::class,
    'effective_from' => 'date',
    'effective_to' => 'date',
    'net_fare' => 'decimal:2',
    'selling_fare' => 'decimal:2',
    'offer_price' => 'decimal:2',
    'child_fare_percentage' => 'decimal:2',
    'infant_fare_percentage' => 'decimal:2',
    'with_meal' => 'boolean',
];

// In BaggageAllowance model
protected $casts = [
    'passenger_type' => PassengerType::class,
    'travel_direction' => TravelDirection::class,
    'is_refundable' => 'boolean',
    'is_exchangable' => 'boolean',
];
```

---

### Validation Layer Requirements

Since business logic cannot be fully enforced at database level, implement validation at:

1. **Form Requests**: Validate ticket_type, fare amounts, dates
2. **Model Observers**: Validate before save
3. **Service Layer**: Centralized validation logic

**Example validation logic**:
```php
// In TicketFare Form Request
public function rules()
{
    return [
        'airline_id' => 'required|exists:airlines,id',
        'airline_classes_id' => 'required|exists:airline_classes,id',
        'route_id' => 'required|exists:routes,id|unique:ticket_fares,route_id',
        'ticket_type' => 'required|in:regular,offer,group',
        'effective_from' => 'required|date',
        'effective_to' => 'required|date|after_or_equal:effective_from',
        'net_fare' => 'required|numeric|min:0',
        'selling_fare' => 'required|numeric|min:0',
        'offer_price' => 'nullable|numeric|min:0',
        'child_fare_percentage' => 'required|numeric|min:0',
        'infant_fare_percentage' => 'required|numeric|min:0',
        'with_meal' => 'required|boolean',
        'user_id' => 'required|exists:users,id',
    ];
}
```

---


## Package & Configuration Tables (Phase 5)

### Overview

This phase adds 2 tables: one for packages (depends on ticket_fares and visa_selling_prices) and one for flight date gap configuration (independent).

---

### Dependency Analysis

### Tables with NO dependencies:
- `flight_date_gap` → independent (global configuration)

### Tables with dependencies:
- **packages** → depends on `ticket_fares`, `visa_selling_prices`

---

### Migration Order (Phase 5: Package & Configuration Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | flight_date_gap | none | `php artisan make:migration create_flight_date_gap_table` |
| 2 | packages | ticket_fares, visa_selling_prices | `php artisan make:migration create_packages_table` |

---

### Design Decisions

#### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `unsignedBigInteger()` to match

#### 2. Foreign Key Constraints

| Table | Column | References | Delete Behavior |
|-------|--------|------------|------------------|
| packages | ticket_fare_id | ticket_fares.id | `restrictOnDelete()` |
| packages | visa_selling_price_id | visa_selling_prices.id | `restrictOnDelete()` |

- `onUpdate('cascade')` on all foreign keys
- `restrictOnDelete()` prevents accidental deletion of parent records with existing children

#### 3. Nullable Rules

| Column | Nullable | Justification |
|--------|----------|---------------|
| `offer_price` | YES | Only used for promotional/offer packages; regular packages use only regular_price |

#### 4. Unique Constraints

| Table | Constraint | Justification |
|-------|------------|---------------|
| packages | ticket_fare_id (unique) | One package per ticket fare |
| flight_date_gap | gap (unique) | Prevent duplicate configuration values |

#### 5. Monetary & Numeric Constraints

| Field | Type | Constraint |
|-------|------|------------|
| regular_price | decimal(10,2) | CHECK >= 0 |
| offer_price | decimal(10,2) | CHECK >= 0 (nullable) |
| gap | integer | CHECK >= 1 |

**Justification**:
- `decimal(10,2)` provides sufficient precision for package prices
- Positive integer constraint for gap ensures valid configuration values

#### 6. Indexes
- Foreign key constraints auto-create indexes
- No redundant manual indexes needed

---

### Business Logic Notes

| Table | Logic |
|-------|-------|
| packages | Combines ticket_fare + visa_selling_price to form complete package |
| packages | offer_price optional - use for promotional packages |
| packages | One package per ticket_fare_id (unique) |
| flight_date_gap | Global configuration table - defines gap value used elsewhere |

---

### Migration File Details

#### 1. flight_date_gap table (create first - independent)

```php
// UP
public function up(): void
{
    Schema::create('flight_date_gap', function (Blueprint $table) {
        $table->id();
        $table->integer('gap')->unique();

        $table->timestamps();
    });

    DB::statement('ALTER TABLE flight_date_gap ADD CONSTRAINT gap_positive CHECK (gap >= 1)');
}

// DOWN
public function down(): void
{
    try {
        DB::statement('ALTER TABLE flight_date_gap DROP CONSTRAINT gap_positive');
    } catch (\Exception $e) {
        // ignore if constraint does not exist
    }

    Schema::dropIfExists('flight_date_gap');
}
```

#### 2. packages table (create second - depends on ticket_fares, visa_selling_prices)

```php
// UP
public function up(): void
{
    Schema::create('packages', function (Blueprint $table) {
        $table->id();
        $table->string('package_name');
        $table->unsignedBigInteger('ticket_fare_id');
        $table->unsignedBigInteger('visa_selling_price_id');
        $table->decimal('regular_price', 10, 2);
        $table->decimal('offer_price', 10, 2)->nullable();

        // Foreign keys with restrictOnDelete
        $table->foreign('ticket_fare_id')
            ->references('id')
            ->on('ticket_fares')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('visa_selling_price_id')
            ->references('id')
            ->on('visa_selling_prices')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        // Unique constraint: one package per ticket_fare
        $table->unique('ticket_fare_id');

        $table->timestamps();
    });

    DB::statement('ALTER TABLE packages ADD CONSTRAINT regular_price_positive CHECK (regular_price >= 0)');
    DB::statement('ALTER TABLE packages ADD CONSTRAINT offer_price_positive CHECK (offer_price >= 0)');
}

// DOWN
public function down(): void
{
    try {
        DB::statement('ALTER TABLE packages DROP CONSTRAINT regular_price_positive');
    } catch (\Exception $e) {
        // ignore if constraint does not exist
    }
    try {
        DB::statement('ALTER TABLE packages DROP CONSTRAINT offer_price_positive');
    } catch (\Exception $e) {
        // ignore if constraint does not exist
    }

    if (Schema::hasTable('packages')) {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['ticket_fare_id']);
            $table->dropForeign(['visa_selling_price_id']);
        });
    }

    Schema::dropIfExists('packages');
}
```

---

### Safe Execution Plan

#### Option 1: Full Migration Run (Recommended)
Run all Phase 5 migrations after creating all files:
```bash
php artisan migrate
```

#### Option 2: Partial/Step-by-Step Execution
If you need to test incrementally:

```bash
# Step 1: Create all migration files
php artisan make:migration create_flight_date_gap_table
php artisan make:migration create_packages_table

# Step 2: Verify migration files content

# Step 3: Run migrations
php artisan migrate

# Step 4: Verify tables created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('packages');
```

#### Option 3: Rollback Plan
If something goes wrong:
```bash
# Rollback last migration
php artisan migrate:rollback

# Rollback all (if needed)
php artisan migrate:fresh
```

---

### Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|-----------------|---------------|
| packages | restrictOnDelete | Medium - prevents ticket_fare/visa_selling_price deletion if packages exist |
| flight_date_gap | N/A (independent) | Low - safe to drop |

---

### Summary (Phase 5)

| Category | Count |
|----------|-------|
| Total Tables | 2 |
| Foreign Keys | 2 |
| Unique Constraints | 2 |
| CHECK Constraints | 3 |
| Indexes | Auto-created by FK |

---

### Combined Summary (All Phases)

| Category | Count |
|----------|-------|
| Total Tables | 24 |
| Total Foreign Keys | 29 |
| Total Unique Constraints | 10 |
| Total CHECK Constraints | 9 |
| Total Indexes | 12+ (auto from FK) |

---

### Model Configuration (Post-Migration)

**Package Model**:
```php
protected $casts = [
    'regular_price' => 'decimal:2',
    'offer_price' => 'decimal:2',
];
```

**FlightDateGap Model**:
```php
protected $casts = [
    'gap' => 'integer',
];
```

---

### Validation Layer Requirements

Since business logic cannot be fully enforced at database level, implement validation at:

1. **Form Requests**: Validate package prices, gap values
2. **Model Observers**: Validate before save
3. **Service Layer**: Centralized validation logic

**Example validation logic**:
```php
// In Package Form Request
public function rules()
{
    return [
        'package_name' => 'required|string|max:255',
        'ticket_fare_id' => 'required|exists:ticket_fares,id|unique:packages,ticket_fare_id',
        'visa_selling_price_id' => 'required|exists:visa_selling_prices,id',
        'regular_price' => 'required|numeric|min:0',
        'offer_price' => 'nullable|numeric|min:0',
    ];
}
```

## Booking & Passenger Tables (Phase 6)

### Overview

This phase adds 4 tables for managing bookings, passengers, passenger statuses, and documents. These tables form the core of the booking system.

---

### Dependency Analysis

### Tables with NO dependencies:
- `passenger_statuses` → independent (reference table)

### Tables with dependencies:
- **bookings** → depends on `users`, `customers`, `offices`, `districts`, `packages`, `fingerprint_charges`, `branches`, `flight_date_gap`
- **passengers** → depends on `bookings`, `passenger_statuses`
- **documents** → polymorphic-like (no FK constraints, uses owner_type + owner_id)

---

### Migration Order (Phase 6: Booking & Passenger Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | passenger_statuses | none | `php artisan make:migration create_passenger_statuses_table` |
| 2 | bookings | users, customers, offices, districts, packages, fingerprint_charges, branches, flight_date_gap | `php artisan make:migration create_bookings_table` |
| 3 | passengers | bookings, passenger_statuses | `php artisan make:migration create_passengers_table` |
| 4 | documents | customers OR passengers (polymorphic) | `php artisan make:migration create_documents_table` |

---

### Design Decisions

#### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `unsignedBigInteger()` to match

#### 2. Foreign Key Constraints

| Table | Column | References | Delete Behavior |
|-------|--------|------------|------------------|
| bookings | user_id | users.id | `restrictOnDelete()` |
| bookings | customer_id | customers.id | `restrictOnDelete()` |
| bookings | office_id | offices.id | `restrictOnDelete()` |
| bookings | district_id | districts.id | `restrictOnDelete()` |
| bookings | package_id | packages.id | `restrictOnDelete()` |
| bookings | fingerprint_charge_id | fingerprint_charges.id | `restrictOnDelete()` |
| bookings | branch_id | branches.id | `restrictOnDelete()` |
| bookings | date_gap_id | flight_date_gap.id | `restrictOnDelete()` |
| passengers | booking_id | bookings.id | `restrictOnDelete()` |
| passengers | passenger_status_id | passenger_statuses.id | `restrictOnDelete()` |

- `onUpdate('cascade')` on all foreign keys
- `restrictOnDelete()` prevents accidental deletion of parent records with existing children

#### 3. Special Handling: documents Table

Implement as **polymorphic-like structure** (NO foreign key constraints):

```php
$table->enum('owner_type', ['customer', 'passenger']);
$table->unsignedBigInteger('owner_id');
```

- `owner_type = 'customer'` → `owner_id` points to `customers.id`
- `owner_type = 'passenger'` → `owner_id` points to `passengers.id`

#### 4. Data Type Decisions

| Field | Type | Decision |
|-------|------|----------|
| flight_date_range | Two columns | `flight_date_from` (date), `flight_date_to` (date) - easier to query than range |
| invoice_id | string | Unique, autogenerated in controller later |

#### 5. Nullable Rules

| Column | Nullable | Justification |
|--------|----------|---------------|
| remarks | YES | Optional notes |
| actual_flight_date | YES | May not be known at booking time |
| description (passenger_statuses) | YES | Optional description |

#### 6. Unique Constraints

| Table | Constraint | Justification |
|-------|------------|---------------|
| bookings | invoice_id | Unique autogenerated ID |
| passenger_statuses | name | Prevent duplicate status names |

#### 7. Enum Handling

**Database Enums:**
```php
// bookings.fingerprint_location
$table->enum('fingerprint_location', ['home', 'office']);

// bookings.discount_type
$table->enum('discount_type', ['fixed_amount', 'percentage']);

// passengers.passenger_type
$table->enum('passenger_type', ['adult', 'child', 'infant']);

// passengers.service_required
$table->enum('service_required', ['all', 'visa_only', 'ticket_only']);

// passengers.ticket_status
$table->enum('ticket_status', ['pending', 'issued', 're-issued', 'refunded']);

// passengers.visa_status
$table->enum('visa_status', ['pending', 'submitted', 'issued']);

// documents.owner_type
$table->enum('owner_type', ['customer', 'passenger']);
```

**Note**: `passenger_type` reuses existing `App\Enums\PassengerType` enum.

#### 8. Monetary & Numeric Constraints

| Field | Type | Constraint |
|-------|------|------------|
| discount_value | decimal(10,2) | CHECK >= 0 |
| discount_amount | decimal(10,2) | CHECK >= 0 |
| pax_qty | integer | CHECK >= 1 |
| stay_duration | integer | CHECK >= 1 |

#### 9. Indexes
- Foreign key constraints auto-create indexes
- No redundant manual indexes needed

---

### Business Logic Notes

| Table | Logic |
|-------|-------|
| bookings | Central entity linking customer, package, and pricing |
| bookings | Discount: discount_type determines how discount_value is interpreted (fixed_amount = absolute, percentage = 0-100) |
| passengers | Belong to bookings - one booking can have multiple passengers |
| passenger_statuses | Tracks lifecycle of each passenger |
| documents | Supports both customers and passengers (polymorphic) |

---

### Migration File Details

#### 1. passenger_statuses table

```php
public function up(): void
{
    Schema::create('passenger_statuses', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->string('description')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('passenger_statuses');
}
```

#### 2. bookings table

```php
public function up(): void
{
    Schema::create('bookings', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('customer_id');
        $table->unsignedBigInteger('office_id');
        $table->unsignedBigInteger('district_id');
        $table->unsignedBigInteger('package_id');
        $table->unsignedBigInteger('fingerprint_charge_id');
        $table->unsignedBigInteger('branch_id');
        $table->string('invoice_id')->unique();
        $table->unsignedBigInteger('date_gap_id');
        $table->enum('fingerprint_location', ['home', 'office']);
        $table->integer('pax_qty');
        $table->enum('discount_type', ['fixed_amount', 'percentage']);
        $table->decimal('discount_value', 10, 2);
        $table->decimal('discount_amount', 10, 2);
        $table->string('remarks')->nullable();

        $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->onUpdate('cascade');
        $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete()->onUpdate('cascade');
        $table->foreign('office_id')->references('id')->on('offices')->restrictOnDelete()->onUpdate('cascade');
        $table->foreign('district_id')->references('id')->on('districts')->restrictOnDelete()->onUpdate('cascade');
        $table->foreign('package_id')->references('id')->on('packages')->restrictOnDelete()->onUpdate('cascade');
        $table->foreign('fingerprint_charge_id')->references('id')->on('fingerprint_charges')->restrictOnDelete()->onUpdate('cascade');
        $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete()->onUpdate('cascade');
        $table->foreign('date_gap_id')->references('id')->on('flight_date_gap')->restrictOnDelete()->onUpdate('cascade');

        $table->timestamps();
    });

    DB::statement('ALTER TABLE bookings ADD CONSTRAINT pax_qty_positive CHECK (pax_qty >= 1)');
    DB::statement('ALTER TABLE bookings ADD CONSTRAINT discount_value_positive CHECK (discount_value >= 0)');
    DB::statement('ALTER TABLE bookings ADD CONSTRAINT discount_amount_positive CHECK (discount_amount >= 0)');
}

public function down(): void
{
    try { DB::statement('ALTER TABLE bookings DROP CONSTRAINT pax_qty_positive'); } catch (\Exception $e) { }
    try { DB::statement('ALTER TABLE bookings DROP CONSTRAINT discount_value_positive'); } catch (\Exception $e) { }
    try { DB::statement('ALTER TABLE bookings DROP CONSTRAINT discount_amount_positive'); } catch (\Exception $e) { }

    if (Schema::hasTable('bookings')) {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['office_id']);
            $table->dropForeign(['district_id']);
            $table->dropForeign(['package_id']);
            $table->dropForeign(['fingerprint_charge_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['date_gap_id']);
        });
    }
    Schema::dropIfExists('bookings');
}
```

#### 3. passengers table

```php
public function up(): void
{
    Schema::create('passengers', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('booking_id');
        $table->unsignedBigInteger('passenger_status_id');
        $table->string('first_name');
        $table->string('last_name');
        $table->string('passport_no');
        $table->string('mobile_no');
        $table->date('date_of_birth');
        $table->enum('passenger_type', ['adult', 'child', 'infant']);
        $table->date('passport_expiry');
        $table->integer('stay_duration');
        $table->enum('service_required', ['all', 'visa_only', 'ticket_only']);
        $table->date('flight_date_from');
        $table->date('flight_date_to');
        $table->date('actual_flight_date')->nullable();
        $table->enum('ticket_status', ['pending', 'issued', 're-issued', 'refunded']);
        $table->enum('visa_status', ['pending', 'submitted', 'issued']);
        $table->string('address');

        $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete()->onUpdate('cascade');
        $table->foreign('passenger_status_id')->references('id')->on('passenger_statuses')->restrictOnDelete()->onUpdate('cascade');

        $table->timestamps();
    });

    DB::statement('ALTER TABLE passengers ADD CONSTRAINT stay_duration_positive CHECK (stay_duration >= 1)');
}

public function down(): void
{
    try { DB::statement('ALTER TABLE passengers DROP CONSTRAINT stay_duration_positive'); } catch (\Exception $e) { }

    if (Schema::hasTable('passengers')) {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['passenger_status_id']);
        });
    }
    Schema::dropIfExists('passengers');
}
```

#### 4. documents table (polymorphic-like)

```php
public function up(): void
{
    Schema::create('documents', function (Blueprint $table) {
        $table->id();
        $table->enum('owner_type', ['customer', 'passenger']);
        $table->unsignedBigInteger('owner_id');
        $table->string('file_path', 512);
        $table->string('display_name');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('documents');
}
```

---

### Safe Execution Plan

```bash
# Step 1: Create all migration files
php artisan make:migration create_passenger_statuses_table
php artisan make:migration create_bookings_table
php artisan make:migration create_passengers_table
php artisan make:migration create_documents_table

# Step 2: Run migrations
php artisan migrate

# Step 3: Verify tables created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('bookings');
```

---

### Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|-----------------|---------------|
| passenger_statuses | independent | Low |
| bookings | restrictOnDelete | Medium - prevents customer/package deletion if bookings exist |
| passengers | restrictOnDelete | Medium - prevents booking/status deletion if passengers exist |
| documents | N/A (no FK) | Low |

---

### Summary (Phase 6)

| Category | Count |
|----------|-------|
| Total Tables | 4 |
| Foreign Keys | 10 |
| Unique Constraints | 2 |
| CHECK Constraints | 4 |
| Enum Columns | 8 |
| Indexes | Auto-created by FK |

---

### Combined Summary (All Phases)

| Category | Count |
|----------|-------|
| Total Tables | 28 |
| Total Foreign Keys | 39 |
| Total Unique Constraints | 12 |
| Total CHECK Constraints | 13 |
| Total Indexes | 15+ (auto from FK) |

---

### PHP Enums Required

**New enums to create:**

```php
// app/Enums/FingerprintLocation.php
enum FingerprintLocation: string
{
    case HOME = 'home';
    case OFFICE = 'office';
}
```

```php
// app/Enums/DiscountType.php
enum DiscountType: string
{
    case FIXED_AMOUNT = 'fixed_amount';
    case PERCENTAGE = 'percentage';
}
```

```php
// app/Enums/ServiceRequired.php
enum ServiceRequired: string
{
    case ALL = 'all';
    case VISA_ONLY = 'visa_only';
    case TICKET_ONLY = 'ticket_only';
}
```

```php
// app/Enums/TicketStatus.php
enum TicketStatus: string
{
    case PENDING = 'pending';
    case ISSUED = 'issued';
    case RE_ISSUED = 're-issued';
    case REFUNDED = 'refunded';
}
```

```php
// app/Enums/VisaStatus.php
enum VisaStatus: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case ISSUED = 'issued';
}
```

```php
// app/Enums/OwnerType.php
enum OwnerType: string
{
    case CUSTOMER = 'customer';
    case PASSENGER = 'passenger';
}
```

**Existing enum to reuse:**
- `App\Enums\PassengerType` - already exists for passenger_type

---

### Model Configuration (Post-Migration)

**Booking Model**:
```php
protected $casts = [
    'fingerprint_location' => FingerprintLocation::class,
    'discount_type' => DiscountType::class,
    'discount_value' => 'decimal:2',
    'discount_amount' => 'decimal:2',
    'pax_qty' => 'integer',
];
```

**Passenger Model**:
```php
protected $casts = [
    'passenger_type' => PassengerType::class,
    'service_required' => ServiceRequired::class,
    'ticket_status' => TicketStatus::class,
    'visa_status' => VisaStatus::class,
    'date_of_birth' => 'date',
    'passport_expiry' => 'date',
    'flight_date_from' => 'date',
    'flight_date_to' => 'date',
    'actual_flight_date' => 'date',
    'stay_duration' => 'integer',
];
```

**Document Model**:
```php
protected $casts = [
    'owner_type' => OwnerType::class,
];
```

---

### Validation Layer Requirements

**Example validation logic**:
```php
// In Booking Form Request
public function rules()
{
    return [
        'user_id' => 'required|exists:users,id',
        'customer_id' => 'required|exists:customers,id',
        'office_id' => 'required|exists:offices,id',
        'district_id' => 'required|exists:districts,id',
        'package_id' => 'required|exists:packages,id',
        'fingerprint_charge_id' => 'required|exists:fingerprint_charges,id',
        'branch_id' => 'required|exists:branches,id',
        'date_gap_id' => 'required|exists:flight_date_gap,id',
        'invoice_id' => 'required|string|unique:bookings,invoice_id',
        'fingerprint_location' => 'required|in:home,office',
        'pax_qty' => 'required|integer|min:1',
        'discount_type' => 'required|in:fixed_amount,percentage',
        'discount_value' => 'required|numeric|min:0',
        'discount_amount' => 'required|numeric|min:0',
        'remarks' => 'nullable|string',
    ];
}
```

---

## Phase 7: Fingerprint Workflow Tables

### Overview

This phase adds 3 tables for managing fingerprint workflow: `fingerprints` (parent workflow per booking), `fingerprint_details` (per-passenger tracking), and `rescheduled_fingerprints` (reschedule records).

### Dependency Analysis

| Table | Dependencies | Notes |
|-------|--------------|-------|
| fingerprints | bookings, users | Phase 6 tables |
| fingerprint_details | fingerprints, passengers | Phase 6 & Phase 7 |
| rescheduled_fingerprints | fingerprint_details | Phase 7 |

### Migration Order (Phase 7)

| Step | Table | Artisan Command |
|------|-------|-----------------|
| 1 | fingerprints | `php artisan make:migration create_fingerprints_table` |
| 2 | fingerprint_details | `php artisan make:migration create_fingerprint_details_table` |
| 3 | rescheduled_fingerprints | `php artisan make:migration create_rescheduled_fingerprints_table` |

### Design Decisions

#### Foreign Key Constraints

| Table | Column | References | Delete Behavior |
|-------|--------|------------|-----------------|
| fingerprints | booking_id | bookings.id | `restrictOnDelete()` |
| fingerprints | assigned_staff_id | users.id | `nullOnDelete()` (nullable) |
| fingerprint_details | fingerprint_id | fingerprints.id | `restrictOnDelete()` |
| fingerprint_details | passenger_id | passengers.id | `restrictOnDelete()` |
| rescheduled_fingerprints | fingerprint_detail_id | fingerprint_details.id | `restrictOnDelete()` |

#### Unique Constraints

| Table | Constraint | Justification |
|-------|------------|---------------|
| fingerprints | booking_id | One fingerprint workflow per booking |
| fingerprint_details | (fingerprint_id, passenger_id) | A passenger can only appear once per workflow |

#### CHECK Constraints

| Table | Field | Constraint |
|-------|-------|------------|
| fingerprints | cost | cost >= 0 |
| rescheduled_fingerprints | occurrence | occurrence >= 1 |

#### Enum Columns

| Column | Values | PHP Enum (to create) |
|--------|--------|----------------------|
| fingerprint_details.status | none, processing, approved, cancelled | `FingerprintStatus` |
| rescheduled_fingerprints.reason | rescheduled_by_client, rescheduled_by_bmt, nfc_problem, others | `RescheduleReason` |

#### Conditional Validation (Application Layer)

| Field | Condition | Enforcement |
|-------|-----------|-------------|
| other_reason | Required only when reason = 'others' | Form Request / Service Layer |

### Migration File Details

#### 1. fingerprints table

```php
public function up(): void
{
    Schema::create('fingerprints', function (Blueprint $table) {
        $table->id();
        $table->foreignId('booking_id')
            ->constrained('bookings')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->date('deadline');
        $table->decimal('cost', 10, 2);
        $table->foreignId('assigned_staff_id')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete()
            ->onUpdate('cascade');

        $table->unique('booking_id');

        $table->timestamps();
    });

    DB::statement('ALTER TABLE fingerprints ADD CONSTRAINT fingerprints_cost_check CHECK (cost >= 0)');
}

public function down(): void
{
    try {
        DB::statement('ALTER TABLE fingerprints DROP CHECK IF EXISTS fingerprints_cost_check');
    } catch (\Exception $e) {
        // MariaDB compatibility: ignore if constraint doesn't exist
    }

    if (Schema::hasTable('fingerprints')) {
        Schema::table('fingerprints', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['assigned_staff_id']);
        });
    }

    Schema::dropIfExists('fingerprints');
}
```

#### 2. fingerprint_details table

```php
public function up(): void
{
    Schema::create('fingerprint_details', function (Blueprint $table) {
        $table->id();
        $table->foreignId('fingerprint_id')
            ->constrained('fingerprints')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->foreignId('passenger_id')
            ->constrained('passengers')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->enum('status', ['none', 'processing', 'approved', 'cancelled']);

        $table->unique(['fingerprint_id', 'passenger_id']);

        $table->timestamps();
    });
}

public function down(): void
{
    if (Schema::hasTable('fingerprint_details')) {
        Schema::table('fingerprint_details', function (Blueprint $table) {
            $table->dropForeign(['fingerprint_id']);
            $table->dropForeign(['passenger_id']);
        });
    }

    Schema::dropIfExists('fingerprint_details');
}
```

#### 3. rescheduled_fingerprints table

```php
public function up(): void
{
    Schema::create('rescheduled_fingerprints', function (Blueprint $table) {
        $table->id();
        $table->foreignId('fingerprint_detail_id')
            ->constrained('fingerprint_details')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->enum('reason', ['rescheduled_by_client', 'rescheduled_by_bmt', 'nfc_problem', 'others']);
        $table->string('other_reason')->nullable();
        $table->date('next_date');
        $table->unsignedInteger('occurrence');

        $table->timestamps();
    });

    DB::statement('ALTER TABLE rescheduled_fingerprints ADD CONSTRAINT rescheduled_fingerprints_occurrence_check CHECK (occurrence >= 1)');
}

public function down(): void
{
    try {
        DB::statement('ALTER TABLE rescheduled_fingerprints DROP CHECK IF EXISTS rescheduled_fingerprints_occurrence_check');
    } catch (\Exception $e) {
        // MariaDB compatibility: ignore if constraint doesn't exist
    }

    if (Schema::hasTable('rescheduled_fingerprints')) {
        Schema::table('rescheduled_fingerprints', function (Blueprint $table) {
            $table->dropForeign(['fingerprint_detail_id']);
        });
    }

    Schema::dropIfExists('rescheduled_fingerprints');
}
```

### Safe Execution Plan

```bash
# Step 1: Create migration files
php artisan make:migration create_fingerprints_table
php artisan make:migration create_fingerprint_details_table
php artisan make:migration create_rescheduled_fingerprints_table

# Step 2: Run migrations
php artisan migrate

# Step 3: Verify tables
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('fingerprints');
```

### Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|-----------------|---------------|
| fingerprints | restrictOnDelete | Medium - prevents booking deletion if fingerprints exist |
| fingerprint_details | restrictOnDelete | Medium - prevents fingerprint deletion if details exist |
| rescheduled_fingerprints | restrictOnDelete | Medium - prevents fingerprint_detail deletion if reschedules exist |

### Summary (Phase 7)

| Category | Count |
|----------|-------|
| Total Tables | 3 |
| Foreign Keys | 5 |
| Unique Constraints | 2 |
| CHECK Constraints | 2 |
| Enum Columns | 2 |

### Combined Summary (All Phases)

| Category | Before | After |
|----------|--------|-------|
| Total Tables | 28 | 31 |
| Total Foreign Keys | 39 | 44 |
| Total Unique Constraints | 12 | 14 |
| Total CHECK Constraints | 13 | 15 |

### Enum Files to Create

The following PHP enum files MUST be created before running migrations:

**File: `app/Enums/FingerprintStatus.php`**
```php
<?php

namespace App\Enums;

enum FingerprintStatus: string
{
    case NONE = 'none';
    case PROCESSING = 'processing';
    case APPROVED = 'approved';
    case CANCELLED = 'cancelled';
}
```

**File: `app/Enums/RescheduleReason.php`**
```php
<?php

namespace App\Enums;

enum RescheduleReason: string
{
    case RESCHEDULED_BY_CLIENT = 'rescheduled_by_client';
    case RESCHEDULED_BY_BMT = 'rescheduled_by_bmt';
    case NFC_PROBLEM = 'nfc_problem';
    case OTHERS = 'others';
}
```

### Model Configuration (Post-Migration)

**Fingerprint Model:**
```php
protected $casts = [
    'deadline' => 'date',
    'cost' => 'decimal:2',
];
```

**FingerprintDetail Model:**
```php
protected $casts = [
    'status' => FingerprintStatus::class,
];
```

**RescheduledFingerprint Model:**
```php
protected $casts = [
    'reason' => RescheduleReason::class,
    'next_date' => 'date',
    'occurrence' => 'integer',
];
```

### Validation Layer Requirements

**RescheduledFingerprint - other_reason conditional validation (Form Request):**
```php
public function rules()
{
    $rules = [
        'fingerprint_detail_id' => 'required|exists:fingerprint_details,id',
        'reason' => 'required|in:rescheduled_by_client,rescheduled_by_bmt,nfc_problem,others',
        'next_date' => 'required|date',
        'occurrence' => 'required|integer|min:1',
    ];

    if ($this->reason === 'others') {
        $rules['other_reason'] = 'required|string|max:255';
    }

    return $rules;
}
```

**FingerprintDetail - unique fingerprint_id + passenger_id (Form Request):**
```php
public function rules()
{
    return [
        'fingerprint_id' => 'required|exists:fingerprints,id',
        'passenger_id' => 'required|exists:passengers,id',
        'status' => 'required|in:none,processing,approved,cancelled',
    ];
}

// Custom validation for composite unique:
// 'passenger_id' => 'unique:fingerprint_details,fingerprint_id,NULL,id,fingerprint_id,' . $this->fingerprint_id
```

---

## Phase 8: Visa Submission Workflow Tables

### Overview

This phase adds 2 tables for managing visa submissions and their cancellations. These tables track visa application workflow per passenger.

---

### Dependency Analysis

#### Tables with dependencies:
- **visa_submissions** → depends on `passengers`, `visa_agents`, `commission_agents`, `visa_selling_prices`
- **cancelled_submissions** → depends on `visa_submissions`

---

### Migration Order (Phase 8: Visa Submission Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | visa_submissions | passengers, visa_agents, commission_agents, visa_selling_prices | `php artisan make:migration create_visa_submissions_table` |
| 2 | cancelled_submissions | visa_submissions | `php artisan make:migration create_cancelled_submissions_table` |

---

### Design Decisions

#### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `foreignId()->constrained()` for proper constraints

#### 2. Foreign Key Constraints

| Table | Column | References | Delete Behavior |
|-------|--------|------------|------------------|
| visa_submissions | passenger_id | passengers.id | `restrictOnDelete()` |
| visa_submissions | visa_agent_id | visa_agents.id | `restrictOnDelete()` |
| visa_submissions | commission_agent_id | commission_agents.id | `restrictOnDelete()` (nullable) |
| visa_submissions | visa_selling_price_id | visa_selling_prices.id | `restrictOnDelete()` |
| cancelled_submissions | visa_submission_id | visa_submissions.id | `restrictOnDelete()` |

- `onUpdate('cascade')` on all foreign keys
- `restrictOnDelete()` prevents accidental deletion of parent records with existing children

#### 3. Nullable Fields

| Column | Nullable | Justification |
|--------|----------|---------------|
| commission_agent_id | YES | Commission agent may not be assigned |
| agent_commission | YES | Commission amount may not be known |
| visa_number | YES | Visa number may be assigned later |
| cancellation_fee | YES | Cancellation fee may be determined later |

#### 4. IMPORTANT: passenger_id NOT Unique

**Justification**:
- One passenger may have multiple visa submissions over time (historical tracking)
- Booking information accessible via `passengers.booking_id` through the existing passenger record
- DO NOT add unique constraint on passenger_id

#### 5. Boolean Field: is_cancelled

- **Default**: `false` (new submissions are active by default)
- Uses `boolean()` with `default(false)`
- Tracks whether the visa submission was cancelled

#### 6. Unique Constraints

| Table | Constraint | Justification |
|-------|------------|---------------|
| cancelled_submissions | visa_submission_id (unique) | One cancellation record per visa submission |

#### 7. Monetary & Numeric Constraints

| Field | Type | Constraint |
|-------|------|------------|
| agent_commission | decimal(10,2) | CHECK (agent_commission IS NULL OR agent_commission >= 0) |
| cancellation_fee | decimal(10,2) | CHECK (cancellation_fee IS NULL OR cancellation_fee >= 0) |

#### 8. Indexes
- Foreign key constraints auto-create indexes
- No redundant manual indexes needed

---

### Business Logic Notes

| Table | Logic |
|-------|-------|
| visa_submissions | One visa submission belongs to one passenger |
| visa_submissions | One passenger may have multiple visa submissions over time |
| visa_submissions | Cancellation state tracked via is_cancelled boolean |
| cancelled_submissions | Stores cancellation-related financial data |
| cancelled_submissions | Only one cancellation record allowed per visa submission |

---

### Migration File Details

#### 1. visa_submissions table

```php
// UP
public function up(): void
{
    Schema::create('visa_submissions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('passenger_id')
            ->constrained('passengers')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->foreignId('visa_agent_id')
            ->constrained('visa_agents')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->foreignId('commission_agent_id')
            ->nullable()
            ->constrained('commission_agents')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->decimal('agent_commission', 10, 2)->nullable();
        $table->foreignId('visa_selling_price_id')
            ->constrained('visa_selling_prices')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->string('visa_number')->nullable();
        $table->boolean('is_cancelled')->default(false);

        $table->timestamps();
    });

    DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT visa_submissions_agent_commission_check CHECK (agent_commission IS NULL OR agent_commission >= 0)');
}

// DOWN
public function down(): void
{
    try {
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS visa_submissions_agent_commission_check');
    } catch (\Exception $e) {
        // MariaDB compatibility: ignore if constraint doesn't exist
    }

    if (Schema::hasTable('visa_submissions')) {
        Schema::table('visa_submissions', function (Blueprint $table) {
            $table->dropForeign(['passenger_id']);
            $table->dropForeign(['visa_agent_id']);
            $table->dropForeign(['commission_agent_id']);
            $table->dropForeign(['visa_selling_price_id']);
        });
    }

    Schema::dropIfExists('visa_submissions');
}
```

#### 2. cancelled_submissions table

```php
// UP
public function up(): void
{
    Schema::create('cancelled_submissions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('visa_submission_id')
            ->constrained('visa_submissions')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->decimal('cancellation_fee', 10, 2)->nullable();

        $table->unique('visa_submission_id');

        $table->timestamps();
    });

    DB::statement('ALTER TABLE cancelled_submissions ADD CONSTRAINT cancelled_submissions_cancellation_fee_check CHECK (cancellation_fee IS NULL OR cancellation_fee >= 0)');
}

// DOWN
public function down(): void
{
    try {
        DB::statement('ALTER TABLE cancelled_submissions DROP CHECK IF EXISTS cancelled_submissions_cancellation_fee_check');
    } catch (\Exception $e) {
        // MariaDB compatibility: ignore if constraint doesn't exist
    }

    if (Schema::hasTable('cancelled_submissions')) {
        Schema::table('cancelled_submissions', function (Blueprint $table) {
            $table->dropForeign(['visa_submission_id']);
        });
    }

    Schema::dropIfExists('cancelled_submissions');
}
```

---

### Safe Execution Plan

```bash
# Step 1: Create migration files
php artisan make:migration create_visa_submissions_table
php artisan make:migration create_cancelled_submissions_table

# Step 2: Run migrations
php artisan migrate

# Step 3: Verify tables
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('visa_submissions');
```

---

### Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|-----------------|---------------|
| visa_submissions | restrictOnDelete | Medium - prevents passenger/visa_agent/commission_agent/visa_selling_price deletion if submissions exist |
| cancelled_submissions | restrictOnDelete | Medium - prevents visa_submission deletion if cancellation record exists |

**Note**: The `restrictOnDelete()` constraints mean you cannot delete parent records (passengers, visa_agents, commission_agents, visa_selling_prices, visa_submissions) if child records exist. You must first delete the child records or modify them to allow parent deletion.

**Rollback ordering risk**: When rolling back, `cancelled_submissions` must be dropped BEFORE `visa_submissions` due to FK dependency. Laravel's migration rollback handles this automatically in reverse order.

---

### Summary (Phase 8)

| Category | Count |
|----------|-------|
| Total Tables | 2 |
| Foreign Keys | 5 |
| Unique Constraints | 1 |
| CHECK Constraints | 2 |
| Boolean Columns | 1 |

---

### Combined Summary (All Phases)

| Category | Before | After |
|----------|--------|-------|
| Total Tables | 31 | 33 |
| Total Foreign Keys | 44 | 49 |
| Total Unique Constraints | 14 | 15 |
| Total CHECK Constraints | 15 | 17 |

---

### Model Configuration (Post-Migration)

**VisaSubmission Model:**
```php
protected $casts = [
    'agent_commission' => 'decimal:2',
    'is_cancelled' => 'boolean',
];
```

**CancelledSubmission Model:**
```php
protected $casts = [
    'cancellation_fee' => 'decimal:2',
];
```

---

### Validation Layer Requirements

**VisaSubmission - Example validation logic:**
```php
public function rules()
{
    return [
        'passenger_id' => 'required|exists:passengers,id',
        'visa_agent_id' => 'required|exists:visa_agents,id',
        'commission_agent_id' => 'nullable|exists:commission_agents,id',
        'visa_selling_price_id' => 'required|exists:visa_selling_prices,id',
        'visa_number' => 'nullable|string|max:255|unique:visa_submissions,visa_number',
        'agent_commission' => 'nullable|numeric|min:0',
        'is_cancelled' => 'boolean',
    ];
}
```

**CancelledSubmission - Example validation logic:**
```php
public function rules()
{
    return [
        'visa_submission_id' => 'required|exists:visa_submissions,id|unique:cancelled_submissions,visa_submission_id',
        'cancellation_fee' => 'nullable|numeric|min:0',
    ];
}
```

---

## Roles & UserRoles Tables (Phase 6)

### Overview

This phase adds 2 tables following existing pivot table conventions (`airline_cities`, `airline_classes`):
- **roles** - independent table for role definitions
- **user_roles** - pivot table for many-to-many user-role relationship

---

### Dependency Analysis

### Tables with NO dependencies:
- `roles` → independent

### Tables with dependencies:
- `user_roles` → depends on `users`, `roles`

---

### Migration Order (Phase 6: Roles & UserRoles)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | roles | none | `php artisan make:migration create_roles_table` |
| 2 | user_roles | users, roles | `php artisan make:migration create_user_roles_table` |

---

### Design Decisions

#### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `unsignedBigInteger()` to match

#### 2. Foreign Key Constraints
| Table | Column | References | Delete Behavior |
|-------|--------|------------|------------------|
| user_roles | user_id | users.id | `cascade` |
| user_roles | role_id | roles.id | `cascade` |

- `onUpdate('cascade')` on all foreign keys
- `cascade` on delete for pivot table - clean up mappings when user/role deleted

#### 3. Unique Constraints
- **roles**: Unique on `name`
- **user_roles**: Composite unique on `(user_id, role_id)` - prevents duplicate role assignment

#### 4. Timestamps
- **roles**: Include `timestamps()` (created_at, updated_at)
- **user_roles**: Include `timestamps()` for audit trail

#### 5. Indexes
- Foreign key constraints auto-create indexes
- Explicit indexes on `user_id` and `role_id` for query optimization

---

### Migration File Details

#### 1. roles table (Independent - create first)

```php
// UP
public function up(): void
{
    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    Schema::dropIfExists('roles');
}
```

#### 2. user_roles table (Pivot - create second)

```php
// UP
public function up(): void
{
    Schema::create('user_roles', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('role_id');
        
        $table->foreign('user_id')
            ->references('id')
            ->on('users')
            ->onDelete('cascade')
            ->onUpdate('cascade');
        
        $table->foreign('role_id')
            ->references('id')
            ->on('roles')
            ->onDelete('cascade')
            ->onUpdate('cascade');
        
        $table->unique(['user_id', 'role_id']);
        $table->index('user_id');
        $table->index('role_id');
        
        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    Schema::dropIfExists('user_roles');
}
```

---

### Safe Execution Plan

#### Option 1: Full Migration Run (Recommended)
Run all Phase 6 migrations after creating all files:
```bash
php artisan migrate
```

#### Option 2: Partial/Step-by-Step Execution
If you need to test incrementally:

```bash
# Step 1: Create migration files
php artisan make:migration create_roles_table
php artisan make:migration create_user_roles_table

# Step 2: Verify migration files content

# Step 3: Run migrations
php artisan migrate

# Step 4: Verify tables created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('roles');
```

#### Option 3: Rollback Plan
If something goes wrong:
```bash
# Rollback last migration
php artisan migrate:rollback

# Rollback all (if needed)
php artisan migrate:fresh
```

---

### Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|-----------------|---------------|
| roles | N/A (independent) | Low - safe to drop |
| user_roles | cascade | Low - safe to drop, clean up mappings |

---

### Summary (Phase 6)

| Category | Count |
|----------|-------|
| Total Tables | 2 |
| Foreign Keys | 2 |
| Unique Constraints | 2 |
| Indexes | 4 |

---

### Combined Summary (All Phases)

| Category | Count |
|----------|-------|
| Total Tables | 24 |
| Total Foreign Keys | 29 |
| Total Unique Constraints | 10 |
| Total CHECK Constraints | 6 |

---

*Plan Version: 9.0*
*Updated: May 2026*

---

## Banks Schema Update

### Overview

Add a `currency` enum field to the existing `banks` table to track which currency a bank operates in.

### Migration File

**File**: `database/migrations/2026_06_02_040955_add_currency_to_banks_table.php`

### Artisan Command

```bash
php artisan make:migration add_currency_to_banks_table
```

### Schema Change

#### UP Method
```php
public function up(): void
{
    Schema::table('banks', function (Blueprint $table) {
        $table->enum('currency', ['SAR', 'BDT'])->nullable()->after('description');
    });
}
```

#### DOWN Method
```php
public function down(): void
{
    Schema::table('banks', function (Blueprint $table) {
        $table->dropColumn('currency');
    });
}
```

### New Enum File

**File**: `app/Enums/Currency.php`

```php
<?php

namespace App\Enums;

enum Currency: string
{
    case SAR = 'SAR';
    case BDT = 'BDT';
}
```

### Model Update

**File**: `app/Models/Bank.php`

| Change | Detail |
|--------|--------|
| `$fillable` | Added `'currency'` |
| `$casts` | Added `'currency' => Currency::class` |

### SQL Schema Update

**File**: `database/schema/mariadb-schema.sql`

Added `currency` column after `description`:
```sql
`currency` enum('SAR','BDT') DEFAULT NULL,
```

### Design Decisions

| Decision | Justification |
|----------|---------------|
| Enum type (`'SAR', 'BDT'`) | Matches existing enum patterns in the project (e.g., `iqama_type`) |
| Nullable | Not all banks may have a currency assigned initially |
| No default value | Explicit assignment required to avoid silent assumptions |
| New migration (not edit existing) | The `banks` table already exists; new migration is the standard Laravel approach |
| `after('description')` | Logical column placement in schema |
| Enum cast in Model | Enables type-safe access (`$bank->currency === Currency::SAR`) |

### Safe Execution Steps

```bash
# Step 1: Create migration (already done)
php artisan make:migration add_currency_to_banks_table

# Step 2: Run migration
php artisan migrate

# Step 3: Verify column added
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('banks');
```

### Rollback

```bash
php artisan migrate:rollback --step=1
```

---

## Phase 9: Users Table - Active Status

### Overview

Add `is_active` boolean column to the existing `users` table to support active/inactive user toggling.

### Dependency Analysis

This is a **column addition** to the existing `users` table. No new tables, no foreign key dependencies.

### Artisan Command

```bash
php artisan make:migration add_is_active_to_users_table
```

### Schema Change

#### UP Method
```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('is_active')
            ->default(true)
            ->after('office_id');
    });
}
```

#### DOWN Method
```php
public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('is_active');
    });
}
```

### Design Decisions

| Decision | Justification |
|----------|---------------|
| `boolean` type | Simple true/false toggle — no need for an enum |
| `default(true)` | Existing users should be active by default when column is added |
| `after('office_id')` | Logical column placement — `branch_id`, `office_id`, then `is_active` |
| New migration (not edit existing) | Standard Laravel approach — the `users` table already exists |
| `boolean` cast in Model | Enables type-safe access (`$user->is_active` returns `bool`) |

### Safe Execution Steps

```bash
# Step 1: Create migration
php artisan make:migration add_is_active_to_users_table

# Step 2: Run migration
php artisan migrate

# Step 3: Verify column added
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('users');
```

### Risks & Edge Cases

| Risk | Mitigation |
|------|------------|
| Existing users without active status | `default(true)` ensures all existing users become active |
| User deactivated while logged in | Handled by global middleware (`CheckActive`) on every request |
| Super Admin accidentally deactivated | Blocked at controller + route level (Super Admin route restriction + `hasRole` guard) |

---

## Invoice Print BDT Display — Add currency_rate_id to bookings

### Overview

Add `currency_rate_id` to the `bookings` table to capture the exchange rate at booking creation time, enabling accurate BDT amount display on invoice prints.

### Migration File

**File**: `database/migrations/2026_06_02_XXXXXX_add_currency_rate_id_to_bookings_table.php`

### Artisan Command

```bash
php artisan make:migration add_currency_rate_id_to_bookings_table
```

### Schema Change

#### UP Method
```php
public function up(): void
{
    Schema::table('bookings', function (Blueprint $table) {
        $table->foreignId('currency_rate_id')
            ->nullable()
            ->constrained('currency_rates')
            ->nullOnDelete()
            ->onUpdate('cascade')
            ->after('fingerprint_office');
    });
}
```

#### DOWN Method
```php
public function down(): void
{
    Schema::table('bookings', function (Blueprint $table) {
        $table->dropForeign(['currency_rate_id']);
        $table->dropColumn('currency_rate_id');
    });
}
```

### Design Decisions

| Decision | Justification |
|----------|---------------|
| Nullable | Existing bookings after migration have no rate; fallback logic handles it |
| `nullOnDelete()` | If a CurrencyRate is deleted, booking is preserved with NULL (falls back to rate-at-creation lookup) |
| `onUpdate('cascade')` | Auto-update FK if referenced rate ID changes |
| Column placement | `after('fingerprint_office')` — logical grouping with booking details |
| New migration (not edit existing) | Standard Laravel approach — the `bookings` table already exists |

### Fallback Logic (in `BookingController::print()`)

```
1. booking.currencyRate (rate stored at booking creation)
   └── if null → fallback
2. CurrencyRate where created_at <= booking.created_at (latest at booking time)
   └── if null or rate <= 0 → fallback
3. Display SAR amounts with "(SAR)" suffix, formatted to 2 decimal places
```

### Safe Execution Steps

```bash
# Step 1: Create migration
php artisan make:migration add_currency_rate_id_to_bookings_table

# Step 2: Run migration
php artisan migrate

# Step 3: Verify column added
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('bookings');
```

### Risks & Edge Cases

| Risk | Mitigation |
|------|------------|
| Existing bookings have null `currency_rate_id` | Fallback #2 queries rate-at-creation-time by `created_at` |
| No CurrencyRate record exists | Fallback #3 shows SAR with `(SAR)` suffix |
| Rate deleted from currency_rates | `nullOnDelete()` preserves booking; fallback handles gracefully |
| Rate is 0 (edge case) | `$rate > 0` guard prevents division-by-zero; falls to SAR display |

---

## Booking Conditions Table

### Overview

This phase adds the `booking_conditions` table for managing terms and conditions text associated with bookings.

### Dependency Analysis

**Independent table** — no foreign key dependencies.

### Migration Order

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | booking_conditions | none | `php artisan make:migration create_booking_conditions_table` |

### Design Decisions

#### 1. ID Configuration
- Primary key uses `bigIncrements()` for consistency

#### 2. Nullable Fields
| Column | Nullable | Justification |
|--------|----------|---------------|
| description | YES | Condition details may be optional; some conditions may only need a title |
| sort_order | YES | Sorting not required initially; conditions will default to insertion order |

#### 3. Default Values
- `is_active` defaults to `true` — new conditions are active by default

#### 4. Timestamps
- Include `timestamps()` (created_at, updated_at) for audit purposes

### Migration File Details

#### booking_conditions table

```php
// UP
public function up(): void
{
    Schema::create('booking_conditions', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->unsignedInteger('sort_order')->nullable();
        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    Schema::dropIfExists('booking_conditions');
}
```

### Safe Execution Plan

```bash
# Step 1: Create migration file
php artisan make:migration create_booking_conditions_table

# Step 2: Run migration
php artisan migrate

# Step 3: Verify table created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('booking_conditions');
```

### Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|-----------------|---------------|
| booking_conditions | N/A (independent) | Low — safe to drop |

### Summary

| Category | Count |
|----------|-------|
| Total Tables | 1 |
| Boolean Columns | 1 |

### Combined Summary (All Phases)

| Category | Before | After |
|----------|--------|-------|
| Total Tables | 33 | 34 |

---

### Model Configuration (Post-Migration)

**BookingCondition Model:**
```php
protected $casts = [
    'is_active' => 'boolean',
    'sort_order' => 'integer',
];
```

---

## Visa Submission Status Migration

Split into two migrations to keep the `visa_agent_id` nullable change isolated from the schema additions.

---

### Migration 1: Make `visa_agent_id` Nullable

**File:** `2026_06_10_100000_make_visa_agent_id_nullable_in_visa_submissions.php`

**Purpose:** Auto-created pending submissions have no agent assigned yet.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_submissions', function (Blueprint $table) {
            $table->foreignId('visa_agent_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('visa_submissions', function (Blueprint $table) {
            DB::table('visa_submissions')->whereNull('visa_agent_id')->update(['visa_agent_id' => 1]);
            $table->foreignId('visa_agent_id')->nullable(false)->change();
        });
    }
};
```

---

### Migration 2: Add Status Columns & Drop `passengers.visa_status`

**File:** `2026_06_10_100001_add_status_to_visa_submissions_and_drop_from_passengers.php`

**Purpose:** Add workflow columns (`net_visa_cost`, `additional_cost`, `final_cost`, `remarks`, `status`) and remove the redundant `visa_status` from passengers.

#### `up()`

```php
public function up(): void
{
    Schema::table('visa_submissions', function (Blueprint $table) {
        $table->decimal('net_visa_cost', 10, 2)
            ->nullable()
            ->after('agent_commission');

        $table->decimal('additional_cost', 10, 2)
            ->nullable()
            ->after('net_visa_cost');

        $table->decimal('final_cost', 10, 2)
            ->nullable()
            ->after('additional_cost');

        $table->string('remarks', 1000)
            ->nullable()
            ->after('final_cost');

        $table->string('status', 20)
            ->default('pending')
            ->after('is_cancelled');
    });

    DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_net_cost_check CHECK (net_visa_cost IS NULL OR net_visa_cost >= 0)');
    DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_add_cost_check CHECK (additional_cost IS NULL OR additional_cost >= 0)');
    DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_final_cost_check CHECK (final_cost IS NULL OR final_cost >= 0)');

    if (Schema::hasColumn('passengers', 'visa_status')) {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropColumn('visa_status');
        });
    }
}
```

#### `down()`

```php
public function down(): void
{
    Schema::table('passengers', function (Blueprint $table) {
        $table->enum('visa_status', ['pending', 'submitted', 'issued'])
            ->nullable()
            ->after('ticket_status');
    });

    try {
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_net_cost_check');
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_add_cost_check');
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_final_cost_check');
    } catch (\Exception $e) {
        // MariaDB compatibility
    }

    Schema::table('visa_submissions', function (Blueprint $table) {
        $table->dropColumn(['net_visa_cost', 'additional_cost', 'final_cost', 'remarks', 'status']);
    });
}
```

---

### Schema After Migration 2

**`visa_submissions` — full column set:**

| # | Column | Type | Constraints |
|---|--------|------|-------------|
| 1 | `id` | bigint PK | auto-increment |
| 2 | `passenger_id` | bigint FK→passengers | NOT NULL, restrictOnDelete |
| 3 | `visa_agent_id` | bigint FK→visa_agents | **nullable** (from Migration 1) |
| 4 | `commission_agent_id` | bigint FK→commission_agents | nullable |
| 5 | `agent_commission` | decimal(10,2) | nullable, ≥0 |
| 6 | `net_visa_cost` | decimal(10,2) | **new**, nullable, ≥0 |
| 7 | `additional_cost` | decimal(10,2) | **new**, nullable, ≥0 |
| 8 | `final_cost` | decimal(10,2) | **new**, nullable, ≥0 |
| 9 | `remarks` | varchar(1000) | **new**, nullable |
| 10 | `visa_selling_price_id` | bigint FK→visa_selling_prices | NOT NULL |
| 11 | `visa_number` | varchar | nullable |
| 12 | `is_cancelled` | boolean | default false |
| 13 | `status` | varchar(20) | **new**, default `'pending'` |
| 14 | `created_at` | timestamp | |
| 15 | `updated_at` | timestamp | |

**`passengers` — removed column:**

| Column | Status |
|--------|--------|
| `visa_status` (enum: pending, submitted, issued) | **dropped** |

---

### Related Model Changes (applied after both migrations)

**`app/Enums/VisaStatus.php`** — add `cancelled`:
```php
enum VisaStatus: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case ISSUED = 'issued';
    case CANCELLED = 'cancelled';
}
```

**`app/Models/VisaSubmission.php`** — update `$fillable` and `$casts`:
```php
protected $fillable = [
    'passenger_id', 'visa_agent_id', 'commission_agent_id', 'agent_commission',
    'visa_selling_price_id', 'visa_number', 'is_cancelled',
    'net_visa_cost', 'additional_cost', 'final_cost', 'remarks', 'status',
];

protected $casts = [
    'agent_commission' => 'decimal:2',
    'net_visa_cost' => 'decimal:2',
    'additional_cost' => 'decimal:2',
    'final_cost' => 'decimal:2',
    'is_cancelled' => 'boolean',
    'status' => \App\Enums\VisaStatus::class,
];
```

**`app/Models/Passenger.php`** — remove `visa_status`:
```php
// Remove from $fillable:
// 'visa_status',

// Remove from $casts:
// 'visa_status' => VisaStatus::class,
```

---

### Backfill Command (Separate Artisan Command)

`php artisan umrah:backfill-visa-submissions`

```php
// app/Console/Commands/BackfillVisaSubmissions.php
public function handle()
{
    $count = 0;
    Passenger::whereDoesntHave('visaSubmission')
        ->where('service_required', '!=', 'ticket_only')
        ->chunk(100, function ($passengers) use (&$count) {
        foreach ($passengers as $passenger) {
            $visaSellingPriceId = $passenger->booking?->package?->visa_selling_price_id;
            VisaSubmission::create([
                'passenger_id' => $passenger->id,
                'visa_selling_price_id' => $visaSellingPriceId ?? 1,
                'status' => 'pending',
            ]);
            $count++;
        }
    });
    $this->info("Created {$count} visa submission(s) for passengers without one.");
}
```

---

## Phase 9: Visa Update Logs Table

### Overview
Add a `visa_update_logs` table to track who made changes to visa submissions and what changed.

### Migration File
`2026_06_10_100002_create_visa_update_logs_table.php`

### Artisan Command
```bash
php artisan make:migration create_visa_update_logs_table
```

### Schema Structure

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `visa_submission_id` | bigint unsigned | FK → visa_submissions, cascade on delete |
| `user_id` | bigint unsigned | FK → users |
| `action` | string | `submitted`, `issued`, `edited`, `cancelled` |
| `old_values` | JSON, nullable | Snapshot of changed tracked fields before update |
| `new_values` | JSON, nullable | Snapshot of changed tracked fields after update |
| `created_at` | timestamp | When the action occurred |

### UP Method
```php
Schema::create('visa_update_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('visa_submission_id')
        ->constrained('visa_submissions')
        ->cascadeOnDelete();
    $table->foreignId('user_id')
        ->constrained('users');
    $table->string('action');
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->timestamp('created_at');
});
```

### DOWN Method
```php
Schema::dropIfExists('visa_update_logs');
```

### Model: `app/Models/VisaUpdateLog.php`
- `const UPDATED_AT = null` (only created_at is used)
- `$fillable`: `visa_submission_id`, `user_id`, `action`, `old_values`, `new_values`
- `$casts`: `['old_values' => 'array', 'new_values' => 'array']`
- Relationships: `visaSubmission(): BelongsTo`, `user(): BelongsTo`

### Observer: `app/Observers/VisaSubmissionObserver.php`
- Hooks into `updated` event on `VisaSubmission`
- Tracks changed fields: `visa_agent_id`, `commission_agent_id`, `agent_commission`, `net_visa_cost`, `additional_cost`, `final_cost`, `visa_number`, `remarks`, `status`
- Determines action from status transition:
  - `pending → submitted` → `submitted`
  - `submitted → issued` → `issued`
  - any → `cancelled` → `cancelled`
  - other field changes → `edited`
- Stores only the changed tracked fields in `old_values`/`new_values` as JSON
- Skips logging when no authenticated user (e.g., console commands)
- Skips logging when no tracked fields changed

### Registration
In `AppServiceProvider::boot()`:
```php
VisaSubmission::observe(VisaSubmissionObserver::class);
```

### Model Update (`app/Models/VisaSubmission.php`)
Add relationship:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function logs(): HasMany
{
    return $this->hasMany(VisaUpdateLog::class);
}
```

### Design Decisions

| Decision | Justification |
|----------|---------------|
| Separate log table | Full history of every change, not just latest state |
| Cascade delete | Removing a visa submission cleans up its logs automatically |
| JSON columns for old/new | Flexible — captures exactly which fields changed without needing a column per field |
| Only log on `updated` (not `created`) | Initial creation is system-generated, not a user action in the visa workflow |
| No `updated_at` column | Log is append-only; only `created_at` matters |
| Observer pattern | Decouples logging from controller logic; fires automatically on any model change |
| Skip when no auth user | Console commands and system processes don't generate log entries |

---

## Phase 2: Branch-Office Merge — Migrations

### Overview

Merge the `offices` table into `branches` by adding `location` (KSA/BD) and `fingerprint_operation` (boolean) columns, then drop `offices`.

### Execution Order

| Step | What |
|------|------|
| 1 | Migration 1 — Add columns to `branches` |
| 2 | **Seeder** — Copy offices→branches, update existing `bookings.office_id` + `users.office_id` to new branch IDs |
| 3 | Migration 2 — Rename `bookings.branch_id`→`booking_branch_id`, `office_id`→`fingerprint_branch_id`; update FKs |
| 4 | Migration 3 — Drop `users.office_id` column + FK |
| 5 | Migration 4 — Drop `offices` table |

Why this order: **Seeder runs before Migration 2** because Migration 2 adds a FK from `fingerprint_branch_id` to `branches.id`. At seeder time the column is still `office_id` with values pointing to `offices.id` — the seeder rewrites them to the new branch IDs so the FK in Migration 2 doesn't fail. On empty DB, seeder is a no-op.

---

### Migration 1 — `add_location_and_fingerprint_operation_to_branches_table`

**File:** `2026_06_xx_100000_add_location_fingerprint_operation_to_branches.php`

```php
Schema::table('branches', function (Blueprint $table) {
    $table->string('location')->default('KSA');
    $table->boolean('fingerprint_operation')->default(false);
});
```

---

### Migration 2 — `rename_branch_and_office_columns_on_bookings_table`

**File:** `2026_06_xx_100001_rename_branch_office_columns_on_bookings.php`

**Note:** Uses `try/catch` around FK drops for idempotency — MariaDB (non-transactional DDL) can leave the DB in a partially migrated state on failure.

```php
foreach (['bookings_branch_id_foreign', 'bookings_office_id_foreign',
          'bookings_booking_branch_id_foreign', 'bookings_fingerprint_branch_id_foreign'] as $fk) {
    try {
        DB::statement("ALTER TABLE bookings DROP FOREIGN KEY `{$fk}`");
    } catch (\Exception $e) {
        // FK doesn't exist — safe to ignore (idempotent)
    }
}

Schema::table('bookings', function (Blueprint $table) {
    $table->renameColumn('branch_id', 'booking_branch_id');
    $table->renameColumn('office_id', 'fingerprint_branch_id');

    $table->foreign('booking_branch_id')->references('id')->on('branches')->onUpdate('cascade');
    $table->foreign('fingerprint_branch_id')->references('id')->on('branches')->onUpdate('cascade');
});
```

---

### Migration 3 — `drop_office_id_from_users_table`

**File:** `2026_06_xx_100002_drop_office_id_from_users.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->dropForeign(['office_id']);
    $table->dropColumn('office_id');
});
```

---

### Migration 4 — `drop_offices_table`

**File:** `2026_06_xx_100003_drop_offices_table.php`

```php
Schema::dropIfExists('offices');
```
## Phase 3: Branch-Office Merge — Seeder

**File:** `database/seeders/MergeOfficesIntoBranchesSeeder.php`

**Command:** `php artisan db:seed --class=MergeOfficesIntoBranchesSeeder`

**Note:** Seeder runs before Migration 4 (so `offices` table still exists for data copy). No-op on empty database. Not added to `DatabaseSeeder` — standalone only.

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MergeOfficesIntoBranchesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Map old office IDs to new branch IDs
            $officeMap = [];
            $offices = DB::table('offices')->get();

            foreach ($offices as $office) {
                $newBranchId = DB::table('branches')->insertGetId([
                    'name' => $office->name,
                    'address' => $office->address,
                    'contacts' => $office->contacts,
                    'location' => 'BD',
                    'fingerprint_operation' => true,
                    'created_at' => $office->created_at ?? now(),
                    'updated_at' => $office->updated_at ?? now(),
                ]);
                $officeMap[$office->id] = $newBranchId;
            }

            // 2. Update existing KSA branches
            DB::table('branches')
                ->whereNull('location')
                ->orWhere('location', '')
                ->update(['location' => 'KSA', 'fingerprint_operation' => false]);

            // 3. Update bookings.office_id (column not yet renamed — Migration 2 runs after seeder)
            foreach ($officeMap as $oldOfficeId => $newBranchId) {
                DB::table('bookings')
                    ->where('office_id', $oldOfficeId)
                    ->update(['office_id' => $newBranchId]);
            }

            // 4. Update users: merge office_id into branch_id
            foreach ($officeMap as $oldOfficeId => $newBranchId) {
                DB::table('users')
                    ->where('office_id', $oldOfficeId)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $newBranchId]);
            }
        });
    }
}
```
---

---

## Phase 1: Issue Ticket — Migrations

### Overview

Create `issued_tickets` (ticket issue workflow) and `issued_ticket_logs` (audit trail) tables. These replace the current client-side-only Issue Ticket flow with persistent database storage.

### Dependency Analysis

| Table | Dependencies |
|-------|--------------|
| issued_tickets | passengers, ticket_fares, users |
| issued_ticket_logs | issued_tickets, users |

### Migration Order

| Step | Table | Artisan Command |
|------|-------|-----------------|
| 1 | issued_tickets | `php artisan make:migration create_issued_tickets_table` |
| 2 | issued_ticket_logs | `php artisan make:migration create_issued_ticket_logs_table` |

### Design Decisions

#### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `foreignId()->constrained()` for proper constraints

#### 2. Foreign Key Constraints

| Table | Column | References | Delete Behavior |
|-------|--------|------------|-----------------|
| issued_tickets | passenger_id | passengers.id | `restrictOnDelete()` |
| issued_tickets | ticket_fare_id | ticket_fares.id | `restrictOnDelete()` (nullable) |
| issued_tickets | user_id | users.id | `nullOnDelete()` (nullable) |
| issued_ticket_logs | issued_ticket_id | issued_tickets.id | `cascadeOnDelete()` |
| issued_ticket_logs | user_id | users.id | `restrictOnDelete()` |

- `onUpdate('cascade')` on all foreign keys
- `restrictOnDelete()` prevents accidental deletion of parent records
- `cascadeOnDelete()` on logs — removing a ticket cleans up its audit trail

#### 3. Enum Columns

| Column | Table | Values | PHP Enum |
|--------|-------|--------|----------|
| issue_type | issued_tickets | regular, additional, pending_outbound | `IssueType` |
| status | issued_tickets | pending, issued, re-issued, refunded | `IssueTicketStatus` |
| action | issued_ticket_logs | issued, edited, re-issued, refunded | `IssueLogAction` |

#### 4. Nullable Rules

| Column | Nullable | Justification |
|--------|----------|---------------|
| ticket_fare_id | YES | Pending tickets may not have a fare selected yet |
| issue_type | YES | Not known until the issue workflow starts |
| pnr | YES | PNR assigned at issue time |
| invoice_number | YES | Invoice generated at issue time |
| ticket_number | YES | Ticket number assigned at issue time |
| baggage_inbound | YES | May not be known for pending tickets |
| baggage_outbound | YES | May not be known for pending tickets |
| user_id | YES | Pending tickets created by system have no user; frozen after first issue |
| issued_at | YES | Only set when status transitions from pending → issued |
| remarks | YES | Optional notes |

#### 5. Default Values

| Column | Default | Justification |
|--------|---------|---------------|
| status | `'pending'` | Tickets start in pending state |
| is_refundable | `false` | Not refundable unless explicitly set |
| is_exchangeable | `false` | Not exchangeable unless explicitly set |
| outbound_pending | `false` | Normal tickets are not pending-outbound |
| user_id freeze rule | — | After status transitions from pending → issued, `user_id` is frozen; all subsequent changes go through `issued_ticket_logs` only |

#### 6. Timestamps
- **issued_tickets**: `timestamps()` (created_at, updated_at) for lifecycle tracking
- **issued_ticket_logs**: Only `created_at` (append-only audit log, no `updated_at`)

#### 7. Indexes
- Foreign key constraints auto-create indexes
- No redundant manual indexes needed

### Migration File Details

#### 1. issued_tickets table

```php
// UP
public function up(): void
{
    Schema::create('issued_tickets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('passenger_id')
            ->constrained('passengers')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->foreignId('ticket_fare_id')
            ->nullable()
            ->constrained('ticket_fares')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->string('issue_type')->nullable();
        $table->string('status')->default('pending');
        $table->string('pnr')->nullable();
        $table->string('invoice_number')->nullable();
        $table->string('ticket_number')->nullable();
        $table->string('baggage_inbound')->nullable();
        $table->string('baggage_outbound')->nullable();
        $table->boolean('is_refundable')->default(false);
        $table->boolean('is_exchangeable')->default(false);
        $table->boolean('outbound_pending')->default(false);
        $table->foreignId('user_id')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete()
            ->onUpdate('cascade');
        $table->timestamp('issued_at')->nullable();
        $table->text('remarks')->nullable();
        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    if (Schema::hasTable('issued_tickets')) {
        Schema::table('issued_tickets', function (Blueprint $table) {
            $table->dropForeign(['passenger_id']);
            $table->dropForeign(['ticket_fare_id']);
            $table->dropForeign(['user_id']);
        });
    }

    Schema::dropIfExists('issued_tickets');
}
```

#### 2. issued_ticket_logs table

```php
// UP
public function up(): void
{
    Schema::create('issued_ticket_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('issued_ticket_id')
            ->constrained('issued_tickets')
            ->cascadeOnDelete()
            ->onUpdate('cascade');
        $table->foreignId('user_id')
            ->constrained('users')
            ->restrictOnDelete()
            ->onUpdate('cascade');
        $table->string('action');
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->timestamp('created_at');
    });
}

// DOWN
public function down(): void
{
    if (Schema::hasTable('issued_ticket_logs')) {
        Schema::table('issued_ticket_logs', function (Blueprint $table) {
            $table->dropForeign(['issued_ticket_id']);
            $table->dropForeign(['user_id']);
        });
    }

    Schema::dropIfExists('issued_ticket_logs');
}
```

### Safe Execution Plan

```bash
# Step 1: Create migration files
php artisan make:migration create_issued_tickets_table
php artisan make:migration create_issued_ticket_logs_table

# Step 2: Verify migration files content

# Step 3: Run migrations
php artisan migrate

# Step 4: Verify tables created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('issued_tickets');
```

### Rollback Considerations

| Table | Delete Behavior | Rollback Risk |
|-------|-----------------|---------------|
| issued_tickets | restrictOnDelete | Medium — prevents passenger/ticket_fare deletion if tickets exist |
| issued_ticket_logs | cascadeOnDelete | Low — logs are cleaned up when ticket is removed |

### Summary (Phase 1)

| Category | Count |
|----------|-------|
| Total Tables | 2 |
| Foreign Keys | 5 |
| Enum Columns | 3 (stored as string, enforced via PHP enum) |
| Boolean Columns | 3 |
| JSON Columns | 1 (old_values/new_values) |

---

## Phase 2: Issue Ticket — Models

### PHP Enum Files to Create

**File: `app/Enums/IssueType.php`**
```php
<?php

namespace App\Enums;

enum IssueType: string
{
    case REGULAR = 'regular';
    case ADDITIONAL = 'additional';
    case PENDING_OUTBOUND = 'pending_outbound';
}
```

**File: `app/Enums/IssueTicketStatus.php`**
```php
<?php

namespace App\Enums;

enum IssueTicketStatus: string
{
    case PENDING = 'pending';
    case ISSUED = 'issued';
    case RE_ISSUED = 're-issued';
    case REFUNDED = 'refunded';
}
```

**File: `app/Enums/IssueLogAction.php`**
```php
<?php

namespace App\Enums;

enum IssueLogAction: string
{
    case ISSUED = 'issued';
    case EDITED = 'edited';
    case RE_ISSUED = 're-issued';
    case REFUNDED = 'refunded';
}
```

### IssuedTicket Model

**File: `app/Models/IssuedTicket.php`**

```php
<?php

namespace App\Models;

use App\Enums\IssueTicketStatus;
use App\Enums\IssueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssuedTicket extends Model
{
    protected $fillable = [
        'passenger_id', 'ticket_fare_id', 'issue_type', 'status',
        'pnr', 'invoice_number', 'ticket_number',
        'baggage_inbound', 'baggage_outbound',
        'is_refundable', 'is_exchangeable', 'outbound_pending',
        'user_id', 'issued_at', 'remarks',
    ];

    protected $casts = [
        'issue_type' => IssueType::class,
        'status' => IssueTicketStatus::class,
        'is_refundable' => 'boolean',
        'is_exchangeable' => 'boolean',
        'outbound_pending' => 'boolean',
        'issued_at' => 'datetime',
    ];

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function ticketFare(): BelongsTo
    {
        return $this->belongsTo(TicketFare::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IssuedTicketLog::class);
    }

    public function logAction(string $action, ?array $oldValues = null, ?array $newValues = null): IssuedTicketLog
    {
        return $this->logs()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
```

### IssuedTicketLog Model

**File: `app/Models/IssuedTicketLog.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedTicketLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'issued_ticket_id', 'user_id', 'action', 'old_values', 'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function issuedTicket(): BelongsTo
    {
        return $this->belongsTo(IssuedTicket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Passenger Model Updates

**File: `app/Models/Passenger.php`** — add to existing model:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Relationships to add:
public function issuedTickets(): HasMany
{
    return $this->hasMany(IssuedTicket::class);
}

public function latestIssuedTicket(): HasOne
{
    return $this->hasOne(IssuedTicket::class)->latestOfMany();
}
```

### Model Configuration Summary

| Model | Key Casts |
|-------|-----------|
| IssuedTicket | `issue_type` → `IssueType`, `status` → `IssueTicketStatus`, booleans → `boolean`, `issued_at` → `datetime` |
| IssuedTicketLog | `old_values`/`new_values` → `array`, `UPDATED_AT = null` |
| Passenger | Add `issuedTickets()` hasMany + `latestIssuedTicket()` hasOne |

### Validation Layer Requirements

```php
// In TicketIssueController or Form Request
public function rules(): array
{
    return [
        'passenger_id' => 'required|exists:passengers,id',
        'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
        'issue_type' => 'nullable|in:regular,additional,pending_outbound',
        'status' => 'required|in:pending,issued,re-issued,refunded',
        'pnr' => 'nullable|string|max:255',
        'invoice_number' => 'nullable|string|max:255',
        'ticket_number' => 'nullable|string|max:255',
        'baggage_inbound' => 'nullable|string|max:255',
        'baggage_outbound' => 'nullable|string|max:255',
        'is_refundable' => 'boolean',
        'is_exchangeable' => 'boolean',
        'outbound_pending' => 'boolean',
    ];
}
```

---

## Phase 11: Issue Ticket — Backfill Command

### Overview

Create pending `issued_tickets` records for existing passengers that do not already have one. This ensures all historical passengers have an issued_ticket entry after the migration.

### Artisan Command

```bash
php artisan tickets:backfill-issued
```

### Command Implementation

**File: `app/Console/Commands/BackfillIssuedTickets.php`**

```php
<?php

namespace App\Console\Commands;

use App\Models\IssuedTicket;
use App\Models\Passenger;
use Illuminate\Console\Command;

class BackfillIssuedTickets extends Command
{
    protected $signature = 'tickets:backfill-issued';
    protected $description = 'Create pending issued_tickets for passengers without one';

    public function handle(): int
    {
        $count = 0;

        Passenger::whereDoesntHave('issuedTickets')
            ->chunk(100, function ($passengers) use (&$count) {
                foreach ($passengers as $passenger) {
                    IssuedTicket::create([
                        'passenger_id' => $passenger->id,
                        'ticket_fare_id' => $passenger->ticket_fare_id,
                        'status' => 'pending',
                    ]);
                    $count++;
                }
            });

        $this->info("Created {$count} pending issued_ticket(s) for passengers without one.");
        return Command::SUCCESS;
    }
}
```

### Execution Steps

```bash
# Step 1: Register in app/Console/Kernel.php if needed (auto-discovered in Laravel 8+)

# Step 2: Run the backfill
php artisan tickets:backfill-issued

# Step 3: Verify
php artisan tinker -> IssuedTicket::count();
```

### Design Decisions

| Decision | Justification |
|----------|---------------|
| Chunked processing | Prevents memory exhaustion with large datasets |
| `whereDoesntHave('issuedTickets')` | Skips passengers already backfilled (idempotent) |
| Copies `ticket_fare_id` | Preserves the existing fare assignment from the passenger record |
| Status = `pending` | Default state — actual issue flow will transition as needed |
| No `user_id` set | Backfill is system-generated; user will be set when ticket is actually issued |
| Safe to re-run | `whereDoesntHave` guard makes it idempotent |

### Risks & Edge Cases

| Risk | Mitigation |
|------|------------|
| Run before migrations | `IssuedTicket` model references a table that doesn't exist — run only after `php artisan migrate` |
| Duplicate on re-run | `whereDoesntHave` guard prevents creating duplicates |
| Passenger has null `ticket_fare_id` | Accepted — `ticket_fare_id` is nullable in the schema |

---

