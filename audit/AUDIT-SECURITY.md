# AUDIT - SECURITY (SEC, AUTH, XSS/CSRF)

**31 findings:** 4 HIGH, 13 MEDIUM, 7 LOW, 7 INFO - all with file/line evidence (see `AUDIT-FINDINGS.md` for the canonical table).
**Method:** static route + middleware analysis (350 routes), controller-level authorization cross-check, full view-layer output scan, dependency advisory scans. No exploit was executed; "exploit scenarios" below are code traces.

---

## 1. Route / middleware posture

| Bucket | Count | Notes |
|---|---:|---|
| `auth` + `role:` | 264 | intended posture |
| `auth` only | 53 | includes several money/PII endpoints (below) |
| `role:` only (no `auth`) | 26 | all from `routes/booking-cancellation.php`; guests get 403 not a redirect (`CheckRole.php:12-15`) |
| guest | 7 | login GET/POST, logout, `_session/ping`, `up`, `storage/*` |
| public mutating | 0 | `routes/web.php:139` deliberately `abort(404)`s the old GET toggle |

**The 53 auth-only routes that matter** (full list in `AUDIT-INVENTORY.md`): `POST /bookings/{id}/payment`, `GET /bookings/{id}/print`, both document upload routes, `documents/{id}` download+destroy, `GET /api/bookings/passengers`, `GET /api/ticket-fares/filter`, `GET /ticket-fares/options`, the whole `passenger-statuses` resource, `POST /api/banks/quick-create`, `GET /reports/branch-wise`, `GET /invoices/{id}/print`, `POST /diagnostics/upload-failure`.

Middleware order verified: `auth` runs before `CheckActive` before `CheckRole`, so deactivated accounts are invalidated before any role check (`AUTH-15`).

---

## 2. HIGH findings - evidence and exploit traces

### SEC-01 - No login throttling (HIGH)
* **Evidence:** `routes/web.php:69` `Route::post('/login', [LoginController::class, 'login'])` - only the `web` stack. No `RateLimiter::for(...)` anywhere in `app/` or `bootstrap/`; the only `throttle` middleware in the app is on `routes/web.php:172,175,178` (refunds) and `:613`.
* **Exploit:** scripted credential stuffing against `POST /login` at unlimited rate. Laravel's default session/bcrypt cost slows each attempt but nothing stops distributed attempts; there is no lockout, no delay, no telemetry.
* **Fix:** in `AppServiceProvider::boot`:
  ```php
  RateLimiter::for('login', fn (Request $r) =>
      Limit::perMinute(5)->by(Str::lower($r->input('email')).'|'.$r->ip()));
  ```
  then `->middleware('throttle:login')` on the login routes (add a second limiter keyed by IP only for the no-email case).
* **Verify:** `for ($i=0;$i<6;$i++) POST /login` (bad creds) -> the 6th returns **429**; a correct login after the window still succeeds. Feature test: 6 bad posts, assert 429 on the last.

### AUTH-01 - Financial JSON for every authenticated role (HIGH)
* **Evidence:** route at `routes/web.php:221` inside the bare `auth` group (`:84`). Payload built in `BookingController::passengerData()` - `profit` (`:549`), `refund_payable` (`:550`), invoice `total_amount/balance/paid_amount` (`:571-573`), `cost` (`:592`), summary `total_package_value`/`total_due`/`total_due_bdt` (`:631-634`), `profit_breakdown` (`:802-803`). The only gate is view-side: `bookings/index.blade.php:125` `$canViewFinancialColumns` (Super Admin/Co Admin/Auditor) which drops `<th>` cells - the JSON is unchanged. `BookingPassengerQuery.php:40-50,259-263` only adjusts *filters*.
* **Exploit:** any logged-in role (Delivery/Fingerprint/Visa/Ticket Staff) calls `GET /api/bookings/passengers?type=passenger` in a loop over pages and collects per-passenger profit, cost and invoice balances for the whole book - the classic hidden-column/visible-payload defect.
* **Fix:** extract `User::canViewFinancials()` (same expression as `bookings/index.blade.php:125`) and (a) unset `profit`, `profit_breakdown`, `cost`, `package_value`, `refund_payable`, invoice totals for callers who lack it, or (b) add `role:` middleware and serve a reduced endpoint to everyone else.
* **Verify:** feature test as `Delivery Staff` -> `assertJsonMissingPath('data.0.profit')`, `assertJsonMissingPath('data.0.profit_breakdown')`; repeat as `Super Admin` -> keys present.

