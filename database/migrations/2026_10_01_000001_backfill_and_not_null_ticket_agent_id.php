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

        // Warn (not fail) if rows remain NULL: orphan issued_ticket_id or source agent also NULL.
        $orphanReissue = DB::table('re_issued_tickets')->whereNull('ticket_agent_id')->count();
        $orphanRefund = DB::table('refunded_tickets')->whereNull('ticket_agent_id')->count();

        if ($orphanReissue > 0 || $orphanRefund > 0) {
            logger()->warning('Statement report migration: rows with NULL ticket_agent_id remain after backfill', [
                're_issued_tickets' => $orphanReissue,
                'refunded_tickets' => $orphanRefund,
            ]);
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
