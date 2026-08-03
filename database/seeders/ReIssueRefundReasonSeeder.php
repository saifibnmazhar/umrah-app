<?php

namespace Database\Seeders;

use App\Models\ReIssueRefundReason;
use Illuminate\Database\Seeder;

class ReIssueRefundReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['name' => 'Voluntary Reissue', 'reason_of' => 're-issue'],
            ['name' => 'Involuntary Reissue', 'reason_of' => 're-issue'],
            ['name' => 'Voluntary Refund', 'reason_of' => 'refund'],
            ['name' => 'Involuntary Refund', 'reason_of' => 'refund'],
            ['name' => 'Schedule Change', 'reason_of' => 're-issue'],
            ['name' => 'Mistake Reissue', 'reason_of' => 're-issue'],
            ['name' => 'Mistake Refund', 'reason_of' => 'refund'],
            ['name' => 'Sector Change Re-Issue', 'reason_of' => 're-issue'],
            ['name' => 'Agent Change', 'reason_of' => 're-issue'],
            ['name' => 'Additional Net Fare', 'reason_of' => 're-issue'],
        ];

        foreach ($reasons as $reason) {
            ReIssueRefundReason::updateOrCreate(
                ['name' => $reason['name']],
                $reason
            );
        }
    }
}
