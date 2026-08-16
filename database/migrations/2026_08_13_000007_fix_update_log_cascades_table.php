<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_update_logs', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();

            $table->string('booking_invoice_id')->nullable()->after('booking_id');
        });

        Schema::table('passenger_update_logs', function (Blueprint $table) {
            $table->dropForeign(['passenger_id']);
            $table->unsignedBigInteger('passenger_id')->nullable()->change();
            $table->foreign('passenger_id')->references('id')->on('passengers')->nullOnDelete();

            $table->string('passport_no')->nullable()->after('passenger_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_update_logs', function (Blueprint $table) {
            $table->dropColumn('booking_invoice_id');
            $table->dropForeign(['booking_id']);
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::table('passenger_update_logs', function (Blueprint $table) {
            $table->dropColumn('passport_no');
            $table->dropForeign(['passenger_id']);
            $table->unsignedBigInteger('passenger_id')->nullable(false)->change();
            $table->foreign('passenger_id')->references('id')->on('passengers')->cascadeOnDelete();
        });
    }
};