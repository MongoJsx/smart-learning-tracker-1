<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_questions', 'points')) {
                $table->integer('points')->default(1)->after('correct_answer');
            }
            if (!Schema::hasColumn('quiz_questions', 'question_text')) {
                $table->renameColumn('question', 'question_text');
            }
            if (!Schema::hasColumn('quiz_questions', 'question_type')) {
                $table->string('question_type')->default('multiple_choice')->after('quiz_id');
            }
            if (!Schema::hasColumn('quiz_questions', 'explanation')) {
                $table->text('explanation')->nullable()->after('points');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn(['points', 'question_type', 'explanation']);
            if (Schema::hasColumn('quiz_questions', 'question_text')) {
                $table->renameColumn('question_text', 'question');
            }
        });
    }
};
