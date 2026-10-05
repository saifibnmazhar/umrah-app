# AUDIT - DATABASE & SCHEMA

**9 findings:** 6 MEDIUM, 2 LOW, 1 INFO (canonical IDs in `AUDIT-FINDINGS.md`).
**Sources:** live read-only queries against the local dev DB (`SHOW TABLES`, `SHOW INDEX`, `SHOW CREATE TABLE`, `KEY_COLUMN_USAGE`, `EXPLAIN SELECT` - no writes, no migrations run), all 149 migration files, all 60 Eloquent models.

---

## 1. Inventory

| Metric | Value |
|---|---|
| Tables | **69** |
| Columns | **680** |
| Foreign keys | **166 - all indexed** (MySQL requires an index on the FK side; verified) |
| Indexes | **262** (incl. 69 PRIMARY) |
| Migrations | **149** (AGENTS.md says "~70" - docs drift, MAINT-14/DB-09) |
| Engines/charset | InnoDB / `utf8mb4_unicode_ci` throughout (no odd-one-out tables) |

**Largest tables by data (rows / bytes):**

| Table | Rows | Size |
|---|---:|---:|
| `issued_ticket_logs` | 1,248 | 8.1 MB |
| `passengers` | 1,240 | 2.1 MB |
| `visa_update_logs` | 2,554 | 1.8 MB |
| `documents` | 3,161 | 1.6 MB |
| `vouchers` | 1,780 | 1.3 MB |
| `payments` | 1,780 | 1.3 MB |
| `bookings` | 794 | 0.9 MB |
| `issued_tickets` | 1,614 | 0.8 MB |
| `passenger_update_logs` | 1,651 | 0.6 MB |
| `fingerprint_detail_logs` | 2,125 | 0.5 MB |

Domain volumes: 794 bookings - 846 customers - 1,240 passengers - 1,285 visa submissions - 1,802 payments - 3,161 documents - 81 ticket fares - 57 packages. Log/audit tables (`*_update_logs`, `issued_ticket_logs`, `visa_update_logs`) already outnumber their parents - the schema is deliberately audit-heavy.

## 2. Integrity checks - verified clean

* **PRIMARY keys:** 69/69 tables (`DB-08`).
* **FK indexing:** 166/166 FK columns are covered by an index (checked against `KEY_COLUMN_USAGE` + `SHOW INDEX` dumps in `audit-tmp-fks.txt` / `audit-tmp-indexes.txt`).
* **Orphan tables:** none - every table is referenced by a model or a migration that still exists; no leftover `offices`/`transaction_type` *data* tables (they were renamed/dropped - see the two live bugs below).
* **Unique constraints present where they matter:** `bookings.invoice_id`, `vouchers.voucher_id`, `airline_cities`, `airline_classes`, `baggage_allowances`, `user_roles` composite uniques, etc.
* **Naming:** consistent plural snake_case; the two exceptions are the historical rename/drop below.

## 3. Index gaps - `EXPLAIN` evidence (MEDIUM)

Query patterns were counted statically first, then confirmed with read-only `EXPLAIN SELECT` on the live dev DB (MariaDB 12.3):

| Finding | Pattern | `EXPLAIN` result | Call sites | Recommended index |
|---|---|---|---|---|
| **DB-01** | owner-morph loads on `documents` (`owner_type`,`owner_id`) via `morphMany` (`Models/Document.php` `owner()` morphTo) | `type=ALL`, `possible_keys=NULL`, `rows=3161` (full scan of the biggest domain table) | passenger/booking show pages (document lists), `DocumentController` loops | `documents(owner_type, owner_id)` |
| **DB-02a** | `whereDate('created_at', ...)` / `created_at >= ?` on `payments` | `type=ALL`, `rows=1780`, `possible_keys=NULL` | 84 `whereDate('created_at')` call sites (73 in `app/`, 11 in `routes/web.php`) app-wide (reports, dashboards, lists) | `payments(created_at)` |
| **DB-02b** | same on `bookings` | `type=ALL`, `rows=794`, `possible_keys=NULL` | booking index filters, reports | `bookings(created_at)` |

Today's row counts are small (sub-4k), so the cost is milliseconds - these are **growth risks with provable shape**, and they are the cheapest fix in the whole audit:

```sql
CREATE INDEX documents_owner_type_owner_id_index ON documents (owner_type, owner_id);
CREATE INDEX payments_created_at_index           ON payments (created_at);
CREATE INDEX bookings_created_at_index           ON bookings (created_at);
-- verify:
EXPLAIN SELECT id FROM documents WHERE owner_type='...' AND owner_id=5;  -- expect type=ref, key=documents_owner_...
```

