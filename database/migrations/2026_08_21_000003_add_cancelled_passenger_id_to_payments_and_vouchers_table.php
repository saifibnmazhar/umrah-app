<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cancelled_passenger_id')
                ->nullable()
                ->after('cancelled_booking_id')
                ->constrained('cancelled_passengers')
                ->nullOnDelete();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('cancelled_passenger_id')
                ->nullable()
                ->after('cancelled_booking_id')
                ->constrained('cancelled_passengers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['cancelled_passenger_id']);
            $table->dropColumn('cancelled_passenger_id');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['cancelled_passenger_id']);
            $table->dropColumn('cancelled_passenger_id');
        });
    }
};
