<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('currency_rate_id')
                ->nullable()
                ->constrained('currency_rates')
                ->nullOnDelete()
                ->onUpdate('cascade')
                ->after('fingerprint_location');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['currency_rate_id']);
            $table->dropColumn('currency_rate_id');
        });
    }
};
