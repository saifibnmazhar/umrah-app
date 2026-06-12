<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['office_id']);

            $table->renameColumn('branch_id', 'booking_branch_id');
            $table->renameColumn('office_id', 'fingerprint_branch_id');

            $table->foreign('booking_branch_id')->references('id')->on('branches')->onUpdate('cascade');
            $table->foreign('fingerprint_branch_id')->references('id')->on('branches')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['booking_branch_id']);
            $table->dropForeign(['fingerprint_branch_id']);

            $table->renameColumn('booking_branch_id', 'branch_id');
            $table->renameColumn('fingerprint_branch_id', 'office_id');

            $table->foreign('branch_id')->references('id')->on('branches')->onUpdate('cascade');
            $table->foreign('office_id')->references('id')->on('offices')->onUpdate('cascade');
        });
    }
};
