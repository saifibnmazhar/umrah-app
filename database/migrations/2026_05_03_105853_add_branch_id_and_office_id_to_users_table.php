<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete()
                ->onUpdate('cascade')
                ->after('password');

            $table->foreignId('office_id')
                ->nullable()
                ->constrained('offices')
                ->nullOnDelete()
                ->onUpdate('cascade')
                ->after('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropForeign(['branch_id']);
        $table->dropForeign(['office_id']);
        $table->dropColumn(['branch_id', 'office_id']);
    });
}
};
