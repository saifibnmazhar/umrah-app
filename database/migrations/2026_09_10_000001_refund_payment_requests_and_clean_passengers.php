<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('refund_payment_requests')) {
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
        } else {
            Schema::table('refund_payment_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('refund_payment_requests', 'passenger_id')) {
                    $table->foreignId('passenger_id')->constrained('passengers')->restrictOnDelete();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'booking_id')) {
                    $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'status')) {
                    $table->enum('status', ['processing', 'paid', 'reverted']);
                }
                if (! Schema::hasColumn('refund_payment_requests', 'refund_payable_snapshot')) {
                    $table->decimal('refund_payable_snapshot', 14, 6)->default(0);
                }
                if (! Schema::hasColumn('refund_payment_requests', 'assigned_by')) {
                    $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'confirmed_by')) {
                    $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'payment_id')) {
                    $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'voucher_id')) {
                    $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'remarks')) {
                    $table->text('remarks')->nullable();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable();
                }
                if (! Schema::hasColumn('refund_payment_requests', 'reverted_at')) {
                    $table->timestamp('reverted_at')->nullable();
                }
            });
        }

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
