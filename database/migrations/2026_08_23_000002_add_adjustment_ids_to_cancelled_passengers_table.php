<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancelled_passengers', function (Blueprint $table) {
            $table->foreignId('adjustment_payment_id')
                ->nullable()
                ->after('refund_voucher_id')
                ->constrained('payments')
                ->nullOnDelete();
            $table->foreignId('adjustment_voucher_id')
                ->nullable()
                ->after('adjustment_payment_id')
                ->constrained('vouchers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cancelled_passengers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjustment_voucher_id');
            $table->dropConstrainedForeignId('adjustment_payment_id');
        });
    }
};
