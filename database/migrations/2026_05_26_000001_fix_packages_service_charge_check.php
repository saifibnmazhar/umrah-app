<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try { DB::statement('ALTER TABLE packages DROP CHECK IF EXISTS packages_service_charge_check'); } catch (\Exception $e) { try { DB::statement('ALTER TABLE packages DROP CONSTRAINT IF EXISTS packages_service_charge_check'); } catch (\Exception $e) {} }
        DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_service_charge_check CHECK (service_charge > 0)');
    }
};
