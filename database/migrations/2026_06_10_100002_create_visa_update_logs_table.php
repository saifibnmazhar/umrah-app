<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_update_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_submission_id')
                ->constrained('visa_submissions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users');
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_update_logs');
    }
};
