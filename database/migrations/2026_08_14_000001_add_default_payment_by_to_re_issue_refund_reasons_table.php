<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_issue_refund_reasons', function (Blueprint $table) {
            if (! Schema::hasColumn('re_issue_refund_reasons', 'default_payment_by')) {
                $table->enum('default_payment_by', ['customer', 'airline', 'employee'])
                    ->nullable()
                    ->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_issue_refund_reasons', function (Blueprint $table) {
            $table->dropColumn('default_payment_by');
        });
    }
};
