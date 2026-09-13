<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->decimal('visa_profit', 14, 6)->default(0)->after('profit');
            $table->timestamp('visa_profit_effective_at')->nullable()->after('visa_profit');
            $table->decimal('ticket_profit', 14, 6)->default(0)->after('visa_profit_effective_at');
            $table->timestamp('ticket_profit_effective_at')->nullable()->after('ticket_profit');
            $table->decimal('service_charge', 14, 6)->default(0)->after('ticket_profit_effective_at');
            $table->timestamp('service_charge_effective_at')->nullable()->after('service_charge');
        });
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropColumn([
                'visa_profit', 'visa_profit_effective_at',
                'ticket_profit', 'ticket_profit_effective_at',
                'service_charge', 'service_charge_effective_at',
            ]);
        });
    }
};
