<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('study_calendar_events') && ! Schema::hasColumn('study_calendar_events', 'event_type')) {
            Schema::table('study_calendar_events', function (Blueprint $table) {
                $table->enum('event_type', ['class', 'exam'])->default('class');
            });
        }

        if (Schema::hasTable('schedules') && ! Schema::hasColumn('schedules', 'schedule_type')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->enum('schedule_type', ['class', 'exam'])->default('class');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'user'])->default('user');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('study_calendar_events') && Schema::hasColumn('study_calendar_events', 'event_type')) {
            Schema::table('study_calendar_events', function (Blueprint $table) {
                $table->dropColumn('event_type');
            });
        }

        if (Schema::hasTable('schedules') && Schema::hasColumn('schedules', 'schedule_type')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropColumn('schedule_type');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
