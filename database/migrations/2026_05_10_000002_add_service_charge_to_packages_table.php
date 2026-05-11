<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('service_charge', 10, 2)->nullable();
        });

        DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_service_charge_check CHECK (service_charge > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE packages DROP CHECK IF EXISTS packages_service_charge_check');

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('service_charge');
        });
    }
};