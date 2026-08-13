<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_date_gap', function (Blueprint $table) {
            $table->id();
            $table->integer('gap')->unique();

            $table->timestamps();
        });

        DB::statement('ALTER TABLE flight_date_gap ADD CONSTRAINT flight_date_gap_gap_check CHECK (gap >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE flight_date_gap DROP CHECK IF EXISTS flight_date_gap_gap_check');

        Schema::dropIfExists('flight_date_gap');
    }
};
