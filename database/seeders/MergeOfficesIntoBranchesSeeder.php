<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MergeOfficesIntoBranchesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $officeMap = [];
            $offices = DB::table('offices')->get();

            foreach ($offices as $office) {
                $newBranchId = DB::table('branches')->insertGetId([
                    'name' => $office->name,
                    'address' => $office->address,
                    'contacts' => $office->contacts,
                    'location' => 'BD',
                    'fingerprint_operation' => true,
                    'created_at' => $office->created_at ?? now(),
                    'updated_at' => $office->updated_at ?? now(),
                ]);
                $officeMap[$office->id] = $newBranchId;
            }

            DB::table('branches')
                ->whereNull('location')
                ->orWhere('location', '')
                ->update(['location' => 'KSA', 'fingerprint_operation' => false]);

            foreach ($officeMap as $oldOfficeId => $newBranchId) {
                DB::table('bookings')
                    ->where('fingerprint_branch_id', $oldOfficeId)
                    ->update(['fingerprint_branch_id' => $newBranchId]);
            }

            foreach ($officeMap as $oldOfficeId => $newBranchId) {
                DB::table('users')
                    ->where('office_id', $oldOfficeId)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $newBranchId]);
            }
        });
    }
}
