# Sprint Roadmap — Feature Delivery Phase

**Source material:** current codebase (branch `auditReport`) + `audit/` reports (architecture only; findings deferred — see §Deferred Audit Remediation).
**Cadence:** effort-driven sprints (no fixed calendar dates) · **Estimation:** hour-based, 8 hours = 1 working day, Sat–Thu week · **Length:** 10 sprints · **Constraint check:** no concrete technical dependency requires changing the mandated order — all eight requirements can start in sequence as specified.

## Grounding facts used for planning

| Area | Current reality |
|---|---|
| Package price | Stored on `packages` (`regular_price`, `offer_price`, `service_charge`), linked to `ticket_fare_id` (unique) + `visa_selling_price_id`. Dashboard already shows name + price; package-config index shows price columns; **dropdowns show name only** |
| G-Confirm | Buttons in `bookings/index.blade.php` → `PUT /passengers/{id}/confirm-group` (`TicketIssueController@confirmGroup`, role group `routes/web.php:580`), 4 actions (`all/in/out/both`), transaction + `issued_ticket_logs` history; **no data capture today** |
| Status model | Passenger-level `passenger_statuses` (incl. `Delivered`, `Departure Done`, `Cancel`); `bookings.is_cancelled` + `passengers.is_cancelled`; `TicketStatus` enum on issued tickets |
| Passport | `passengers.passport_no` — indexed, **not unique**; `customers.passport_no` is unique |
| Passenger index | `GET /passengers` route exists but `PassengerController@index` and the view are **absent** (route 500s if hit; nothing links to it). The real passenger listing is the passenger tab of `bookings/index.blade.php` fed by `/api/bookings/passengers` |
| Reports | Established pattern: page route + `/api/reports/...` data + optional `/print`, role-gated (`web.php:343-372`). `/reports/reissue-refund` (`:370`) is a **placeholder view with mock rows, no data endpoint** |
| RBAC | Custom `roles` (12) + `user_roles`, `role:` route middleware (264 routes), controller `abort(403)` checks, Blade UI checks, branch scoping helper duplicated in 7 controllers |
| SMS / queue | **Zero** notification code; `mobile_no` on passengers + customers; **no queue worker** (supervisord runs php-fpm + nginx only), no `app/Jobs`, no scheduler |

## Estimation Basis

- Estimates are in **hours**, converted at **8 hours = 1 working day**, working week Saturday–Thursday (5 days/week).
- Development and TDD are performed by **Opencode agentic AI** — estimates reflect agentic throughput (parallel test/code cycles), not human developer velocity.
- Calendar dates are intentionally **not fixed**; a sprint starts when its predecessor exits. A sprint completes when its scope meets its exit criteria — its hour budget is a planning guide, not a cap.
- Hour budgets are calibrated after Sprint 1 actuals (record actuals in the sprint review).

---

## 1. Roadmap Table

| Sprint | Theme | Requirement | Goal | Priority | Dependencies | Size | Time Span (est.) |
|---|---|---|---|---|---|---|---|
| 1 | Demurrage & Compensation Reporting | R1 | Employees/company compensation visible for refunds + re-issues | P1 (core) | Product Decision: exemption rule | M | ~24h ≈ 3 working days (0.6 wk) |
| 2 | Package Name + Live Price | R2 | Authoritative package price shown beside name in 3 surfaces | P1 (core) | Decision: price definition; design kept R6-compatible | S–M | ~16h ≈ 2 working days (0.4 wk) |
| 3 | Passenger Index + Booking Performance | R3 | Measured load/query/payload improvement on passenger list + booking page | P1 (core) | Decision: "Passenger Index" definition; baseline before changes | M | ~32h ≈ 4 working days (0.8 wk) |
| 4 | G-Confirm Additional Data | R4 | Additional data captured when tickets go pending → awaiting-group | P1 (core) | Decision: the fields (blocking) | M | ~32h ≈ 4 working days (0.8 wk) |
| 5 | Same Passenger Booking Restriction | R5 | One active booking per passport, enforced as a server-side rule | P1 (core) | Decisions: passport/departure rules | M | ~32h ≈ 4 working days (0.8 wk) |
| 6–7 | Passenger-Based Package Assignment | R6 | Package moves from booking-level to passenger-level | P1 (core, last) | R2 compatibility review; discovery phase inside sprint 6 | L | ~88h ≈ 11 working days (S6 ~24h/3d + S7 ~64h/8d ≈ 2.2 wk) |
| 8–9 | RBAC → CBAC | R7 | Capability-based authorization replacing role checks | P2 (platform) | All core features shipped (their routes included in mapping) | L | ~120h ≈ 15 working days (S8 ~40h/5d + S9 ~80h/10d ≈ 3.0 wk) |
| 10 | SMS Module | R8 | Reusable event-driven SMS capability | P2 (platform) | Provider decision; queue/worker decision | M | ~40h ≈ 5 working days (1.0 wk) |
| | | | | | **Total** | | **~384h ≈ 48 working days ≈ 9.6 weeks** |

**Estimate rationale:** S1 read-only report on existing tables = fastest core feature; S2 display-only = smallest; S3 includes mandatory baseline+re-measure cycles; S4/S5 each = one flow + server rule + full QA matrix; S6 is discovery only; S7 is the largest single delivery (blast radius: invoice/profit/cancellation/reports/history); S8 discovery/parity-mapping of 350 routes; S9 the migration itself; S10 first-ever notification layer incl. queue-worker decision.