**Verify:** re-run the three `EXPLAIN`s -> `ref`/`range` instead of `ALL`; `php artisan test` stays green (new indexes cannot break the suite).

## 4. Live bugs (MEDIUM)

### DB-03 - `transaction-types` validates against a dropped table (live 500)
* `app/Http/Controllers/TransactionTypeController.php:29` - `'name' => 'required|string|unique:transaction_type,name'`
* `:54` - `Rule::unique('transaction_type', 'name')`
* `database/migrations/2026_05_17_000001_rename_transaction_type_to_transaction_types.php` renamed the table; `app/Models/TransactionType.php` correctly declares `protected $table = 'transaction_types'`.
* **Proof:** `SELECT COUNT(*) FROM transaction_type;` -> `ERROR 1146 (42S02): Table 'binmishal_umrah_local.transaction_type' doesn't exist`.
* Route is live: `routes/web.php:150` `Route::resource('transaction-types', ...)` (role-gated, but any role that can reach it gets a 500). 135 test-file mentions of "TransactionType" exist but **0 tests hit the route** - hence a green suite with a broken endpoint.
* **Fix:** `unique:transaction_types,name` + `Rule::unique('transaction_types','name')`.
* **Verify:** `POST /transaction-types` with a valid payload -> **302** (was 500); duplicate name -> **422**.

### DB-04 - FormRequests still validate `exists:offices,id` (LOW, dead code)
* `app/Http/Requests/StoreBookingRequest.php:26`, `UpdateBookingRequest.php:19` - `'office_id' => ... exists:offices,id`
* `database/migrations/2026_06_12_100003_drop_offices_table.php` dropped the table.
* Currently harmless: neither FormRequest is used by `BookingController` (it validates inline), and only a test references them - so no live 500. It becomes a 500 the moment someone wires the FormRequests in.
* **Fix:** delete the rules (or the unused FormRequests).
* **Verify:** `php artisan test` still green; `rg 'exists:offices' app/` = 0.

## 5. Data-model risks (MEDIUM)

### DB-05 - `passengers` cannot be soft-deleted while its dependents can
`app/Models/Passenger.php` has no `SoftDeletes`, but `cancelled_bookings`, `cancelled_passengers`, `refunded_tickets`, `re_issued_tickets`, `issued_tickets` and log tables all soft-delete or reference passengers. Deleting a passenger therefore either trips restrict FKs with a raw 500 or (worse) strands refund/cancellation history that points at nothing.
**Fix:** add `SoftDeletes` to `Passenger` (and to `Booking` if consistent), or block deletion with an explicit, friendly rule ("passengers with financial history cannot be deleted").
**Verify:** delete a passenger who has refunds -> controlled error/message, no 500; history rows still resolve.

### DB-06 - deleting a booking erases ticket audit trails
`BookingController::destroy` calls `forceDelete()` on `issued_tickets` (`:2294`, and again at `:2303` - duplicate), and `issued_ticket_logs` has a cascade from `2026_06_12_100005` - so the record of what was issued/voided disappears while finance tables keep their rows.
**Fix:** soft-delete issued tickets; keep logs; remove the duplicate call (`Q-06`).
**Verify:** after a booking delete, `issued_ticket_logs` still contains the rows.

### DB-07 - no unique backstop on `cancelled_bookings.booking_id`
`database/migrations/2026_07_14_000004` creates the table without a unique on `booking_id`, and `CancellationService.php:25-53` checks `is_cancelled` **before** its own insert - so two concurrent "initiate cancellation" requests can create two pipelines for one booking (`S-02` is the code half of this finding).
**Fix:** `unique index on booking_id` + move the guard inside the transaction.
**Verify:** two concurrent inserts -> the second fails on the constraint (or is deduped); UI shows a single cancellation.

## 6. Migration / documentation health (INFO)

* 149 migrations across 2025-2026, all applied cleanly in dev; rename/drop migrations are clearly named (`rename_transaction_type_to_transaction_types`, `drop_offices_table`).
* `AGENTS.md` claims "~70 migrations, ~50 models" and `phpunit.xml` uses MySQL `umrah_test` rather than the documented SQLite in-memory - update the handbook (MAINT-14/TEST-06).
* No migration was run during this audit; no schema was modified.

## 7. Recommended order

1. **DB-03** (one-line fix, live 500) - with a route test.
2. **DB-01/DB-02** indexes (three `CREATE INDEX`, verified by `EXPLAIN`).
3. **DB-07** unique index (with the `S-02` guard fix so the constraint is not hit by happy-path code).
4. **DB-05/DB-06** retention decisions (soft-delete vs blocked delete - product decision).
5. **DB-04** dead validation cleanup + docs refresh.
