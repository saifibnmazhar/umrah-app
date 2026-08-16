<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('re_issue_refund_reasons', function (Blueprint $table) {
            $table->id();
            $table->enum('reason_of', ['re-issue', 'refund']);
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_issue_refund_reasons');
    }
};
