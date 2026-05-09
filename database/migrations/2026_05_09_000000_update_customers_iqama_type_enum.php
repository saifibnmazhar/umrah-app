<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('iqama_type', ['none', 'self', 'referral'])->change();
        });
    }

    public function down(): void
    {
        DB::table('customers')
            ->where('iqama_type', 'none')
            ->update(['iqama_type' => 'self']);

        Schema::table('customers', function (Blueprint $table) {
            $table->enum('iqama_type', ['self', 'referral'])->change();
        });
    }
};
