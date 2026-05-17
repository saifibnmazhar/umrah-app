# Payment System Implementation Plan

## Overview

This document outlines the complete implementation plan for a synchronized accounting/payment workflow in the Laravel-based Umrah Booking application.

---

## Current Architecture Analysis

### Existing Components

| Component | Status |
|-----------|--------|
| `bookings` table | ✅ Has `invoice_id` field |
| `invoices` table | ⚠️ Minimal - needs enhancements |
| `payments` table | ⚠️ Partial - needs invoice relation |
| `vouchers` table | ⚠️ Partial - needs invoice relation |
| `transaction_type` table | ⚠️ Needs rename to `transaction_types` |
| BookingService | ✅ Has `generateInvoiceId()` method |
| Payment modal in booking form | ⚠️ Exists in UI but not connected to backend |

### Key Design Issues

1. Invoices are too minimal - no amounts, balance, status
2. Payments/Vouchers link to booking directly - should be invoice-specific for customer transactions
3. No atomic transaction handling - Payment and Voucher created separately
4. No payment direction tracking (inflow vs outflow)
5. Payment modal not connected to backend

---

## Database Schema

### Financial Parties

1. **Customers** - Payments tracked via Invoice
2. **Ticket Agents** - Payments tracked directly (no invoice)
3. **Visa Agents** - Payments tracked directly (no invoice)
4. **Commission Agents** - Payments tracked directly (no invoice)

### Transaction Flow

```
CUSTOMER FLOW (Invoice-Specific):
Invoice (1) ←──→ Payment (N) ←──→ Voucher (N)
     ↓
  Booking (1) → Customer (1)

AGENT/VENDOR FLOW (Non-Invoice):
Payment (N) ←──→ Voucher (N)
(no invoice_id - nullable)
```

---

## Implementation Phases

### Phase 1: Database Migrations

#### Migration 1: Rename transaction_type to transaction_types

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_type')) {
            Schema::rename('transaction_type', 'transaction_types');
        }

        if (!Schema::hasTable('transaction_types')) {
            Schema::create('transaction_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->enum('type', ['debit', 'credit']);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transaction_types')) {
            Schema::rename('transaction_types', 'transaction_type');
        }
    }
};
```

**File**: `database/migrations/2026_05_17_000001_rename_transaction_type_to_transaction_types.php`

---

#### Migration 2: Update Invoices Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_amount', 14, 2)
                ->unsigned()
                ->default(0)
                ->after('user_id');

            $table->decimal('paid_amount', 14, 2)
                ->unsigned()
                ->default(0)
                ->after('total_amount');

            $table->decimal('balance', 14, 2)
                ->unsigned()
                ->default(0)
                ->after('paid_amount');

            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled', 'refunded'])
                ->default('pending')
                ->after('balance');

            $table->text('notes')->nullable()->after('status');
        });

        DB::statement(
            'ALTER TABLE invoices ADD CONSTRAINT invoices_total_amount_check CHECK (total_amount >= 0)'
        );
        DB::statement(
            'ALTER TABLE invoices ADD CONSTRAINT invoices_paid_amount_check CHECK (paid_amount >= 0)'
        );
        DB::statement(
            'ALTER TABLE invoices ADD CONSTRAINT invoices_balance_check CHECK (balance >= 0)'
        );
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE invoices DROP CHECK IF EXISTS invoices_total_amount_check');
        } catch (\Exception $e) {}

        try {
            DB::statement('ALTER TABLE invoices DROP CHECK IF EXISTS invoices_paid_amount_check');
        } catch (\Exception $e) {}

        try {
            DB::statement('ALTER TABLE invoices DROP CHECK IF EXISTS invoices_balance_check');
        } catch (\Exception $e) {}

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $columnsToDrop = ['total_amount', 'paid_amount', 'balance', 'status', 'notes'];
                $existingColumns = array_intersect($columnsToDrop, Schema::getColumnListing('invoices'));

                if (!empty($existingColumns)) {
                    $table->dropColumn($existingColumns);
                }
            });
        }
    }
};
```

**File**: `database/migrations/2026_05_17_000002_update_invoices_table.php`

---

