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

---

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

## Finance & Accounting Tables (Phase 7)

### Overview

This phase adds 5 finance-related tables for managing currency rates, invoices, payments, transaction types, and vouchers. These tables form the core financial tracking system.

---

### Dependency Analysis

### Tables with NO dependencies:
- `transaction_type` → independent (reference table)
- `currency_rates` → depends on users (but user can have many rates - not unique)

### Tables with dependencies:
- **invoices** → depends on `bookings`, `branches`, `users`
- **payments** → depends on `bookings`, `branches`, `users`, `currency_rates`, `banks`, `ticket_agents`, `visa_agents`, `commission_agents`
- **vouchers** → depends on `bookings`, `payments`, `branches`, `users`, `currency_rates`, `banks`, `ticket_agents`, `visa_agents`, `commission_agents`, `transaction_type`

---

### Migration Order (Phase 7: Finance & Accounting Tables)

| Step | Table | Dependencies | Artisan Command |
|------|-------|--------------|-----------------|
| 1 | currency_rates | users | `php artisan make:migration create_currency_rates_table` |
| 2 | transaction_type | none | `php artisan make:migration create_transaction_type_table` |
| 3 | invoices | bookings, branches, users | `php artisan make:migration create_invoices_table` |
| 4 | payments | bookings, branches, users, currency_rates, banks, ticket_agents, visa_agents, commission_agents | `php artisan make:migration create_payments_table` |
| 5 | vouchers | bookings, payments, branches, users, currency_rates, banks, ticket_agents, visa_agents, commission_agents, transaction_type | `php artisan make:migration create_vouchers_table` |

---

### Design Decisions

#### 1. ID Configuration
- All primary keys use `bigIncrements()` for consistency
- Foreign keys use `unsignedBigInteger()` to match

#### 2. Foreign Key Constraints

| Table | Column | References | Delete Behavior |
|-------|--------|------------|------------------|
| currency_rates | user_id | users.id | `restrictOnDelete()` |
| invoices | booking_id | bookings.id | `restrictOnDelete()` |
| invoices | branch_id | branches.id | `restrictOnDelete()` |
| invoices | user_id | users.id | `restrictOnDelete()` |
| payments | booking_id | bookings.id | `restrictOnDelete()` |
| payments | branch_id | branches.id | `restrictOnDelete()` |
| payments | user_id | users.id | `restrictOnDelete()` |
| payments | currency_rate_id | currency_rates.id | `restrictOnDelete()` |
| payments | bank_id | banks.id | `restrictOnDelete()` |
| payments | ticket_agent_id | ticket_agents.id | `restrictOnDelete()` |
| payments | visa_agent_id | visa_agents.id | `restrictOnDelete()` |
| payments | commission_agent_id | commission_agents.id | `restrictOnDelete()` |
| vouchers | booking_id | bookings.id | `restrictOnDelete()` |
| vouchers | payment_id | payments.id | `restrictOnDelete()` |
| vouchers | branch_id | branches.id | `restrictOnDelete()` |
| vouchers | user_id | users.id | `restrictOnDelete()` |
| vouchers | currency_rate_id | currency_rates.id | `restrictOnDelete()` |
| vouchers | bank_id | banks.id | `restrictOnDelete()` |
| vouchers | ticket_agent_id | ticket_agents.id | `restrictOnDelete()` |
| vouchers | visa_agent_id | visa_agents.id | `restrictOnDelete()` |
| vouchers | commission_agent_id | commission_agents.id | `restrictOnDelete()` |
| vouchers | transaction_type_id | transaction_type.id | `restrictOnDelete()` |

- `onUpdate('cascade')` on all foreign keys
- `restrictOnDelete()` prevents accidental deletion of parent records with existing children

#### 3. Nullable Rules

| Column | Nullable | Justification |
|--------|----------|---------------|
| payments.transaction_id | YES | Cash payments may not have transaction ID |
| vouchers.transaction_id | YES | Cash payments may not have transaction ID |
| payments.bank_id | YES | Depends on payment_method (cash vs bank) |
| payments.ticket_agent_id | YES | Optional - depends on context |
| payments.visa_agent_id | YES | Optional - depends on context |
| payments.commission_agent_id | YES | Optional - depends on context |
| vouchers.bank_id | YES | Depends on payment_method (cash vs bank) |
| vouchers.ticket_agent_id | YES | Optional - depends on context |
| vouchers.visa_agent_id | YES | Optional - depends on context |
| vouchers.commission_agent_id | YES | Optional - depends on context |

