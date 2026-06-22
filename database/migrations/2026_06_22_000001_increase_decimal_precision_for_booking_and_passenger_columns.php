<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('discount_value', 14, 6)->change();
            $table->decimal('discount_amount', 14, 6)->change();
            $table->decimal('total_value', 14, 6)->change();
        });

        Schema::table('passengers', function (Blueprint $table) {
            $table->decimal('package_value', 14, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('discount_value', 10, 2)->change();
            $table->decimal('discount_amount', 10, 2)->change();
            $table->decimal('total_value', 14, 2)->change();
        });

        Schema::table('passengers', function (Blueprint $table) {
            $table->decimal('package_value', 12, 2)->change();
        });
    }
};
