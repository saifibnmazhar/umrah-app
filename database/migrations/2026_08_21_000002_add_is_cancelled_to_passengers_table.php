<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->boolean('is_cancelled')->default(false)->after('refund_payable');
            $table->timestamp('cancelled_at')->nullable()->after('is_cancelled');
        });
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropColumn(['is_cancelled', 'cancelled_at']);
        });
    }
};
