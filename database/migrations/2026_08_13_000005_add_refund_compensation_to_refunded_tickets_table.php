<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->decimal('refund_compensation', 14, 6)
                ->default(0)
                ->after('service_charge');
        });
    }

    public function down(): void
    {
        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->dropColumn('refund_compensation');
        });
    }
};
