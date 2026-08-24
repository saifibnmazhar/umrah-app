<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->decimal('profit', 14, 6)
                ->default(0)
                ->after('package_value');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('passengers') && Schema::hasColumn('passengers', 'profit')) {
            Schema::table('passengers', function (Blueprint $table) {
                $table->dropColumn('profit');
            });
        }
    }
};
