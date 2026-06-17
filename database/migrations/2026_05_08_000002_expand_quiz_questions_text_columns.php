<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('quiz_questions')) {
            return;
        }

        if (Schema::hasColumn('quiz_questions', 'question_text')) {
            DB::statement("ALTER TABLE `quiz_questions` MODIFY `question_text` TEXT NOT NULL");
        }

        if (Schema::hasColumn('quiz_questions', 'options')) {
            DB::statement("ALTER TABLE `quiz_questions` MODIFY `options` LONGTEXT NULL");
        }

        if (Schema::hasColumn('quiz_questions', 'correct_answer')) {
            DB::statement("ALTER TABLE `quiz_questions` MODIFY `correct_answer` TEXT NULL");
        }

        if (Schema::hasColumn('quiz_questions', 'explanation')) {
            DB::statement("ALTER TABLE `quiz_questions` MODIFY `explanation` TEXT NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('quiz_questions')) {
            return;
        }

        if (Schema::hasColumn('quiz_questions', 'question_text')) {
            DB::statement("ALTER TABLE `quiz_questions` MODIFY `question_text` VARCHAR(255) NOT NULL");
        }

        if (Schema::hasColumn('quiz_questions', 'options')) {
            DB::statement("ALTER TABLE `quiz_questions` MODIFY `options` VARCHAR(255) NULL");
        }

        if (Schema::hasColumn('quiz_questions', 'correct_answer')) {
            DB::statement("ALTER TABLE `quiz_questions` MODIFY `correct_answer` VARCHAR(255) NULL");
        }

        if (Schema::hasColumn('quiz_questions', 'explanation')) {
            DB::statement("ALTER TABLE `quiz_questions` MODIFY `explanation` VARCHAR(255) NULL");
        }
    }
};

