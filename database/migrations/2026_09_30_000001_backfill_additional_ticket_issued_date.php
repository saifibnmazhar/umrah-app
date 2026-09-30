<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill issued_date for additional tickets from created_at.
     *
     * Pre-migration invariant: every additional ticket has issued_date NULL
     * (the field was never required and processAdditional() defaulted it to now()
     * only for the create timestamp path — existing rows stayed NULL).
     * DetermineTicketEffectiveDate() only reads regularTickets(), so no other
     * issue type's effective date is affected.
     *
     * issued_ticket_logs is not a valid source: processAdditional() never calls
     * logAction().
     */
    public function up(): void
    {
        DB::table('issued_tickets')
            ->where('issue_type', 'additional')
            ->whereNull('issued_date')
            ->update(['issued_date' => DB::raw('created_at')]);
    }

    /**
     * Data-only migration: irreversible by design.
     *
     * Pre-state was "all additional tickets have issued_date NULL", but rows
     * legitimately written after this migration (A2 makes the field required)
     * are indistinguishable from backfilled ones — so rollback is a no-op
     * rather than nullifying real data.
     */
    public function down(): void
    {
        //
    }
};
