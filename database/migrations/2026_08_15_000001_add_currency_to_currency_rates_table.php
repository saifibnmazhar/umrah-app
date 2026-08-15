<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->string('currency', 3)->default('BDT')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
