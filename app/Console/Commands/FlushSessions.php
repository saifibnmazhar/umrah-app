<?php

namespace App\Console\Commands;

use App\Services\SessionInvalidator;
use Illuminate\Console\Command;

class FlushSessions extends Command
{
    protected $signature = 'sessions:flush {--force : Skip the confirmation prompt}';

    protected $description = 'Invalidate all active sessions, forcing every user to log in again';

    public function handle(SessionInvalidator $invalidator): int
    {
        if (! $this->option('force') && ! $this->confirm('This will log every user out. Continue?')) {
            $this->warn('Aborted. No sessions were invalidated.');

            return self::SUCCESS;
        }

        $result = $invalidator->invalidate();

        $this->info(sprintf(
            'Sessions flushed (driver: %s, store: %s%s). Every user must log in again.',
            $result['driver'] ?? 'unknown',
            $result['store'] ?? 'none',
            $result['invalidated'] === null ? '' : ', removed: '.$result['invalidated'],
        ));

        return self::SUCCESS;
    }
}
