# Umrah App — Full Working History Report

> **Scope:** complete git history of this repository, 2026-04-26 → 2026-10-05.
> **Generated:** 2026-10-05 from `git log`, branch/PR topology, migrations, `docs/plans/`, and the test suite.
> **Companion docs:** [Development Handbook](README.md), [AGENTS.md](../AGENTS.md).

---

## 1. Executive Overview

| Metric | Value |
|---|---|
| Total commits | **2,218** |
| Active period | 2026-04-26 → 2026-10-05 (~5.3 months) |
| Contributors | 5 — Mostafiz Ur Rahman (945), shantoriaz6 (596), FUZLUL HAQUE (583), Azhar Ibn Mostafiz (203), Hermes Agent (2) |
| PR merges | **159** (115 in `mostafiz-8bits/umrah-app`, 44 in `saifibnmazhar/umrah-app`) |
| Branch merges | 454 merge commits across **188 distinct branches** |
| Local branches | 60+; remote branches: ~300 |
| Codebase size | 60 Eloquent models, 57 controllers, 153 Blade views, 149 migrations, 618 route lines |
| Tests | 115 test files, ~704 `test_*` methods (SQLite in-memory, PHPUnit 11) |
| Commit style | Conventional Commits adopted mid-project: `fix:` 199, `feat:` 159, `docs:` 57, `chore:` 15, `refactor:` 9, `test:` 5, `perf:` 4, `ci:` 3, `style:` 2 — remaining ~1,765 are informal subjects ("completed taskN", "issue fix") |
| Fix-related commits | **~648** (subject matches fix/bug/issue/solved/problem) |

**Commits & fix-related commits per month:**

| Month | Commits | Fix-related |
|---|---|---|
| 2026-04 | 1 | 0 |
| 2026-05 | 505 | 105 |
| 2026-06 | 556 | 184 |
| 2026-07 | 459 | 117 |
| 2026-08 | 404 | 189 |
| 2026-09 | 280 | 98 |
| 2026-10 (to 10-05) | 13 | 4 |

**What the project is:** a Laravel 12 + Blade/Alpine.js ERP for an umrah travel agency — bookings, passengers, ticketing (issue/re-issue/refund/void), visas, fingerprint tracking, packages & fares, invoices/payments/vouchers, profit & loss and branch/agent/user reports, role-based access, CI/CD and Docker deployment.

---

## 2. Repository & Workflow Topology

- **Remotes:** `origin` = `github.com/mostafiz-8bits/umrah-app` (original team repo), `upstream` = `github.com/saifibnmazhar/umrah-app` (client/main repo). From mid-August the team's daily work moved to `saifibnmazhar` with `upstream/develop` merges into the working branches (e.g. `merge: integrate upstream develop (saifibnmazhar)`, 2026-08-17).
- **Trunk flow:** `main` is the trunk. 2026-05-07 → 2026-06-09 landed via weekly PRs `#1–#30` from `mostafiz-8bits/dev`. After that, main's first-parent line contains **90 PR merges** directly (feature branches like `branch-wise-report`, `booking_cancel_workflow`, `profit_loss_report`) plus **30 `Merge branch 'dev'` integrations** that carried batches of PRs merged into `dev` first (e.g. `#126–#131` ticketing work). From 2026-09 the flow became `develop → staging → main` (10 staging merges on main, roughly daily in September). 159 PR merges exist repo-wide across both GitHub orgs.
- **Branch naming:** highly granular, mostly one branch per feature or bug: `booking_cancel_workflow`, `ticketing/re-issue_refund`, `profitCalculationSystem`, `fingerprintWorkflowAdjustments`, `passenger_cancellation_workflow`, plus issue-style branches (`*_issue`, `*_fix`, `pagination_issue`, `dateRangeIssue`, `toastIssue`).
- **Plan-driven development:** from 2026-09 features start as approved markdown plans in `docs/plans/` (status "PLAN ONLY — DO NOT IMPLEMENT until explicitly approved"), then implementation commits reference the plan.

---

## 3. Development Phases (Monthly Narrative)

