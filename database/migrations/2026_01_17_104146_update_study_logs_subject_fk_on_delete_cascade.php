<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('study_logs')) {
            return;
        }

        // Best-effort: DB dumps often have different constraint names.
        try {
            Schema::table('study_logs', function (Blueprint $table) {
                $table->dropForeign(['subject_id']);
            });
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            Schema::table('study_logs', function (Blueprint $table) {
                $table->foreign('subject_id')
                      ->references('id')->on('subjects')
                      ->onDelete('cascade');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('study_logs')) {
            return;
        }

        try {
            Schema::table('study_logs', function (Blueprint $table) {
                $table->dropForeign(['subject_id']);
            });
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            Schema::table('study_logs', function (Blueprint $table) {
                $table->foreign('subject_id')
                      ->references('id')->on('subjects');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
};

