<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_issued_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('re_issued_tickets', 'payment_option')) {
                $table->enum('payment_option', ['customer_payment', 'refund_adjustment'])
                    ->nullable()
                    ->after('payment_by');
            }

            if (! Schema::hasColumn('re_issued_tickets', 'refund_adjustment_amount')) {
                $table->decimal('refund_adjustment_amount', 14, 6)
                    ->default(0)
                    ->after('payment_option');
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_issued_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('re_issued_tickets', 'refund_adjustment_amount')) {
                $table->dropColumn('refund_adjustment_amount');
            }

            if (Schema::hasColumn('re_issued_tickets', 'payment_option')) {
                $table->dropColumn('payment_option');
            }
        });
    }
};
