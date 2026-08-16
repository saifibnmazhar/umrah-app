<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->decimal('total_customer_payment', 14, 6)
                  ->default(0)
                  ->after('refund_adjustment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->dropColumn('total_customer_payment');
        });
    }
};
