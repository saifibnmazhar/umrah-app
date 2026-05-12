<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')
                ->constrained('passengers')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->foreignId('visa_agent_id')
                ->constrained('visa_agents')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->foreignId('commission_agent_id')
                ->nullable()
                ->constrained('commission_agents')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->decimal('agent_commission', 10, 2)->nullable();
            $table->foreignId('visa_selling_price_id')
                ->constrained('visa_selling_prices')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->string('visa_number')->nullable();
            $table->boolean('is_cancelled')->default(false);

            $table->timestamps();
        });

        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT visa_submissions_agent_commission_check CHECK (agent_commission IS NULL OR agent_commission >= 0)');
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS visa_submissions_agent_commission_check');
        } catch (\Exception $e) {
            // MariaDB compatibility: ignore if constraint doesn't exist
        }

        if (Schema::hasTable('visa_submissions')) {
            Schema::table('visa_submissions', function (Blueprint $table) {
                $table->dropForeign(['passenger_id']);
                $table->dropForeign(['visa_agent_id']);
                $table->dropForeign(['commission_agent_id']);
                $table->dropForeign(['visa_selling_price_id']);
            });
        }

        Schema::dropIfExists('visa_submissions');
    }
};