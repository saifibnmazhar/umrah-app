<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('iqama_type', ['self', 'referral']);
            $table->string('passport_no');
            $table->string('iqama_no');
            $table->string('mobile_no');
            $table->string('ref_iqama_no')->nullable();
            $table->string('ref_mobile_no')->nullable();
            $table->string('ref_iqama_doc', 512)->nullable();
            $table->string('address');
            $table->unique('iqama_no');
            $table->unique('passport_no');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
