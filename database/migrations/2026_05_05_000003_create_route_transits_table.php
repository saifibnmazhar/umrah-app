<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_transits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->unsignedBigInteger('transit_city_id');
            $table->unsignedInteger('transit_time'); // Minutes

            $table->foreign('route_id')
                ->references('id')
                ->on('routes')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('transit_city_id')
                ->references('id')
                ->on('city_codes')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('route_transits')) {
            Schema::table('route_transits', function (Blueprint $table) {
                $table->dropForeign(['route_id']);
                $table->dropForeign(['transit_city_id']);
            });
        }

        Schema::dropIfExists('route_transits');
    }
};