### April 2026 — Bootstrap
- `d26dbb83` **Initial Laravel setup** (2026-04-26); `205b37fa` **Add UI reference folder** (2026-05-02) — the static HTML/JS design files that every Blade screen is built against.

### May 2026 — Foundation: schema, models, and reference CRUD (505 commits)
- **Tasklist phase (May 2–3):** 16 numbered "tasks" by FUZLUL HAQUE built the settings module scaffolding (controller → validation → index/create/edit views per entity).
- **Core schema:** `feat: add core database schema with related entities and pivot tables` + Eloquent models (branches, banks, districts, offices, airlines, city codes, classes, pivots); then reference tables (visa agents, ticket agents, customers, commission agents, fingerprint charges, visa agent costs, selling prices).
- **Domain schema:** routes with multi-segments/transits (May 5), ticket fares + group tickets + baggage (May 6), packages + flight date gaps (May 7), bookings/passengers/passenger statuses/documents (May 8), currency rates, transaction types, invoices/payments/vouchers (May 9), visa submissions & cancelled submissions (May 11), fingerprints/details/reschedules (May 12), roles/user_roles (May 20).
- **Screens:** district/reference CRUD, then routes, fares, packages, bookings, passengers, dashboard, customers, invoices, payments, vouchers — each with index/create/edit Blade views and schema/model-based validation.
- **Milestone features:** payments in booking details + invoice calculation (`feat: payment system in booking details`, `feat: dynamically sync invoice financial section`), invoice print redesign, document upload on booking/passenger, baggage display, transit route fixes.
- **Role-based access control (late May):** whole sections restricted by role — ticketing, visa, fingerprint, settings to privileged roles; financial columns and inline editing admin/auditor-only; booking create/edit to admin & branch roles; dashboard cards restricted.
- **First tests:** `BookingEditPackagePreloadTest`-era tests appear (2026-05-21 "pre-selection issue").

