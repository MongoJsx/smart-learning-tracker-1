<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quizzes')) {
            Schema::create('quizzes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subject_id')->constrained()->onDelete('cascade');
                $table->foreignId('lesson_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('title');
                $table->text('description')->nullable();
                $table->integer('duration_minutes')->default(60);
                $table->integer('passing_score')->default(60);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('quiz_questions')) {
            Schema::create('quiz_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
                $table->string('question_type')->default('multiple_choice');
                $table->text('question_text');
                $table->json('options');
                $table->string('correct_answer');
                $table->integer('points')->default(1);
                $table->text('explanation')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('quiz_attempts')) {
            Schema::create('quiz_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
                $table->json('answers');
                $table->integer('score');
                $table->boolean('passed')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
