<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancelled_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('total_paid', 14, 6);
            $table->decimal('service_charge_deduction', 14, 6)->nullable();
            $table->decimal('refund_amount', 14, 6)->default(0);
            $table->foreignId('cancellation_branch_id')->constrained('branches')->restrictOnDelete();
            $table->enum('status', ['cancellation processing', 'cancelled'])->default('cancellation processing');
            $table->foreignId('deduction_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('deduction_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('refund_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancelled_bookings');
    }
};
