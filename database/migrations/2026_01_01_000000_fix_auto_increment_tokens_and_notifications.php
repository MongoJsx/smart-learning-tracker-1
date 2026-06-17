<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            DB::statement('ALTER TABLE `personal_access_tokens` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        if (Schema::hasTable('notification_email_settings')) {
            DB::statement('ALTER TABLE `notification_email_settings` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    public function down(): void
    {
        // No down migration because reverting AUTO_INCREMENT safely is not guaranteed.
    }
};
