<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_fare_id');
            $table->date('inbound_date');
            $table->date('outbound_date');
            $table->string('pnr');
            $table->integer('ticket_qty');
            $table->boolean('is_refundable');
            $table->boolean('is_exchangable');

            $table->foreign('ticket_fare_id')
                ->references('id')
                ->on('ticket_fares')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE group_tickets ADD CONSTRAINT group_tickets_ticket_qty_check CHECK (ticket_qty >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE group_tickets DROP CHECK IF EXISTS group_tickets_ticket_qty_check');

        if (Schema::hasTable('group_tickets')) {
            Schema::table('group_tickets', function (Blueprint $table) {
                $table->dropForeign(['ticket_fare_id']);
            });
        }

        Schema::dropIfExists('group_tickets');
    }
};
