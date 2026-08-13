# Invoice & Booking Audit Logging — Implementation Plan

## Goal
Make every change (create, update, delete) to `bookings` and `invoices` tables persist in log tables, with a human-readable `reason` for `invoice.total_amount` changes (passenger add/remove/update, package change, re-issue customer payment).

## Files to create
1. `database/migrations/2026_08_13_000005_create_invoice_update_logs_table.php`
2. `database/migrations/2026_08_13_000006_fix_update_log_cascades_table.php`
3. `app/Models/InvoiceUpdateLog.php`
4. `app/Observers/InvoiceObserver.php`

## Files to modify
- `app/Models/Invoice.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/InvoiceService.php`
- `app/Services/BookingService.php`
- `app/Http/Controllers/BookingController.php`
- `app/Http/Controllers/PassengerController.php`
- `app/Http/Controllers/ReIssueController.php`
- `app/Http/Controllers/TicketRequestController.php`
- `app/Observers/BookingObserver.php`
- `app/Observers/PassengerObserver.php`
- `app/Services/CancellationService.php` (optional reason context)

---

## Change 1 — Migration: `invoice_update_logs` table

```php
// database/migrations/2026_08_13_000005_create_invoice_update_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_update_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');                 // created | updated | deleted
            $table->string('reason')->nullable();     // why total_amount changed
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_update_logs');
    }
};
```

Key points: `invoice_id` FK is `nullable` + `nullOnDelete` so a `deleted` log survives invoice deletion. `user_id` nullable so finance/queued flows log even without auth.

## Change 2 — Migration: fix cascade bug on existing log tables

Current FKs use `cascadeOnDelete`, which destroys the just-written `deleted` log rows when the parent row is removed. Convert to `nullOnDelete`:

```php
// database/migrations/2026_08_13_000006_fix_update_log_cascades_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_update_logs', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
        });

        Schema::table('passenger_update_logs', function (Blueprint $table) {
            $table->dropForeign(['passenger_id']);
            $table->unsignedBigInteger('passenger_id')->nullable()->change();
            $table->foreign('passenger_id')->references('id')->on('passengers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_update_logs', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::table('passenger_update_logs', function (Blueprint $table) {
            $table->dropForeign(['passenger_id']);
            $table->unsignedBigInteger('passenger_id')->nullable(false)->change();
            $table->foreign('passenger_id')->references('id')->on('passengers')->cascadeOnDelete();
        });
    }
};
```

## Change 3 — Model `InvoiceUpdateLog`

```php
// app/Models/InvoiceUpdateLog.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceUpdateLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['invoice_id', 'user_id', 'action', 'reason', 'old_values', 'new_values'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
```

## Change 4 — `Invoice` model: transient reason property

Add to the class body (not fillable, not a DB column):

```php
public ?string $audit_reason = null;
```

## Change 5 — `InvoiceObserver`

```php
// app/Observers/InvoiceObserver.php
<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\InvoiceUpdateLog;
use Illuminate\Support\Facades\Auth;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        InvoiceUpdateLog::create([
            'invoice_id' => $invoice->id,
            'user_id'    => Auth::id(),
            'action'     => 'created',
            'reason'     => $invoice->audit_reason ?? 'created',
            'old_values' => null,
            'new_values' => $invoice->attributesToArray(),
        ]);
    }

    public function updated(Invoice $invoice): void
    {
        $dirty = $invoice->getDirty();
        if (empty($dirty)) return;

        $original = $invoice->getOriginal();
        $old = $new = [];
        foreach ($dirty as $key => $value) {
            $old[$key] = $original[$key] ?? null;
            $new[$key] = $value;
        }

        InvoiceUpdateLog::create([
            'invoice_id' => $invoice->id,
            'user_id'    => Auth::id(),
            'action'     => 'updated',
            'reason'     => $invoice->audit_reason ?? 'updated',
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    public function deleting(Invoice $invoice): void
    {
        InvoiceUpdateLog::create([
            'invoice_id' => $invoice->id,
            'user_id'    => Auth::id(),
            'action'     => 'deleted',
            'reason'     => 'deleted',
            'old_values' => collect($invoice->attributesToArray())->except(['created_at', 'updated_at'])->toArray(),
            'new_values' => null,
        ]);
    }
}
```

