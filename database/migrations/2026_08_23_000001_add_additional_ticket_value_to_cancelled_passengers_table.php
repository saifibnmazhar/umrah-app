<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cancelled_passengers', 'additional_ticket_value')) {
            Schema::table('cancelled_passengers', function (Blueprint $table) {
                $table->decimal('additional_ticket_value', 14, 6)->default(0)->after('package_value');
            });
        }

        if (! Schema::hasColumn('cancelled_passengers', 'total_passenger_due')) {
            Schema::table('cancelled_passengers', function (Blueprint $table) {
                $table->decimal('total_passenger_due', 14, 6)->default(0)->after('additional_ticket_value');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cancelled_passengers', function (Blueprint $table) {
            if (Schema::hasColumn('cancelled_passengers', 'additional_ticket_value')) {
                $table->dropColumn('additional_ticket_value');
            }
            if (Schema::hasColumn('cancelled_passengers', 'total_passenger_due')) {
                $table->dropColumn('total_passenger_due');
            }
        });
    }
};
