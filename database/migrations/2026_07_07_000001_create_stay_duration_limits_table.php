<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stay_duration_limits', function (Blueprint $table) {
            $table->id();
            $table->integer('min_days')->default(1);
            $table->integer('max_days')->default(85);
            $table->timestamps();
        });

        DB::table('stay_duration_limits')->insert([
            'min_days' => 1,
            'max_days' => 85,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_duration_limits');
    }
};
