<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE passengers SET flight_date_from = DATE_ADD(flight_date_from, INTERVAL 1 DAY) WHERE flight_date_from IS NOT NULL');
        DB::statement('UPDATE passengers SET flight_date_to = DATE_ADD(flight_date_to, INTERVAL 1 DAY) WHERE flight_date_to IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE passengers SET flight_date_from = DATE_SUB(flight_date_from, INTERVAL 1 DAY) WHERE flight_date_from IS NOT NULL');
        DB::statement('UPDATE passengers SET flight_date_to = DATE_SUB(flight_date_to, INTERVAL 1 DAY) WHERE flight_date_to IS NOT NULL');
    }
};
