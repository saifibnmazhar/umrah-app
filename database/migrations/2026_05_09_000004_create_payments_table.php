<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('currency_rate_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('ticket_agent_id')->nullable();
            $table->unsignedBigInteger('visa_agent_id')->nullable();
            $table->unsignedBigInteger('commission_agent_id')->nullable();
            $table->date('payment_date');
            $table->enum('payment_method', ['cash', 'bank']);
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('bdt_amount', 12, 2);

            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('currency_rate_id')
                ->references('id')
                ->on('currency_rates')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('bank_id')
                ->references('id')
                ->on('banks')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('ticket_agent_id')
                ->references('id')
                ->on('ticket_agents')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('visa_agent_id')
                ->references('id')
                ->on('visa_agents')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->foreign('commission_agent_id')
                ->references('id')
                ->on('commission_agents')
                ->restrictOnDelete()
                ->onUpdate('cascade');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount >= 0)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_bdt_amount_check CHECK (bdt_amount >= 0)');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payments DROP CHECK IF EXISTS payments_amount_check');
        }
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payments DROP CHECK IF EXISTS payments_bdt_amount_check');
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['booking_id']);
                $table->dropForeign(['branch_id']);
                $table->dropForeign(['user_id']);
                $table->dropForeign(['currency_rate_id']);
                $table->dropForeign(['bank_id']);
                $table->dropForeign(['ticket_agent_id']);
                $table->dropForeign(['visa_agent_id']);
                $table->dropForeign(['commission_agent_id']);
            });
        }

        Schema::dropIfExists('payments');
    }
};
