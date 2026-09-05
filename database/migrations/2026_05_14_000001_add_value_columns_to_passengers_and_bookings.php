<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->decimal('package_value', 12, 2)
                ->unsigned()
                ->nullable()
                ->after('ticket_fare_id');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE passengers ADD CONSTRAINT passengers_package_value_check CHECK (package_value >= 0)'
            );
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('total_value', 14, 2)
                ->unsigned()
                ->nullable()
                ->after('discount_amount');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE bookings ADD CONSTRAINT bookings_total_value_check CHECK (total_value >= 0)'
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            try {
                DB::statement(
                    'ALTER TABLE passengers DROP CHECK IF EXISTS passengers_package_value_check'
                );
            } catch (Exception $e) {
                // MariaDB compatibility
            }
            try {
                DB::statement(
                    'ALTER TABLE bookings DROP CHECK IF EXISTS bookings_total_value_check'
                );
            } catch (Exception $e) {
                // MariaDB compatibility
            }
        }

        if (Schema::hasTable('passengers') && Schema::hasColumn('passengers', 'package_value')) {
            Schema::table('passengers', function (Blueprint $table) {
                $table->dropColumn('package_value');
            });
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'total_value')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('total_value');
            });
        }
    }
};
