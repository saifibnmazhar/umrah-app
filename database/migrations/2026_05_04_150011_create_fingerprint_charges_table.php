<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fingerprint_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')
                ->constrained('districts')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->decimal('fingerprint_charge', 10, 2);
            $table->unique('district_id');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE fingerprint_charges ADD CONSTRAINT fingerprint_charges_fingerprint_charge_check CHECK (fingerprint_charge >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE fingerprint_charges DROP CHECK IF EXISTS fingerprint_charges_fingerprint_charge_check');
        }

        if (Schema::hasTable('fingerprint_charges')) {
            Schema::table('fingerprint_charges', function (Blueprint $table) {
                $table->dropForeign(['district_id']);
                $table->dropForeign(['user_id']);
            });
        }

        Schema::dropIfExists('fingerprint_charges');
    }
};
