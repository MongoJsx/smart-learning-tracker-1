<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LearningGoalTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GoalController extends Controller
{
    private ?array $goalTableColumns = null;

    public function summary(): JsonResponse
    {
        $user = request()->user();
        $timezone = config('app.timezone', 'Asia/Bangkok');
        $now = Carbon::now($timezone);
        $subjectId = request()->integer('subject_id');
        $semesterId = request()->integer('semester_id');
        $context = $this->resolveQuestContext($user, $subjectId, $semesterId);

        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY);

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        return response()->json([
            'today' => $this->buildSummary($user, $todayStart, $todayEnd, 'daily', $context),
            'week' => $this->buildSummary($user, $weekStart, $weekEnd, 'weekly', $context),
            'month' => $this->buildSummary($user, $monthStart, $monthEnd, 'monthly', $context),
        ]);
    }

    public function upsertTarget(): JsonResponse
    {
        // Log incoming request for debugging when clients report failures
        try {
            Log::info('upsertTarget request', request()->all());
        } catch (\Throwable $e) {
            // ignore logging failures
        }

        $user = request()->user();
        $timezone = config('app.timezone', 'Asia/Bangkok');
        $now = Carbon::now($timezone);

        $data = request()->validate([
            'period_type' => ['required', 'in:daily,weekly,monthly'],
            'target_quiz_sets' => ['nullable', 'integer', 'min:1', 'max:999', 'required_without:target_sessions'],
            'target_sessions' => ['nullable', 'integer', 'min:1', 'max:999'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'semester_id' => ['nullable', 'integer', 'min:1'],
        ]);

        // Normalize legacy/alternate field name so the rest of the method can use `target_quiz_sets`.
        if (! isset($data['target_quiz_sets']) && isset($data['target_sessions'])) {
            $data['target_quiz_sets'] = $data['target_sessions'];
        }

        // extra logging to help debug persistence issues
        try {
            Log::info('upsertTarget debug', [
                'user_id' => $user?->id ?? null,
                'period_type' => $data['period_type'] ?? null,
                'target_quiz_sets' => $data['target_quiz_sets'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
        }

        [$start, $end] = $this->resolvePeriodBounds($data['period_type'], $now);
        $context = $this->resolveQuestContext(
            $user,
            isset($data['subject_id']) ? (int) $data['subject_id'] : null,
            isset($data['semester_id']) ? (int) $data['semester_id'] : null
        );

        $goal = null;
        if (Schema::hasTable((new LearningGoalTarget())->getTable())) {
            $query = LearningGoalTarget::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('period_type', $data['period_type'])
                ->where('period_start', $start->toDateTimeString());

            if ($this->goalTableHasColumn('subject_id')) {
                if (isset($data['subject_id'])) {
                    $query->where('subject_id', (int) $data['subject_id']);
                } else {
                    $query->whereNull('subject_id');
                }
            }

            if ($this->goalTableHasColumn('schedule_id')) {
                $query->whereNull('schedule_id');
            }

            $goal = $query->first();
        }

        $goalData = $this->extractQuizGoalData($goal, $data['period_type'], $context);
        $goalData['quest_type'] = 'quiz_mastery';
        $goalData['target_quiz_sets'] = (int) ($data['target_quiz_sets'] ?? 0);
        $goalData['target_questions'] = 0;
        $goalData['target_value'] = (int) $goalData['target_quiz_sets'];
        $goalData['title'] = $this->buildQuizCountTitle($data['period_type'], $context);
        $goalData['description'] = $this->buildQuizCountDescription($data['period_type'], $goalData['target_quiz_sets'], $context);
        $goalData['filter_subject_id'] = isset($data['subject_id']) ? (int) $data['subject_id'] : null;
        $goalData['filter_semester_id'] = isset($data['semester_id']) ? (int) $data['semester_id'] : null;

        $goalStatus = ['status' => 'not_achieved', 'achieved' => false, 'current_value' => 0, 'progress_percent' => 0];
        $questProgress = ['current_value' => 0, 'progress_percent' => 0];

        $saved = $this->persistQuestState(
            $user,
            $start,
            $end,
            $data['period_type'],
            $goal,
            $goalData,
            $goalStatus,
            $questProgress
        );

        return response()->json([
            'message' => 'อัปเดตเป้าหมายแบบทดสอบเรียบร้อยแล้ว',
            'goal' => $saved,
        ]);
    }

    public function resetTarget(): JsonResponse
    {
        $user = request()->user();
        $timezone = config('app.timezone', 'Asia/Bangkok');
        $now = Carbon::now($timezone);

        $data = request()->validate([
            'period_type' => ['required', 'in:daily,weekly,monthly'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'semester_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (!Schema::hasTable((new LearningGoalTarget())->getTable())) {
            return response()->json(['message' => 'รีเซ็ตเป้าหมายเรียบร้อยแล้ว']);
        }

        [$start] = $this->resolvePeriodBounds($data['period_type'], $now);

        LearningGoalTarget::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('period_type', $data['period_type'])
            ->where('period_start', $start->toDateTimeString())
            ->when(
                $this->goalTableHasColumn('subject_id'),
                fn ($query) => isset($data['subject_id'])
                    ? $query->where('subject_id', (int) $data['subject_id'])
                    : $query->whereNull('subject_id')
            )
            ->when(
                $this->goalTableHasColumn('schedule_id'),
                fn ($query) => $query->whereNull('schedule_id')
            )
            ->delete();

        return response()->json([
            'message' => 'รีเซ็ตเป้าหมายเรียบร้อยแล้ว',
        ]);
    }

    private function buildSummary(User $user, Carbon $start, Carbon $end, string $periodType, array $context): array
    {
        $goal = null;
        if (Schema::hasTable((new LearningGoalTarget())->getTable())) {
            $goal = LearningGoalTarget::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('period_type', $periodType)
                ->where('period_start', $start->toDateTimeString())
                ->when(
                    $this->goalTableHasColumn('subject_id'),
                    fn ($query) => !empty($context['filter_subject_id'])
                        ? $query->where('subject_id', (int) $context['filter_subject_id'])
                        : $query->whereNull('subject_id')
                )
                ->when(
                    $this->goalTableHasColumn('schedule_id'),
                    fn ($query) => $query->whereNull('schedule_id')
                )
                ->first();
        }

        $goalData = $this->extractQuizGoalData($goal, $periodType, $context);

        if (! Schema::hasTable('quiz_attempts') || ! Schema::hasTable('quizzes') || ! Schema::hasTable('subjects')) {
            return $this->emptySummary($start, $end, $periodType, $goalData);
        }

        $baseQuery = DB::table('quiz_attempts')
            ->join('quizzes', 'quiz_attempts.quiz_id', '=', 'quizzes.id')
            ->join('subjects', 'quizzes.subject_id', '=', 'subjects.id')
            ->where('quiz_attempts.user_id', $user->id)
            ->where('subjects.user_id', $user->id)
            ->whereBetween('quiz_attempts.created_at', [$start->toDateTimeString(), $end->toDateTimeString()]);

        if (!empty($context['filter_subject_id'])) {
            $baseQuery->where('subjects.id', (int) $context['filter_subject_id']);
        } elseif (!empty($context['filter_semester_id'])) {
            $baseQuery->where('subjects.semester_id', (int) $context['filter_semester_id']);
        }

        $totals = (clone $baseQuery)
            ->selectRaw('COUNT(*) as quiz_sets, COALESCE(SUM(JSON_LENGTH(quiz_attempts.answers)), 0) as question_count')
            ->first();

        $subjects = (clone $baseQuery)
            ->selectRaw('subjects.id as subject_id, subjects.name as subject_name, COUNT(*) as quiz_sets, COALESCE(SUM(JSON_LENGTH(quiz_attempts.answers)), 0) as question_count')
            ->groupBy('subjects.id', 'subjects.name')
            ->orderByDesc('question_count')
            ->get();

        $currentQuestions = (int) ($totals->question_count ?? 0);
        $currentQuizSets = (int) ($totals->quiz_sets ?? 0);
        $goalStatus = $this->resolveQuizGoalStatus($currentQuestions, $goalData['target_questions'], $currentQuizSets, $goalData['target_quiz_sets']);
        $questProgress = $this->resolveQuestProgress($goalData['quest_type'], $currentQuestions, $currentQuizSets, $goalData['target_value']);

        $goal = $this->persistQuestState(
            $user,
            $start,
            $end,
            $periodType,
            $goal,
            $goalData,
            $goalStatus,
            $questProgress
        );

        return [
            'period_type' => $periodType,
            'period_start' => $start->toIso8601String(),
            'period_end' => $end->toIso8601String(),
            'total_sessions' => $currentQuizSets,
            'total_minutes' => 0,
            'total_quiz_sets' => $currentQuizSets,
            'total_questions' => $currentQuestions,
            'subjects' => $subjects,
            'goal' => [
                'goal_type' => 'quiz_practice',
                'target_value' => $goalData['target_value'],
                'current_value' => $questProgress['current_value'],
                'target_sessions' => $goalData['target_quiz_sets'],
                'target_minutes' => null,
                'target_questions' => 0,
                'target_quiz_sets' => $goalData['target_quiz_sets'],
                'current_questions' => $currentQuestions,
                'current_quiz_sets' => $currentQuizSets,
                'progress_percent' => $goalStatus['progress_percent'],
                'status' => $goalStatus['status'],
                'achieved' => $goalStatus['achieved'],
            ],
            'quest' => [
                'id' => $goal?->id,
                'quest_type' => $goalData['quest_type'],
                'title' => $goalData['title'],
                'description' => $goalData['description'],
                'reward_points' => $goalData['reward_points'],
                'status' => $goalStatus['achieved'] ? 'completed' : 'pending',
                'target_value' => $goalData['target_value'],
                'current_value' => $questProgress['current_value'],
                'progress_percent' => $questProgress['progress_percent'],
                'focus_subject_id' => $goalData['focus_subject_id'],
                'focus_subject_name' => $goalData['focus_subject_name'],
                'focus_lesson_title' => $goalData['focus_lesson_title'],
                'focus_quiz_title' => $goalData['focus_quiz_title'],
                'celebration_message' => $goalStatus['achieved']
                    ? sprintf('ภารกิจสำเร็จแล้ว +%d แต้ม', $goalData['reward_points'])
                    : 'ลุยต่ออีกนิด ภารกิจนี้ใกล้สำเร็จแล้ว',
                'cta_path' => '/quizzes',
            ],
        ];
    }

    private function resolveQuizGoalStatus(
        int $currentQuestions,
        int $questionTarget,
        int $currentQuizSets,
        int $quizSetTarget
    ): array {
        if ($questionTarget <= 0 && $quizSetTarget <= 0) {
            return [
                'status' => 'not_set',
                'achieved' => false,
                'current_value' => $currentQuestions,
                'progress_percent' => 0,
            ];
        }

        $questionProgress = $questionTarget > 0 ? (int) round(($currentQuestions / $questionTarget) * 100) : 0;
        $quizSetProgress = $quizSetTarget > 0 ? (int) round(($currentQuizSets / $quizSetTarget) * 100) : 0;
        $achieved = ($questionTarget > 0 && $currentQuestions >= $questionTarget)
            || ($quizSetTarget > 0 && $currentQuizSets >= $quizSetTarget);

        return [
            'status' => $achieved ? 'achieved' : 'not_achieved',
            'achieved' => $achieved,
            'current_value' => $currentQuestions,
            'progress_percent' => max(0, min(100, max($questionProgress, $quizSetProgress))),
        ];
    }

    private function emptySummary(Carbon $start, Carbon $end, string $periodType, array $goalData): array
    {
        return [
            'period_type' => $periodType,
            'period_start' => $start->toIso8601String(),
            'period_end' => $end->toIso8601String(),
            'total_sessions' => 0,
            'total_minutes' => 0,
            'total_quiz_sets' => 0,
            'total_questions' => 0,
            'subjects' => [],
            'goal' => [
                'goal_type' => 'quiz_practice',
                'target_value' => $goalData['target_value'],
                'current_value' => 0,
                'target_sessions' => $goalData['target_quiz_sets'],
                'target_minutes' => null,
                'target_questions' => 0,
                'target_quiz_sets' => $goalData['target_quiz_sets'],
                'current_questions' => 0,
                'current_quiz_sets' => 0,
                'progress_percent' => 0,
                'status' => 'not_achieved',
                'achieved' => false,
            ],
            'quest' => [
                'id' => null,
                'quest_type' => $goalData['quest_type'],
                'title' => $goalData['title'],
                'description' => $goalData['description'],
                'reward_points' => $goalData['reward_points'],
                'status' => 'pending',
                'target_value' => $goalData['target_value'],
                'current_value' => 0,
                'progress_percent' => 0,
                'focus_subject_id' => $goalData['focus_subject_id'],
                'focus_subject_name' => $goalData['focus_subject_name'],
                'focus_lesson_title' => $goalData['focus_lesson_title'],
                'focus_quiz_title' => $goalData['focus_quiz_title'],
                'celebration_message' => sprintf('ภารกิจสำเร็จแล้ว +%d แต้ม', $goalData['reward_points']),
                'cta_path' => '/quizzes',
            ],
        ];
    }

    private function resolveQuestContext(User $user, ?int $subjectId = null, ?int $semesterId = null): array
    {
        $fallback = [
            'focus_subject_id' => null,
            'focus_subject_name' => null,
            'focus_quiz_title' => null,
            'focus_lesson_title' => null,
            'filter_subject_id' => $subjectId,
            'filter_semester_id' => $semesterId,
        ];

        if (! Schema::hasTable('subjects')) {
            return $fallback;
        }

        $latestQuiz = null;
        if (Schema::hasTable('quizzes')) {
            $latestQuiz = DB::table('quizzes')
                ->join('subjects', 'quizzes.subject_id', '=', 'subjects.id')
                ->where('subjects.user_id', $user->id)
                ->when($subjectId, fn ($query) => $query->where('subjects.id', $subjectId))
                ->when(!$subjectId && $semesterId, fn ($query) => $query->where('subjects.semester_id', $semesterId))
                ->select(
                    'subjects.id as subject_id',
                    'subjects.name as subject_name',
                    'quizzes.title as quiz_title'
                )
                ->orderByDesc('quizzes.updated_at')
                ->orderByDesc('quizzes.id')
                ->first();
        }

        if ($latestQuiz) {
            $fallback = [
                'focus_subject_id' => (int) $latestQuiz->subject_id,
                'focus_subject_name' => (string) $latestQuiz->subject_name,
                'focus_quiz_title' => (string) $latestQuiz->quiz_title,
                'focus_lesson_title' => null,
                'filter_subject_id' => $subjectId,
                'filter_semester_id' => $semesterId,
            ];
        }

        if (! Schema::hasTable('lessons') || empty($fallback['focus_subject_id'])) {
            return $fallback;
        }

        $latestLesson = DB::table('lessons')
            ->where('subject_id', $fallback['focus_subject_id'])
            ->select('title')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($latestLesson && is_string($latestLesson->title) && trim($latestLesson->title) !== '') {
            $fallback['focus_lesson_title'] = trim($latestLesson->title);
        }

        return $fallback;
    }

    private function extractQuizGoalData(?LearningGoalTarget $goal, string $periodType, array $context): array
    {
        $defaults = match ($periodType) {
            'daily' => ['questions' => 0, 'quiz_sets' => 0, 'reward_points' => 15],
            'weekly' => ['questions' => 0, 'quiz_sets' => 0, 'reward_points' => 40],
            'monthly' => ['questions' => 0, 'quiz_sets' => 0, 'reward_points' => 120],
            default => ['questions' => 0, 'quiz_sets' => 0, 'reward_points' => 15],
        };

        $metadata = is_array($goal?->metadata) ? $goal->metadata : [];
        $goalSubjectId = (int) ($goal?->subject_id ?? $metadata['filter_subject_id'] ?? 0);
        $contextSubjectId = (int) ($context['filter_subject_id'] ?? 0);
        $goalSemesterId = (int) ($metadata['filter_semester_id'] ?? 0);
        $contextSemesterId = (int) ($context['filter_semester_id'] ?? 0);
        $sameSubjectFilter = $goalSubjectId === $contextSubjectId;
        $sameSemesterFilter = $contextSubjectId > 0 ? true : $goalSemesterId === $contextSemesterId;
        $sameFilter = $sameSubjectFilter && $sameSemesterFilter;
        $titleAndDescription = $this->buildQuestTitleAndDescription($periodType, $context);

        $targetQuestions = $sameFilter ? $this->readGoalInt($goal, ['target_minutes', 'target_questions'], 0) : 0;
        $targetQuizSets = $sameFilter ? $this->readGoalInt($goal, ['target_sessions', 'target_quiz_sets'], 0) : 0;
        $targetValue = $sameFilter ? $this->readGoalInt($goal, ['target_value'], 0) : 0;
        $rewardPoints = $sameFilter ? $this->readGoalInt($goal, ['reward_points'], 0) : 0;

        $questType = $sameFilter ? ($this->readGoalString($goal, ['quest_type']) ?: 'quiz_mastery') : 'quiz_mastery';
        if ($targetValue <= 0) {
            $targetValue = $questType === 'quiz_mastery' ? $targetQuizSets : $targetQuestions;
        }

        if ($rewardPoints <= 0) {
            $rewardPoints = $defaults['reward_points'];
        }

        return [
            'quest_type' => $questType,
            'title' => $this->readGoalString($goal, ['title']) ?: $titleAndDescription['title'],
            'description' => $titleAndDescription['description'],
            'target_value' => $targetValue,
            'target_questions' => $targetQuestions,
            'target_quiz_sets' => $targetQuizSets,
            'reward_points' => $rewardPoints,
            'focus_subject_id' => $context['focus_subject_id'],
            'focus_subject_name' => $context['focus_subject_name'],
            'focus_lesson_title' => $context['focus_lesson_title'],
            'focus_quiz_title' => $context['focus_quiz_title'],
            'filter_subject_id' => $context['filter_subject_id'],
            'filter_semester_id' => $context['filter_semester_id'],
        ];
    }

    private function buildQuestTitleAndDescription(string $periodType, array $context): array
    {
        $subjectName = $context['focus_subject_name'] ?: 'วิชาที่กำลังเรียน';
        $lessonTitle = $context['focus_lesson_title'] ?: null;
        $quizTitle = $context['focus_quiz_title'] ?: null;

        return match ($periodType) {
            'daily' => [
                'title' => $lessonTitle
                    ? "พิชิตบทเรียน {$lessonTitle}"
                    : ($quizTitle ? "ผ่านควิซ {$quizTitle}" : "Daily Quest ของ {$subjectName}"),
                'description' => $lessonTitle
                    ? "ทำแบบทดสอบของ {$subjectName} เรื่อง {$lessonTitle} ให้ผ่านเกณฑ์ภายในวันนี้"
                    : ($quizTitle
                        ? "เก็บแต้มจากแบบทดสอบ {$quizTitle} ของ {$subjectName} ให้สำเร็จวันนี้"
                        : "เคลียร์แบบฝึกหัดของ {$subjectName} อย่างน้อย 1 ชุด หรือทำครบตามจำนวนข้อวันนี้"),
            ],
            'weekly' => [
                'title' => "เป้าหมายแบบทดสอบของ {$subjectName}",
                'description' => "ทำแบบทดสอบของ {$subjectName} ให้ครบตามจำนวนที่ตั้งไว้ภายในสัปดาห์นี้",
            ],
            'monthly' => [
                'title' => "เป้าหมายแบบทดสอบของ {$subjectName}",
                'description' => "ทำแบบทดสอบของ {$subjectName} ให้ครบตามจำนวนที่ตั้งไว้ภายในเดือนนี้",
            ],
            default => [
                'title' => "Quest ของ {$subjectName}",
                'description' => "สะสมความคืบหน้าในการเรียนของ {$subjectName}",
            ],
        };
    }

    private function resolveQuestProgress(string $questType, int $currentQuestions, int $currentQuizSets, int $targetValue): array
    {
        $currentValue = $questType === 'quiz_mastery' ? $currentQuizSets : $currentQuestions;
        $progressPercent = $targetValue > 0
            ? max(0, min(100, (int) round(($currentValue / $targetValue) * 100)))
            : 0;

        return [
            'current_value' => $currentValue,
            'progress_percent' => $progressPercent,
        ];
    }

    private function persistQuestState(
        User $user,
        Carbon $start,
        Carbon $end,
        string $periodType,
        ?LearningGoalTarget $goal,
        array $goalData,
        array $goalStatus,
        array $questProgress
    ): ?LearningGoalTarget {
        if (! Schema::hasTable((new LearningGoalTarget())->getTable())) {
            return $goal;
        }

        $attributes = [
            'user_id' => $user->id,
            'period_type' => $periodType,
            'period_start' => $start->toDateTimeString(),
        ];

        if ($this->goalTableHasColumn('period_end')) {
            $attributes['period_end'] = $end->toDateTimeString();
        }

        if ($this->goalTableHasColumn('subject_id')) {
            $subjectId = $goalData['filter_subject_id'] ?? null;
            // Guard against invalid subject IDs that would violate the FK constraint.
            if ($subjectId !== null && Schema::hasTable('subjects')) {
                $subjectExists = DB::table('subjects')
                    ->where('id', (int) $subjectId)
                    ->where('user_id', $user->id)
                    ->exists();

                $attributes['subject_id'] = $subjectExists ? (int) $subjectId : null;
            } else {
                $attributes['subject_id'] = null;
            }
        }

        if ($this->goalTableHasColumn('schedule_id')) {
            $attributes['schedule_id'] = null;
        }

        $payload = [];

        if ($this->goalTableHasColumn('quest_type')) {
            $payload['quest_type'] = $goalData['quest_type'];
        }

        if ($this->goalTableHasColumn('title')) {
            $payload['title'] = $goalData['title'];
        }

        if ($this->goalTableHasColumn('target_value')) {
            $payload['target_value'] = $goalData['target_value'];
        }

        if ($this->goalTableHasColumn('current_value')) {
            $payload['current_value'] = $questProgress['current_value'];
        }

        if ($this->goalTableHasColumn('reward_points')) {
            $payload['reward_points'] = $goalData['reward_points'];
        }

        if ($this->goalTableHasColumn('status')) {
            $payload['status'] = $goalStatus['achieved'] ? 'completed' : 'pending';
        }

        if ($this->goalTableHasColumn('target_sessions')) {
            $payload['target_sessions'] = $goalData['target_quiz_sets'];
        }

        if ($this->goalTableHasColumn('target_minutes')) {
            $payload['target_minutes'] = $goalData['target_questions'];
        }

        if ($this->goalTableHasColumn('target_questions')) {
            $payload['target_questions'] = $goalData['target_questions'];
        }

        if ($this->goalTableHasColumn('target_quiz_sets')) {
            $payload['target_quiz_sets'] = $goalData['target_quiz_sets'];
        }

        if ($this->goalTableHasColumn('metadata')) {
            $payload['metadata'] = [
                'description' => $goalData['description'],
                'focus_subject_id' => $goalData['focus_subject_id'],
                'focus_subject_name' => $goalData['focus_subject_name'],
                'focus_lesson_title' => $goalData['focus_lesson_title'],
                'focus_quiz_title' => $goalData['focus_quiz_title'],
                'filter_subject_id' => $goalData['filter_subject_id'] ?? null,
                'filter_semester_id' => $goalData['filter_semester_id'] ?? null,
                'progress_percent' => $questProgress['progress_percent'],
            ];
        }

        return LearningGoalTarget::withoutGlobalScopes()->updateOrCreate(
            [
                'user_id' => $attributes['user_id'],
                'period_type' => $attributes['period_type'],
                'period_start' => $attributes['period_start'],
                'subject_id' => $attributes['subject_id'] ?? null,
                'schedule_id' => $attributes['schedule_id'] ?? null,
            ],
            array_merge($attributes, $payload)
        );
    }

    private function goalTableHasColumn(string $column): bool
    {
        if ($this->goalTableColumns === null) {
            $this->goalTableColumns = Schema::hasTable('learning_goal_targets')
                ? Schema::getColumnListing('learning_goal_targets')
                : [];
        }

        return in_array($column, $this->goalTableColumns, true);
    }

    private function readGoalInt(?LearningGoalTarget $goal, array $columns, int $fallback): int
    {
        if (! $goal) {
            return $fallback;
        }

        foreach ($columns as $column) {
            if (! $this->goalTableHasColumn($column)) {
                continue;
            }

            $value = $goal->getAttribute($column);
            if ($value !== null && $value !== '') {
                return (int) $value;
            }
        }

        return $fallback;
    }

    private function readGoalString(?LearningGoalTarget $goal, array $columns): ?string
    {
        if (! $goal) {
            return null;
        }

        foreach ($columns as $column) {
            if (! $this->goalTableHasColumn($column)) {
                continue;
            }

            $value = $goal->getAttribute($column);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function resolvePeriodBounds(string $periodType, Carbon $now): array
    {
        return match ($periodType) {
            'daily' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'weekly' => [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY)],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private function buildQuizCountTitle(string $periodType, array $context): string
    {
        $subjectName = $context['focus_subject_name'] ?: 'วิชาที่กำลังเรียน';

        return match ($periodType) {
            'daily' => "ทำแบบทดสอบของ {$subjectName}",
            'weekly' => "สะสมแบบทดสอบของ {$subjectName}",
            'monthly' => "เป้าหมายแบบทดสอบของ {$subjectName}",
            default => "แบบทดสอบของ {$subjectName}",
        };
    }

    private function buildQuizCountDescription(string $periodType, int $targetQuizSets, array $context): string
    {
        $subjectName = $context['focus_subject_name'] ?: 'วิชาที่กำลังเรียน';

        return match ($periodType) {
            'daily' => "ทำแบบทดสอบของ {$subjectName} ให้ครบ {$targetQuizSets} ชุดภายในวันนี้",
            'weekly' => "ทำแบบทดสอบของ {$subjectName} ให้ครบ {$targetQuizSets} ชุดภายในสัปดาห์นี้",
            'monthly' => "ทำแบบทดสอบของ {$subjectName} ให้ครบ {$targetQuizSets} ชุดภายในเดือนนี้",
            default => "ทำแบบทดสอบของ {$subjectName} ให้ครบ {$targetQuizSets} ชุด",
        };
    }
}
