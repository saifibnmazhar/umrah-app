<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_selling_prices', function (Blueprint $table) {
            $table->decimal('selling_price', 10, 6)->change();
        });

        Schema::table('visa_agent_costs', function (Blueprint $table) {
            $table->decimal('visa_agent_cost', 10, 6)->change();
        });

        Schema::table('fingerprint_charges', function (Blueprint $table) {
            $table->decimal('fingerprint_charge', 10, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('visa_selling_prices', function (Blueprint $table) {
            $table->decimal('selling_price', 10, 2)->change();
        });

        Schema::table('visa_agent_costs', function (Blueprint $table) {
            $table->decimal('visa_agent_cost', 10, 2)->change();
        });

        Schema::table('fingerprint_charges', function (Blueprint $table) {
            $table->decimal('fingerprint_charge', 10, 2)->change();
        });
    }
};
