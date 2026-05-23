<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE documents MODIFY COLUMN owner_type VARCHAR(255) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE documents MODIFY COLUMN owner_type ENUM('customer', 'passenger') NOT NULL");
    }
};
