<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_cost_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fingerprint_id')->constrained('fingerprints')->cascadeOnDelete();
            $table->decimal('cost', 14, 6);
            $table->foreignId('cost_updated_by')->constrained('users');
            $table->timestamps();

            $table->index('fingerprint_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_cost_logs');
    }
};
