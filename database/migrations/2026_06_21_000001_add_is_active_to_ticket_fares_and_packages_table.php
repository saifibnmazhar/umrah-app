<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_fares', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('user_id');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('service_charge');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_fares', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