---

## 2. Sprint Detail

### Sprint 1 — Demurrage & Compensation Reporting

**Time span:** ~24h ≈ 3 working days (Sat–Thu), ≈ 0.6 weeks
**Sprint Goal:** Ticketing/management can open a report that totals re-issue and refund compensation attributable to employees and the company, for any date range.

**Product Requirements:** R1

**Scope**

* Report page + data endpoint + print, following the existing report pattern (`reports/*` page + `/api/reports/*` + `/print`).
* Data sources only: `refunded_tickets.refund_compensation` and `re_issued_tickets.total_cost`, filtered to `payment_by ∈ {employee, company}`.
* Filters: date range, type (refund / re-issue / all), payment_by, employee (the `user_id`/`ticket_agent_id` on each record), branch if the existing report pattern supports it.
* Grouping: employee vs company; per-employee subtotals + grand totals.
* Employee exemption handling per the decided rule (see Product Decisions).
* Historical records: both tables retain full history (`created_at`, `refund_date`/`re_issue_date`, soft deletes).
* Authorization under **current RBAC** (candidate role set mirrors the existing `report.reissue-refund` route: Super Admin, Co Admin, Ticket Admin, Ticket Staff — confirm).
* Print output consistent with existing report print routes.

**Out of Scope**

* Any audit remediation — including known currency/date-boundary defects in *other* reports (`M-07`, `M-10`, `R-02`, `R-03`). New code simply follows correct conventions.
* Repairing or rewiring the placeholder `/reports/reissue-refund` view beyond the extend-vs-replace decision.
* Refund/re-issue data-entry flows themselves.

**Existing Codebase Areas (high level):** `refunded_tickets`, `re_issued_tickets`; `RefundController`, `ReIssueController`, `TicketIssueController` (record producers); `reports/*` view + controller pattern; `routes/web.php` report route block; role middleware.

**Dependencies:** Product Decision (exemption semantics) resolved before day 1 — it changes the data model shape (flag column vs separate list vs filter).

**QA Scope:** employee compensation rows; company rows; exempt-employee behavior; multiple records per employee; date-range boundaries (first/last day inclusive); totals vs sum of rows; empty result set; soft-deleted records excluded; unauthorized role → 403; print output matches on-screen totals.

**Acceptance Criteria**

* Report returns only `employee`/`company` records from the two sources.
* Group subtotals and grand total equal the sum of listed rows (verified in test).
* Exemption rule behaves exactly as decided (exempted rows treated per decision — visible-but-flagged or excluded).
* Date filter inclusive on both ends.
* Non-permitted roles receive 403; permitted roles can print.

**Risks:** exemption rule ambiguity silently becoming an implementation assumption (mitigated by blocking DoR); ambiguity over which date field drives filtering (`refund_date`/`re_issue_date` vs `created_at`); `payment_by` is nullable — null-handling decision; compensation amounts are signed/zero in some rows.

**Future Audit Considerations:** none in scope; the report is read-only.

**Sprint Exit Criteria:** AC met; feature tests for every QA row above pass; `php artisan test` green; `vendor/bin/pint` clean; product sign-off on report layout and totals; actual hours recorded for estimate calibration.

---

### Sprint 2 — Package Name + Live Price

**Time span:** ~16h ≈ 2 working days (Sat–Thu), ≈ 0.4 weeks
**Sprint Goal:** The authoritative current package price appears next to the package name on the dashboard, in package-selection dropdowns, and on the package-configuration index — consistently.

**Product Requirements:** R2

**Scope**

* Define and document the single "live price" rule from **existing** fields (`packages.regular_price`, `offer_price`, `service_charge`; offer-vs-regular precedence; active/inactive handling; currency display via the existing `@currency` store). No new pricing architecture.
* Dashboard package cards (`dashboard/index.blade.php`) — align to the decided rule (currently `regular_price + service_charge` with an offer branch).
* Package dropdowns: booking create/edit/show option labels (currently name only; price already carried in data attributes), and the bookings-page package filter (`$packagesList` selects `id` + `package_name` only).
* Package-configuration index (settings packages tab) — align to the same rule (currently shows both regular and offer columns).
* Unit/feature tests for the price rule and each surface.

**Out of Scope**

* Pricing engine changes, fare-engine changes, currency-rate logic.
* Audit items touching this page (`AUTH-04` net-fare exposure in the passenger payload, `MAINT-01` view size) — deferred; noted as risk only.

**Existing Codebase Areas:** `Package` model + relations (`ticketFare`, `visaSellingPrice`, inbound/outbound fares); `DashboardController` `$packages`; `settings/index.blade.php` package tab + `SettingsController` package methods; `bookings/create|edit|show` dropdowns; `bookings/index.blade.php` package filter.

**Dependencies:** none technical. **R2 → R6 compatibility:** price display must be keyed to the package entity and rendered through one shared surface (single rule/helper, not logic embedded in booking-level assumptions), so that when R6 introduces passenger-level packages the same display works for a passenger's package selector without redesign. Sprint 2 explicitly delivers the price rule in a package-agnostic form; R6 re-verifies it in Sprint 6 discovery.

