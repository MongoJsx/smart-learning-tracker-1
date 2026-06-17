<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('portfolio_images')) {
            return;
        }

        $primaryKey = DB::selectOne("
            SELECT COUNT(*) AS total
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'portfolio_images'
              AND CONSTRAINT_TYPE = 'PRIMARY KEY'
        ");

        if ((int) ($primaryKey->total ?? 0) === 0) {
            DB::statement('ALTER TABLE `portfolio_images` ADD PRIMARY KEY (`id`)');
        }

        DB::statement('ALTER TABLE `portfolio_images` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('portfolio_images')) {
            return;
        }

        DB::statement('ALTER TABLE `portfolio_images` MODIFY `id` BIGINT UNSIGNED NOT NULL');
    }
};
