<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareerPath;
use App\Models\CareerRecommendation;
use App\Models\User;
use App\Services\AI\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CareerAdvisorController extends Controller
{
    public function __construct(private readonly AIService $aiService)
    {
    }

    public function insights(Request $request): JsonResponse
    {
        $user = $request->user();
        $topSubjects = $this->topSubjects($user->id);
        $weakSubjects = $this->weakSubjects($user->id, collect($topSubjects)->pluck('id')->all());
        $latestQuiz = $this->latestQuizAttempt($user->id);
        $latestQuizAnalysis = $this->latestQuizAnalysis($user->id);

        return response()->json([
            'top_subjects' => $topSubjects->values(),
            'weak_subjects' => $weakSubjects->values(),
            'latest_quiz' => $latestQuiz,
            'latest_quiz_analysis' => $latestQuizAnalysis,
        ]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! Schema::hasTable('career_recommendations')) {
            return response()->json([]);
        }

        $topSubjects = $this->topSubjects($user->id);
        if ($topSubjects->isEmpty()) {
            CareerRecommendation::query()->where('user_id', $user->id)->delete();
            return response()->json([]);
        }

        $defaultSubjectsText = collect($topSubjects)
            ->pluck('subject_name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->take(6)
            ->map(fn ($name) => trim((string) $name))
            ->implode(', ');

        $items = CareerRecommendation::query()
            ->with('careerPath')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (CareerRecommendation $rec) => $this->mapRecommendation($rec, $defaultSubjectsText))
            ->filter()
            ->values();

        return response()->json($items);
    }

    public function analyze(Request $request): JsonResponse
    {
        $user = $request->user();
        $topSubjects = $this->topSubjects($user->id);
        $weakSubjects = $this->weakSubjects($user->id, collect($topSubjects)->pluck('id')->all());
        $latestQuiz = $this->latestQuizAttempt($user->id);
        $latestQuizAnalysis = $this->latestQuizAnalysis($user->id);
        $subjectProfiles = $this->buildSubjectProfiles($topSubjects);

        if ($subjectProfiles === []) {
            $this->clearRecommendations($user->id);

            return response()->json([
                'top_subjects' => [],
                'weak_subjects' => [],
                'latest_quiz' => $latestQuiz,
                'latest_quiz_analysis' => $latestQuizAnalysis,
                'recommendations' => [],
                'message' => 'ยังไม่มีคะแนนแบบฝึกหัดเพียงพอ กรุณาทำแบบฝึกหัดก่อนเพื่อให้ระบบวิเคราะห์อาชีพได้',
            ]);
        }

        $recommendations = [];
        $message = null;

        try {
            $recommendations = $this->aiService->generateCareerRecommendations($user, $subjectProfiles, [
                'latest_quiz' => $latestQuiz,
                'latest_quiz_analysis' => $latestQuizAnalysis,
                'weak_subjects' => $weakSubjects->values()->all(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Career AI analysis failed.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $message = $e->getMessage() ?: 'ไม่สามารถวิเคราะห์อาชีพด้วย AI ได้ในขณะนี้';
        }

        if ($recommendations === []) {
            $this->clearRecommendations($user->id);
            $message = $message ?: 'AI ยังไม่สามารถวิเคราะห์อาชีพจากคะแนนแบบฝึกหัดจริงได้ หรือข้อมูลคะแนนยังไม่เพียงพอ';
        } else {
            $this->storeRecommendations($user, $topSubjects, $recommendations);
        }

        return response()->json([
            'top_subjects' => $topSubjects->values(),
            'weak_subjects' => $weakSubjects->values(),
            'latest_quiz' => $latestQuiz,
            'latest_quiz_analysis' => $latestQuizAnalysis,
            'recommendations' => $recommendations,
            'message' => $message,
        ]);
    }

    private function topSubjects(int $userId): Collection
    {
        $stats = $this->subjectStats($userId);
        $highestLatestQuizScore = (float) ($stats->max('latest_quiz_score') ?? 0);

        return $stats
            ->filter(fn (array $row) => (int) ($row['quiz_attempt_count'] ?? 0) > 0)
            ->map(function (array $row) use ($highestLatestQuizScore) {
                $latestQuiz = (float) ($row['latest_quiz_score'] ?? 0);
                $avgQuiz = (float) ($row['avg_quiz_score'] ?? 0);
                $maxQuiz = (float) ($row['max_quiz_score'] ?? 0);
                $attempts = (int) ($row['quiz_attempt_count'] ?? 0);
                $passedCount = (int) ($row['passed_count'] ?? 0);
                $passRatePercent = $attempts > 0 ? ($passedCount / $attempts) * 100 : 0;
                $isLatestTop = $latestQuiz > 0 && $highestLatestQuizScore > 0 && abs($latestQuiz - $highestLatestQuizScore) < 0.001;

                $score = ($avgQuiz * 0.45)
                    + ($latestQuiz * 0.30)
                    + ($maxQuiz * 0.15)
                    + ($passRatePercent * 0.10)
                    + min(5, $attempts);

                $row['strength_score'] = round($score, 3);
                $row['is_latest_top_score'] = $isLatestTop;
                $row['pass_rate'] = round($passRatePercent, 1);
                $row['study_hours'] = 0.0;

                return $row;
            })
            ->sortByDesc('strength_score')
            ->values()
            ->take(5)
            ->map(function (array $row) {
                unset($row['strength_score']);
                return $row;
            });
    }

    /**
     * @param  array<int,int>  $excludeSubjectIds
     */
    private function weakSubjects(int $userId, array $excludeSubjectIds = []): Collection
    {
        $stats = $this->subjectStats($userId)
            ->filter(fn (array $row) => (int) ($row['quiz_attempt_count'] ?? 0) > 0);

        if ($excludeSubjectIds !== []) {
            $stats = $stats->reject(fn (array $row) => in_array((int) ($row['id'] ?? 0), $excludeSubjectIds, true))->values();
        }

        return $stats
            ->map(function (array $row) {
                $attempts = (int) ($row['quiz_attempt_count'] ?? 0);
                $latestQuiz = (float) ($row['latest_quiz_score'] ?? 0);
                $avgQuiz = (float) ($row['avg_quiz_score'] ?? 0);
                $passedCount = (int) ($row['passed_count'] ?? 0);
                $passRatePercent = $attempts > 0 ? ($passedCount / $attempts) * 100 : 0;

                $riskScore = (100 - $avgQuiz) * 0.45
                    + (100 - $latestQuiz) * 0.35
                    + (100 - $passRatePercent) * 0.20;

                $row['weak_score'] = round($riskScore, 3);
                $row['pass_rate'] = round($passRatePercent, 1);

                return $row;
            })
            ->sortByDesc('weak_score')
            ->values()
            ->take(2)
            ->map(function (array $row) {
                $avgQuiz = (float) ($row['avg_quiz_score'] ?? 0);
                $latestQuiz = (float) ($row['latest_quiz_score'] ?? 0);
                $attempts = (int) ($row['quiz_attempt_count'] ?? 0);
                $passRate = (float) ($row['pass_rate'] ?? 0);

                $hint = 'คะแนนแบบฝึกหัดยังควรพัฒนา แนะนำทบทวนข้อที่ตอบผิดและลองทำแบบฝึกหัดซ้ำ';
                if ($latestQuiz < 50) {
                    $hint = 'คะแนนล่าสุดต่ำกว่า 50% แนะนำทวนเนื้อหาพื้นฐานและทำโจทย์เพิ่มก่อนวิเคราะห์อาชีพ';
                } elseif ($avgQuiz < 60) {
                    $hint = 'คะแนนเฉลี่ยยังต่ำกว่า 60% แนะนำฝึกเพิ่มเพื่อให้ข้อมูลวิเคราะห์อาชีพน่าเชื่อถือขึ้น';
                } elseif ($passRate < 60) {
                    $hint = 'อัตราผ่านยังไม่สูง แนะนำทำแบบฝึกหัดหลายชุดเพื่อดูความถนัดจริง';
                }

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'subject_name' => (string) ($row['subject_name'] ?? ''),
                    'avg_quiz_score' => $avgQuiz,
                    'latest_quiz_score' => $latestQuiz,
                    'quiz_attempt_count' => $attempts,
                    'pass_rate' => $passRate,
                    'hint' => $hint,
                    'next_steps' => [
                        'ทบทวนข้อที่ตอบผิดจากแบบฝึกหัดล่าสุด',
                        'ทำแบบฝึกหัดเพิ่มอย่างน้อย 1 ชุด',
                        'วิเคราะห์อาชีพอีกครั้งหลังมีคะแนนใหม่',
                    ],
                ];
            });
    }

    /**
     * รวมสถิติจากคะแนนแบบฝึกหัดจริงเท่านั้น
     *
     * @return Collection<int, array<string,mixed>>
     */
    private function subjectStats(int $userId): Collection
    {
        if (! Schema::hasTable('subjects') || ! Schema::hasTable('quizzes') || ! Schema::hasTable('quiz_attempts')) {
            return collect();
        }

        $answerCountSql = $this->answerCountSql();

        $quizAgg = DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.user_id', $userId)
            ->selectRaw('quizzes.subject_id as subject_id')
            ->selectRaw('COUNT(*) as quiz_attempt_count')
            ->selectRaw("AVG(CASE WHEN {$answerCountSql} > 0 THEN (quiz_attempts.score / {$answerCountSql}) * 100 ELSE 0 END) as avg_quiz_score")
            ->selectRaw("MAX(CASE WHEN {$answerCountSql} > 0 THEN (quiz_attempts.score / {$answerCountSql}) * 100 ELSE 0 END) as max_quiz_score")
            ->selectRaw('SUM(CASE WHEN quiz_attempts.passed = 1 THEN 1 ELSE 0 END) as passed_count')
            ->groupBy('quizzes.subject_id');

        $latestQuizIdsAgg = DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.user_id', $userId)
            ->selectRaw('quizzes.subject_id as subject_id, MAX(quiz_attempts.id) as latest_attempt_id')
            ->groupBy('quizzes.subject_id');

        $latestQuizAgg = DB::query()
            ->fromSub($latestQuizIdsAgg, 'lqid')
            ->join('quiz_attempts', 'quiz_attempts.id', '=', 'lqid.latest_attempt_id')
            ->selectRaw('lqid.subject_id as subject_id')
            ->selectRaw("CASE WHEN {$answerCountSql} > 0 THEN (quiz_attempts.score / {$answerCountSql}) * 100 ELSE 0 END as latest_quiz_score");

        $query = DB::table('subjects')
            ->where('subjects.user_id', $userId)
            ->leftJoinSub($quizAgg, 'qa', 'qa.subject_id', '=', 'subjects.id')
            ->leftJoinSub($latestQuizAgg, 'lqa', 'lqa.subject_id', '=', 'subjects.id')
            ->selectRaw('subjects.id, subjects.name as subject_name')
            ->selectRaw('COALESCE(qa.quiz_attempt_count, 0) as quiz_attempt_count')
            ->selectRaw('COALESCE(qa.avg_quiz_score, 0) as avg_quiz_score')
            ->selectRaw('COALESCE(qa.max_quiz_score, 0) as max_quiz_score')
            ->selectRaw('COALESCE(qa.passed_count, 0) as passed_count')
            ->selectRaw('COALESCE(lqa.latest_quiz_score, 0) as latest_quiz_score');

        try {
            return collect($query->get())->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'subject_name' => (string) ($row->subject_name ?? ''),
                    'quiz_attempt_count' => (int) ($row->quiz_attempt_count ?? 0),
                    'avg_quiz_score' => round((float) ($row->avg_quiz_score ?? 0), 1),
                    'max_quiz_score' => round((float) ($row->max_quiz_score ?? 0), 1),
                    'latest_quiz_score' => round((float) ($row->latest_quiz_score ?? 0), 1),
                    'passed_count' => (int) ($row->passed_count ?? 0),
                ];
            })->values();
        } catch (\Throwable $e) {
            Log::warning('Failed to load career subject quiz stats.', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * @param  Collection<int, array<string,mixed>>  $topSubjects
     * @return array<int, array<string,mixed>>
     */
    private function buildSubjectProfiles(Collection $topSubjects): array
    {
        return $topSubjects
            ->filter(fn (array $subject) => (int) ($subject['quiz_attempt_count'] ?? 0) > 0)
            ->map(function (array $subject) {
                $attempts = (int) ($subject['quiz_attempt_count'] ?? 0);
                $passedCount = (int) ($subject['passed_count'] ?? 0);
                $avgScore = (float) ($subject['avg_quiz_score'] ?? 0);
                $latestScore = (float) ($subject['latest_quiz_score'] ?? 0);
                $maxScore = (float) ($subject['max_quiz_score'] ?? 0);
                $passRate = $attempts > 0 ? round(($passedCount / $attempts) * 100, 1) : 0.0;

                return [
                    'id' => (int) $subject['id'],
                    'name' => (string) $subject['subject_name'],
                    'quiz_attempt_count' => $attempts,
                    'avg_quiz_score' => $avgScore,
                    'latest_quiz_score' => $latestScore,
                    'max_quiz_score' => $maxScore,
                    'passed_count' => $passedCount,
                    'pass_rate' => $passRate,
                    'skill_level' => $avgScore >= 80
                        ? 'ถนัดมากจากคะแนนแบบฝึกหัด'
                        : ($avgScore >= 60 ? 'มีพื้นฐานจากคะแนนแบบฝึกหัด' : 'ควรพัฒนาจากคะแนนแบบฝึกหัด'),
                ];
            })
            ->values()
            ->all();
    }

    private function latestQuizAttempt(int $userId): ?array
    {
        if (! Schema::hasTable('quiz_attempts') || ! Schema::hasTable('quizzes') || ! Schema::hasTable('subjects')) {
            return null;
        }

        $row = DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('subjects', 'subjects.id', '=', 'quizzes.subject_id')
            ->where('quiz_attempts.user_id', $userId)
            ->orderByDesc('quiz_attempts.id')
            ->selectRaw('quiz_attempts.score, quiz_attempts.passed, quiz_attempts.answers, quiz_attempts.created_at')
            ->selectRaw('quizzes.title as quiz_title')
            ->selectRaw('subjects.name as subject_name')
            ->first();

        if (! $row) {
            return null;
        }

        $answers = json_decode((string) ($row->answers ?? '[]'), true);
        $total = is_array($answers) ? count($answers) : 0;
        $score = (int) ($row->score ?? 0);
        $percentage = $total > 0 ? (int) round(($score / $total) * 100) : 0;

        return [
            'quiz_title' => (string) ($row->quiz_title ?? ''),
            'subject_name' => (string) ($row->subject_name ?? ''),
            'score' => $score,
            'total' => $total,
            'percentage' => $percentage,
            'passed' => (bool) ($row->passed ?? false),
            'created_at' => (string) ($row->created_at ?? ''),
        ];
    }

    private function latestQuizAnalysis(int $userId): ?array
    {
        if (! Schema::hasTable('quiz_attempts') || ! Schema::hasTable('quiz_questions') || ! Schema::hasTable('quizzes')) {
            return null;
        }

        $attempt = DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.user_id', $userId)
            ->orderByDesc('quiz_attempts.id')
            ->select('quiz_attempts.answers', 'quiz_attempts.score', 'quizzes.title as quiz_title')
            ->first();

        if (! $attempt) {
            return null;
        }

        $answerRows = json_decode((string) ($attempt->answers ?? '[]'), true);
        if (! is_array($answerRows) || $answerRows === []) {
            return null;
        }

        $questionIds = collect($answerRows)->pluck('question_id')->filter()->map(fn ($id) => (int) $id)->all();
        if ($questionIds === []) {
            return null;
        }

        $questions = DB::table('quiz_questions')
            ->whereIn('id', $questionIds)
            ->select('id', 'question_text', 'correct_answer')
            ->get()
            ->keyBy('id');

        $weakPoints = [];
        $wrongCount = 0;
        foreach ($answerRows as $row) {
            $qid = (int) ($row['question_id'] ?? 0);
            $selected = trim((string) ($row['selected_answer'] ?? ''));
            $q = $questions->get($qid);
            if (! $q) {
                continue;
            }
            $correct = trim((string) ($q->correct_answer ?? ''));
            if ($selected === '' || mb_strtolower($selected, 'UTF-8') !== mb_strtolower($correct, 'UTF-8')) {
                $wrongCount++;
                $weakPoints[] = Str::limit(trim((string) ($q->question_text ?? '')), 120, '...');
            }
        }

        $total = count($answerRows);
        if ($total <= 0) {
            return null;
        }

        $percentage = (int) round((((int) ($attempt->score ?? 0)) / $total) * 100);
        $performance = $percentage >= 80 ? 'ดีมาก' : ($percentage >= 60 ? 'ปานกลาง' : 'ควรปรับปรุง');

        return [
            'quiz_title' => (string) ($attempt->quiz_title ?? ''),
            'performance' => $performance,
            'score_percent' => $percentage,
            'wrong_count' => $wrongCount,
            'total' => $total,
            'weak_points' => array_values(array_slice(array_unique($weakPoints), 0, 6)),
        ];
    }

    /**
     * @param  Collection<int, array<string,mixed>>  $topSubjects
     * @param  array<int, array<string,mixed>>  $recommendations
     */
    private function storeRecommendations(User $user, Collection $topSubjects, array $recommendations): void
    {
        if (! Schema::hasTable('career_recommendations')) {
            return;
        }

        $columns = Schema::getColumnListing('career_recommendations');
        $hasCareerColumn = in_array('career', $columns, true);
        $hasCareerPathId = in_array('career_path_id', $columns, true);
        $hasCareerPathsTable = Schema::hasTable('career_paths');

        CareerRecommendation::query()->where('user_id', $user->id)->delete();

        $subjectNameMap = collect($topSubjects)
            ->filter(fn (array $subject) => ! empty($subject['subject_name']))
            ->mapWithKeys(fn (array $subject) => [Str::lower($subject['subject_name']) => $subject['id']]);

        foreach ($recommendations as $recommendation) {
            $careerName = trim((string) ($recommendation['career'] ?? ''));
            $subjectsText = trim((string) ($recommendation['subjects'] ?? ''));
            $skillsText = trim((string) ($recommendation['skills'] ?? ''));
            $reasonText = trim((string) ($recommendation['reason'] ?? ''));

            if ($careerName === '' || $subjectsText === '' || $skillsText === '' || $reasonText === '') {
                continue;
            }

            $subjectId = $this->matchSubjectId($subjectNameMap, $subjectsText);
            if ($subjectId === null) {
                continue;
            }

            $payload = [
                'user_id' => $user->id,
                'subject_id' => $subjectId,
                'score' => max(0, min(100, (float) ($recommendation['score'] ?? 0))),
                'reason' => $this->fitDbVarchar($reasonText, 255),
                'metadata' => $this->normalizeRecommendationMetadata($recommendation),
            ];

            if ($hasCareerColumn) {
                $payload['career'] = $this->fitDbVarchar($careerName, 255);
            }

            if ($hasCareerPathId && $hasCareerPathsTable) {
                $careerPath = CareerPath::firstOrCreate(
                    ['name' => $careerName],
                    ['description' => $reasonText]
                );
                $payload['career_path_id'] = $careerPath->id;
            }

            try {
                CareerRecommendation::create($payload);
            } catch (\Throwable $exception) {
                Log::warning('Skipping career recommendation persistence after database error.', [
                    'user_id' => $user->id,
                    'career' => $careerName,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function clearRecommendations(int $userId): void
    {
        if (Schema::hasTable('career_recommendations')) {
            CareerRecommendation::query()->where('user_id', $userId)->delete();
        }
    }

    /**
     * @param  Collection<string, int>  $subjectNameMap
     */
    private function matchSubjectId(Collection $subjectNameMap, string $subjectsText): ?int
    {
        if ($subjectsText === '') {
            return null;
        }

        $haystack = Str::lower($subjectsText);
        foreach ($subjectNameMap as $subjectName => $subjectId) {
            if ($subjectName !== '' && Str::contains($haystack, $subjectName)) {
                return (int) $subjectId;
            }
        }

        return null;
    }

    private function mapRecommendation(CareerRecommendation $rec, string $defaultSubjectsText): ?array
    {
        $career = trim((string) ($rec->career ?? ''));
        if ($career === '') {
            $career = trim((string) ($rec->careerPath?->name ?? ''));
        }

        $metadata = $this->decodeMetadata($rec);
        if ($career === '' && is_string($metadata['career'] ?? null)) {
            $career = trim((string) $metadata['career']);
        }

        $skills = trim((string) ($metadata['skills'] ?? ''));
        $subjects = trim((string) ($metadata['subjects'] ?? ''));

        if ($career === '' || $skills === '') {
            return null;
        }

        return [
            'id' => $rec->id,
            'career' => $career,
            'skills' => $skills,
            'subjects' => $subjects !== '' ? $subjects : $defaultSubjectsText,
            'score' => $rec->score ?? 0,
            'reason' => $rec->reason,
            'created_at' => optional($rec->created_at)->toDateTimeString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMetadata(CareerRecommendation $rec): array
    {
        $rawMetadata = $rec->getRawOriginal('metadata');
        if (is_string($rawMetadata) && trim($rawMetadata) !== '') {
            $decoded = json_decode($rawMetadata, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($rec->metadata) ? $rec->metadata : [];
    }

    private function normalizeRecommendationMetadata(array $recommendation): ?array
    {
        $skills = trim((string) ($recommendation['skills'] ?? ''));
        $subjects = trim((string) ($recommendation['subjects'] ?? ''));
        if ($skills === '' || $subjects === '') {
            return null;
        }

        $metadata = [
            'skills' => $this->fitDbVarchar($skills, 80),
            'subjects' => $this->fitDbVarchar($subjects, 80),
        ];

        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && strlen($encoded) <= 240) {
            return $metadata;
        }

        $skillsLimit = 72;
        $subjectsLimit = 72;
        for ($i = 0; $i < 18; $i++) {
            $metadata['skills'] = $this->fitDbVarchar($skills, $skillsLimit);
            $metadata['subjects'] = $this->fitDbVarchar($subjects, $subjectsLimit);
            $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE);
            if ($encoded !== false && strlen($encoded) <= 240) {
                return $metadata;
            }
            $skillsLimit = max(12, $skillsLimit - 6);
            $subjectsLimit = max(12, $subjectsLimit - 6);
        }

        return null;
    }

    private function fitDbVarchar(string $value, int $maxBytes): ?string
    {
        $text = trim($value);
        if ($text === '') {
            return null;
        }

        if (strlen($text) <= $maxBytes) {
            return $text;
        }

        $limit = max(4, $maxBytes - 3);
        $cut = mb_strcut($text, 0, $limit, 'UTF-8');

        return rtrim($cut).'...';
    }

    private function answerCountSql(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "json_array_length(quiz_attempts.answers)",
            'pgsql' => "jsonb_array_length(quiz_attempts.answers::jsonb)",
            default => "JSON_LENGTH(quiz_attempts.answers)",
        };
    }
}
