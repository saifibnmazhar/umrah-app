<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fingerprint_id')
                ->constrained('fingerprints')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->foreignId('passenger_id')
                ->constrained('passengers')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->enum('status', ['none', 'processing', 'approved', 'cancelled']);

            $table->unique(['fingerprint_id', 'passenger_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fingerprint_details')) {
            Schema::table('fingerprint_details', function (Blueprint $table) {
                $table->dropForeign(['fingerprint_id']);
                $table->dropForeign(['passenger_id']);
            });
        }

        Schema::dropIfExists('fingerprint_details');
    }
};
