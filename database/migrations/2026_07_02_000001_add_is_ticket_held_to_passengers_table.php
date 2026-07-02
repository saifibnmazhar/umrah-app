<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->boolean('is_ticket_held')->default(false)->after('package_value');
            $table->foreignId('ticket_held_by')->nullable()->constrained('users')->nullOnDelete()->after('is_ticket_held');
            $table->timestamp('ticket_held_at')->nullable()->after('ticket_held_by');
        });
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropColumn(['is_ticket_held', 'ticket_held_by', 'ticket_held_at']);
        });
    }
};
