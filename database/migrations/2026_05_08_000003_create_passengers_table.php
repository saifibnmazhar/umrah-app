<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('passenger_status_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('passport_no');
            $table->string('mobile_no');
            $table->date('date_of_birth');
            $table->enum('passenger_type', ['adult', 'child', 'infant']);
            $table->date('passport_expiry');
            $table->integer('stay_duration');
            $table->enum('service_required', ['all', 'visa_only', 'ticket_only']);
            $table->date('flight_date_from');
            $table->date('flight_date_to');
            $table->date('actual_flight_date')->nullable();
            $table->enum('ticket_status', ['pending', 'issued', 're-issued', 'refunded']);
            $table->enum('visa_status', ['pending', 'submitted', 'issued']);
            $table->string('address');

            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('passenger_status_id')
                ->references('id')
                ->on('passenger_statuses')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE passengers ADD CONSTRAINT stay_duration_positive CHECK (stay_duration >= 1)');
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE passengers DROP CONSTRAINT stay_duration_positive');
        } catch (\Exception $e) {
            // ignore if constraint does not exist
        }

        if (Schema::hasTable('passengers')) {
            Schema::table('passengers', function (Blueprint $table) {
                $table->dropForeign(['booking_id']);
                $table->dropForeign(['passenger_status_id']);
            });
        }

        Schema::dropIfExists('passengers');
    }
};