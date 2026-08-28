<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('profit', 14, 6)
                ->default(0)
                ->after('total_value');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'profit')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('profit');
            });
        }
    }
};
