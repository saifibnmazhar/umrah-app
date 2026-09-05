<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cancelled_bookings', 'confirmed_by_id')) {
            Schema::table('cancelled_bookings', function (Blueprint $table) {
                $table->foreignId('confirmed_by_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('cancelled_bookings', 'reverted_by_id')) {
            Schema::table('cancelled_bookings', function (Blueprint $table) {
                $table->foreignId('reverted_by_id')
                    ->nullable()
                    ->after('confirmed_by_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('cancelled_bookings', 'deleted_at')) {
            Schema::table('cancelled_bookings', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::table('cancelled_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('cancelled_bookings', 'confirmed_by_id')) {
                $table->dropForeign(['confirmed_by_id']);
                $table->dropColumn('confirmed_by_id');
            }
            if (Schema::hasColumn('cancelled_bookings', 'reverted_by_id')) {
                $table->dropForeign(['reverted_by_id']);
                $table->dropColumn('reverted_by_id');
            }
            if (Schema::hasColumn('cancelled_bookings', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