**QA Scope:** price shown in all three surfaces equals the same authoritative value; offer-priced packages display correctly; inactive-package behavior per decision; SAR/BDT display modes; packages with/without `service_charge`; regression of package selection totals on booking create.

**Acceptance Criteria**

* All three surfaces display the identical computed price for the same package.
* Price derives from current package data (change a package price → surfaces reflect it on next load).
* No change to how prices are stored or calculated for bookings.
* Dropdown selection still populates existing booking price logic unchanged.

**Risks:** ambiguity of "live" (stored vs fare-derived) producing inconsistent surfaces; increasing visibility of prices to roles that select packages (selling prices are already visible in booking flows — no supplier cost involved).

**Future Audit Considerations:** `AUTH-04/05/06` (cost-field exposure) deferred — this feature surfaces *selling* price only.

**Sprint Exit Criteria:** single documented price rule; three surfaces consistent (test-asserted); suite green; product confirms rule and inactive-package behavior; actual hours recorded.

---

### Sprint 3 — Passenger Index + Booking Page Performance

**Time span:** ~32h ≈ 4 working days (Sat–Thu), ≈ 0.8 weeks
**Sprint Goal:** Measured, verified improvement in loading of the passenger listing and the booking page — with a recorded before/after baseline.

**Product Requirements:** R3

**Scope**

1. **Baseline phase (first, mandatory):** lightweight measurement — initial response time, DB query count, page payload size, frontend request count, dominant slow queries, perceived load. If tooling is missing, add *lightweight* measurement (in-scope infrastructure for this feature only).
2. **Clarification gate:** confirm that "Passenger Index" means the passenger tab of the bookings page + `/api/bookings/passengers` (the current reality; the standalone `GET /passengers` route has no controller method/view today). If a standalone page is wanted, that is a **new build** and must be re-scoped (Product Decision).
3. **Optimization phase — only the highest-value bottlenecks for these two pages:** e.g. per-request `@php` fare/package assembly on the bookings page, payload of the passenger data endpoint, redundant queries in the data path, targeted indexes for the queries these two pages actually run (`documents` owner lookup, `created_at` filters), request-race/stale-response guard on the passenger list loader.
4. **Validation phase:** re-measure the same metrics; publish before/after numbers.

**Out of Scope**

* Global caching strategy, dashboard query consolidation, middleware role-query optimization, app-wide `fetch` error handling, view decomposition as a goal, `x-cloak` cleanup — all deferred audit work unless *directly* on this page's critical path (and then only the minimal slice).
* Functional redesign of the booking/passenger UI.

**Existing Codebase Areas:** `bookings/index.blade.php` (structure, `@php` block, `loadPassengerData`, 29 fetch sites); `BookingController@passengerData` + `BookingPassengerQuery`; `/api/bookings/passengers`; `documents`/`payments`/`bookings` query shapes; `packagesList` preload.

**Dependencies:** none. Baseline must exist before optimization starts (sprint-internal sequencing).

**QA Scope:** full functional regression of bookings page (tabs, filters, pagination, modals, G-Confirm buttons, refund/re-issue panels) and passenger tab; metric comparison table attached to the sprint review.

**Acceptance Criteria**

* Baseline metrics recorded before any change; after-metrics recorded after.
* A numeric target is agreed at the baseline review (e.g., response-time and query-count reduction) and **met** — no improvement claimed without measurement.
* Zero functional regression (suite green + manual smoke of both pages).
* Measurement notes documented for reuse.

**Risks:** local dev dataset (794 bookings) may mask prod-scale pain — consider synthetic volume for the baseline; instrumentation time eating optimization time; temptation to fold in unrelated audit remediation (explicitly out of scope).

**Future Audit Considerations:** `PERF-01/02` (global caching, middleware query), `PERF-04` (fetch error handling), `MAINT-01` (view size), `DB-01/02` beyond these pages — deferred.

**Sprint Exit Criteria:** before/after numbers published; target met; regression testing passed; scope discipline documented (what was measured but deliberately not changed); actual hours recorded.

---

### Sprint 4 — G-Confirm Additional Data

**Time span:** ~32h ≈ 4 working days (Sat–Thu), ≈ 0.8 weeks
**Sprint Goal:** Moving an issued ticket row from `pending` to `awaiting-group` via G-Confirm captures the required additional information, validated and recorded, without altering the existing status architecture.

**Product Requirements:** R4

**Scope**

* Introduce a data-entry step in the G-Confirm flow (the four current actions `all/in/out/both`), replacing the immediate fire-and-forget request with a form interaction.
* Revised controller acceptance of the additional data; validation (mandatory fields per decision); persistence at the conceptually correct place (decision) with the existing `issued_ticket_logs` history retained.
* Who enters it: the roles already authorized on `PUT /passengers/{id}/confirm-group` (current RBAC).
* Success/failure UI behavior consistent with the page's existing toast pattern; error states and validation messages.
* Audit/history requirements: the transition remains logged; additional data visible in the record trail.
* Tests: valid, invalid, missing-required, unauthorized, plus each action variant and the interaction with `revert-group`.

**Out of Scope**

