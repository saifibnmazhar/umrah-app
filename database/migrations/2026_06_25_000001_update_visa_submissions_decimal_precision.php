<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS visa_submissions_agent_commission_check');
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_net_cost_check');
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_add_cost_check');
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_final_cost_check');

        DB::statement('ALTER TABLE visa_submissions MODIFY agent_commission DECIMAL(14,6) NULL');
        DB::statement('ALTER TABLE visa_submissions MODIFY net_visa_cost DECIMAL(14,6) NULL');
        DB::statement('ALTER TABLE visa_submissions MODIFY additional_cost DECIMAL(14,6) NULL');
        DB::statement('ALTER TABLE visa_submissions MODIFY final_cost DECIMAL(14,6) NULL');

        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT visa_submissions_agent_commission_check CHECK (agent_commission IS NULL OR agent_commission >= 0)');
        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_net_cost_check CHECK (net_visa_cost IS NULL OR net_visa_cost >= 0)');
        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_add_cost_check CHECK (additional_cost IS NULL OR additional_cost >= 0)');
        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_final_cost_check CHECK (final_cost IS NULL OR final_cost >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS visa_submissions_agent_commission_check');
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_net_cost_check');
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_add_cost_check');
        DB::statement('ALTER TABLE visa_submissions DROP CHECK IF EXISTS vs_final_cost_check');

        DB::statement('ALTER TABLE visa_submissions MODIFY agent_commission DECIMAL(10,2) NULL');
        DB::statement('ALTER TABLE visa_submissions MODIFY net_visa_cost DECIMAL(10,2) NULL');
        DB::statement('ALTER TABLE visa_submissions MODIFY additional_cost DECIMAL(10,2) NULL');
        DB::statement('ALTER TABLE visa_submissions MODIFY final_cost DECIMAL(10,2) NULL');

        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT visa_submissions_agent_commission_check CHECK (agent_commission IS NULL OR agent_commission >= 0)');
        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_net_cost_check CHECK (net_visa_cost IS NULL OR net_visa_cost >= 0)');
        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_add_cost_check CHECK (additional_cost IS NULL OR additional_cost >= 0)');
        DB::statement('ALTER TABLE visa_submissions ADD CONSTRAINT vs_final_cost_check CHECK (final_cost IS NULL OR final_cost >= 0)');
    }
};
