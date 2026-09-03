<?php

namespace Database\Seeders;

use App\Models\ReIssueRefundReason;
use Illuminate\Database\Seeder;

class ReIssueRefundReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['name' => 'Voluntary Reissue', 'reason_of' => 're-issue', 'default_payment_by' => 'customer'],
            ['name' => 'Involuntary Reissue', 'reason_of' => 're-issue', 'default_payment_by' => 'airline'],
            ['name' => 'Voluntary Refund', 'reason_of' => 'refund', 'default_payment_by' => 'customer'],
            ['name' => 'Involuntary Refund', 'reason_of' => 'refund', 'default_payment_by' => 'airline'],
            ['name' => 'Schedule Change', 'reason_of' => 're-issue', 'default_payment_by' => 'airline'],
            ['name' => 'Mistake Reissue', 'reason_of' => 're-issue', 'default_payment_by' => 'employee'],
            ['name' => 'Mistake Refund', 'reason_of' => 'refund', 'default_payment_by' => 'employee'],
            ['name' => 'Sector Change Re-Issue', 'reason_of' => 're-issue', 'default_payment_by' => null],
            ['name' => 'Agent Change', 'reason_of' => 're-issue', 'default_payment_by' => null],
            ['name' => 'Additional Net Fare', 'reason_of' => 're-issue', 'default_payment_by' => 'customer'],
        ];

        foreach ($reasons as $reason) {
            ReIssueRefundReason::updateOrCreate(
                ['name' => $reason['name']],
                $reason
            );
        }
    }
}
