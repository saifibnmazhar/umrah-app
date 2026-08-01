<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE issued_ticket_logs 
            MODIFY COLUMN action ENUM('issued','edited','re-issued','refunded','confirmed_group')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE issued_ticket_logs 
            MODIFY COLUMN action ENUM('issued','edited','re-issued','refunded')");
    }
};
