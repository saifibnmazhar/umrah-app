<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $t) {
            $t->index('passport_no');
            $t->index('mobile_no');
            $t->index('flight_date_from');
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->index('booking_branch_id');
            $t->index('fingerprint_branch_id');
        });
        Schema::table('fingerprints', function (Blueprint $t) {
            $t->index('assigned_staff_id');
            $t->index('deadline');
        });
        Schema::table('fingerprint_details', function (Blueprint $t) {
            $t->index('status');
        });
        Schema::table('issued_tickets', function (Blueprint $t) {
            $t->index(['passenger_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('issued_tickets', function (Blueprint $t) {
            $t->dropIndex(['passenger_id', 'status']);
        });
        Schema::table('fingerprint_details', function (Blueprint $t) {
            $t->dropIndex(['status']);
        });
        Schema::table('fingerprints', function (Blueprint $t) {
            $t->dropIndex(['assigned_staff_id']);
            $t->dropIndex(['deadline']);
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex(['booking_branch_id']);
            $t->dropIndex(['fingerprint_branch_id']);
        });
        Schema::table('passengers', function (Blueprint $t) {
            $t->dropIndex(['passport_no']);
            $t->dropIndex(['mobile_no']);
            $t->dropIndex(['flight_date_from']);
        });
    }
};
