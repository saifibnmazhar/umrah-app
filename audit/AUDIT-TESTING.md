# AUDIT - TESTING

**6 findings:** 4 MEDIUM, 1 LOW, 1 INFO (`TEST-01..06`; canonical table in `AUDIT-FINDINGS.md`).

---

## 1. Suite baseline (`TEST-06`, INFO - verified clean)

| Metric | Value |
|---|---|
| Result | **723 passed, 0 failed, 0 errored, 2,833 assertions** |
| Duration | 304 s (~5 min) |
| Files | 115 (98 `Feature/`, 14 `Unit/`, 2 `Concerns/`, base `TestCase.php`) |
| Methods | 704 `public function test*(` declarations (+ 101 `@test` annotations) |
| Command | `php artisan test` / `vendor/bin/phpunit` |
| Config | `phpunit.xml`: `APP_ENV=testing`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, **`DB_CONNECTION=mysql`, `DB_DATABASE=umrah_test`** (127.0.0.1:3306, user `test`) - *not* SQLite in-memory as AGENTS.md states |

**Coverage that genuinely exists** (spot-read + grep): booking profit calculation and profit breakdown, cancellation flows (booking + passenger), refund caps and refund payments, ticket fare snapshots and fare role access, ticket void/re-issue, visa hold/revert, effective-date report filters, fingerprint approval gating, `PackageAccessControl`, `ServiceRequired` gating, flight-date slot enforcement, invoice/voucher rendering (incl. cancelled-booking vouchers), currency-rate handling, `BookingEditPackagePreload`.

**Authorization assertions that exist:** 40 in total - 32 `assertForbidden` + 8 `assertStatus(403)` across 13 files (`TicketFareRoleAccessTest`, `PackageAccessControlTest`, `VisaHoldTest`, `VisaRevertTest`, `TicketVoidTest`, `RefundPaymentTest`, `BookingFingerprintLocationAccessTest`, `CancelledRecordTest`, `PassengerCancellationControllerTest`, `FlightDateSlotEnforcementTest`, `PassengerServiceRequiredGatingTest`, `FingerprintApprovalConfirmationTest`, `TicketRefundPaymentsTabTest`). `assertJsonMissingPath` appears **0 times** - no test ever asserts that a payload *omits* a field.

## 2. The gaps - exactly where the security findings live

| ID | Sev | Gap | Evidence (suite grep) |
|---|---|---|---|
| `TEST-01` | MEDIUM | No test covers document upload/download authorization (the `AUTH-07/08/13` IDOR surface) | `documents.*download` -> **0** test hits; no test posts to `/documents/upload` with a foreign `booking_id` |
| `TEST-02` | MEDIUM | No test hits `bookings.payment.store` (the `AUTH-02` surface) | `payment.store` -> **0** test hits |
| `TEST-03` | MEDIUM | No concurrency/`lockForUpdate` tests for the money-path races (`BIZ L-01/L-02/S-03/M-13`) | `lockForUpdate` -> **0** test hits; no parallel/re-entrant test harness anywhere |
| `TEST-04` | MEDIUM | No test asserts financial fields are withheld from low-privilege roles (`AUTH-01/04/05/06`) | `profit_breakdown` -> **0**; `assertJsonMissingPath` -> **0**; `net_fare` appears 191 times but never in a role-redaction assertion |
| `TEST-05` | LOW | Only `UserFactory` exists - 723 tests hand-build every model, so fixtures drift silently with schema changes | `database/factories/` contains `UserFactory.php` only |

This is the audit's central testing insight: **the suite is strong on business rules and weak on the exact surfaces where 13 HIGH/13 MEDIUM security findings live.** A green suite therefore does not imply the authorization layer works.

## 3. Recommended tests (write these first - TDD per AGENTS.md)

```php
// TEST-01  tests/Feature/DocumentAuthorizationTest.php
$this->actingAs($otherUser)->get("/documents/{$document->id}/download")->assertForbidden();
$this->actingAs($otherBranchUser)->get("/passengers/{$passenger->id}/download-all-docs")->assertForbidden();

// TEST-02  tests/Feature/BookingPaymentAuthorizationTest.php
$this->actingAs($ticketStaff)->post("/bookings/{$booking->id}/payment", $payload)->assertForbidden();
$this->actingAs($otherBranchAdmin)->post("/bookings/{$booking->id}/payment", $payload)->assertForbidden();

// TEST-04  tests/Feature/FinancialPayloadRedactionTest.php
$json = $this->actingAs($deliveryStaff)->getJson('/api/bookings/passengers?type=passenger')->json();
$this->assertArrayNotHasKey('profit', $json['data'][0]);
$this->assertArrayNotHasKey('net_fare', $this->actingAs($visaStaff)->get('/bookings')->viewData('ticketFaresList')[0]);

// TEST-03  tests/Feature/RefundConcurrencyTest.php
// open tx A, run the confirm logic twice against the same passenger row,
// assert exactly one refund Payment/Voucher row exists afterwards.
```

Add route-level regression tests for the two live 500s as well (`DB-03`: `POST /transaction-types` -> 302; `MAINT-02`: `GET /invoices/1/print` -> 200/404, never 500) - today neither is covered, which is why both shipped green.

## 4. Process notes

* **TDD discipline exists and works** (AGENTS.md documents the cycle; the suite reflects it) - extend it to authorization and concurrency, which currently have no net.
* **Factories:** adding `BookingFactory`, `PassengerFactory`, `PaymentFactory`, `DocumentFactory` would cut fixture boilerplate and make schema changes fail loudly (closes `TEST-05`).
* **CI:** `.github/workflows/build-push.yml` runs the suite against MySQL 8.0 with migrations - keep the new tests free of Redis/queue dependencies (`CACHE_STORE=array`, `QUEUE_CONNECTION=sync` already guarantee that).
* **Docs:** update AGENTS.md's testing section (it claims SQLite in-memory + "~70 migrations") - tracked as `MAINT-14`.