* New ticket statuses or changes to `TicketStatus`.
* Group-ticket/`pending_outbound` logic changes beyond accepting the new data.
* Audit remediation of ticket status-write patterns (`S-04`, `S-06`) — new code keeps the existing transaction/logging conventions but does not refactor old paths.
* The `bookings/index.blade.php` decomposition (R4 touches it, but restructuring is R3-adjacent and deferred).

**Existing Codebase Areas:** `bookings/index.blade.php` (buttons `:811-821`, `confirmTickets` `:4743`); `TicketIssueController@confirmGroup`/`revertGroup`; `routes/web.php:595-598` + role group `:580`; `issued_tickets`, `issued_ticket_logs`.

**Dependencies:** **Blocking Product Decision — the actual fields** (they are not defined anywhere in the codebase or requirements; nothing may be invented). Also: mandatory-or-not, editability after confirmation.

**QA Scope:** valid submission; invalid (per rule); missing required; unauthorized role; each of the four actions; revert after confirm (data retained/cleared per decision); history entry written; no change to non-G-Confirm status paths.

**Acceptance Criteria**

* `pending → awaiting-group` cannot complete without the required data (when declared mandatory).
* Invalid input is rejected with field-level messages; no status change occurs on rejection.
* The additional data is retrievable from the record/history after the fact.
* Existing G-Confirm actions and revert behavior work unchanged otherwise; suite green.

**Risks:** scope creep into redesigning the confirmation UX; ambiguous field semantics; interaction with the outbound-ticket creation branch of `confirmGroup`; concurrency expectations (`R`-series audit findings) noted but deferred.

**Future Audit Considerations:** `S-03/S-04/S-06` (state-transition guard placement) deferred — new data capture must follow the existing transaction pattern, not modify guard architecture.

**Sprint Exit Criteria:** fields signed off *before* development (DoR); AC met; QA matrix green; history verified; actual hours recorded.

---

### Sprint 5 — Same Passenger Booking Restriction

**Time span:** ~32h ≈ 4 working days (Sat–Thu), ≈ 0.8 weeks
**Sprint Goal:** A passenger with an active booking cannot be put into another booking — enforced as a server-side business rule keyed on passport number.

**Product Requirements:** R5

**Scope**

* Business-rule definition and server-side enforcement at booking creation (and at passenger-add, per decision), covering:
  * passport normalization (case/whitespace/format);
  * missing passport handling (per decision);
  * duplicate passport values in historical data;
  * active-booking determination: cancelled / delivered / departure-occurred releases the restriction (using `bookings.is_cancelled`, passenger statuses `Cancel`/`Delivered`/`Departure Done`, and departure date/time per decision);
  * multi-passenger bookings (restriction evaluated per passport);
  * concurrency strategy for simultaneous booking creation (integrity backstop decision);
* User-facing validation messages (clear, actionable) in booking create/passenger add flows;
* Full QA matrix (listed below).

**Out of Scope**

* Data cleanup/deduplication of historical passports (unless a decision explicitly adds it).
* UI-only restriction (server rule is mandatory).
* Audit authorization work on booking endpoints.

**Existing Codebase Areas:** `passengers` (`passport_no`, statuses, `flight_date_from`, `actual_flight_date`, `is_cancelled`); `bookings` (`is_cancelled`, `pax_qty`); `BookingController@store`/`addPassenger`; `BookingService`; `customers.passport_no` (unique — separate entity); `BookingPassengerQuery`.

**Dependencies:** Product Decisions (missing passport, normalization, departure basis, active = booking-level vs passenger-level, enforcement points) resolved before development.

**QA Scope:** normal duplicate blocked; cancelled booking allows; delivered allows; departed allows; active blocks; multiple passengers in one booking (one blocked passport blocks appropriately); passport format variants (`ab123` vs `AB123` vs padded); concurrent double submission; clear message rendering; non-English/edge characters if present in data.

**Acceptance Criteria**

* Second booking containing the same normalized passport with an active booking is rejected server-side (API-level test, not just UI).
* Released states (cancelled / delivered / departed) permit the new booking.
* Concurrent attempts: at most one succeeds (verified by test or documented integrity mechanism).
* Message shown to the user names the blocking condition.
* No false positives for distinct passengers sharing name/dob.

**Risks:** false blocking on dirty historical passport data (mitigate: decide normalization + exception handling up front); performance of the active-lookup under concurrency (passport is indexed); ambiguity of "departure" causing inconsistent release behavior.

**Future Audit Considerations:** `AUTH-02/03` (booking/payment endpoint authorization) deferred — the new rule lives behind existing RBAC as-is; `DB-07`-style unique-backstop patterns are *reference material* only.

**Sprint Exit Criteria:** all QA rows green as automated tests where feasible; product confirms release conditions behave as intended on real sample data; suite green; actual hours recorded.

---

### Sprint 6 — Passenger-Based Package Assignment: Discovery & Design

**Time span:** ~24h ≈ 3 working days (Sat–Thu), ≈ 0.6 weeks (part 1 of R6; followed immediately by Sprint 7)
**Sprint Goal:** Complete impact discovery and a signed-off design so implementation can proceed safely in Sprint 7; deliver any zero-risk foundations agreed at planning.

**Product Requirements:** R6 (part 1 of 2)

**Scope**