### June 2026 — Workflow hardening & the decimal-precision campaign (556 commits, 184 fix commits)
- **Visa workflow:** visa status moved onto `visa_submissions` (`add_status_to_visa_submissions_and_drop_from_passengers`), `visa_update_logs`, cancelled-submission fixes, net-cost editability, visa price/net cost display after submit.
- **Ticketing:** `issued_tickets` + `issued_ticket_logs` tables (2026-06-12), offer price column, ticket option selection in issue modal, PNR under Issued status, offer-price field per passenger type, inbound/outbound issue fields.
- **Branch/office restructure:** offices table dropped, branch code added, bookings' branch/office columns renamed — `branch-office-merge`.
- **Fingerprint:** location-based operation flag on branches, cost logs + detail logs tables, `done` status semantics ("done for all → approve"; "done for 1 pax → done for others"), approval date in status column, admin doc delete, staff permissions.
- **Payments:** sender/receiver bank + remarks columns, receiver_bank backfill migration, "Other" sender bank, branch_id made nullable, referral branch dropdown default fix.
- **Decimal/currency precision campaign (June 17–29):** three phases of migrations raising decimal precision (`increase_decimal_precision…phase2`, booking/passenger columns, visa submissions, fingerprint costs) plus per-form fixes (issue-ticket net/selling/offer fare, visa create/edit, fingerprint staff cost column, page redirect precision).
- **Roles/permissions expansion:** delivery-staff role with booking capability, co-admin fingerprint-cost access, `is_active` users, RBAC refinements.
- **Engineering fixes:** human-readable DB error messages, npm fix (#33), passenger index bug (#31), stay-duration form issues (#35/#36), timezone/date-shift in flight date handling, `order booking/create before resource wildcard`.

### July 2026 — Reports, filters, cancellation v1, branch access (459 commits)
- **Reporting suite built:** branch-wise report (#61), due report (#77/#84), visa agent report (#90), ticket agent report, pending outbound report, report payment history (#95), due collection (#96), payment history branch-wise (#92), user-wise report (#106), profit/loss report (#105), total receiving/profit cards, branch-wise dashboard summary card.
- **Filtering everywhere:** package filter, booking status filter, payment-wise filter, status-change filter, visa agent filter, branch filter, column headline dynamic, compound status filter — plus server-side pagination groundwork (fingerprints, booking index, passenger index).
- **Booking cancellation v1:** `booking_cancel_workflow` PR #101, `cancelled_bookings` table, `is_cancelled` flags, cancelled-booking links on payments/vouchers, update-log tables (booking/passenger).
- **Passenger/index evolution:** current status made dynamic (fingerprint/ticket/visa), remarks column, stay-duration column (max 85 days), PAX-QTY, passenger age calculation (infant/child limits 19 months / 11y7mo), discount column in due.
- **Access control hardening:** admin role (#68), SBI permission (#49), super admin payment edit, package field restricted to Super/Co Admin, fingerprint report restricted to admin/auditor.
- **Finance fixes:** `COALESCE` fallback for `currency_rates.rate` in raw SQL, package calculation bug, due column discounted total, currency toggling issue.
- **Reverted PR:** `revert-112-issued_ticket_review` (2026-07-21) — issued-ticket review feature merged then rolled back.
- **Backfill tooling (July 29–31):** `fix: remove issued ticket fare sync from passengers and add package-based backfill command`, `fix: add financial recalculation command` — introduce artisan backfill/recalc commands after sync logic churn.

### August 2026 — Ticketing workflow v2, cancellations v2, profit system, DevOps (404 commits, 189 fix commits)
- **Big ticketing rewrite (`ticketing/*` branches, PRs #121–#131):** double-ticket + awaiting-group columns, group confirmation system, ticket remarks, issue-out from 3-dot menu, outbound ticket creation, re-issue/re-issue-out forms, ticket status, compound OP/non-OP filters, G-Confirm/G-Cancel visibility, ticket type change clears ticket, baggage allowance fixes, seeder/migrations.
- **Re-issue & refund domain (new tables 2026-07-27 → 08-14):** `re_issue_refund_reasons`, `re_issued_tickets`, `refunded_tickets`, `ticket_requests`, refund links on payments, `refund_payable` on passengers, refund compensation, invoice update logs. Refund voucher system, refunded ticket history, payment options on re-issue.
- **Passenger cancellation workflow v2:** `cancelled_passengers` table + flags + payment/voucher links, soft deletes & `reverted_by` on cancelled bookings, additional ticket value & adjustment IDs, mix payment, confirm-page timezone fix (2026-08-21 → 24).
- **Profit calculation system (`profitCalculationSystem` branch):** `add_profit_to_bookings/passengers/fingerprints` (2026-08-24), total cost on re-issued tickets, profit breakdown modals (ticket/visa/additional/fingerprint/booking), documented bug-fix rounds ("bug a/b/c fixed", "passenger profit fix").
- **Visa hold feature, status change filter, fingerprint workflow adjustments** — each planned in docs then implemented (2026-08-30 `is_visa_held` migration).
- **Infrastructure milestone (2026-08-10):** `feat: add Docker multi-stage build, GitHub CI/CD pipeline, and production deployment setup` — `docker-compose.prod.yml`, `deploy-prod.sh`, `.github/workflows/build-push.yml`, then staging workflow (08-16), CI job splitting/caching (08-13/14).
- **Production firefighting (08-13 → 08-21):** MySQL service/pdo_mysql restore, PHP 8.4 platform pin, setup-php action swap, trust-proxy/HTTPS redirect loop, 413 upload limits + multi-file upload, storage tmp dir for PDFs, Cloudflare TrustProxies middleware, null-user 500s, staging mixed-content fix, idempotent migrations, x-cloak flashes.
- **Performance:** TDD-first query optimization — `perf(query-opt)` eliminating N+1 across dashboard/reports/services, eager-loading currency rates, caching lookups (08-18).
- **Handbook (2026-08-10):** `docs/` Diátaxis-style handbook 01–08 + AGENTS.md conventions.
- **Livewire v4.4 added** to composer (08-24) — installed but not yet used in any Blade view.

### September 2026 — Snapshots, refunds, deploy hardening (280 commits)
- **Fare/price snapshot system (`packageEdit` branch):** plan rewritten to snapshot fares on `issued_tickets`; `booking_service_charge` + backfill, `package_update_logs` & `ticket_fare_update_logs`, historic child/infant percentages for recalc on passenger-type change (plan #14), fare lock hardening, `update fare now have impact on package value`.
- **Package name snapshot (plan #12):** `bookings.package_name` with backfill so renamed packages don't rewrite booking history.
- **Passenger extra charge (plan #13):** `passengers.extra_charge` folded into service charge/profit, SAR/BDT currency toggle sync.
- **Refundable booking cancellation (plan #09) & ticket refund payments tab (plan #10):** `total_passenger_refundable` on cancelled bookings, `refund_payment_requests` + `clean_passengers` migration, voucher-based reporting, currency prefixes on refund modals, passenger status set on refund confirm.
- **Group cancel workflow:** G-Cancel reverts awaiting-group tickets to pending; status snapshot on cancelled bookings (09-20).
- **Server-side pagination indexes** (09-10), passenger index UI refresh/scroll-position fixes, computed status filter mismatch fixes.
- **Void ticket feature (plan):** `void` added to issued ticket log actions (09-27), void option availability, void button branch.
- **Deploy hardening saga (09-26 → 09-28):** plan #15 automated post-deploy cache clear + forced logout → `feat: clear caches and probe CSRF on every deploy, persist Redis sessions` → **reverted** (`revert: drop the CSRF probe`) → corrected root cause (`fix: heartbeat sessions and turn 419 CSRF errors into a graceful redirect` — 419s were idle-session expiry, not caches) → deploy health-check ordering fix.
- **Migrations guarded:** `hasIndex()`/`hasColumn()`/`hasTable()` guards to make migrations re-runnable (09-10, 09-24, 09-25).
- **ERDs generated** (`docs/erd/`, 09-26): overview, booking-core, finance, ticketing, visa, fingerprint, logs, users-auth, travel-fares.

### October 2026 (to 10-05) — Validation & price-sync fixes (13 commits)
- Form validation added across the money-in forms: additional confirmation, re-issue, refund (both forms).
- `fix: sync visa submission selling price with booking package on package change`
- `fix: decide visa submission backfill price from package update logs instead of updated_at`
- Profit-loss print optimization plan approved and implemented (`profitLossPrintLoad`), test fixes, `develop → staging → main` merges.

---

## 4. Feature Inventory by Domain

| Domain | Built | Key artifacts |
|---|---|---|
| **Reference/settings data** | May 2–5 | Districts, branches, banks, airlines, airline cities/classes, city codes, travel classes, transaction types, routes (segments/transits), controllers + index/create/edit views each |
| **Customers & documents** | May 4–9, May 23–27 | `customers`, `documents` (owner_type polymorphic), doc upload fixes throughout May |
| **Packages & fares** | May 6–10, Jul–Sep | `packages`, `ticket_fares`, `group_tickets`, `baggage_allowances`, flight date gaps, service charge, is_active, offer price, fare snapshot system |
| **Bookings** | May 8 → ongoing | `bookings`, booking conditions, value columns, currency_rate_id, package_name snapshot, update logs, discount, edit/create apps with cut-off flight-date algorithm |
| **Passengers** | May 8 → ongoing | `passengers`, statuses, extra charge, refund payable, component profits, hold flag, cancellation |
| **Ticketing** | Jun → Sep | `issued_tickets` + logs, issue/issue-out, double ticket, awaiting group, group confirmation, re-issue/re-issue-out, refunded tickets, ticket requests, void, ticket remarks, ticket agent report |
| **Visas** | May 11 → Aug | `visa_submissions`, status + update logs, selling prices, agent costs, hold feature, revert/re-submission, agent report, admin form |
| **Fingerprints** | May 12 → Aug | fingerprints/details/reschedules, cost logs + detail logs, location-based operation, workflow adjustments, staff/admin pages, report |
| **Finance** | May 9 → Sep | invoices/payments/vouchers + update logs, currency rates (BDT/SAR toggles), payment sender/receiver banks, refunds, refund vouchers, discount sync |
| **Reports** | Jul → Sep | Profit/loss (per customer/passenger tabs), branch-wise, due, due collection, payment receiving/history, visa agent, ticket agent, pending outbound, user-wise sales, dashboard cards/modals |
| **Profit calculation** | Aug → Sep | `profit` columns on bookings/passengers/fingerprints, breakdown modals, component profits, extra charge, service charge snapshot |
| **Cancellations** | Jul → Sep | Booking cancellation (v1 Jul, refundable v2 Sep), passenger cancellation (Aug), group cancel/G-Cancel (Sep), cancelled bookings/passengers tables, revert support |
| **Auth/roles** | May 20 → Sep | roles/user_roles, RBAC per section, branch-level access, delivery staff, admin/co-admin/super admin/auditor, is_active, access hardening for packages/fares |
| **Dashboard** | May → Sep | Summary cards, branch-wise profit, fingerprint profit, request/package sections by role, review box, round-figure fixes, modal breakdowns |
| **Docs/ERD** | Aug → Sep | Handbook 01–08, plans 09–15, ERD suite, AGENTS.md |

---

## 5. Feature Adjustments & Iterations

Evidence of significant rework (not just first builds):

1. **Booking/passenger cancellation — three iterations.** v1 `booking_cancel_workflow` (Jul, PR #101) → passenger-level cancellation with `cancelled_passengers` + adjustments (Aug 21–24) → refundable-amount redesign (`docs/09-plan-refundable-booking-cancellation.md`, revised 09-05/09-06 with "three-case editability, voucher-based reporting, test fixtures") → group cancel/G-Cancel (Sep) with status snapshots (09-20).
2. **Re-issue/refund feature plan v1 → v2.** `docs: update re-issue form plan to v2 — legacy preservation + gap fixes` (Sep), then `reissue updated plan implemented` — legacy data preserved while closing gaps; October added client-side validation on all three forms.
3. **Fare snapshot system rewritten.** `docs: rewrite plan for fare snapshot system using issued_tickets columns` → `docs: update fare snapshot plan with user feedback and issue fixes` → implementation (historic child/infant %, fare lock, backfills). Follow-ups: package name snapshot (#12) and extra charge (#13) both revised the day after drafting.
4. **Issued-ticket review PR reverted.** PR #112 merged 2026-07-21 then immediately rolled back by PR #113.
5. **Issued-ticket ↔ passenger fare sync removed.** `fix: remove issued ticket fare sync from passengers and add package-based backfill command` (07-29) — replaced live sync with an artisan backfill; then `fix: decide visa submission backfill price from package update logs instead of updated_at` (10-04) replaced an `updated_at` heuristic with explicit `package_update_logs`.
6. **Office → Branch model.** Offices table dropped (06-12), branch code added, `branch-office-merge` branch, bookings' office columns renamed.
7. **Passenger current status recomputed repeatedly:** static column → dynamic from fingerprint/ticket/visa (Jun) → stored status fix (07-02) → computed status filter mismatch plan (Sep) → `clear_computed_passenger_statuses` migration (08-31).
8. **Deploy cache-clear plan round 2.** Plan #15 Phase 1-4 implemented, CSRF probe **reverted**, then corrected diagnosis: 419 = idle session expiry → heartbeat sessions + graceful redirect.
9. **Repo/remote migration.** Work moved from `mostafiz-8bits` to `saifibnmazhar` (Aug); PR numbering restarted (#1–#44); `upstream/*` merges reconciled both lines through August–September.
10. **Reversals/reverts in history:** `Revert "fix: harden booking creation error handling…"` (08-06), `revert: drop the CSRF probe…` (09-28), `revert-112-issued_ticket_review`.

---

## 6. Bugs & Errors Fixed

~648 fix-related commits (29% of all commits). Keyword frequency across subjects: ticket 250, passenger 227, booking 196, date 165, filter 123, fingerprint 106, visa 103, payment 96, deploy/CI 85, package 85, docs/plan 86, permission/role/access 83, currency/decimal 77, profit 66, invoice 62, route 55, test 35, dashboard 31, pagination 29, validation 23.

### 6.1 Money & precision (highest-severity class)
- Three migration phases raising decimal precision (06-17, 06-20, 06-22) + visa/fingerprint-specific precision (06-25, 06-26).
- Per-form decimal fixes: issue-ticket net/selling/offer fare, visa create/edit, fingerprint cost columns, "decimal_issue_of_forms" branch.
- Currency toggle (BDT/SAR) bugs: fingerprint staff toggle, ticket fare create/edit, due report PDF, package conversion, visa admin form, currency toggling issue, `COALESCE` fallback on `currency_rates.rate` in raw SQL (07-14), currency prefix on refund modals (Sep), `currency prepix fixed in invoice` (Sep), `currency_round_issue_for_bank_pay`.
- Negative balances allowed on invoices (07-02 migration).

### 6.2 Dates, ranges & timezones
- Flight-date cut-off algorithm ported to edit/show pages; timezone date-shift fix (May); `flight_date_range_edit`, `date_range_issue`, `dateRangeValidation`, `date-format`, `date-range` branches; expected flight date filter (Aug); booking date column on profit/loss tabs (Sep).

### 6.3 Filters, search & pagination
- 123 filter commits: status/branch/package/payment-wise/visa-agent/route/date filters across indexes and reports; "filter-bug", "empty-filter", "filtering_issue_in_report".
- Server-side pagination: indexes migration (09-10), pagination applied to booking/passenger/fingerprint indexes, `pagination_fix` PR #123, `pagination_issue` branch with conflict-heavy merges, profit-loss **print/load** performance problem (`profitLossLoadIssue`, `profitLossPrintLoad`, plans 09-08/09-29 → optimization implemented 09-30).

### 6.4 Permissions & access
- `fix: redirect disabled visa selling price URLs and restore auth middleware` (Sep), `fix: harden package and ticket-fare access control` (Sep), branch-level dashboard access, SBI permission (#49), branch user access issue, `fix: guard passengers input, branch-check passenger update` (Sep), undefined-variable filter-permission errors (Jun).

### 6.5 Forms & validation
- Required-field enforcement (issue-ticket form #93, service-required filter), Alpine edit-form crash (07-04), stale modal data (reset ticket_option/BDT fields, Sep), toggle-field issues, passenger create/edit required flight-date slot + stay duration enforcement (Sep), duplicate route, double-submit progress bars (08-07), client-side validation on submit buttons (Aug).

### 6.6 Data integrity & backfills
- Backfill/recalc commands (07-29/31), `update_fare` impacting package value, package value recalculation, table-already-exists / `tearDownAfterClass` test-suite fixes (08-24), idempotent migrations (08-17), guarded indexes/columns (09-10 → 09-25), `clear_computed_passenger_statuses` (08-31), additional ticket issued-date backfill (09-30).

### 6.7 Production/DevOps incidents (August & late September)
- CI: `setup-php` action inaccessible → `shivammathur/setup-php@v2`; MySQL service/pdo_mysql restored; PHP 8.4 platform pin + composer.lock regen + ext-gd; asset build added to test job; parallel unit/feature jobs + Composer cache.
- Production: redirect loop (`SESSION_SECURE_COOKIE` + proxy Proto), trust proxies + forced HTTPS, 413 upload limits (nginx + PHP + `max_file_uploads`), PDF generation failing (missing `storage/app/tmp`), Cloudflare IP trust (`TrustProxies` middleware), `null user()` 500, mixed-content on staging, `Failed to load trust proxies from Cloudflare` (documented in AGENTS.md — `LARAVEL_CLOUDFLARE_ENABLED=false` escape hatch).
- Deploy: health-check ordering before permissions/migrations, `MIGRATE=false` staging sample, image tag naming (`staging` not `staging-latest`), Watchtower labels, 419 CSRF graceful redirect.

### 6.8 UI/UX & misc
- Toast issue, modal background/positioning, x-cloak flashes (3 commits, 08-18), column widths (`collumn_width`, `columnWidthRestore`, fixed-width removal), scroll position on passenger index refresh, mobile column widths, menu/navigation bugs (very first bug commits: `booing menu bug fixed`, `solved dashboard bug`), human-readable DB errors, action menus closing on click.

---

## 7. Infrastructure, Testing & Documentation History

**CI/CD & Docker (all introduced 2026-08-10+):**
- `feat: add Docker multi-stage build, GitHub CI/CD pipeline, and production deployment setup` (08-10) → staging workflow (08-16) → PHP 8.4 pin/ext fixes (08-13) → parallel test jobs + caching (08-14) → `staging.yml` tag fix (08-16).
- Workflows today: `.github/workflows/build-push.yml` (test-php → test-js → Docker build/push to ghcr.io) and `staging.yml` (no SSH deploy). Deploy scripts: `deploy-prod.sh`, `deploy-staging.sh`. Entry point runs cache + migrations.
- Note: **no Watchtower auto-deploy actually runs** despite the label (documented in AGENTS.md §8).

**Testing:**
- Test suite grew from a single May test to **115 files / ~704 test methods**, concentrated in Aug–Sep: 13 booking-form workflow tests (08-15), TDD query-opt tests (08-18), cancellation/invoice/re-issue/refund suites (08-19 → 09), profit fixture alignment, revert-redirect expectations.
- CI failures fixed iteratively: table-already-exists, `tearDownAfterClass` cleanup, `migrate:fresh` in try/catch, missing `tearDownAfterClass` cascade (Sep).

**Documentation:**
- 2026-08-10: Development Handbook (`docs/01`–`08`, Diátaxis) + later AGENTS.md.
- Plans: `09` refundable cancellation, `10` ticket refund payments tab, `11` fare snapshot before/after + manual tests, `12` booking package-name snapshot, `13` passenger extra charge, `14` historic child/infant %, `15` automated deploy cache-clear/forced logout — several with explicit "PLAN ONLY" approval gates and revision rounds.
- 2026-09-26: ERD suite (`docs/erd/*.mmd` + SVG/PDF) for overview, booking, finance, ticketing, visa, fingerprint, logs, users-auth, fares.

---

## 8. Appendix

### A. Methodology
- Aggregations from `git log` (all refs): monthly counts, subject keyword themes, conventional-commit prefixes, PR/branch merge counts, contributors (`git shortlog -sn --all`), `--first-parent main` trunk timeline.
- Cross-checked against `database/migrations/` (149 files with dated prefixes), `app/Http/Controllers`, `resources/views`, `docs/plans/`, `tests/`, `.github/workflows/`.
- "Fix-related" = subject matching `fix|bug|issue|solved|problem` (captures informal subjects like "issue fix"); may over-count feature branches named `*_issue`.

### B. Notable commits (trunk milestones)
| Date | Commit | Subject |
|---|---|---|
| 2026-04-26 | `d26dbb83` | Initial laravel setup |
| 2026-05-03 | `e59067c8` | feat: add core database schema with related entities and pivot tables |
| 2026-05-07 | PR #1 | First dev → main merge |
| 2026-06-12 | — | issued_tickets + logs migrations |
| 2026-07-15 | PR #101 | booking_cancel_workflow |
| 2026-08-10 | `3d8b1b9e` | feat: Docker multi-stage build, GitHub CI/CD, production deployment |
| 2026-08-17 | — | merge: integrate upstream develop (saifibnmazhar) |
| 2026-08-24 | — | profit columns migrations |
| 2026-09-13 | — | first `develop → staging → main` cadence begins |
| 2026-09-26 | — | ERD generated + deploy cache-clear plan |
| 2026-10-01 | PR #43/#44 | profitLossPrintLoad + validation round |

### C. Timeline at a glance
```
Apr 26  May                 Jun                 Jul                 Aug                 Sep            Oct 5
  |       |                   |                   |                   |                   |              |
setup → schema+models+CRUD → visa/ticket/fin     → reports+filters    → ticketing v2        → snapshots     → validations
        RBAC+invoices        precision campaign   → cancellation v1    → cancellation v2     → refunds       → visa price
        first tests          fingerprint logs     → backfill commands  → profit system       → group cancel  → sync fixes
                             office→branch        → PRs #31–#131       → Docker/CI/CD        → deploy 419 fix
                                                  → PRs #100–#131      → prod firefighting   → ERDs
                                                                      → handbook+tests      → plan-gated dev
```