#### 4. Unique Constraints

| Table | Constraint | Justification |
|-------|------------|---------------|
| vouchers | voucher_id | Autogenerated unique identifier |
| transaction_type | name | Prevent duplicate transaction type names |

#### 5. Enum Handling

**Database Enums:**
```php
// payments.payment_method
$table->enum('payment_method', ['cash', 'bank']);

// vouchers.payment_method
$table->enum('payment_method', ['cash', 'bank']);

// transaction_type.type
$table->enum('type', ['debit', 'credit']);
```

**Recommended PHP Enums** (for model casting):

**File**: `app/Enums/PaymentMethod.php`
```php
<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK = 'bank';
}
```

**File**: `app/Enums/TransactionType.php`
```php
<?php

namespace App\Enums;

enum TransactionType: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
}
```

#### 6. Monetary & Numeric Constraints

All monetary fields must be **non-negative** (>= 0):

| Field | Table | Type | Constraint |
|-------|-------|------|------------|
| rate | currency_rates | decimal(10,4) | CHECK >= 0 |
| amount | payments | decimal(12,2) | CHECK >= 0 |
| bdt_amount | payments | decimal(12,2) | CHECK >= 0 |
| amount | vouchers | decimal(12,2) | CHECK >= 0 |
| bdt_amount | vouchers | decimal(12,2) | CHECK >= 0 |

**Justification**:
- `decimal(10,4)` for currency_rates rate (supports 4-decimal precision for exchange rates)
- `decimal(12,2)` for payment amounts (supports up to 999,999,999.99)
- CHECK constraints at DB level prevent negative values

#### 7. Indexes
- Foreign key constraints auto-create indexes
- No redundant manual indexes needed
- voucher_id unique constraint will create index automatically

---

### Business Logic Notes

| Table | Logic |
|-------|-------|
| currency_rates | Stores exchange rates - one user can have multiple rates |
| transaction_type | Defines debit/credit behavior for voucher entries |
| invoices | Linked to bookings - tracks what was billed |
| payments | Tracks financial transactions per booking - supports multiple agents |
| vouchers | Accounting entries - linked to both booking and payment |

---

### Migration File Details

#### 1. currency_rates table (create first - depends on users)

```php
// UP
public function up(): void
{
    Schema::create('currency_rates', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->decimal('rate', 10, 4);

        // Foreign key
        $table->foreign('user_id')
            ->references('id')
            ->on('users')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->timestamps();
    });

    // Add CHECK constraint
    DB::statement('ALTER TABLE currency_rates ADD CONSTRAINT currency_rates_rate_check CHECK (rate >= 0)');
}

// DOWN
public function down(): void
{
    DB::statement('ALTER TABLE currency_rates DROP CHECK IF EXISTS currency_rates_rate_check');

    if (Schema::hasTable('currency_rates')) {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }

    Schema::dropIfExists('currency_rates');
}
```

#### 2. transaction_type table (independent)

```php
// UP
public function up(): void
{
    Schema::create('transaction_type', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->enum('type', ['debit', 'credit']);
        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    Schema::dropIfExists('transaction_type');
}
```

#### 3. invoices table (depends on bookings, branches, users)

```php
// UP
public function up(): void
{
    Schema::create('invoices', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('booking_id');
        $table->unsignedBigInteger('branch_id');
        $table->unsignedBigInteger('user_id');

        // Foreign keys
        $table->foreign('booking_id')
            ->references('id')
            ->on('bookings')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('branch_id')
            ->references('id')
            ->on('branches')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('user_id')
            ->references('id')
            ->on('users')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->timestamps();
    });
}

// DOWN
public function down(): void
{
    if (Schema::hasTable('invoices')) {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['user_id']);
        });
    }

    Schema::dropIfExists('invoices');
}
```

#### 4. payments table (depends on bookings, branches, users, currency_rates, banks, agents)

