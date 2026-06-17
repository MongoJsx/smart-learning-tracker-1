<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_email_logs')) {
            return;
        }

        // Some imported DB dumps create `id` without AUTO_INCREMENT; this breaks inserts.
        try {
            DB::statement('ALTER TABLE `notification_email_logs` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT');
        } catch (\Throwable $e) {
            // Best-effort; keep migration non-fatal so deploys don't break.
        }

        // Ensure a primary key exists for AUTO_INCREMENT (best-effort).
        try {
            DB::statement('ALTER TABLE `notification_email_logs` ADD PRIMARY KEY (`id`)');
        } catch (\Throwable $e) {
            // Ignore if it already exists or cannot be added.
        }
    }

    public function down(): void
    {
        // No down migration because reverting AUTO_INCREMENT safely is not guaranteed.
    }
};

