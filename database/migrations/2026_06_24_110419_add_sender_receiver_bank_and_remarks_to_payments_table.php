<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('sender_bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignId('receiver_bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('remarks', 255)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['sender_bank_id']);
            $table->dropForeign(['receiver_bank_id']);
            $table->dropColumn(['sender_bank_id', 'receiver_bank_id', 'remarks']);
        });
    }
};
