<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('regular_price', 14, 6)->change();
            $table->decimal('offer_price', 14, 6)->nullable()->change();
            $table->decimal('service_charge', 14, 6)->nullable()->change();
        });

        Schema::table('ticket_fares', function (Blueprint $table) {
            $table->decimal('net_fare', 14, 6)->change();
            $table->decimal('selling_fare', 14, 6)->change();
            $table->decimal('offer_price', 14, 6)->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_amount', 14, 6)->unsigned()->change();
            $table->decimal('paid_amount', 14, 6)->unsigned()->change();
            $table->decimal('balance', 14, 6)->unsigned()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 14, 6)->change();
            $table->decimal('bdt_amount', 14, 6)->change();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->decimal('amount', 14, 6)->change();
            $table->decimal('bdt_amount', 14, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('regular_price', 10, 2)->change();
            $table->decimal('offer_price', 10, 2)->nullable()->change();
            $table->decimal('service_charge', 10, 2)->nullable()->change();
        });

        Schema::table('ticket_fares', function (Blueprint $table) {
            $table->decimal('net_fare', 10, 2)->change();
            $table->decimal('selling_fare', 10, 2)->change();
            $table->decimal('offer_price', 10, 2)->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_amount', 14, 2)->unsigned()->change();
            $table->decimal('paid_amount', 14, 2)->unsigned()->change();
            $table->decimal('balance', 14, 2)->unsigned()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
            $table->decimal('bdt_amount', 12, 2)->change();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
            $table->decimal('bdt_amount', 12, 2)->change();
        });
    }
};
