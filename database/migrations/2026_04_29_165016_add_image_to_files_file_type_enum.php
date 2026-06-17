<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('files') || ! Schema::hasColumn('files', 'file_type')) {
            return;
        }

        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE files MODIFY file_type ENUM('pdf', 'word', 'audio', 'image', 'other') NOT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('files') || ! Schema::hasColumn('files', 'file_type')) {
            return;
        }

        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE files MODIFY file_type ENUM('pdf', 'word', 'audio', 'other') NOT NULL");
    }
};
