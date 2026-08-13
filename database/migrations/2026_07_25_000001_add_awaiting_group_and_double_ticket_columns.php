<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE issued_tickets MODIFY COLUMN status ENUM('pending', 'issued', 're-issued', 'refunded', 'awaiting-group') NOT NULL DEFAULT 'pending'");

        DB::statement('ALTER TABLE packages MODIFY COLUMN ticket_fare_id BIGINT UNSIGNED NULL');

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('ticket_fare_inbound_id')->nullable()->after('ticket_fare_id')->constrained('ticket_fares')->nullOnDelete();
            $table->foreignId('ticket_fare_outbound_id')->nullable()->after('ticket_fare_inbound_id')->constrained('ticket_fares')->nullOnDelete();
            $table->boolean('is_double_ticket')->default(false)->after('ticket_fare_outbound_id');
        });

        Schema::table('passengers', function (Blueprint $table) {
            $table->text('ticket_remarks')->nullable()->after('ticket_fare_id');
            $table->foreignId('ticket_fare_inbound_id')->nullable()->after('ticket_remarks')->constrained('ticket_fares')->nullOnDelete();
            $table->foreignId('ticket_fare_outbound_id')->nullable()->after('ticket_fare_inbound_id')->constrained('ticket_fares')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropForeign(['ticket_fare_outbound_id']);
            $table->dropForeign(['ticket_fare_inbound_id']);
            $table->dropColumn(['ticket_fare_outbound_id', 'ticket_fare_inbound_id', 'ticket_remarks']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['ticket_fare_outbound_id']);
            $table->dropForeign(['ticket_fare_inbound_id']);
            $table->dropColumn(['ticket_fare_outbound_id', 'ticket_fare_inbound_id', 'is_double_ticket']);
        });

        DB::statement('UPDATE packages SET ticket_fare_id = 0 WHERE ticket_fare_id IS NULL');
        DB::statement('ALTER TABLE packages MODIFY COLUMN ticket_fare_id BIGINT UNSIGNED NOT NULL');

        DB::statement("ALTER TABLE issued_tickets MODIFY COLUMN status ENUM('pending', 'issued', 're-issued', 'refunded') NOT NULL DEFAULT 'pending'");
    }
};
