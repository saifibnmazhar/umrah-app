# Database Migration Plan: Reference Tables

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

*Plan Version: 4.0*
*Updated: May 2026*