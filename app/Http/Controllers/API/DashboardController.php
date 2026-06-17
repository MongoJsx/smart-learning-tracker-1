<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function overview(): JsonResponse
    {
        $user = request()->user();
        $subjectCount = $user->subjects()->count();
        $studyLogCount = $user->subjects()->withCount('studyLogs')->get()->sum('study_logs_count');
        $quizCount = $user->subjects()->withCount('quizzes')->get()->sum('quizzes_count');
        $quizAttempts = $user->quizAnswers()->count();

        return response()->json([
            'subjects' => $subjectCount,
            'study_logs' => $studyLogCount,
            'quizzes' => $quizCount,
            'quiz_attempts' => $quizAttempts,
        ]);
    }

    public function progress(): JsonResponse
    {
        $user = request()->user();
        $range = now()->subMonths(3);

        $studyTrend = DB::table('study_logs')
            ->selectRaw('DATE_FORMAT(log_date, "%Y-%m") as period, SUM(duration_minutes) as total_minutes')
            ->join('subjects', 'study_logs.subject_id', '=', 'subjects.id')
            ->where('subjects.user_id', $user->id)
            ->where('log_date', '>=', $range)
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $quizTrend = DB::table('quiz_answers')
            ->selectRaw('DATE_FORMAT(answered_at, "%Y-%m") as period, AVG(score) as average_score')
            ->join('quiz_questions', 'quiz_answers.question_id', '=', 'quiz_questions.id')
            ->join('quizzes', 'quiz_questions.quiz_id', '=', 'quizzes.id')
            ->join('subjects', 'quizzes.subject_id', '=', 'subjects.id')
            ->where('quiz_answers.user_id', $user->id)
            ->whereNotNull('answered_at')
            ->where('answered_at', '>=', $range)
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json([
            'study_trend' => $studyTrend,
            'quiz_trend' => $quizTrend,
        ]);
    }
}
