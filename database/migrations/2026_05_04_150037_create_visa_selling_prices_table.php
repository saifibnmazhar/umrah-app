<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visa_selling_prices', function (Blueprint $table) {
            $table->id();
            $table->decimal('selling_price', 10, 2);
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE visa_selling_prices ADD CONSTRAINT visa_selling_prices_selling_price_check CHECK (selling_price >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE visa_selling_prices DROP CHECK IF EXISTS visa_selling_prices_selling_price_check');

        if (Schema::hasTable('visa_selling_prices')) {
            Schema::table('visa_selling_prices', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        Schema::dropIfExists('visa_selling_prices');
    }
};
