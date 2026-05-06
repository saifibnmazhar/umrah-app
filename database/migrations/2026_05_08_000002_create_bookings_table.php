<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('office_id');
            $table->unsignedBigInteger('district_id');
            $table->unsignedBigInteger('package_id');
            $table->unsignedBigInteger('fingerprint_charge_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('invoice_id')->unique();
            $table->unsignedBigInteger('date_gap_id');
            $table->enum('fingerprint_location', ['home', 'office']);
            $table->integer('pax_qty');
            $table->enum('discount_type', ['fixed_amount', 'percentage']);
            $table->decimal('discount_value', 10, 2);
            $table->decimal('discount_amount', 10, 2);
            $table->string('remarks')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('office_id')
                ->references('id')
                ->on('offices')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('package_id')
                ->references('id')
                ->on('packages')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('fingerprint_charge_id')
                ->references('id')
                ->on('fingerprint_charges')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('date_gap_id')
                ->references('id')
                ->on('flight_date_gap')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_pax_qty_check CHECK (pax_qty >= 1)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_discount_value_check CHECK (discount_value >= 0)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_discount_amount_check CHECK (discount_amount >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CHECK IF EXISTS bookings_pax_qty_check');
        DB::statement('ALTER TABLE bookings DROP CHECK IF EXISTS bookings_discount_value_check');
        DB::statement('ALTER TABLE bookings DROP CHECK IF EXISTS bookings_discount_amount_check');

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropForeign(['customer_id']);
                $table->dropForeign(['office_id']);
                $table->dropForeign(['district_id']);
                $table->dropForeign(['package_id']);
                $table->dropForeign(['fingerprint_charge_id']);
                $table->dropForeign(['branch_id']);
                $table->dropForeign(['date_gap_id']);
            });
        }

        Schema::dropIfExists('bookings');
    }
};