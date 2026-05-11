<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->date('deadline');
            $table->decimal('cost', 10, 2);
            $table->foreignId('assigned_staff_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->unique('booking_id');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE fingerprints ADD CONSTRAINT fingerprints_cost_check CHECK (cost >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fingerprints DROP CHECK IF EXISTS fingerprints_cost_check');

        if (Schema::hasTable('fingerprints')) {
            Schema::table('fingerprints', function (Blueprint $table) {
                $table->dropForeign(['booking_id']);
                $table->dropForeign(['assigned_staff_id']);
            });
        }

        Schema::dropIfExists('fingerprints');
    }
};