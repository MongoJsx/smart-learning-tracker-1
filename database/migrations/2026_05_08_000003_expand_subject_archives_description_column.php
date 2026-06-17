<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subjects_archives')) {
            return;
        }

        // Imported dumps may create this as VARCHAR(255); force LONGTEXT for full archive content.
        DB::statement('ALTER TABLE `subjects_archives` MODIFY `description` LONGTEXT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('subjects_archives')) {
            return;
        }

        DB::statement('ALTER TABLE `subjects_archives` MODIFY `description` TEXT NULL');
    }
};

