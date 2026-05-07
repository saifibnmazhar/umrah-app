<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_tickets', function (Blueprint $table) {
            $table->date('inbound_date')->nullable()->change();
            $table->date('outbound_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('group_tickets', function (Blueprint $table) {
            $table->date('inbound_date')->nullable(false)->change();
            $table->date('outbound_date')->nullable(false)->change();
        });
    }
};