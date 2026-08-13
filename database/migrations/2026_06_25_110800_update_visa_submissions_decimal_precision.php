<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK visa_submissions_agent_commission_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE visa_submissions DROP CONSTRAINT visa_submissions_agent_commission_check');
            } catch (Exception $e) {
            }
        }
        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK vs_net_cost_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE visa_submissions DROP CONSTRAINT vs_net_cost_check');
            } catch (Exception $e) {
            }
        }
        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK vs_add_cost_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE visa_submissions DROP CONSTRAINT vs_add_cost_check');
            } catch (Exception $e) {
            }
        }
        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK vs_final_cost_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE visa_submissions DROP CONSTRAINT vs_final_cost_check');
            } catch (Exception $e) {
            }
        }

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
        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK visa_submissions_agent_commission_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE visa_submissions DROP CONSTRAINT visa_submissions_agent_commission_check');
            } catch (Exception $e) {
            }
        }
        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK vs_net_cost_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE visa_submissions DROP CONSTRAINT vs_net_cost_check');
            } catch (Exception $e) {
            }
        }
        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK vs_add_cost_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE visa_submissions DROP CONSTRAINT vs_add_cost_check');
            } catch (Exception $e) {
            }
        }
        try {
            DB::statement('ALTER TABLE visa_submissions DROP CHECK vs_final_cost_check');
        } catch (Exception $e) {
            try {
                DB::statement('ALTER TABLE visa_submissions DROP CONSTRAINT vs_final_cost_check');
            } catch (Exception $e) {
            }
        }

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
