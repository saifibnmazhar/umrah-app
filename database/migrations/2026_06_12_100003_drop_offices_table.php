<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('offices');
    }

    public function down(): void
    {
        Schema::create('offices', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('contacts');
            $table->timestamps();
        });
    }
};
