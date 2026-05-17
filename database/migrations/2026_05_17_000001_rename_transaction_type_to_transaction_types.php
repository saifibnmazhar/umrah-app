<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_type')) {
            Schema::rename('transaction_type', 'transaction_types');
        }

        if (!Schema::hasTable('transaction_types')) {
            Schema::create('transaction_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->enum('type', ['debit', 'credit']);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transaction_types')) {
            Schema::rename('transaction_types', 'transaction_type');
        }
    }
};