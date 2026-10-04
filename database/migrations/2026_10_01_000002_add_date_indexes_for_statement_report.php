<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_tickets', function (Blueprint $table) {
            $table->index('issued_date', 'issued_tickets_issued_date_index');
            $table->index('inbound_date', 'issued_tickets_inbound_date_index');
            $table->index('outbound_date', 'issued_tickets_outbound_date_index');
            $table->index(['ticket_agent_id', 'issued_date'], 'issued_tickets_agent_issued_index');
        });

        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->index('re_issue_date', 're_issued_tickets_re_issue_date_index');
            $table->index('inbound_date', 're_issued_tickets_inbound_date_index');
            $table->index('outbound_date', 're_issued_tickets_outbound_date_index');
        });

        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->index('refund_date', 'refunded_tickets_refund_date_index');
            $table->index('inbound_date', 'refunded_tickets_inbound_date_index');
            $table->index('outbound_date', 'refunded_tickets_outbound_date_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('payment_date', 'payments_payment_date_index');
            $table->index(['ticket_agent_id', 'payment_date'], 'payments_agent_payment_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('issued_tickets', function (Blueprint $table) {
            $table->dropIndex('issued_tickets_issued_date_index');
            $table->dropIndex('issued_tickets_inbound_date_index');
            $table->dropIndex('issued_tickets_outbound_date_index');
            $table->dropIndex('issued_tickets_agent_issued_index');
        });

        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->dropIndex('re_issued_tickets_re_issue_date_index');
            $table->dropIndex('re_issued_tickets_inbound_date_index');
            $table->dropIndex('re_issued_tickets_outbound_date_index');
        });

        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->dropIndex('refunded_tickets_refund_date_index');
            $table->dropIndex('refunded_tickets_inbound_date_index');
            $table->dropIndex('refunded_tickets_outbound_date_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_payment_date_index');
            $table->dropIndex('payments_agent_payment_date_index');
        });
    }
};
