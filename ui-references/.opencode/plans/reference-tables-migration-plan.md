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

*Plan Version: 1.1*
*Updated: May 2026*