<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D8 (plan 17): user_id NULL means "system"/CLI — see
 * 2026_10_12_000001_make_user_id_nullable_on_booking_update_logs.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passenger_update_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('passenger_update_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('passenger_update_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('passenger_update_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
