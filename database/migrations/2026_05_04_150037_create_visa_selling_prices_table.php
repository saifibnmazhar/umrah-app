<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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

        DB::statement('ALTER TABLE visa_selling_prices ADD CONSTRAINT selling_price_positive CHECK (selling_price >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE visa_selling_prices DROP CONSTRAINT selling_price_positive');
        } catch (\Exception $e) {
            // ignore if constraint does not exist
        }

        if (Schema::hasTable('visa_selling_prices')) {
            Schema::table('visa_selling_prices', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        Schema::dropIfExists('visa_selling_prices');
    }
};
