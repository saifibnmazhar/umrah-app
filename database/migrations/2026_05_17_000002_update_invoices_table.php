<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        } catch (Exception $e) {
        }

        try {
            DB::statement('ALTER TABLE invoices DROP CHECK IF EXISTS invoices_paid_amount_check');
        } catch (Exception $e) {
        }

        try {
            DB::statement('ALTER TABLE invoices DROP CHECK IF EXISTS invoices_balance_check');
        } catch (Exception $e) {
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $columnsToDrop = ['total_amount', 'paid_amount', 'balance', 'status', 'notes'];
                $existingColumns = array_intersect($columnsToDrop, Schema::getColumnListing('invoices'));

                if (! empty($existingColumns)) {
                    $table->dropColumn($existingColumns);
                }
            });
        }
    }
};
