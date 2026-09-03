<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('refunded_tickets')) {
            Schema::create('refunded_tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('ticket_agent_id')->nullable()->constrained('ticket_agents')->restrictOnDelete();
                $table->foreignId('ticket_fare_id')->nullable()->constrained('ticket_fares')->nullOnDelete();
                $table->foreignId('group_ticket_id')->nullable()->constrained('group_tickets')->nullOnDelete();
                $table->foreignId('issued_ticket_id')->nullable()->constrained('issued_tickets')->nullOnDelete();

                $table->string('ticket_number', 100)->nullable();
                $table->string('pnr', 50)->nullable();

                $table->date('refund_date')->nullable();
                $table->date('inbound_date')->nullable();
                $table->date('outbound_date')->nullable();

                $table->decimal('selling_fare', 14, 6)->default(0);
                $table->decimal('net_fare', 14, 6)->default(0);
                $table->decimal('offer_price', 14, 6)->default(0);

                $table->boolean('is_refundable')->default(false);
                $table->boolean('is_exchangeable')->default(false);

                $table->string('baggage_inbound')->nullable();
                $table->string('baggage_outbound')->nullable();

                $table->decimal('iata_refunded_amount', 14, 6)->default(0);
                $table->decimal('refund_to_customer', 14, 6)->default(0);
                $table->decimal('service_charge', 14, 6)->default(0);

                $table->enum('payment_by', ['customer', 'airline', 'employee'])->nullable();

                $table->foreignId('reason_id')->nullable()->constrained('re_issue_refund_reasons')->nullOnDelete();
                $table->text('remarks')->nullable();

                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refunded_tickets');
    }
};
