<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill re_issued_tickets.ticket_agent_id from source issued ticket.
        DB::table('re_issued_tickets')
            ->whereNull('ticket_agent_id')
            ->whereNotNull('issued_ticket_id')
            ->update([
                'ticket_agent_id' => DB::raw('(SELECT ticket_agent_id FROM issued_tickets WHERE issued_tickets.id = re_issued_tickets.issued_ticket_id)'),
            ]);

        // Backfill refunded_tickets.ticket_agent_id from source issued ticket.
        DB::table('refunded_tickets')
            ->whereNull('ticket_agent_id')
            ->whereNotNull('issued_ticket_id')
            ->update([
                'ticket_agent_id' => DB::raw('(SELECT ticket_agent_id FROM issued_tickets WHERE issued_tickets.id = refunded_tickets.issued_ticket_id)'),
            ]);

        // Fail fast if rows remain NULL (orphan issued_ticket_id or source
        // agent also NULL): running ->change() on a partial state would crash
        // mid-migrate on MySQL. Backfill these orphans with a human-chosen
        // agent (R0 audit) before deploying. See §12 of
        // docs/plans/16-statement-report-plan.md.
        $orphanReissueIds = DB::table('re_issued_tickets')->whereNull('ticket_agent_id')->pluck('id')->all();
        $orphanRefundIds = DB::table('refunded_tickets')->whereNull('ticket_agent_id')->pluck('id')->all();

        if ($orphanReissueIds !== [] || $orphanRefundIds !== []) {
            $audit = <<<'SQL'
                SELECT ri.id FROM re_issued_tickets ri
                  LEFT JOIN issued_tickets it ON it.id = ri.issued_ticket_id
                  WHERE ri.ticket_agent_id IS NULL
                    AND (ri.issued_ticket_id IS NULL OR it.ticket_agent_id IS NULL);
                -- same for refunded_tickets
                SQL;

            throw new RuntimeException(
                'Migration aborted: rows with NULL ticket_agent_id remain after backfill. '.
                'Backfill them with a human-chosen agent (R0 audit) before deploying. '.
                're_issued_tickets: ['.implode(',', $orphanReissueIds).'] '.
                'refunded_tickets: ['.implode(',', $orphanRefundIds).'] '.
                'Audit query: '.$audit
            );
        }

        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->foreignId('ticket_agent_id')->nullable(false)->change();
        });

        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->foreignId('ticket_agent_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->foreignId('ticket_agent_id')->nullable()->change();
        });

        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->foreignId('ticket_agent_id')->nullable()->change();
        });
    }
};