### AUTH-02 - Payments without role or branch check (HIGH)
* **Evidence:** `routes/web.php:186` `POST /bookings/{booking}/payment` has no `role:` while `:185` (`bookings.update`) and `:187` (`bookings.destroy`) do. `storePayment()` (`BookingController.php:2697-2716`) never calls `ensureBranchAccess()` - defined at `:128-135` and used at `:1888,2002,2241,2286,2320,2483,2879,2896`. Downstream it also writes `cancelled_bookings.refund_amount` (`:2771-2774`).
* **Exploit:** a low-privilege user in branch A posts a payment against a booking in branch B, moving recorded cash and (via `M-06`) rewriting `paid_amount`/`balance`.
* **Fix:** `->middleware('role:...')` on the route + `abort_unless`-style `ensureBranchAccess($booking)` as the first statement.
* **Verify:** `assertForbidden` for (a) a role without payment rights, (b) an authorized user from another branch.

### AUTH-03 - Discount bypass on invoice creation (HIGH)
* **Evidence:** `BookingController.php:1453-1455` zeroes `discount_type`/`discount_value` for non-admins (route `POST /bookings`, `routes/web.php:155`), but `:1644-1656` builds the invoice from `$validated['discount_type']`/`['discount_value']` - the raw request. Validation accepts both at `:1347-1348`.
* **Exploit:** a non-admin posts `discount_type=percent&discount_value=100`: the booking row shows no discount (gate passed) while the created invoice is discounted - ledger disagreement, and money is given away by a role that must not be able to.
* **Fix:** compute the invoice from the role-normalised `$booking->discount_type`/`discount_value` (reuse the `:1453` gate), or unset both keys once at validation.
* **Verify:** non-admin POST with a discount -> invoice `total` identical to the undiscounted total; admin POST -> discount applied.

---

## 3. MEDIUM findings - evidence and fixes

| ID | Finding and evidence | Fix | Verify |
|---|---|---|---|
| SEC-07 | Advisories: `laravel/framework` 12.66.0 < 12.69 (XSS on debug page - mitigated by `APP_DEBUG=false` in `.env.production.sample`), `league/commonmark` med+high (0 markdown usage in app), `league/flysystem` low (control-char path bypass - relevant: filenames come from clients); npm `axios` high (dev, only `resources/js/bootstrap.js`), `esbuild` low (dev server) | `composer update laravel/framework --with-dependencies`; drop axios/bootstrap if unused; verify commonmark is unreachable | `composer audit --locked` -> 0 high; `npm audit --omit=dev` -> 0 high |
| AUTH-04 | `net_fare`/`selling_fare` for all fares (incl. inactive, `bookings/index.blade.php:52-82`) built at `:106-107` and serialized at `:3478` - **before** `$canViewFinancialColumns` is computed at `:125`, so the gate cannot apply | hoist the gate above line 52 and drop cost fields, or load fares from a gated endpoint | view-source of `/bookings` as Visa Staff contains no `"net_fare"` |
| AUTH-05 | `/api/ticket-fares/filter` (`routes/web.php:222`) returns `'net_fare' => $fare->net_fare` (`TicketFareController.php:376`) | gate by role or exclude the key | `assertJsonMissingPath('data.0.net_fare')` |
| AUTH-06 | `/ticket-fares/options` (`routes/web.php:119`) returns whole `TicketFare` models (`TicketRequestController.php:729-750`); `TicketFare` has no `$hidden` (only `User.php:37` does) | `$hidden = ['net_fare','offer_price',...]` or a DTO | options payload has no cost fields |
| AUTH-07 | `DocumentController::download` (`:64-86`) - no ownership, no branch check; `upload`/`uploadPassenger` (`:15-62`, `:88-129`) accept arbitrary `booking_id`/`passenger_id`; routes `routes/web.php:211-213` are auth-only | assert owner as `PassengerController.php:450` does, plus `ensureBranchAccess` | other-user/other-branch download -> 403 |
| AUTH-08 | `PassengerController::downloadDocument` (`:447-474`) checks owner but not branch; `downloadAllDocuments` (`:507-513`) checks neither (siblings at `:75,283,553` do) | `ensureBranchAccess($passenger)` on both | cross-branch -> 403 |
| AUTH-09 | `GET /bookings/{booking}/print` (`routes/web.php:183`) -> `BookingController::print` (`:2552`) with zero `ensureBranchAccess`/`abort(403)` hits across `:2552-2696` (compare `:2896`) | `ensureBranchAccess` + `role:` | cross-branch print -> 403 |
| AUTH-10 | `Route::resource('passenger-statuses', ...)` (`routes/web.php:151`) - all 6 REST routes auth-only; `PassengerStatusController` does no internal check | add `role:` (it is master-data CRUD) | non-admin -> 403 |
| AUTH-11 | `POST /api/banks/quick-create` (`routes/web.php:613`) -> `BankController::quickStore` (`:74-98`) creates bank master data for any authenticated user | add `role:` | non-admin -> 403 |
| AUTH-12 | `GET /reports/branch-wise` (`routes/web.php:535`) auth-only while `:533`, `:534`, `:536` require `role:...,Auditor` | add the same `role:` | non-auditor -> 403 |
| AUTH-13 | `DELETE /documents/{document}` (`DocumentController.php:131-140`) - role-only (Super/Co Admin or Fingerprint Admin), no branch scope, unlike `PassengerController.php:478-480` | reuse the role+branch rule | cross-branch delete -> 403 |
| AUTH-14 | `PassengerController::updateStatus` (`:840-889`) lacks `ensureBranchAccess` which every sibling has (`:75,283,479,774,808`); its catch (`:883`) does not log | add branch check + `Log::error` | cross-branch status change -> 403 |
| XSS-01 | Client filename -> `innerHTML` at 4 sites: `passengers/show.blade.php:615`, `passengers/edit.blade.php:1260`, `bookings/show.blade.php:1844,1880`; value from `DocumentController.php:117,50` `getClientOriginalName()` (the `mimes:` rule validates content, not name). A correct `escapeHtml()` already exists at `bookings/show.blade.php:1192-1196` and is used at `:1436-1439` | wrap all four with `escapeHtml()`; sanitise on write (strip `<>` / server-generate the name) | upload `<img src=x onerror=alert(1)>.pdf` -> literal text, no alert |

