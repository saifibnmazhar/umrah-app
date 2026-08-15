<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE fingerprints DROP CHECK fingerprints_cost_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE fingerprints DROP CONSTRAINT fingerprints_cost_check');
            } catch (Exception $e) {
            }
        }

        DB::statement('ALTER TABLE fingerprints MODIFY cost DECIMAL(14,6) NOT NULL');

        DB::statement('ALTER TABLE fingerprints ADD CONSTRAINT fingerprints_cost_check CHECK (cost >= 0)');
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE fingerprints DROP CHECK fingerprints_cost_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE fingerprints DROP CONSTRAINT fingerprints_cost_check');
            } catch (Exception $e) {
            }
        }

        DB::statement('ALTER TABLE fingerprints MODIFY cost DECIMAL(10,2) NOT NULL');

        DB::statement('ALTER TABLE fingerprints ADD CONSTRAINT fingerprints_cost_check CHECK (cost >= 0)');
    }
};
