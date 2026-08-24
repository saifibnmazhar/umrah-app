<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('passengers', 'is_cancelled')) {
            Schema::table('passengers', function (Blueprint $table) {
                $table->boolean('is_cancelled')->default(false)->after('refund_payable');
            });
        }

        if (! Schema::hasColumn('passengers', 'cancelled_at')) {
            Schema::table('passengers', function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('is_cancelled');
            });
        }
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            if (Schema::hasColumn('passengers', 'is_cancelled')) {
                $table->dropColumn('is_cancelled');
            }
            if (Schema::hasColumn('passengers', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });
    }
};
