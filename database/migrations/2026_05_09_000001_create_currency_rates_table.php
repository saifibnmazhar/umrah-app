<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('rate', 10, 4);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE currency_rates ADD CONSTRAINT currency_rates_rate_check CHECK (rate >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE currency_rates DROP CHECK IF EXISTS currency_rates_rate_check');

        if (Schema::hasTable('currency_rates')) {
            Schema::table('currency_rates', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        Schema::dropIfExists('currency_rates');
    }
};