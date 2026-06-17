<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('subjects')) {
            return;
        }

        if (! $this->hasColumnSafe('subjects', 'semester_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->unsignedInteger('semester_id')->nullable()->after('user_id');
                $table->index('semester_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subjects') || ! $this->hasColumnSafe('subjects', 'semester_id')) {
            return;
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex(['semester_id']);
            $table->dropColumn('semester_id');
        });
    }

    private function hasColumnSafe(string $table, string $column): bool
    {
        try {
            return DB::selectOne(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
                [$table, $column]
            ) !== null;
        } catch (Throwable $error) {
            return false;
        }
    }
};
