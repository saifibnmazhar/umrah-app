<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE fingerprint_details MODIFY COLUMN status ENUM('none', 'processing', 'approved', 'cancelled', 'done') NOT NULL DEFAULT 'none'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE fingerprint_details MODIFY COLUMN status ENUM('none', 'processing', 'approved', 'cancelled') NOT NULL DEFAULT 'none'");
    }
};
