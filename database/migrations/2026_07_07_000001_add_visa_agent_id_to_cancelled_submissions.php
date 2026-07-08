<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancelled_submissions', function (Blueprint $table) {
            $table->foreignId('visa_agent_id')
                ->nullable()
                ->constrained('visa_agents')
                ->nullOnDelete()
                ->after('visa_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('cancelled_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visa_agent_id');
        });
    }
};
