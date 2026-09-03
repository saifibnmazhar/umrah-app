<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_fares', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['route_id']);

            // Drop unique index
            $table->dropUnique('ticket_fares_route_id_unique');

            // Add normal index
            $table->index('route_id');

            // Recreate foreign key
            $table->foreign('route_id')
                ->references('id')
                ->on('routes')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_fares', function (Blueprint $table) {
            // Drop FK
            $table->dropForeign(['route_id']);

            // Drop normal index
            $table->dropIndex(['route_id']);

            // Restore unique
            $table->unique('route_id');

            // Restore FK
            $table->foreign('route_id')
                ->references('id')
                ->on('routes')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }
};
