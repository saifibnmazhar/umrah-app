<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('airline_id');
            $table->enum('route_type', ['oneway_inbound', 'oneway_outbound', 'round', 'multi_city']);
            $table->enum('flight_type', ['direct', 'transit']);
            $table->unsignedBigInteger('from_city_id')->nullable();
            $table->unsignedBigInteger('to_city_id')->nullable();
            $table->unsignedBigInteger('return_city_id')->nullable();

            $table->foreign('airline_id')
                ->references('id')
                ->on('airlines')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('from_city_id')
                ->references('id')
                ->on('city_codes')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('to_city_id')
                ->references('id')
                ->on('city_codes')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('return_city_id')
                ->references('id')
                ->on('city_codes')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('routes')) {
            Schema::table('routes', function (Blueprint $table) {
                $table->dropForeign(['airline_id']);
                $table->dropForeign(['from_city_id']);
                $table->dropForeign(['to_city_id']);
                $table->dropForeign(['return_city_id']);
            });
        }

        Schema::dropIfExists('routes');
    }
};