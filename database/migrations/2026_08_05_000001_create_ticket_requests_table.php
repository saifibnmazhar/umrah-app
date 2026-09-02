<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_requests')) {
            Schema::create('ticket_requests', function (Blueprint $table) {
                $table->id();

                // Who made the request and from which branch (branch may be null)
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('request_branch_id')->nullable()->constrained('branches')->nullOnDelete();

                // Booking linkage (denormalized for fast dashboard grouping)
                $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();

                // Identity anchor for all request types
                $table->foreignId('passenger_id')->constrained()->restrictOnDelete()->onUpdate('cascade');

                // Source ticket for re_issue / refund; NULL for additional
                $table->foreignId('issued_ticket_id')->nullable()->constrained('issued_tickets')->nullOnDelete();

                $table->enum('request_type', ['re_issue', 'refund', 'additional']);
                $table->enum('status', ['pending', 'processed', 'rejected'])->default('pending');
                $table->enum('ticket_option', ['up', 'down', 'both'])->nullable();
                $table->date('probable_date_up')->nullable();
                $table->date('probable_date_down')->nullable();
                $table->date('visa_expiry_date')->nullable();

                $table->text('remark')->nullable();

                $table->timestamp('requested_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('rejected_at')->nullable();

                // Traceability: the resulting record once the request is fulfilled
                $table->foreignId('result_re_issued_ticket_id')->nullable()->constrained('re_issued_tickets')->nullOnDelete();
                $table->foreignId('result_refunded_ticket_id')->nullable()->constrained('refunded_tickets')->nullOnDelete();
                $table->foreignId('result_issued_ticket_id')->nullable()->constrained('issued_tickets')->nullOnDelete();

                $table->index(['booking_id', 'status', 'request_type']);

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_requests');
    }
};
