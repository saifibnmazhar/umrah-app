<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D8 (plan 17): user_id NULL means "system"/CLI. After the observer auth
 * guards are removed, unauthenticated/CLI log writes would otherwise fail the
 * NOT NULL constraint inside the model event and abort the originating
 * edit/command. Template: invoice_update_logs (nullable + nullOnDelete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_update_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('booking_update_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_update_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('booking_update_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
