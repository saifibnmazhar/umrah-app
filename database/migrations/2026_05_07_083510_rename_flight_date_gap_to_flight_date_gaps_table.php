<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flight_date_gap')) {
            Schema::rename('flight_date_gap', 'flight_date_gaps');

            try {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    DB::statement('ALTER TABLE flight_date_gaps DROP CONSTRAINT flight_date_gap_gap_check');
                }
            } catch (Exception $e) {
            }

            DB::statement('ALTER TABLE flight_date_gaps ADD CONSTRAINT flight_date_gaps_gap_check CHECK (gap >= 1)');
        } elseif (Schema::hasTable('flight_date_gaps')) {
            try {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    DB::statement('ALTER TABLE flight_date_gaps DROP CONSTRAINT flight_date_gap_gap_check');
                }
            } catch (Exception $e) {
            }

            try {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    DB::statement('ALTER TABLE flight_date_gaps DROP CONSTRAINT flight_date_gaps_gap_check');
                }
            } catch (Exception $e) {
            }

            DB::statement('ALTER TABLE flight_date_gaps ADD CONSTRAINT flight_date_gaps_gap_check CHECK (gap >= 1)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('flight_date_gaps')) {
            try {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    DB::statement('ALTER TABLE flight_date_gaps DROP CONSTRAINT flight_date_gaps_gap_check');
                }
            } catch (Exception $e) {
            }

            DB::statement('ALTER TABLE flight_date_gaps ADD CONSTRAINT flight_date_gap_gap_check CHECK (gap >= 1)');

            Schema::rename('flight_date_gaps', 'flight_date_gap');
        }
    }
};
