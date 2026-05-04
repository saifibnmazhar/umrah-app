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

*Plan Version: 2.1*
*Updated: May 2026*