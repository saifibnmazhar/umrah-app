<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->foreignId('refund_payment_branch_id')->nullable()->after('refund_payable')
                ->constrained('branches')->nullOnDelete();
            $table->string('refund_payment_status')->nullable()->after('refund_payment_branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropForeign(['refund_payment_branch_id']);
            $table->dropColumn(['refund_payment_branch_id', 'refund_payment_status']);
        });
    }
};
