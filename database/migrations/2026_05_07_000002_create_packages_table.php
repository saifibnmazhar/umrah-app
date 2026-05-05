<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_name');
            $table->unsignedBigInteger('ticket_fare_id');
            $table->unsignedBigInteger('visa_selling_price_id');
            $table->decimal('regular_price', 10, 2);
            $table->decimal('offer_price', 10, 2)->nullable();

            $table->foreign('ticket_fare_id')
                ->references('id')
                ->on('ticket_fares')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('visa_selling_price_id')
                ->references('id')
                ->on('visa_selling_prices')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->unique('ticket_fare_id');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE packages ADD CONSTRAINT regular_price_positive CHECK (regular_price >= 0)');
        DB::statement('ALTER TABLE packages ADD CONSTRAINT offer_price_positive CHECK (offer_price >= 0)');
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE packages DROP CONSTRAINT regular_price_positive');
        } catch (\Exception $e) {
            // ignore if constraint does not exist
        }
        try {
            DB::statement('ALTER TABLE packages DROP CONSTRAINT offer_price_positive');
        } catch (\Exception $e) {
            // ignore if constraint does not exist
        }

        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropForeign(['ticket_fare_id']);
                $table->dropForeign(['visa_selling_price_id']);
            });
        }

        Schema::dropIfExists('packages');
    }
};