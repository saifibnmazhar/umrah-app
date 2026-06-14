<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained()->restrictOnDelete()->onUpdate('cascade');
            $table->foreignId('booking_id')->constrained()->restrictOnDelete()->onUpdate('cascade');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_agent_id')->nullable()->constrained('ticket_agents')->restrictOnDelete();
            $table->foreignId('ticket_fare_id')->nullable()->constrained('ticket_fares')->nullOnDelete();
            $table->foreignId('group_ticket_id')->nullable()->constrained('group_tickets')->nullOnDelete();

            $table->string('ticket_number', 100)->nullable();
            $table->string('pnr', 50)->nullable();

            $table->date('issued_date')->nullable();
            $table->date('inbound_date')->nullable();
            $table->date('outbound_date')->nullable();

            $table->decimal('selling_fare', 12, 2)->default(0);
            $table->decimal('net_fare', 12, 2)->default(0);

            $table->boolean('is_refundable')->default(false);
            $table->boolean('is_exchangeable')->default(false);

            $table->string('baggage_inbound')->nullable();
            $table->string('baggage_outbound')->nullable();

            $table->boolean('outbound_pending')->default(false);

            $table->enum('issue_type', ['regular', 'additional', 'pending_outbound'])->nullable();
            $table->enum('status', ['pending', 'issued', 're-issued', 'refunded'])->default('pending');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_tickets');
    }
};
