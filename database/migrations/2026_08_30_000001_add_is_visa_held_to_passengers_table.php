<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->boolean('is_visa_held')->default(false)->after('ticket_held_at');
            $table->foreignId('visa_held_by')->nullable()->constrained('users')->nullOnDelete()->after('is_visa_held');
            $table->timestamp('visa_held_at')->nullable()->after('visa_held_by');
        });
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropColumn(['is_visa_held', 'visa_held_by', 'visa_held_at']);
        });
    }
};
