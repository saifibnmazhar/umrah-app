<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_ticket_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issued_ticket_id')
                ->constrained('issued_tickets')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();
            $table->enum('action', ['issued', 'edited', 're-issued', 'refunded']);
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_ticket_logs');
    }
};
