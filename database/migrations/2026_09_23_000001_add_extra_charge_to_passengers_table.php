<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('passengers', 'extra_charge')) {
            Schema::table('passengers', function (Blueprint $table) {
                $table->decimal('extra_charge', 14, 6)->default(0)->after('booking_service_charge');
            });
        }
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropColumn('extra_charge');
        });
    }
};