* Domain discovery of every dependent area: booking, passenger, package, package price, invoice, payment, profit, cancellation, refund, re-issue, ticket, reports, dashboard, package selection, booking/passenger forms, historical data, exports/prints/PDFs.
* Determine what depends on booking-level `package_id` (NOT NULL + `package_name` snapshot) vs what is already passenger-level (passengers already carry `ticket_fare_id` / inbound / outbound fares — an important architectural asymmetry).
* Data-model + migration strategy decision; historical-data strategy (backfill passenger rows vs derive-on-read).
* Financial-continuity plan: how invoices, paid amounts, profit and historical reports remain stable.
* **R2 → R6 compatibility review:** confirm the Sprint 2 price rule works unchanged for passenger-level package selection; list any deltas.
* Rollout/rollback approach and test strategy for Sprint 7.

**Out of Scope:** production data changes (Sprint 7); any audit remediation; redesign of package pricing itself.

**Existing Codebase Areas:** `Booking`, `Passenger`, `Package` models; `ProfitCalculationService`, `InvoiceService`, `BookingService`; booking create/edit/show + passenger forms; reports/dashboard package filters; invoice/voucher/prints; `package_update_logs`.

**Dependencies:** R2 delivered (price rule exists to reuse).

**QA Scope:** design review checklist; impact matrix reviewed by whoever owns finance reporting.

**Acceptance Criteria**

* Impact matrix complete (all listed areas marked: affected / not affected / how).
* Data + migration strategy and historical-data rule approved.
* R2 compatibility confirmed or deltas listed and approved.
* Test plan for Sprint 7 written (per §Testing Approach).

**Risks:** underestimated blast radius; reporting continuity for historical bookings.

**Sprint Exit Criteria:** signed design pack; no unresolved blocking Product Decision; Sprint 7 scope sized; actual hours recorded.

---

### Sprint 7 — Passenger-Based Package Assignment: Delivery

**Time span:** ~64h ≈ 8 working days (Sat–Thu), ≈ 1.6 weeks (part 2 of R6; R6 total ≈ 2.2 weeks)
**Sprint Goal:** Each passenger carries their own package; bookings with mixed packages work end-to-end; historical data and financials remain correct.

**Product Requirements:** R6 (part 2 of 2)

**Scope**

* Implement the approved model change and migration/backfill.
* Update the dependent flows per the impact matrix: booking forms, passenger forms, package selection, invoice/payment/profit computation, cancellation/refund/re-issue/ticket paths, reports, dashboard, filters, prints/exports.
* Preserve behavior for existing bookings per the historical-data decision (booking-level package retained as fallback/default where decided).
* Full regression of affected domains + the Sprint 7 test plan.

**Out of Scope:** audit remediation (financial races `L-01/L-02/M-13` etc. — deferred; new code follows existing transaction conventions); package pricing redesign; UI overhaul beyond what mixed packages require.

**Dependencies:** Sprint 6 design pack.

**QA Scope:** one package per passenger; different passengers different packages; all-same-package booking behaves as before; historical booking compatibility; invoice/payment/profit correctness for mixed bookings; cancellation/refund/re-issue on mixed bookings; reports totals continuity; prints/PDFs correct; rollback rehearsal.

**Acceptance Criteria**

* Mixed-package booking created, invoiced, paid, cancelled/refunded correctly end-to-end.
* Pre-migration bookings display and report identical values as before the change.
* Profit and invoice totals reconcile for a mixed booking (test-asserted).
* Existing suite green; new tests from the Sprint 7 plan green.

**Risks:** highest-risk sprint of the roadmap (financial + historical continuity); long-tail surfaces (prints, exports); data migration on production volume.

**Future Audit Considerations:** `M-02/M-05/M-06` (money math), `T-01/T-02` (transactions), `L-*` (locks) — explicitly deferred; R6 must not be used as cover to refactor them.

**Sprint Exit Criteria:** AC met; migration executed and verified on a production-like copy; product + finance sign-off; rollback plan validated; actual hours recorded.

---

### Sprint 8 — CBAC Discovery & Strategy

**Time span:** ~40h ≈ 5 working days (Sat–Thu), ≈ 1.0 week (part 1 of R7; followed immediately by Sprint 9)
**Sprint Goal:** A complete capability model and migration strategy for RBAC → CBAC, approved and piloted on a bounded slice.

**Product Requirements:** R7 (part 1 of 2)

**Scope**

* Authorization architecture discovery: `role:` middleware coverage (264 routes), controller-level checks, Blade/UI checks, branch/scope logic (the `ensureBranchAccess` family), role list (12 roles) and how UI gates map to them.
* Capability taxonomy (module × action granularity) and naming rules.
* Role → capability mapping for all 12 roles (incl. inheritance/`Super Admin` semantics).
* Route-level, controller/service-level, and UI-level capability strategies.
* Administrative management model (who grants capabilities — planning level only).
* Branch/scope considerations (capability *vs* scope — how they compose).
* Backward compatibility approach (dual-run: `role:` continues to work), migration ordering, testing strategy, rollout plan, RBAC deprecation criteria.
* Pilot: apply the model to a small, bounded route set to validate the strategy.

**Out of Scope:** app-wide authorization changes; fixing existing authorization gaps (audit `AUTH-*` — deferred to a later phase; the mapping exercise *documents* them but does not remediate them); performance work on role lookups.

