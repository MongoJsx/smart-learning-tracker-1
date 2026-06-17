<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $subjectId = $request->input('subject_id');

        if ($subjectId && ! Subject::where('id', $subjectId)->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'ไม่พบรายวิชาที่เลือก'], 422);
        }

        $quizzes = Quiz::with(['subject', 'questions'])
            ->whereHas('subject', fn ($query) => $query->where('user_id', $userId))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->get();
        
        return response()->json($quizzes);
    }

    public function show(Quiz $quiz): JsonResponse
    {
        abort_unless($quiz->subject->user_id === request()->user()->id, 403, 'Unauthorized');
        return response()->json($quiz->load(['questions', 'subject']));
    }
    public function submit(Request $request, Quiz $quiz): JsonResponse
    {
        abort_unless($quiz->subject->user_id === $request->user()->id, 403, 'Unauthorized');
        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        $userId = $request->user()->id;
        $score = $this->calculateScore($quiz, $validated['answers'], $userId);
        $passed = $score >= $quiz->passing_score;

        $attemptId = DB::table('quiz_attempts')->insertGetId([
            'user_id' => $userId,
            'quiz_id' => $quiz->id,
            'answers' => json_encode($validated['answers']),
            'score' => $score,
            'passed' => $passed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $attempt = DB::table('quiz_attempts')->where('id', $attemptId)->first();

        if ($attempt && is_string($attempt->answers)) {
            $attempt->answers = json_decode($attempt->answers, true);
        }

        return response()->json([
            'attempt' => $attempt,
            'score' => $score,
            'passed' => $passed,
        ]);
    }

    private function calculateScore(Quiz $quiz, array $answers, int $userId): int
    
    {
        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($quiz->questions as $question) {
            $totalPoints += $question->points ?? 1;
            
            $selectedAnswer = $answers[$question->id] ?? null;
            $isCorrect = $selectedAnswer === $question->correct_answer;
            
            if ($isCorrect) {
                $earnedPoints += $question->points ?? 1;
            }

            // บันทึกคำตอบแต่ละข้อ
            QuizAnswer::create([
                'question_id' => $question->id,
                'user_id' => $userId,
                'selected_answer' => $selectedAnswer,
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? ($question->points ?? 1) : 0,
            ]);
        }

        return $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100) : 0;
    }
}
