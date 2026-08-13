<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing check (created by 2026_05_10_000002) so it can be recreated
        // with the stricter `> 0` condition. MySQL 8.0.16+ supports DROP CHECK.
        try {
            DB::statement('ALTER TABLE packages DROP CHECK packages_service_charge_check');
        } catch (Exception $e) {
            // Constraint didn't exist yet (e.g. on a fresh DB where 2026_05_10 hasn't
            // run, or a different MySQL version) — try DROP CONSTRAINT instead.
        }

        try {
            DB::statement('ALTER TABLE packages DROP CONSTRAINT packages_service_charge_check');
        } catch (Exception $e) {
            // Constraint didn't exist and DROP CONSTRAINT also failed — safe to ignore.
        }

        DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_service_charge_check CHECK (service_charge > 0)');
    }
};
