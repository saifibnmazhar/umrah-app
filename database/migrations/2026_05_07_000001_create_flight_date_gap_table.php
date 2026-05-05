<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_date_gap', function (Blueprint $table) {
            $table->id();
            $table->integer('gap')->unique();

            $table->timestamps();
        });

        DB::statement('ALTER TABLE flight_date_gap ADD CONSTRAINT gap_positive CHECK (gap >= 1)');
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE flight_date_gap DROP CONSTRAINT gap_positive');
        } catch (\Exception $e) {
            // ignore if constraint does not exist
        }

        Schema::dropIfExists('flight_date_gap');
    }
};