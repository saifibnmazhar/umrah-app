<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->unsignedBigInteger('ticket_fare_id')->nullable()->after('gender');

            $table->foreign('ticket_fare_id')
                ->references('id')
                ->on('ticket_fares')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropForeign(['ticket_fare_id']);
            $table->dropColumn('ticket_fare_id');
        });
    }
};