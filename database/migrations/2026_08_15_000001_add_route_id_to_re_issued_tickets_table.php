<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->foreignId('route_id')
                  ->nullable()
                  ->after('group_ticket_id')
                  ->constrained('routes')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('route_id');
        });
    }
};