**Existing Codebase Areas:** `routes/web.php` + `booking-cancellation.php` middleware matrix; `CheckRole`/`CheckActive` middleware; `Role`/`User` models + `user_roles`; controller authorization helpers; Blade role conditions; branch scoping.

**Dependencies:** all six core features shipped — their new routes/controllers/UI gates are included in the taxonomy and mapping (they were built against current RBAC and must be translated, not rebuilt).

**QA Scope:** discovery completeness review; taxonomy/mapping reviewed against every route (coverage report); pilot slice behaves identically under both systems.

**Acceptance Criteria**

* Capability taxonomy covers 100% of registered routes (coverage report attached).
* Every existing role maps to a capability set; no capability gap vs today's effective permissions (regression matrix).
* Dual-run strategy proven on the pilot slice: same allow/deny outcomes as `role:`.
* Rollout and RBAC-deprecation criteria written and approved.

**Risks:** taxonomy too fine (unmanageable) or too coarse (no gain); branch/scope conflation; silent permission changes during mapping.

**Sprint Exit Criteria:** strategy pack approved; pilot validated; Sprint 9 scope sized; actual hours recorded.

---

### Sprint 9 — CBAC Migration & Rollout

**Time span:** ~80h ≈ 10 working days (Sat–Thu), ≈ 2.0 weeks (part 2 of R7; R7 total ≈ 3.0 weeks)
**Sprint Goal:** Application authorization runs on capabilities with `role:` dual-running, full test coverage of allow/deny behavior, and an approved deprecation path for RBAC.

**Product Requirements:** R7 (part 2 of 2)

**Scope**

* Route-level, controller/service-level and UI-level migration to capabilities across the app (ordered by module; pilot first).
* Administrative management of capabilities in production use.
* Compatibility layer so un-migrated areas keep working (`role:` untouched until retired).
* Comprehensive testing: capability allow/deny, role→capability mapping, migrated-vs-unmigrated parity, branch/scope composition.
* Rollout plan execution (per-module), monitoring of denials, and the RBAC deprecation recommendation (criteria + decision — actual retirement may be scheduled later).

**Out of Scope:** deleting the RBAC system (deprecation decision only); authorization *bug fixes* uncovered along the way (log them into the deferred audit backlog); SMS.

**Dependencies:** Sprint 8 strategy pack.

**QA Scope:** parity suite (every route: same outcome before/after); deny cases per capability; UI hidden/shown parity; branch-scoped access unchanged; regression of sprints 1–6 features' authorization; migration idempotency.

**Acceptance Criteria**

* Parity report: 0 unintended allow/deny differences across all routes.
* Capability tests green; suite green.
* Capability admin flow usable by the designated admin role.
* Deprecation criteria met or explicitly deferred with sign-off.

**Risks:** largest regression surface in the roadmap (350 routes); performance of authorization checks (watch item); half-migrated state lasting too long (mitigate: bounded per-module order).

**Future Audit Considerations:** `AUTH-01..14` (current authorization gaps) — discovery will *catalog* them into the deferred backlog; remediation happens in the later engineering phase, not here.

**Sprint Exit Criteria:** parity 0-diff; QA sign-off; deprecation recommendation approved; rollback path for the compatibility layer documented; actual hours recorded.

---

### Sprint 10 — SMS Module

**Time span:** ~40h ≈ 5 working days (Sat–Thu), ≈ 1.0 week
**Sprint Goal:** A reusable, queued SMS capability that sends templated messages for booking, payment, fingerprint, visa and ticket events — with delivery tracking, retries and tests.

**Product Requirements:** R8

**Scope**

* Provider decision (no provider exists in the codebase — selection is a Product Decision, not an assumption) + configuration/secrets handling.
* API integration boundary kept at an event/notification level (avoid per-controller coupling; the app has no existing notification layer to conform to).
* Message templates for the five event families; recipient rules (passenger `mobile_no`, customer `mobile_no`/`ref_mobile_no`, per-event decision).
* Event triggers across booking, payment, fingerprint, visa, ticket flows.
* Delivery status capture, failure handling, retry policy, duplicate prevention, logging (with PII/amount redaction from day one).
* Queue/background processing — **requires a queue worker decision**: none exists today (supervisord runs php-fpm + nginx; no `app/Jobs`, no scheduler). Scope includes the worker/scheduler operational setup or an explicitly accepted synchronous mode.
* Testing strategy (fake/stub provider driver), monitoring/dashboards for failures.

**Out of Scope:** email/in-app notifications; deep per-controller refactors; unrelated audit logging fixes (deferred) — except that *new* SMS logs follow the redaction rule as an intrinsic design requirement; customer-facing preference management (unless decided).

**Existing Codebase Areas:** `passengers.mobile_no`, `customers.mobile_no`/`ref_mobile_no`; the five domain flows' controllers/services; `config/*`; Docker/supervisord (worker); test harness (`CACHE_STORE=array`, `QUEUE_CONNECTION=sync` in tests).

**Dependencies:** none technical (deliberately last); provider + queue decisions before development; R7 shipped (SMS admin access defined via capabilities).

**QA Scope:** each of the five events triggers exactly one message; correct recipient; template rendering with real data; provider failure → retry per policy; duplicate suppression (double events); queue/worker absent → failure mode is safe; log contains no secrets; unauthorized access to SMS admin/monitoring denied.

