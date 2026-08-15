<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visa_agent_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_agent_id')
                ->constrained('visa_agents')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->decimal('visa_agent_cost', 10, 2);
            $table->unique('visa_agent_id');
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE visa_agent_costs ADD CONSTRAINT visa_agent_costs_visa_agent_cost_check CHECK (visa_agent_cost >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE visa_agent_costs DROP CHECK IF EXISTS visa_agent_costs_visa_agent_cost_check');
        }

        if (Schema::hasTable('visa_agent_costs')) {
            Schema::table('visa_agent_costs', function (Blueprint $table) {
                $table->dropForeign(['visa_agent_id']);
                $table->dropForeign(['user_id']);
            });
        }

        Schema::dropIfExists('visa_agent_costs');
    }
};
