<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_submissions', function (Blueprint $table) {
            $table->decimal('net_visa_cost', 10, 2)
                ->nullable()
                ->after('agent_commission');

            $table->decimal('additional_cost', 10, 2)
                ->nullable()
                ->after('net_visa_cost');

            $table->decimal('final_cost', 10, 2)
                ->nullable()
                ->after('additional_cost');

            $table->string('remarks', 1000)
                ->nullable()
                ->after('final_cost');

            $table->string('status', 20)
                ->default('pending')
                ->after('is_cancelled');
        });

        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_net_cost_check CHECK (net_visa_cost IS NULL OR net_visa_cost >= 0)');
        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_add_cost_check CHECK (additional_cost IS NULL OR additional_cost >= 0)');
        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_final_cost_check CHECK (final_cost IS NULL OR final_cost >= 0)');

        if (Schema::hasColumn('passengers', 'visa_status')) {
            Schema::table('passengers', function (Blueprint $table) {
                $table->dropColumn('visa_status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->enum('visa_status', ['pending', 'submitted', 'issued'])
                ->nullable()
                ->after('ticket_status');
        });

        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_net_cost_check');
            DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_add_cost_check');
            DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_final_cost_check');
        } catch (Exception $e) {
            // MariaDB compatibility
        }

        Schema::table('visa_submissions', function (Blueprint $table) {
            $table->dropColumn(['net_visa_cost', 'additional_cost', 'final_cost', 'remarks', 'status']);
        });
    }
};
