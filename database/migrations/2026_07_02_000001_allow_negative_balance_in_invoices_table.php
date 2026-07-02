<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try { DB::statement('ALTER TABLE invoices DROP CHECK invoices_balance_check'); } catch (\Exception $e) { try { DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_balance_check'); } catch (\Exception $e) {} }

        DB::statement('ALTER TABLE invoices MODIFY COLUMN `balance` DECIMAL(14,6) DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoices MODIFY COLUMN `balance` DECIMAL(14,2) UNSIGNED DEFAULT 0');

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_balance_check CHECK (balance >= 0)');
    }
};