```php
// UP
public function up(): void
{
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('booking_id');
        $table->unsignedBigInteger('branch_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('currency_rate_id')->nullable();
        $table->unsignedBigInteger('bank_id')->nullable();
        $table->unsignedBigInteger('ticket_agent_id')->nullable();
        $table->unsignedBigInteger('visa_agent_id')->nullable();
        $table->unsignedBigInteger('commission_agent_id')->nullable();
        $table->date('payment_date');
        $table->enum('payment_method', ['cash', 'bank']);
        $table->string('transaction_id')->nullable();
        $table->decimal('amount', 12, 2);
        $table->decimal('bdt_amount', 12, 2);

        // Foreign keys
        $table->foreign('booking_id')
            ->references('id')
            ->on('bookings')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('branch_id')
            ->references('id')
            ->on('branches')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('user_id')
            ->references('id')
            ->on('users')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('currency_rate_id')
            ->references('id')
            ->on('currency_rates')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('bank_id')
            ->references('id')
            ->on('banks')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('ticket_agent_id')
            ->references('id')
            ->on('ticket_agents')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('visa_agent_id')
            ->references('id')
            ->on('visa_agents')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('commission_agent_id')
            ->references('id')
            ->on('commission_agents')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->timestamps();
    });

    // Add CHECK constraints
    DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount >= 0)');
    DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_bdt_amount_check CHECK (bdt_amount >= 0)');
}

// DOWN
public function down(): void
{
    DB::statement('ALTER TABLE payments DROP CHECK IF EXISTS payments_amount_check');
    DB::statement('ALTER TABLE payments DROP CHECK IF EXISTS payments_bdt_amount_check');

    if (Schema::hasTable('payments')) {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['currency_rate_id']);
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['ticket_agent_id']);
            $table->dropForeign(['visa_agent_id']);
            $table->dropForeign(['commission_agent_id']);
        });
    }

    Schema::dropIfExists('payments');
}
```

#### 5. vouchers table (depends on bookings, payments, branches, users, currency_rates, banks, agents, transaction_type)

```php
// UP
public function up(): void
{
    Schema::create('vouchers', function (Blueprint $table) {
        $table->id();
        $table->string('voucher_id')->unique();
        $table->unsignedBigInteger('booking_id');
        $table->unsignedBigInteger('payment_id');
        $table->unsignedBigInteger('branch_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('currency_rate_id')->nullable();
        $table->unsignedBigInteger('bank_id')->nullable();
        $table->unsignedBigInteger('ticket_agent_id')->nullable();
        $table->unsignedBigInteger('visa_agent_id')->nullable();
        $table->unsignedBigInteger('commission_agent_id')->nullable();
        $table->unsignedBigInteger('transaction_type_id');
        $table->date('payment_date');
        $table->enum('payment_method', ['cash', 'bank']);
        $table->string('transaction_id')->nullable();
        $table->decimal('amount', 12, 2);
        $table->decimal('bdt_amount', 12, 2);

        // Foreign keys
        $table->foreign('booking_id')
            ->references('id')
            ->on('bookings')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('payment_id')
            ->references('id')
            ->on('payments')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('branch_id')
            ->references('id')
            ->on('branches')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('user_id')
            ->references('id')
            ->on('users')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('currency_rate_id')
            ->references('id')
            ->on('currency_rates')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('bank_id')
            ->references('id')
            ->on('banks')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('ticket_agent_id')
            ->references('id')
            ->on('ticket_agents')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('visa_agent_id')
            ->references('id')
            ->on('visa_agents')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('commission_agent_id')
            ->references('id')
            ->on('commission_agents')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->foreign('transaction_type_id')
            ->references('id')
            ->on('transaction_type')
            ->restrictOnDelete()
            ->onUpdate('cascade');

        $table->timestamps();
    });

    // Add CHECK constraints
    DB::statement('ALTER TABLE vouchers ADD CONSTRAINT vouchers_amount_check CHECK (amount >= 0)');
    DB::statement('ALTER TABLE vouchers ADD CONSTRAINT vouchers_bdt_amount_check CHECK (bdt_amount >= 0)');
}

// DOWN
public function down(): void
{
    DB::statement('ALTER TABLE vouchers DROP CHECK IF EXISTS vouchers_amount_check');
    DB::statement('ALTER TABLE vouchers DROP CHECK IF EXISTS vouchers_bdt_amount_check');

    if (Schema::hasTable('vouchers')) {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['currency_rate_id']);
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['ticket_agent_id']);
            $table->dropForeign(['visa_agent_id']);
            $table->dropForeign(['commission_agent_id']);
            $table->dropForeign(['transaction_type_id']);
        });
    }

    Schema::dropIfExists('vouchers');
}
```

