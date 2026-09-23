<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancelled_bookings', function (Blueprint $table) {
            $table->json('passenger_statuses_snapshot')->nullable()->after('total_passenger_refundable');
        });
    }

    public function down(): void
    {
        Schema::table('cancelled_bookings', function (Blueprint $table) {
            $table->dropColumn('passenger_statuses_snapshot');
        });
    }
};
