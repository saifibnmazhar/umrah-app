<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'passenger_id')) {
                $table->foreignId('passenger_id')
                    ->nullable()
                    ->after('cancelled_booking_id')
                    ->constrained('passengers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payments', 'refunded_ticket_id')) {
                $table->foreignId('refunded_ticket_id')
                    ->nullable()
                    ->after('passenger_id')
                    ->constrained('refunded_tickets')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payments', 're_issued_ticket_id')) {
                $table->foreignId('re_issued_ticket_id')
                    ->nullable()
                    ->after('refunded_ticket_id')
                    ->constrained('re_issued_tickets')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'passenger_id')) {
                $table->dropForeign(['passenger_id']);
                $table->dropColumn('passenger_id');
            }

            if (Schema::hasColumn('payments', 'refunded_ticket_id')) {
                $table->dropForeign(['refunded_ticket_id']);
                $table->dropColumn('refunded_ticket_id');
            }

            if (Schema::hasColumn('payments', 're_issued_ticket_id')) {
                $table->dropForeign(['re_issued_ticket_id']);
                $table->dropColumn('re_issued_ticket_id');
            }
        });
    }
};
