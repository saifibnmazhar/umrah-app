<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_fares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('airline_id');
            $table->unsignedBigInteger('airline_classes_id');
            $table->unsignedBigInteger('route_id');
            $table->enum('ticket_type', ['regular', 'offer', 'group']);
            $table->date('effective_from');
            $table->date('effective_to');
            $table->decimal('net_fare', 10, 2);
            $table->decimal('selling_fare', 10, 2);
            $table->decimal('offer_price', 10, 2)->nullable();
            $table->decimal('child_fare_percentage', 5, 2);
            $table->decimal('infant_fare_percentage', 5, 2);
            $table->boolean('with_meal');
            $table->unsignedBigInteger('user_id');

            $table->foreign('airline_id')
                ->references('id')
                ->on('airlines')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('airline_classes_id')
                ->references('id')
                ->on('airline_classes')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('route_id')
                ->references('id')
                ->on('routes')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->unique('route_id');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT ticket_fares_net_fare_check CHECK (net_fare >= 0)');
        DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT ticket_fares_selling_fare_check CHECK (selling_fare >= 0)');
        DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT ticket_fares_offer_price_check CHECK (offer_price >= 0)');
        DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT ticket_fares_child_fare_percentage_check CHECK (child_fare_percentage >= 0)');
        DB::statement('ALTER TABLE ticket_fares ADD CONSTRAINT ticket_fares_infant_fare_percentage_check CHECK (infant_fare_percentage >= 0)');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ticket_fares DROP CHECK IF EXISTS ticket_fares_net_fare_check');
        }
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ticket_fares DROP CHECK IF EXISTS ticket_fares_selling_fare_check');
        }
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ticket_fares DROP CHECK IF EXISTS ticket_fares_offer_price_check');
        }
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ticket_fares DROP CHECK IF EXISTS ticket_fares_child_fare_percentage_check');
        }
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ticket_fares DROP CHECK IF EXISTS ticket_fares_infant_fare_percentage_check');
        }

        if (Schema::hasTable('ticket_fares')) {
            Schema::table('ticket_fares', function (Blueprint $table) {
                $table->dropForeign(['airline_id']);
                $table->dropForeign(['airline_classes_id']);
                $table->dropForeign(['route_id']);
                $table->dropForeign(['user_id']);
            });
        }

        Schema::dropIfExists('ticket_fares');
    }
};