**Acceptance Criteria**

* All five event families send the correct template to the correct recipient (test-asserted via fake provider).
* Failures retry per the decided policy and are visible in delivery status + logs.
* No duplicate sends for repeated/duplicate events.
* Configuration fully environment-driven; no secrets in repo.
* Monitoring/alerting for failures in place (or explicitly accepted as manual).

**Risks:** provider selection latency; queue worker operational gap; template/content ownership; deliverability/regulatory rules unknown to engineering.

**Future Audit Considerations:** `SEC-05/Q-08` (sensitive logging) — deferred as *remediation*, but new SMS logging must not repeat the pattern.

**Sprint Exit Criteria:** five events live in a controlled rollout; retry/failure verified; tests green; runbook (config, template changes, failure triage) delivered; product confirms message content/language; actual hours recorded.

---

## 3. Product Decisions Required

> **Placeholder policy:** decisions below are intentionally unresolved. Each is resolved at the referenced sprint's Definition-of-Ready review before that sprint's development starts.

**Demurrage (R1) — blocking for Sprint 1**

* How is employee exemption determined (flag on the employee/user, a list, a per-record mark)?
* Is the exemption permanent or transaction-specific? Who can set it? Where is it managed?
* Should exempted compensation remain visible (flagged) or be excluded from totals?
* Which date drives the range: `refund_date`/`re_issue_date` or record creation date?
* Are nullable `payment_by` rows shown anywhere or dropped entirely?
* Which roles may view the report?

**Package Price (R2) — blocking for Sprint 2**

* What is the authoritative "live price" (stored `regular_price` + `service_charge`? offer price when set? fare-derived value)?
* Is the price currency-specific for display purposes (SAR/BDT switching)?
* Do inactive packages display a price (and where)?

**Performance (R3) — blocking for Sprint 3**

* Does "Passenger Index" mean the bookings-page passenger tab (current reality), or is a standalone `/passengers` page expected (currently route exists with no controller method/view — a new build)?
* What improvement target is acceptable (set at baseline review)?

**G-Confirm (R4) — blocking for Sprint 4**

* What are the additional fields? (Not defined anywhere — must be provided.)
* Which are mandatory? Who may enter them? Editable after confirmation?
* Does `revert-group` clear, retain or hide them?

**Same Passenger (R5) — blocking for Sprint 5**

* What happens when passport number is missing on a passenger?
* What normalization applies (case, whitespace, padding, checksum)?
* Does "departure has occurred" use `actual_flight_date`, `flight_date_from`, or a booking-level departure?
* Is active determined per passenger status or per booking status when a booking mixes statuses?
* Which endpoints enforce it (booking create only, or add-passenger too)? What is the concurrency backstop?

**Passenger Package (R6) — blocking for Sprint 7 (design in Sprint 6)**

* Can a passenger change package after booking? Under what authorization?
* What happens to historical financial records (backfill vs derive-on-read)?
* Can different passengers carry different package prices (they naturally would via different packages — confirm)?
* What happens when a passenger is removed — does the booking package reassert?
* Is the booking-level package retained as default/fallback for backward compatibility?

**CBAC (R7) — blocking for Sprint 8**

* Initial capability taxonomy granularity and ownership?
* Which roles map to which capabilities (esp. Super Admin/Co Admin inheritance)?
* Are capabilities global or branch-scoped, and how do the two compose?

**SMS (R8) — blocking for Sprint 10**

* Which provider? Which sender identity/branding?
* Languages/templates and who owns content?
* Retry policy and what constitutes "successful delivery" (accepted vs delivered vs read)?
* Queue worker: adopt background processing (new operational component) or accept synchronous sends with retries?

---

## 4. Definition of Ready

A sprint item is Ready when:

