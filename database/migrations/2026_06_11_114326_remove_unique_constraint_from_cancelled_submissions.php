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
        Schema::table('cancelled_submissions', function (Blueprint $table) {
            $table->dropForeign(['visa_submission_id']);
            $table->dropUnique(['visa_submission_id']);
        });

        Schema::table('cancelled_submissions', function (Blueprint $table) {
            $table->index('visa_submission_id');
            $table->foreign('visa_submission_id')
                ->references('id')
                ->on('visa_submissions')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('cancelled_submissions', function (Blueprint $table) {
            $table->dropForeign(['visa_submission_id']);
            $table->dropIndex(['visa_submission_id']);
        });

        Schema::table('cancelled_submissions', function (Blueprint $table) {
            $table->unique('visa_submission_id');
            $table->foreign('visa_submission_id')
                ->references('id')
                ->on('visa_submissions')
                ->restrictOnDelete()
                ->onUpdate('cascade');
        });
    }
};
