<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fingerprints', function (Blueprint $table) {
            $table->decimal('profit', 14, 6)
                ->default(0)
                ->after('cost');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fingerprints') && Schema::hasColumn('fingerprints', 'profit')) {
            Schema::table('fingerprints', function (Blueprint $table) {
                $table->dropColumn('profit');
            });
        }
    }
};
