<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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

        DB::statement('ALTER TABLE visa_agent_costs ADD CONSTRAINT visa_agent_cost_positive CHECK (visa_agent_cost >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE visa_agent_costs DROP CONSTRAINT visa_agent_cost_positive');
        } catch (\Exception $e) {
            // ignore if constraint does not exist
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
