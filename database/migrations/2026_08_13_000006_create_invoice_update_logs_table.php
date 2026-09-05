<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_update_logs')) {
            Schema::create('invoice_update_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('booking_invoice_id')->nullable();
                $table->string('action');
                $table->string('reason')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamp('created_at');
                $table->index('invoice_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_update_logs');
    }
};
