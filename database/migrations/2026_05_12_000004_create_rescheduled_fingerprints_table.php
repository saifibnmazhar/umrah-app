<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rescheduled_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fingerprint_detail_id')
                ->constrained('fingerprint_details')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->enum('reason', ['rescheduled_by_client', 'rescheduled_by_bmt', 'nfc_problem', 'others']);
            $table->string('other_reason')->nullable();
            $table->date('next_date');
            $table->unsignedInteger('occurrence');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE rescheduled_fingerprints ADD CONSTRAINT rescheduled_fingerprints_occurrence_check CHECK (occurrence >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE rescheduled_fingerprints DROP CHECK IF EXISTS rescheduled_fingerprints_occurrence_check');

        if (Schema::hasTable('rescheduled_fingerprints')) {
            Schema::table('rescheduled_fingerprints', function (Blueprint $table) {
                $table->dropForeign(['fingerprint_detail_id']);
            });
        }

        Schema::dropIfExists('rescheduled_fingerprints');
    }
};