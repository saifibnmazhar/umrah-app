<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_tickets', function (Blueprint $table) {
            $table->decimal('selling_fare', 14, 6)->default(0)->change();
            $table->decimal('net_fare', 14, 6)->default(0)->change();
        });

        Schema::table('issued_tickets', function (Blueprint $table) {
            $table->decimal('offer_price', 14, 6)->nullable()->after('net_fare');
        });

        DB::statement('ALTER TABLE issued_tickets ADD CONSTRAINT issued_tickets_selling_fare_check CHECK (selling_fare >= 0)');
        DB::statement('ALTER TABLE issued_tickets ADD CONSTRAINT issued_tickets_net_fare_check CHECK (net_fare >= 0)');
        DB::statement('ALTER TABLE issued_tickets ADD CONSTRAINT issued_tickets_offer_price_check CHECK (offer_price IS NULL OR offer_price >= 0)');
    }

    public function down(): void
    {
        Schema::table('issued_tickets', function (Blueprint $table) {
            $table->dropColumn('offer_price');
        });

        Schema::table('issued_tickets', function (Blueprint $table) {
            $table->decimal('selling_fare', 12, 2)->default(0)->change();
            $table->decimal('net_fare', 12, 2)->default(0)->change();
        });

        DB::statement('ALTER TABLE issued_tickets DROP CONSTRAINT IF EXISTS issued_tickets_selling_fare_check');
        DB::statement('ALTER TABLE issued_tickets DROP CONSTRAINT IF EXISTS issued_tickets_net_fare_check');
        DB::statement('ALTER TABLE issued_tickets DROP CONSTRAINT IF EXISTS issued_tickets_offer_price_check');
    }
};
