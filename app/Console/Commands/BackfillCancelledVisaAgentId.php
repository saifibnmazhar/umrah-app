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
            JOIN visa_update_logs vul
                ON vul.visa_submission_id = cs.visa_submission_id
                AND JSON_UNQUOTE(JSON_EXTRACT(vul.new_values, '$.status')) = 'cancelled'
            SET cs.visa_agent_id = JSON_UNQUOTE(JSON_EXTRACT(vul.old_values, '$.visa_agent_id'))
            WHERE cs.visa_agent_id IS NULL
        ");

        $this->info("Backfilled {$affected} cancelled_submission records.");
        return Command::SUCCESS;
    }
}
