<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCancelledVisaAgentId extends Command
{
    protected $signature = 'visa:backfill-cancelled-agent';
    protected $description = 'Backfill visa_agent_id in cancelled_submissions from visa_update_logs';

    public function handle(): int
    {
        $affected = DB::statement("
            UPDATE cancelled_submissions cs
            INNER JOIN (
                SELECT cs2.id,
                       vul2.old_values
                FROM (
                    SELECT id,
                           visa_submission_id,
                           ROW_NUMBER() OVER (
                               PARTITION BY visa_submission_id
                               ORDER BY created_at, id
                           ) AS rn
                    FROM cancelled_submissions
                ) cs2
                INNER JOIN (
                    SELECT old_values,
                           visa_submission_id,
                           ROW_NUMBER() OVER (
                               PARTITION BY visa_submission_id
                               ORDER BY created_at, id
                           ) AS rn
                    FROM visa_update_logs
                    WHERE action = 'cancelled'
                ) vul2 ON cs2.visa_submission_id = vul2.visa_submission_id
                      AND cs2.rn = vul2.rn
            ) mapped ON cs.id = mapped.id
            SET cs.visa_agent_id = JSON_UNQUOTE(JSON_EXTRACT(mapped.old_values, '$.visa_agent_id'))
            WHERE cs.visa_agent_id IS NULL
        ");

        $this->info("Backfilled {$affected} cancelled_submission records.");
        return Command::SUCCESS;
    }
}
