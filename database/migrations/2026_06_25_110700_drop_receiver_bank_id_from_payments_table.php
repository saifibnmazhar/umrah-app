<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['receiver_bank_id']);
            $table->dropColumn('receiver_bank_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('receiver_bank_id')
                ->nullable()
                ->constrained('banks')
                ->nullOnDelete();
        });
    }
};
