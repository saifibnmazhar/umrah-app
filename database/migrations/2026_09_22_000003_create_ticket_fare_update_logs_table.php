<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_fare_update_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_fare_id')->nullable()->constrained('ticket_fares')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_fare_update_logs');
    }
};
