<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $manual = ['Hold', 'Cancel', 'Delivered', 'Ticket Refund Done', 'Departure Done'];
        $manualIds = DB::table('passenger_statuses')->whereIn('name', $manual)->pluck('id');
        DB::table('passengers')
            ->whereNotNull('passenger_status_id')
            ->when($manualIds->isNotEmpty(), fn ($q) => $q->whereNotIn('passenger_status_id', $manualIds))
            ->update(['passenger_status_id' => null]);
    }

    public function down(): void {}
};
