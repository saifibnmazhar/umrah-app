<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_submissions', function (Blueprint $table) {
            $table->foreignId('visa_agent_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('visa_submissions', function (Blueprint $table) {
            DB::table('visa_submissions')->whereNull('visa_agent_id')->update(['visa_agent_id' => 1]);
            $table->foreignId('visa_agent_id')->nullable(false)->change();
        });
    }
};
