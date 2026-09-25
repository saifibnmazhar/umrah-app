<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Drop foreign key first so the unique index can be replaced
            $table->dropForeign(['ticket_fare_id']);

            // Drop unique index
            $table->dropUnique('packages_ticket_fare_id_unique');

            // Add normal index
            $table->index('ticket_fare_id');

            // Recreate foreign key
            $table->foreign('ticket_fare_id')
                ->references('id')
                ->on('ticket_fares')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Drop FK
            $table->dropForeign(['ticket_fare_id']);

            // Drop normal index
            $table->dropIndex(['ticket_fare_id']);

            // Restore unique
            $table->unique('ticket_fare_id');

            // Restore FK
            $table->foreign('ticket_fare_id')
                ->references('id')
                ->on('ticket_fares')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }
};
