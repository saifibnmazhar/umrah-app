<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_detail_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fingerprint_detail_id')
                ->constrained('fingerprint_details')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users');
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at');

            $table->index('fingerprint_detail_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_detail_logs');
    }
};
