<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancelled_bookings', function (Blueprint $table) {
            $table->foreignId('confirmed_by_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('reverted_by_id')
                ->nullable()
                ->after('confirmed_by_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('cancelled_bookings', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by_id']);
            $table->dropForeign(['reverted_by_id']);
            $table->dropColumn(['confirmed_by_id', 'reverted_by_id']);
            $table->dropSoftDeletes();
        });
    }
};
