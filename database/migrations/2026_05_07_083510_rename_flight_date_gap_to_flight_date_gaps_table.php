<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign key from bookings first
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['date_gap_id']);
        });

        // Rename table
        Schema::rename('flight_date_gap', 'flight_date_gaps');

        // Rename check constraint
        try {
            DB::statement('ALTER TABLE flight_date_gaps DROP CONSTRAINT flight_date_gap_gap_check');
        } catch (\Exception $e) {
            // ignore
        }

        DB::statement(
            'ALTER TABLE flight_date_gaps ADD CONSTRAINT flight_date_gaps_gap_check CHECK (gap >= 1)'
        );

        // Recreate foreign key
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('date_gap_id')
                ->references('id')
                ->on('flight_date_gaps')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        // Drop updated FK first
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['date_gap_id']);
        });

        // Drop updated check constraint
        try {
            DB::statement('ALTER TABLE flight_date_gaps DROP CONSTRAINT flight_date_gaps_gap_check');
        } catch (\Exception $e) {
            // ignore
        }

        // Rename table back
        Schema::rename('flight_date_gaps', 'flight_date_gap');

        // Restore original check constraint
        DB::statement(
            'ALTER TABLE flight_date_gap ADD CONSTRAINT flight_date_gap_gap_check CHECK (gap >= 1)'
        );

        // Restore original FK
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('date_gap_id')
                ->references('id')
                ->on('flight_date_gap')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }
};