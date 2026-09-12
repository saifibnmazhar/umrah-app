<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $t) {
            if (! Schema::hasIndex('passengers', 'passengers_passport_no_index')) {
                $t->index('passport_no');
            }
            if (! Schema::hasIndex('passengers', 'passengers_mobile_no_index')) {
                $t->index('mobile_no');
            }
            if (! Schema::hasIndex('passengers', 'passengers_flight_date_from_index')) {
                $t->index('flight_date_from');
            }
        });
        Schema::table('bookings', function (Blueprint $t) {
            if (! Schema::hasIndex('bookings', 'bookings_booking_branch_id_index')) {
                $t->index('booking_branch_id');
            }
            if (! Schema::hasIndex('bookings', 'bookings_fingerprint_branch_id_index')) {
                $t->index('fingerprint_branch_id');
            }
        });
        Schema::table('fingerprints', function (Blueprint $t) {
            if (! Schema::hasIndex('fingerprints', 'fingerprints_assigned_staff_id_index')) {
                $t->index('assigned_staff_id');
            }
            if (! Schema::hasIndex('fingerprints', 'fingerprints_deadline_index')) {
                $t->index('deadline');
            }
        });
        Schema::table('fingerprint_details', function (Blueprint $t) {
            if (! Schema::hasIndex('fingerprint_details', 'fingerprint_details_status_index')) {
                $t->index('status');
            }
        });
        Schema::table('issued_tickets', function (Blueprint $t) {
            if (! Schema::hasIndex('issued_tickets', 'issued_tickets_passenger_id_status_index')) {
                $t->index(['passenger_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('issued_tickets', function (Blueprint $t) {
            if (Schema::hasIndex('issued_tickets', 'issued_tickets_passenger_id_status_index')) {
                $t->dropIndex(['passenger_id', 'status']);
            }
        });
        Schema::table('fingerprint_details', function (Blueprint $t) {
            if (Schema::hasIndex('fingerprint_details', 'fingerprint_details_status_index')) {
                $t->dropIndex(['status']);
            }
        });
        Schema::table('fingerprints', function (Blueprint $t) {
            if (Schema::hasIndex('fingerprints', 'fingerprints_assigned_staff_id_index')) {
                $t->dropIndex(['assigned_staff_id']);
            }
            if (Schema::hasIndex('fingerprints', 'fingerprints_deadline_index')) {
                $t->dropIndex(['deadline']);
            }
        });
        Schema::table('bookings', function (Blueprint $t) {
            if (Schema::hasIndex('bookings', 'bookings_booking_branch_id_index')) {
                $t->dropIndex(['booking_branch_id']);
            }
            if (Schema::hasIndex('bookings', 'bookings_fingerprint_branch_id_index')) {
                $t->dropIndex(['fingerprint_branch_id']);
            }
        });
        Schema::table('passengers', function (Blueprint $t) {
            if (Schema::hasIndex('passengers', 'passengers_passport_no_index')) {
                $t->dropIndex(['passport_no']);
            }
            if (Schema::hasIndex('passengers', 'passengers_mobile_no_index')) {
                $t->dropIndex(['mobile_no']);
            }
            if (Schema::hasIndex('passengers', 'passengers_flight_date_from_index')) {
                $t->dropIndex(['flight_date_from']);
            }
        });
    }
};
