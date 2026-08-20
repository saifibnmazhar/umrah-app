<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->enum('payment_by', ['customer', 'airline', 'employee', 'company'])
                ->nullable()
                ->change();
        });

        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->enum('payment_by', ['customer', 'airline', 'employee', 'company'])
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('re_issued_tickets', function (Blueprint $table) {
            $table->enum('payment_by', ['customer', 'airline', 'employee'])
                ->nullable()
                ->change();
        });

        Schema::table('refunded_tickets', function (Blueprint $table) {
            $table->enum('payment_by', ['customer', 'airline', 'employee'])
                ->nullable()
                ->change();
        });
    }
};
