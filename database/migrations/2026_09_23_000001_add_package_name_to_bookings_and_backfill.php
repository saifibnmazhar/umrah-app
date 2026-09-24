<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('package_name')->nullable()->after('package_id');
        });

        DB::transaction(function () {
            DB::table('bookings')
                ->join('packages', 'bookings.package_id', '=', 'packages.id')
                ->update(['bookings.package_name' => DB::raw('packages.package_name')]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('package_name');
        });
    }
};
