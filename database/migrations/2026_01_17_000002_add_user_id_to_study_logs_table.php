<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('study_logs', 'user_id')) {
            return;
        }

        Schema::table('study_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->index('user_id');
        });

        DB::table('study_logs')
            ->join('subjects', 'study_logs.subject_id', '=', 'subjects.id')
            ->update(['study_logs.user_id' => DB::raw('subjects.user_id')]);

        DB::statement('ALTER TABLE study_logs MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('study_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('study_logs', 'user_id')) {
            return;
        }

        Schema::table('study_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
