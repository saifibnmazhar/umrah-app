<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_multi_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->unsignedBigInteger('from_city_id');
            $table->unsignedBigInteger('to_city_id');
            $table->enum('segment_direction', ['inbound', 'outbound']);

            $table->foreign('route_id')
                ->references('id')
                ->on('routes')
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

            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('route_multi_segments')) {
            Schema::table('route_multi_segments', function (Blueprint $table) {
                $table->dropForeign(['route_id']);
                $table->dropForeign(['from_city_id']);
                $table->dropForeign(['to_city_id']);
            });
        }

        Schema::dropIfExists('route_multi_segments');
    }
};