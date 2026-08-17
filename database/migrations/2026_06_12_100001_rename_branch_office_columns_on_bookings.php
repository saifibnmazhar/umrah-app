<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings_branch_id_foreign', 'bookings_office_id_foreign',
            'bookings_booking_branch_id_foreign', 'bookings_fingerprint_branch_id_foreign'] as $fk) {
            try {
                DB::statement("ALTER TABLE bookings DROP FOREIGN KEY `{$fk}`");
            } catch (Exception $e) {
                // FK doesn't exist — safe to ignore (idempotent)
            }
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('branch_id', 'booking_branch_id');
            $table->renameColumn('office_id', 'fingerprint_branch_id');

            $table->foreign('booking_branch_id')->references('id')->on('branches')->onUpdate('cascade');
            $table->foreign('fingerprint_branch_id')->references('id')->on('branches')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        foreach (['bookings_booking_branch_id_foreign', 'bookings_fingerprint_branch_id_foreign'] as $fk) {
            try {
                DB::statement("ALTER TABLE bookings DROP FOREIGN KEY `{$fk}`");
            } catch (Exception $e) {
                // FK doesn't exist — safe to ignore (idempotent)
            }
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('booking_branch_id', 'branch_id');
            $table->renameColumn('fingerprint_branch_id', 'office_id');

            $table->foreign('branch_id')->references('id')->on('branches')->onUpdate('cascade');
            $table->foreign('office_id')->references('id')->on('offices')->onUpdate('cascade');
        });
    }
};