1. Business behavior is understood and written (no undefined rules).
2. Dependencies (data, prior sprints, other teams) are identified and available.
3. Acceptance criteria exist and are testable.
4. Required data/fields are specified (especially new inputs like R4's fields).
5. Authorization expectations are understood **against current RBAC** (route role set identified).
6. QA scope is defined.
7. All Product Decisions marked blocking for that sprint are resolved.
8. Out-of-scope boundaries (incl. deferred audit items) are stated.
9. Hour budget for the sprint is estimated and the predecessor sprint has exited.

---

## 5. Definition of Done

A feature is Done when:

1. Functionality complete per scope.
2. Acceptance criteria satisfied and demonstrated.
3. Current RBAC respected (until the CBAC migration covers it).
4. Validation complete (input rules + user-facing messages).
5. Feature tests written per the testing approach; existing tests still pass (`php artisan test` green).
6. No unintended regression in existing functionality (targeted regression executed).
7. Relevant UI states handled (loading, empty, error, success).
8. Relevant error states handled (failure messages, no silent failure).
9. Documentation updated where necessary (routes/roles notes, user-facing changes).
10. QA accepted.
11. Actual hours recorded against the estimate for calibration.

*Not required for Done:* unrelated audit remediation, dependency upgrades, dead-code cleanup, global performance work.

---

## 6. Testing Approach (per requirement)

The project has a strong baseline (723 tests / 2,833 assertions) — every sprint extends it; tests are written TDD-first per `AGENTS.md`.

| Requirement | Required test coverage |
|---|---|
| R1 Demurrage | employee compensation rows; company rows; exempt employee behavior; multiple records; date filtering incl. boundaries; report totals = row sum; empty set; role denial (403) |
| R2 Package price | authoritative price computation (incl. offer/service-charge variants); dashboard surface; dropdown surface; package-config surface; all three equal for same package |
| R3 Performance | baseline metric capture; post-optimization metric capture; functional regression of bookings page + passenger tab; no behavior change assertions |
| R4 G-Confirm | valid submission; invalid; missing required; unauthorized; each action variant (`all/in/out/both`); revert interaction; history record written |
| R5 Same passenger | active blocks; cancelled allows; delivered allows; departed allows; multi-passenger; passport format variants; missing passport per decision; concurrent attempt |
| R6 Passenger package | one package per passenger; mixed packages in one booking; uniform-booking parity with old behavior; historical booking compatibility; invoice/payment/profit correctness; cancellation/refund on mixed bookings |
| R7 CBAC | capability allow/deny; role→capability mapping; route parity (before/after identical outcomes); UI parity; branch/scope composition; migration idempotency |
| R8 SMS | each event triggers; correct recipient; correct template; provider failure; retry; duplicate prevention; log redaction; queue-absent failure mode |

---

## 7. Deferred Audit Remediation

The following audit findings are **intentionally deferred to a later engineering phase** and are not part of this roadmap. They were deliberately not converted into sprints, and none may reorder the feature sequence above.

* **Security:** login brute-force throttling (`SEC-01`), security headers (`SEC-02`), sensitive-payload logging (`SEC-05/06`), dependency advisories (`SEC-07`), route double-loading (`SEC-04`).
* **Financial integrity:** refund/cancellation race conditions and lock placement (`L-01/L-02/S-03/M-13`), money-math defects (`M-02/M-05/M-06/M-07/M-10`), transaction gaps (`T-02..T-07`), voucher/invoice sequencing (`L-04`, `Q-07`).
* **Authorization:** financial payload exposure (`AUTH-01/04/05/06`), payment/print/document IDOR (`AUTH-02/07/08/09/13/14`), ungated routes (`AUTH-10/11/12`), discount gate (`AUTH-03`).
* **Concurrency:** state-machine guard placement (`S-01..S-06`), stale-response race (`PERF-03`) — *except* the minimal passenger-list request guard allowed inside R3's narrow scope.
* **Database:** missing indexes (`DB-01/02` — only the slices proven to serve R3's two pages may be touched), live `transaction-types` 500 (`DB-03`), soft-delete/cascade retention (`DB-05/06`), unique backstop (`DB-07`).
* **Performance:** global caching/dashboard queries (`PERF-01`), middleware role query (`PERF-02`), fetch error handling (`PERF-04`), synchronous exports (`PERF-05`).
* **Maintainability:** 7,507-line view decomposition (`MAINT-01`), broken invoice route (`MAINT-02`), dead code/components/config (`MAINT-03/04/05/10`), silent catches & raw error messages (`MAINT-08/09`), duplicated auth helpers (`MAINT-07`).
* **Testing:** missing authorization/IDOR/concurrency/payload-redaction tests (`TEST-01..04`), factory gaps (`TEST-05`).

This section exists so the roadmap is not read as having forgotten the audit — it was used, as instructed, to understand architecture and to avoid planning conflicts.

---

## 8. Recommended Sprint Sequence

1. **Demurrage / Compensation (S1)** — read-only report on existing data; smallest unknown surface; establishes report-pattern reuse; unblocked once exemption semantics are decided.
2. **Package Name + Live Price (S2)** — display-level change with no data-model risk; produces the **package-agnostic price rule** that R6 reuses (R2 → R6 compatibility), which is why it must precede R6 even though it touches packages.
3. **Passenger & Booking Performance (S3)** — early because every later feature (especially R4's form and R6's package selectors) lands on these same pages; a faster, measurable baseline prevents later features inheriting a slow surface and prevents "performance work" from being re-litigated during R6.
4. **G-Confirm (S4)** — localized flow change inside ticket domain; independent of R2/R3 except it benefits from S3's page work; blocked only on field definitions.
5. **Same Passenger Restriction (S5)** — self-contained business rule; must land **before** R6 because R6 changes how passengers relate to packages, and the restriction's passenger-identity semantics should stabilize first.
6. **Passenger-Based Package Assignment (S6–7)** — deliberately last of the core six: it is a domain-wide change (booking → passenger ownership) with the largest blast radius (invoice, profit, cancellation, reports, forms, history); it consumes R2's compatibility rule and must not be disturbed by earlier in-flight features. Discovery sprint precedes delivery sprint by design.
7. **RBAC → CBAC (S8–9)** — architecture initiative placed after all core features so its capability taxonomy covers the *final* route/UI surface (including R6's new authorization points) — migrating first would guarantee rework. Dual-run keeps earlier features untouched.
8. **SMS (S10)** — a cross-cutting notification layer that would otherwise have to be retrofitted into every feature touched above; placing it last means its events can hook onto stabilized domain flows (booking, payment, fingerprint, visa, ticket) and it needs provider + queue decisions that should not gate core delivery.

**Governing principle:** business priority → existing architecture → feature dependency → sprint capacity → QA. The audit shaped *where QA attention and risk notes go*; it did not reorder the product.