#### Migration 3: Update Payments Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete()
                ->onUpdate('cascade')
                ->after('id');

            $table->unsignedBigInteger('booking_id')
                ->nullable()
                ->change();

            $table->text('notes')->nullable()->after('bdt_amount');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);

                if (Schema::hasColumn('payments', 'invoice_id')) {
                    $table->dropColumn('invoice_id');
                }

                if (Schema::hasColumn('payments', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }
    }
};
```

**File**: `database/migrations/2026_05_17_000003_update_payments_table.php`

---

#### Migration 4: Update Vouchers Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete()
                ->onUpdate('cascade')
                ->after('id');

            $table->unsignedBigInteger('booking_id')
                ->nullable()
                ->change();

            $table->text('notes')->nullable()->after('bdt_amount');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);

                if (Schema::hasColumn('vouchers', 'invoice_id')) {
                    $table->dropColumn('invoice_id');
                }

                if (Schema::hasColumn('vouchers', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }
    }
};
```

**File**: `database/migrations/2026_05_17_000004_update_vouchers_table.php`

---

#### Migration 5: Update Transaction Types Foreign Key

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['transaction_type_id']);

            $table->foreign('transaction_type_id')
                ->references('id')
                ->on('transaction_types')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        // No need to revert - reference stays valid
    }
};
```

**File**: `database/migrations/2026_05_17_000005_update_vouchers_transaction_type_foreign.php`

---

### Phase 2: Enums

#### InvoiceStatus Enum

**File**: `app/Enums/InvoiceStatus.php`

```php
<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
}
```

---

### Phase 3: Database Seeder

#### Transaction Type Seeder

**File**: `database/seeders/TransactionTypeSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $transactionTypes = [
            // Credit (Inflow) - customer payments
            [
                'name' => 'Initial Payment',
                'type' => 'credit',
            ],
            [
                'name' => 'Due Collection',
                'type' => 'credit',
            ],

            // Debit (Outflow) - refunds and agent payments
            [
                'name' => 'Customer Refund',
                'type' => 'debit',
            ],
            [
                'name' => 'Ticket Agent Payment',
                'type' => 'debit',
            ],
            [
                'name' => 'Visa Agent Payment',
                'type' => 'debit',
            ],
        ];

        foreach ($transactionTypes as $type) {
            TransactionType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
```

---

### Phase 4: Model Updates

#### Invoice Model

**File**: `app/Models/Invoice.php`

Add relationships and accessors:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\InvoiceStatus;

class Invoice extends Model
{
    protected $fillable = [
        'booking_id',
        'branch_id',
        'user_id',
        'total_amount',
        'paid_amount',
        'balance',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'status' => InvoiceStatus::class,
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'booking_id', 'id');
    }
}
```

---

#### Payment Model

**File**: `app/Models/Payment.php`

Add invoice relationship and notes field:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'booking_id',
        'branch_id',
        'user_id',
        'currency_rate_id',
        'bank_id',
        'ticket_agent_id',
        'visa_agent_id',
        'commission_agent_id',
        'payment_date',
        'payment_method',
        'transaction_id',
        'amount',
        'bdt_amount',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'bdt_amount' => 'decimal:2',
        'payment_method' => \App\Enums\PaymentMethod::class,
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currencyRate(): BelongsTo
    {
        return $this->belongsTo(CurrencyRate::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function ticketAgent(): BelongsTo
    {
        return $this->belongsTo(TicketAgent::class);
    }

    public function visaAgent(): BelongsTo
    {
        return $this->belongsTo(VisaAgent::class);
    }

    public function commissionAgent(): BelongsTo
    {
        return $this->belongsTo(CommissionAgent::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
```

---

#### Voucher Model

**File**: `app/Models/Voucher.php`

Add invoice relationship and notes field:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = [
        'voucher_id',
        'invoice_id',
        'booking_id',
        'payment_id',
        'branch_id',
        'user_id',
        'currency_rate_id',
        'bank_id',
        'ticket_agent_id',
        'visa_agent_id',
        'commission_agent_id',
        'transaction_type_id',
        'payment_date',
        'payment_method',
        'transaction_id',
        'amount',
        'bdt_amount',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'bdt_amount' => 'decimal:2',
        'payment_method' => \App\Enums\PaymentMethod::class,
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currencyRate(): BelongsTo
    {
        return $this->belongsTo(CurrencyRate::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function ticketAgent(): BelongsTo
    {
        return $this->belongsTo(TicketAgent::class);
    }

    public function visaAgent(): BelongsTo
    {
        return $this->belongsTo(VisaAgent::class);
    }

    public function commissionAgent(): BelongsTo
    {
        return $this->belongsTo(CommissionAgent::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }
}
```

---

### Phase 5: Service Layer

#### InvoiceService

**File**: `app/Services/InvoiceService.php`

```php
<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Booking;
use App\Enums\InvoiceStatus;

class InvoiceService
{
    public function createForBooking(Booking $booking): Invoice
    {
        return Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $booking->branch_id,
            'user_id' => $booking->user_id,
            'total_amount' => $booking->total_value,
            'paid_amount' => 0,
            'balance' => $booking->total_value,
            'status' => InvoiceStatus::PENDING,
        ]);
    }

    public function updatePaymentStatus(Invoice $invoice): void
    {
        $invoice->paid_amount = $invoice->payments()->sum('bdt_amount');
        $invoice->balance = $invoice->total_amount - $invoice->paid_amount;

        if ($invoice->balance <= 0) {
            $invoice->status = InvoiceStatus::PAID;
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = InvoiceStatus::PARTIAL;
        } else {
            $invoice->status = InvoiceStatus::PENDING;
        }

        $invoice->save();
    }

    public function canAcceptPayment(Invoice $invoice, float $amount): bool
    {
        return ($invoice->balance - $amount) >= 0;
    }

    public function calculateBalance(Invoice $invoice): float
    {
        return $invoice->total_amount - $invoice->paid_amount;
    }
}
```

---

#### VoucherService

**File**: `app/Services/VoucherService.php`

```php
<?php

namespace App\Services;

use App\Models\Voucher;

class VoucherService
{
    public function generateVoucherNumber(): string
    {
        $prefix = 'VCH-' . date('Ymd');

        $lastVoucher = Voucher::where('voucher_id', 'like', "{$prefix}%")
            ->orderBy('voucher_id', 'desc')
            ->first();

        $sequence = $lastVoucher
            ? intval(substr($lastVoucher->voucher_id, -4)) + 1
            : 1;

        return $prefix . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function createVoucher(array $data): Voucher
    {
        $data['voucher_id'] = $this->generateVoucherNumber();
        return Voucher::create($data);
    }
}
```

---

#### PaymentService

**File**: `app/Services/PaymentService.php`

```php
<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private VoucherService $voucherService,
        private InvoiceService $invoiceService
    ) {}

    /**
     * Create customer payment (invoice-specific)
     * Transaction type must be credit
     */
    public function createCustomerPayment(Invoice $invoice, array $data): array
    {
        if (!$this->invoiceService->canAcceptPayment($invoice, $data['bdt_amount'])) {
            throw new \Exception('Payment exceeds invoice balance');
        }

        return DB::transaction(function () use ($invoice, $data) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'booking_id' => $invoice->booking_id,
                'branch_id' => $data['branch_id'],
                'user_id' => $data['user_id'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'bdt_amount' => $data['bdt_amount'],
                'bank_id' => $data['bank_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'currency_rate_id' => $data['currency_rate_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $transactionType = TransactionType::find($data['transaction_type_id']);

            $voucher = $this->voucherService->createVoucher([
                'invoice_id' => $invoice->id,
                'booking_id' => $invoice->booking_id,
                'payment_id' => $payment->id,
                'branch_id' => $data['branch_id'],
                'user_id' => $data['user_id'],
                'transaction_type_id' => $data['transaction_type_id'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'bdt_amount' => $data['bdt_amount'],
                'bank_id' => $data['bank_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'currency_rate_id' => $data['currency_rate_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->invoiceService->updatePaymentStatus($invoice);

            return [$payment, $voucher];
        });
    }

    /**
     * Create agent payment (non-invoice)
     * Transaction type must be debit
     */
    public function createAgentPayment(string $agentType, array $data): array
    {
        $agentIdField = $agentType . '_id';

        return DB::transaction(function () use ($agentType, $agentIdField, $data) {
            $payment = Payment::create([
                'invoice_id' => null,
                'booking_id' => $data['booking_id'] ?? null,
                'branch_id' => $data['branch_id'],
                'user_id' => $data['user_id'],
                $agentIdField => $data[$agentIdField],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'bdt_amount' => $data['bdt_amount'],
                'bank_id' => $data['bank_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'currency_rate_id' => $data['currency_rate_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $voucher = $this->voucherService->createVoucher([
                'invoice_id' => null,
                'booking_id' => $data['booking_id'] ?? null,
                'payment_id' => $payment->id,
                'branch_id' => $data['branch_id'],
                'user_id' => $data['user_id'],
                $agentIdField => $data[$agentIdField],
                'transaction_type_id' => $data['transaction_type_id'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'bdt_amount' => $data['bdt_amount'],
                'bank_id' => $data['bank_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'currency_rate_id' => $data['currency_rate_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return [$payment, $voucher];
        });
    }
}
```

---

### Phase 6: Update BookingService

**File**: `app/Services/BookingService.php`

Add method to create invoice:

```php
public function createInvoiceForBooking(Booking $booking): Invoice
{
    return app(InvoiceService::class)->createForBooking($booking);
}
```

---

### Phase 7: Controller Updates

#### BookingController Updates

Update `store()` method to:
1. Create invoice after booking is created
2. Process initial payment if provided in request
3. Validate no overpayment

```php
// After booking is created and passengers are added
$invoice = $this->bookingService->createInvoiceForBooking($booking);

// If initial payment was submitted with booking
if (!empty($validated['payment'])) {
    $invoice = Invoice::where('booking_id', $booking->id)->first();
    app(PaymentService::class)->createCustomerPayment($invoice, $validated['payment']);
}
```

---

### Phase 8: Frontend Updates

#### JavaScript Updates

**File**: `resources/js/booking.js`

Update `savePayment()` to:
1. Submit payment data via AJAX
2. Validate payment amount
3. Handle response

#### Blade Template Updates

**File**: `resources/views/bookings/create.blade.php`

Update payment modal to:
1. Submit payment data with booking form
2. Add hidden form fields for payment data

---

## Transaction Type Mapping

| Transaction Type Name | Type (debit/credit) | Direction | Use Case |
|-----------------------|---------------------|-----------|-----------|
| Initial Payment | credit | inflow | Customer first payment (from booking modal) |
| Due Collection | credit | inflow | Customer due payment |
| Customer Refund | debit | outflow | Refund to customer |
| Ticket Agent Payment | debit | outflow | Payment to ticket agent |
| Visa Agent Payment | debit | outflow | Payment to visa agent |

---

## Implementation Order

1. **Migration 1**: Rename transaction_type to transaction_types
2. **Seeder**: Seed transaction types
3. **Migration 2**: Update invoices table
4. **Migration 3**: Update payments table
5. **Migration 4**: Update vouchers table
6. **Migration 5**: Update transaction_type foreign key
7. **Phase 2**: Create InvoiceStatus enum
8. **Phase 4**: Update models (Invoice, Payment, Voucher)
9. **Phase 5**: Create services (InvoiceService, VoucherService, PaymentService)
10. **Phase 6**: Update BookingService
11. **Phase 7**: Update controllers
12. **Phase 8**: Update frontend

---

## File Summary

| Phase | Files to Create | Files to Modify |
|-------|-----------------|-----------------|
| 1 | 5 migration files | - |
| 2 | `app/Enums/InvoiceStatus.php` | - |
| 3 | `database/seeders/TransactionTypeSeeder.php` | - |
| 4 | - | `app/Models/Invoice.php`<br>`app/Models/Payment.php`<br>`app/Models/Voucher.php` |
| 5 | `app/Services/InvoiceService.php`<br>`app/Services/VoucherService.php`<br>`app/Services/PaymentService.php` | - |
| 6 | - | `app/Services/BookingService.php` |
| 7 | - | `app/Http/Controllers/BookingController.php`<br>`app/Http/Controllers/PaymentController.php` |
| 8 | - | `resources/js/booking.js`<br>`resources/views/bookings/create.blade.php` |

---

## Business Rules

1. Each booking must have exactly ONE invoice
2. Customer-related transactions MUST be invoice-specific (Initial Payment, Due Collection, Refund)
3. Agent/vendor payments are NOT invoice-specific (Ticket Agent Payment, Visa Agent Payment)
4. Every financial transaction must create a payment record AND a voucher record
5. Vouchers table acts as the master financial ledger
6. Prevent over-payment (validate balance before accepting payment)
7. Transaction type's `type` field determines direction: credit = inflow, debit = outflow

---

## Notes

- Payment modal in create booking form should process **Initial Payment** (credit transaction type)
- Booking's `invoice_id` field is used as the invoice identifier (no separate invoice number needed)
- Invoice `total_amount` comes from booking's `total_value`
- Invoice is created automatically when a booking is created