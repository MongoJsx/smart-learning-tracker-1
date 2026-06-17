<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('subjects')) {
            return;
        }

        if (! Schema::hasColumn('subjects', 'start_date')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->date('start_date')->nullable()->after('target_hours');
            });
        }

        if (! Schema::hasColumn('subjects', 'start_time')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->time('start_time')->nullable()->after('start_date');
            });
        }

        if (! Schema::hasColumn('subjects', 'end_time')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->time('end_time')->nullable()->after('start_time');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subjects')) {
            return;
        }

        if (Schema::hasColumn('subjects', 'end_time')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropColumn('end_time');
            });
        }

        if (Schema::hasColumn('subjects', 'start_time')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropColumn('start_time');
            });
        }

        if (Schema::hasColumn('subjects', 'start_date')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropColumn('start_date');
            });
        }
    }
};
