<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baggage_allowances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_fare_id');
            $table->enum('passenger_type', ['adult', 'child', 'infant']);
            $table->enum('travel_direction', ['inbound', 'outbound']);
            $table->string('allowance');

            $table->foreign('ticket_fare_id')
                ->references('id')
                ->on('ticket_fares')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->unique(['ticket_fare_id', 'passenger_type', 'travel_direction'], 'baggage_allowance_unique');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('baggage_allowances')) {
            Schema::table('baggage_allowances', function (Blueprint $table) {
                $table->dropForeign(['ticket_fare_id']);
                $table->dropIndex('baggage_allowance_unique');
            });
        }

        Schema::dropIfExists('baggage_allowances');
    }
};