Unlike Booking/Passenger observers, this one **always logs** (nullable user) to guarantee audit coverage.

## Change 6 — Register observer

`app/Providers/AppServiceProvider.php`:

```php
use App\Models\Invoice;
use App\Observers\InvoiceObserver;
// in boot():
Invoice::observe(InvoiceObserver::class);
```

## Change 7 — Carry reason through `updateTotals`

`app/Services/InvoiceService.php`:

```php
public function updateTotals(Invoice $invoice, float $newTotal, ?string $reason = null): void
{
    $invoice->audit_reason = $reason;
    $invoice->total_amount = $newTotal;
    $invoice->balance = max(0, $newTotal - $invoice->paid_amount);

    $invoice->status = match (true) {
        $invoice->balance <= 0 => InvoiceStatus::PAID,
        $invoice->paid_amount > 0 => InvoiceStatus::PARTIAL,
        default => InvoiceStatus::PENDING,
    };

    $invoice->save();
}
```

## Change 8 — `BookingService` updates

`app/Services/BookingService.php`:

- `syncFinancials(Booking $booking, ?string $reason = null)` — pass `$reason` into `updateTotals($invoice, $discountedTotal, $reason)`.
- Replace `saveQuietly()` with `save()` so observers fire:
  - `recalculateBookingTotal()`: `$passenger->saveQuietly();` → `save()`, and `$booking->saveQuietly();` → `save()`
  - `syncFinancials()`: `$booking->discount_amount` save → `save()`

## Change 9 — `BookingController` call sites

`app/Http/Controllers/BookingController.php`:

- `syncBookingFinancials(Booking $booking, ?string $reason = null)` → pass through `syncFinancials($booking, $reason)`.
- `store()`: `$booking->saveQuietly();` → `save()`; set `$invoice->audit_reason = 'booking_created';` before the `$invoice->save()` for the discounted total.
- `addPassenger()` → `syncBookingFinancials($booking, 'passenger_added')`
- `removePassenger()` → `syncBookingFinancials($booking, 'passenger_removed')`
- `update()` → `syncBookingFinancials($booking, 'booking_updated')`
- `updateFingerprintLocation()` → `syncBookingFinancials($booking, 'fingerprint_location_updated')`
- `recalculatePassengerValue()` → `syncBookingFinancials($booking, 'passenger_value_recalculated')`

## Change 10 — `PassengerController` call sites

- `update()`: `$this->bookingService->syncFinancials($booking, 'passenger_updated');`
- `destroy()`: `$this->bookingService->syncFinancials($booking, 'passenger_removed');`

## Change 11 — Re-issue customer payment paths

Both `app/Http/Controllers/ReIssueController.php` (line ~158) and `app/Http/Controllers/TicketRequestController.php` (line ~261):

```php
app(InvoiceService::class)->updateTotals(
    $invoice,
    (float) $invoice->total_amount + $totalCustomerPayment,
    're_issued_ticket_customer_payment'
);
```

## Change 12 — `BookingObserver` / `PassengerObserver`: add `created` event

```php
public function created(Booking $booking): void
{
    $user = Auth::user();
    if (!$user) return;

    BookingUpdateLog::create([
        'booking_id' => $booking->id,
        'user_id'    => $user->id,
        'action'     => 'created',
        'old_values' => null,
        'new_values' => $booking->attributesToArray(),
    ]);
}
```

Same pattern for `PassengerObserver` (`passenger_id`, `PassengerUpdateLog`, `Booking` → `Passenger`).

## Change 13 — CancellationService reason context (optional)

- `cancel()`: `$invoice->audit_reason = 'booking_cancelled';` before `$invoice->update(['status' => CANCELLED])`
- refund block (line ~173): `$invoice->audit_reason = 'refund';` before the `$invoice->update([...])`

---

## Verification
- `php artisan migrate` (no errors, FKs rebuilt)
- `php artisan test` / existing test suite
- Manual trace: add passenger → `invoice_update_logs` row with `action=updated, reason=passenger_added, old_values.total_amount → new_values.total_amount`