---

## 4. LOW and INFO (brief)

* **SEC-02** (LOW, Needs Verification): `docker/nginx/conf.d/default.conf` sets no HSTS/X-Frame-Options/Referrer-Policy/COOP. Whether Cloudflare supplies them cannot be read from this repo - verify at the edge with `curl -I https://<host>`.
* **SEC-03** (LOW): 26 role-only routes give guests `403` instead of a login redirect (`CheckRole.php:12-15`). Fix: require `auth` first (also fixes the odd `route:list` middleware bucket).
* **SEC-04** (LOW): `routes/booking-cancellation.php` is loaded twice - `routes/web.php:618` (outside the auth group that ends at `:616`) and `bootstrap/app.php:24-27`. Load once, inside `auth`.
* **SEC-05** (LOW): full payment payloads logged (`PaymentService.php:20,24,86`), a payment debug line (`BookingController.php:1661`), raw SQL bindings (`bootstrap/app.php:54-59`). Redact/downgrade; drop binding logs in prod.
* **SEC-06** (LOW): `POST /diagnostics/upload-failure` logs unvalidated input (`routes/web.php:165`).
* **SEC-08** (INFO): 0 hardcoded secrets in the tracked tree; `.env.production.sample` keeps `APP_DEBUG=false` and `SESSION_SECURE_COOKIE=true`.
* **XSS-02** (LOW, Likely): the app's single `x-html` (`bookings/index.blade.php:626`) renders unescaped city `code` values (admin-set via `CityCodeController`, `routes/web.php:95`) - privilege-abuse/persistence XSS, not external.
* **XSS-03** (LOW): the app's single `{!! !!}` (`components/empty-state.blade.php:4`) sits in a never-used component - latent sink; delete or escape.
* **XSS-04 / CSRF-01..03** (INFO): verified clean - 104 `@php` with 0 raw echoes; 71 `@json` and 0 `json_encode` in views; all 70 state-changing `fetch` calls send `X-CSRF-TOKEN`; 23 non-GET forms without `@csrf` are all JS-intercepted with a token; 0 state-changing GET routes; every view-hidden sensitive action has a matching route `role:` or controller `abort(403)` (traced for visa actions, discount, document delete, booking edit).
* **AUTH-15 / AUTH-16** (INFO): deactivated accounts are invalidated per request (`CheckActive`); `/api/*` shares the session `web` stack (no token auth model - document it before building a real API).

---

## 5. Remediation order (security)

1. `SEC-01` login throttle (hours).
2. `AUTH-01` financial payload gating + `AUTH-04/05/06` `net_fare` removal (day).
3. `AUTH-02/09/10/11/12/13/14` route+controller gates (day).
4. `AUTH-03` discount gate (hours).
5. `AUTH-07/08` document IDOR + `XSS-01` filename escaping (day).
6. `SEC-07` framework upgrade to 12.69+ (hours, then `php artisan test`).
7. LOWs in one pass (`SEC-02..06`, `XSS-02/03`).

Each fix should land with its "Verify" test from the table - the TEST findings (`TEST-01..04`) are exactly the missing regression net for this file.
