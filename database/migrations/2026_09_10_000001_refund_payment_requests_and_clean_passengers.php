<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained('passengers')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->enum('status', ['processing', 'paid', 'reverted']);
            $table->decimal('refund_payable_snapshot', 14, 6)->default(0);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('passengers', function (Blueprint $table) {
            if (Schema::hasColumn('passengers', 'refund_payment_branch_id')) {
                try {
                    $table->dropForeign(['refund_payment_branch_id']);
                } catch (Throwable $e) {
                }
                $table->dropColumn('refund_payment_branch_id');
            }
            if (Schema::hasColumn('passengers', 'refund_payment_status')) {
                $table->dropColumn('refund_payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_payment_requests');
    }
};
