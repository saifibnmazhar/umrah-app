<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancelled_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_submission_id')
                ->constrained('visa_submissions')
                ->restrictOnDelete()
                ->onUpdate('cascade');
            $table->decimal('cancellation_fee', 10, 2)->nullable();

            $table->unique('visa_submission_id');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE cancelled_submissions ADD CONSTRAINT cancelled_submissions_cancellation_fee_check CHECK (cancellation_fee IS NULL OR cancellation_fee >= 0)');
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE cancelled_submissions DROP CHECK IF EXISTS cancelled_submissions_cancellation_fee_check');
        } catch (\Exception $e) {
            // MariaDB compatibility: ignore if constraint doesn't exist
        }

        if (Schema::hasTable('cancelled_submissions')) {
            Schema::table('cancelled_submissions', function (Blueprint $table) {
                $table->dropForeign(['visa_submission_id']);
            });
        }

        Schema::dropIfExists('cancelled_submissions');
    }
};