<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('career_recommendations')) {
            return;
        }

        Schema::table('career_recommendations', function (Blueprint $table) {
            if (! Schema::hasColumn('career_recommendations', 'career')) {
                $table->string('career', 255)->nullable()->after('subject_id');
            }
        });

        // allow AI-driven records without fixed career_paths mapping
        if (Schema::hasColumn('career_recommendations', 'career_path_id')) {
            DB::statement('ALTER TABLE `career_recommendations` DROP FOREIGN KEY `career_recommendations_career_path_id_foreign`');
            DB::statement('ALTER TABLE `career_recommendations` MODIFY `career_path_id` BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE `career_recommendations` ADD CONSTRAINT `career_recommendations_career_path_id_foreign` FOREIGN KEY (`career_path_id`) REFERENCES `career_paths`(`id`) ON DELETE CASCADE');
        }

        // Remove old fixed recommendations/paths so the system uses fresh AI-generated data only.
        DB::table('career_recommendations')->delete();
        if (Schema::hasTable('career_paths')) {
            DB::table('career_paths')->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('career_recommendations')) {
            return;
        }

        if (Schema::hasColumn('career_recommendations', 'career_path_id')) {
            DB::statement('ALTER TABLE `career_recommendations` DROP FOREIGN KEY `career_recommendations_career_path_id_foreign`');
            DB::statement('ALTER TABLE `career_recommendations` MODIFY `career_path_id` BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE `career_recommendations` ADD CONSTRAINT `career_recommendations_career_path_id_foreign` FOREIGN KEY (`career_path_id`) REFERENCES `career_paths`(`id`) ON DELETE CASCADE');
        }

        if (Schema::hasColumn('career_recommendations', 'career')) {
            Schema::table('career_recommendations', function (Blueprint $table) {
                $table->dropColumn('career');
            });
        }
    }
};