---

### Safe Execution Plan

#### Option 1: Full Migration Run (Recommended)
Run all Phase 7 migrations after creating all files:
```bash
php artisan migrate
```

#### Option 2: Partial/Step-by-Step Execution
If you need to test incrementally:

```bash
# Step 1: Create all migration files
php artisan make:migration create_currency_rates_table
php artisan make:migration create_transaction_type_table
php artisan make:migration create_invoices_table
php artisan make:migration create_payments_table
php artisan make:migration create_vouchers_table

# Step 2: Verify migration files content

# Step 3: Run migrations
php artisan migrate

# Step 4: Verify tables created
php artisan tinker -> DB::getSchemaBuilder()->getColumnListing('payments');
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
| currency_rates | restrictOnDelete | Medium - prevents user deletion if rates exist |
| transaction_type | independent | Low |
| invoices | restrictOnDelete | Medium - prevents booking/branch/user deletion if invoices exist |
| payments | restrictOnDelete | Medium - prevents booking/agent deletion if payments exist |
| vouchers | restrictOnDelete | High - depends on multiple tables |

**Note**: The `restrictOnDelete()` constraints mean you cannot delete parent records if child records exist. You must first delete the child records or modify them to allow parent deletion.

---

### Summary (Phase 7)

| Category | Count |
|----------|-------|
| Total Tables | 5 |
| Foreign Keys | 25 |
| Unique Constraints | 2 |
| CHECK Constraints | 5 |
| Enum Columns | 3 |
| Indexes | Auto-created by FK |

---

### Combined Summary (All Phases)

| Category | Count |
|----------|-------|
| Total Tables | 33 |
| Total Foreign Keys | 64 |
| Total Unique Constraints | 14 |
| Total CHECK Constraints | 18 |
| Total Indexes | 20+ (auto from FK) |

---

### Enum Definitions Required

Create the following enums before running migrations:

**File**: `app/Enums/PaymentMethod.php`
```php
<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK = 'bank';
}
```

**File**: `app/Enums/TransactionType.php`
```php
<?php

namespace App\Enums;

enum TransactionType: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
}
```

**Model Cast Examples**:
```php
// In Payment model
protected $casts = [
    'payment_method' => PaymentMethod::class,
    'amount' => 'decimal:2',
    'bdt_amount' => 'decimal:2',
    'payment_date' => 'date',
];

// In Voucher model
protected $casts = [
    'payment_method' => PaymentMethod::class,
    'amount' => 'decimal:2',
    'bdt_amount' => 'decimal:2',
    'payment_date' => 'date',
];

// In TransactionType model
protected $casts = [
    'type' => TransactionType::class,
];
```

---

### Validation Layer Requirements

Since business logic cannot be fully enforced at database level, implement validation at:

1. **Form Requests**: Validate payment amounts, agent assignments, voucher generation
2. **Model Observers**: Validate before save
3. **Service Layer**: Centralized validation logic

**Example validation logic**:
```php
// In Payment Form Request
public function rules()
{
    return [
        'booking_id' => 'required|exists:bookings,id',
        'branch_id' => 'required|exists:branches,id',
        'user_id' => 'required|exists:users,id',
        'currency_rate_id' => 'nullable|exists:currency_rates,id',
        'bank_id' => 'nullable|exists:banks,id',
        'ticket_agent_id' => 'nullable|exists:ticket_agents,id',
        'visa_agent_id' => 'nullable|exists:visa_agents,id',
        'commission_agent_id' => 'nullable|exists:commission_agents,id',
        'payment_date' => 'required|date',
        'payment_method' => 'required|in:cash,bank',
        'transaction_id' => 'nullable|string|max:255',
        'amount' => 'required|numeric|min:0',
        'bdt_amount' => 'required|numeric|min:0',
    ];
}
```

---

*Plan Version: 7.0*
*Updated: May 2026*