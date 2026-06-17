<?php

namespace Database\Seeders;

use App\Models\QuizAnswer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // สร้าง Admin account
        User::updateOrCreate(
            ['email' => '651463052@crru.ac.th'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123456'),
                'education_level' => 'Administrator',
                'role' => 'admin',
            ]
        );

        $user = User::updateOrCreate(
            ['email' => 'learner@example.com'],
            [
                'name' => 'Demo Learner',
                'password' => Hash::make('password123'),
                'education_level' => 'Undergraduate',
                'role' => 'user',
            ]
        );

        $subjects = [
            [
                'name' => 'Mathematics',
                'description' => 'Calculus and algebra review',
                'color' => '#2563eb',
                'target_hours' => 15,
            ],
            [
                'name' => 'English Literature',
                'description' => 'Modern poetry and prose analysis',
                'color' => '#f97316',
                'target_hours' => 10,
            ],
            [
                'name' => 'Physics',
                'description' => 'Mechanics and thermodynamics practice',
                'color' => '#14b8a6',
                'target_hours' => 12,
            ],
        ];

        foreach ($subjects as $index => $subjectData) {
            $subject = $user->subjects()->create($subjectData);

            $studyLog = $subject->studyLogs()->create([
                'user_id' => $user->id,
                'title' => "Study Session ".($index + 1),
                'note' => 'Reviewed core concepts and solved practice problems.',
                'log_date' => Carbon::now()->subDays($index + 1),
                'duration_minutes' => 90 - ($index * 10),
                'mood' => ['Focused', 'Inspired', 'Confident'][$index],
            ]);

            $studyLog->summaries()->create([
                'content' => "Key takeaways:\n- Revised fundamentals\n- Noted areas for improvement\n- Planned next study session",
                'ai_model' => 'gpt-4o-mini',
            ]);

            $quiz = $subject->quizzes()->create([
                'title' => $subject->name.' Quick Check',
                'description' => 'AI generated practice questions.',
                'ai_model' => 'gpt-4o-mini',
                'metadata' => ['difficulty' => 'medium'],
            ]);

            $question = $quiz->questions()->create([
                'question_text' => 'Sample question for '.$subject->name.'.',
                'question_type' => 'multiple_choice',
                'options' => ['Option A', 'Option B', 'Option C', 'Option D'],
                'correct_answer' => 'Option A',
                'explanation' => 'This is the correct choice for the demo dataset.',
            ]);

            QuizAnswer::create([
                'question_id' => $question->id,
                'user_id' => $user->id,
                'selected_answer' => 'Option A',
                'is_correct' => true,
                'score' => 1,
                'metadata' => ['seeded' => true],
                'answered_at' => Carbon::now()->subDays($index),
            ]);
        }
    }
}
