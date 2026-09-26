<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('package_update_logs')) {
            Schema::create('package_update_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users');
                $table->string('action');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamp('created_at')->nullable();
            });

            return;
        }

        // Table already exists (partial state) — add only the missing columns.
        $this->ensureColumn('package_id', fn (Blueprint $t) => $t->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete());
        $this->ensureColumn('user_id', fn (Blueprint $t) => $t->foreignId('user_id')->constrained('users'));
        $this->ensureColumn('action', fn (Blueprint $t) => $t->string('action'));
        $this->ensureColumn('old_values', fn (Blueprint $t) => $t->json('old_values')->nullable());
        $this->ensureColumn('new_values', fn (Blueprint $t) => $t->json('new_values')->nullable());
        $this->ensureColumn('created_at', fn (Blueprint $t) => $t->timestamp('created_at')->nullable());
    }

    public function down(): void
    {
        Schema::dropIfExists('package_update_logs');
    }

    private function ensureColumn(string $column, Closure $definition): void
    {
        if (! Schema::hasColumn('package_update_logs', $column)) {
            Schema::table('package_update_logs', $definition);
        }
    }
};
