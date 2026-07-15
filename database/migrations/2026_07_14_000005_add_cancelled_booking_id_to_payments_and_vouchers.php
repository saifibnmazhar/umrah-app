<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cancelled_booking_id')->nullable()->after('booking_id')->constrained('cancelled_bookings')->nullOnDelete();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('cancelled_booking_id')->nullable()->after('booking_id')->constrained('cancelled_bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['cancelled_booking_id']);
            $table->dropColumn('cancelled_booking_id');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['cancelled_booking_id']);
            $table->dropColumn('cancelled_booking_id');
        });
    }
};
