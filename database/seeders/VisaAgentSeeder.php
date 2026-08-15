<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VisaAgentSeeder extends Seeder
{
    public function run(): void
    {
        $userId = DB::table('users')->value('id');

        DB::table('visa_agents')->insertOrIgnore([
            [
                'name' => 'Global Visa Services',
                'address' => 'House 15, Road 7, Dhaka, Bangladesh',
                'contacts' => '+880 2-9876-5432',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Al-Madina Travel & Tours',
                'address' => 'Shop 22, Commercial Market, Chittagong, Bangladesh',
                'contacts' => '+880 31-9123-456',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create visa agent costs only for agents that don't have one yet
        $agentIds = DB::table('visa_agents')->pluck('id')->toArray();
        $existingCostAgentIds = DB::table('visa_agent_costs')->pluck('visa_agent_id')->toArray();

        foreach ($agentIds as $agentId) {
            if (! in_array($agentId, $existingCostAgentIds)) {
                DB::table('visa_agent_costs')->insert([
                    'visa_agent_id' => $agentId,
                    'user_id' => $userId,
                    'visa_agent_cost' => 1200.000000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
