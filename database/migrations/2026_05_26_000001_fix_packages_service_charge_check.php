<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE packages DROP CONSTRAINT packages_service_charge_check');
        DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_service_charge_check CHECK (service_charge >= 0)');

        DB::statement('ALTER TABLE packages DROP CONSTRAINT packages_service_charge_check');
        DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_service_charge_check CHECK (service_charge > 0)');
    }
};
