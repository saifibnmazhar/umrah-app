<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete()
                ->onUpdate('cascade')
                ->after('id');

            $table->unsignedBigInteger('booking_id')
                ->nullable()
                ->change();

            $table->text('notes')->nullable()->after('bdt_amount');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);

                if (Schema::hasColumn('vouchers', 'invoice_id')) {
                    $table->dropColumn('invoice_id');
                }

                if (Schema::hasColumn('vouchers', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }
    }
};
