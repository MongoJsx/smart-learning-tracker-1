<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\CareerRecommendation;
use App\Models\FileAttachment;
use App\Models\LearningNotification;
use App\Models\Lesson;
use App\Models\MoodLog;
use App\Models\Quiz;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\StudyCalendarEvent;
use App\Models\StudyLog;
use App\Models\StudyNotification;
use App\Models\Subject;
use App\Models\Summary;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Throwable;


use Illuminate\Http\Request;

class SubjectController extends Controller

{
    public function __construct()
    {
        // ✅ บังคับต้องล็อกอินด้วย token ทุก action
        $this->middleware('auth:sanctum');
    }

    public function index(): AnonymousResourceCollection
    {
        $user = request()->user();
        $includeAll = request()->boolean('include_all') || request()->boolean('all');
        $canViewAll = $includeAll && $user && $user->role === 'admin';

        $query = $canViewAll
            ? Subject::withoutGlobalScope('user')->withCount('studyLogs')
            : $user->subjects()->withCount('studyLogs');

        if ($this->canUseSemesterColumn()) {
            $query->with('semester');
        }

        if (request()->boolean('include_study_logs')) {
            $query->with(['studyLogs' => fn ($q) => $q->latest('log_date')]);
        }

        if ($canViewAll) {
            $query->orderBy('user_id')->orderByDesc('updated_at');
        } else {
            $query->orderByDesc('updated_at');
        }

        $subjects = $query->get();
        return SubjectResource::collection($subjects);
    }

    public function store(SubjectRequest $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validated();

        // If semester is enabled but client omitted it, auto-pick the current semester.
        if ($this->canUseSemesterColumn() && empty($data['semester_id'])) {
            $data['semester_id'] = $this->resolveCurrentSemesterId();
        }

        $timing = $this->resolveScheduleTiming($data);
        if (isset($timing['error'])) {
            return response()->json(['message' => $timing['error']], 422);
        }

        $subjectPayload = $this->filterSchedulePayload($data);
        $subject = $user->subjects()->create($subjectPayload);
        $this->syncSubjectCalendarEvent($subject, $user, $data, $timing);

        $subject = $subject->fresh()->loadCount('studyLogs');
        if ($this->canUseSemesterColumn()) {
            $subject->load('semester');
        }

        return response()->json(new SubjectResource($subject), 201);
    }

    public function show(Subject $subject): SubjectResource
    {
        $this->authorizeSubject($subject);
        if ($this->canUseSemesterColumn()) {
            $subject->load('semester');
        }
        return new SubjectResource($subject->loadCount('studyLogs'));
    }

    public function update(SubjectRequest $request, Subject $subject): SubjectResource|JsonResponse
    {
        $this->authorizeSubject($subject);
        $user = $request->user();
        $data = $request->validated();
        $timing = $this->resolveScheduleTiming($data);
        if (isset($timing['error'])) {
            return response()->json(['message' => $timing['error']], 422);
        }

        $subjectPayload = $this->filterSchedulePayload($data);
        $subject->update($subjectPayload);
        $this->syncSubjectCalendarEvent($subject, $user, $data, $timing);

        $subject = $subject->fresh()->loadCount('studyLogs');
        if ($this->canUseSemesterColumn()) {
            $subject->load('semester');
        }

        return new SubjectResource($subject);
    }

    public function destroy(Request $request, Subject $subject): JsonResponse
    {
        if ((int) $subject->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $this->deleteSubject($subject);

        return response()->json(['message' => 'Deleted'], 200);
    }

    public function destroyById(Request $request): JsonResponse
    {
        $user = $request->user();
        $subjectId = (int) ($request->input('subject_id') ?? $request->input('id', 0));

        if (! $user || $subjectId <= 0) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $subject = Subject::withoutGlobalScopes()
            ->where('id', $subjectId)
            ->where('user_id', $user->id)
            ->first();

        if (! $subject) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $this->deleteSubject($subject);

        return response()->json(['message' => 'Deleted'], 200);
    }

    private function authorizeSubject(Subject $subject): void
    {
        abort_unless($subject->user_id === request()->user()->id, 403, 'Unauthorized');
    }

    private function deleteSubject(Subject $subject): void
    {
        DB::transaction(function () use ($subject) {
            $subjectId = $subject->id;
            $studyLogTable = (new StudyLog())->getTable();
            $studyLogIds = collect();

            if ($this->hasTableSafe($studyLogTable)) {
                $studyLogIds = StudyLog::where('subject_id', $subjectId)->pluck('id');
            }

            if ($studyLogIds->isNotEmpty()) {
                if ($this->hasTableSafe((new FileAttachment())->getTable())) {
                    FileAttachment::whereIn('study_log_id', $studyLogIds)->delete();
                }
                if ($this->hasTableSafe((new Summary())->getTable())) {
                    Summary::whereIn('study_log_id', $studyLogIds)->delete();
                }
                if ($this->hasTableSafe((new StudyCalendarEvent())->getTable())) {
                    StudyCalendarEvent::whereIn('study_log_id', $studyLogIds)->delete();
                }
                if ($this->hasTableSafe((new LearningNotification())->getTable())) {
                    LearningNotification::whereIn('study_log_id', $studyLogIds)->delete();
                }
            }

            if ($this->hasTableSafe((new StudyCalendarEvent())->getTable())) {
                StudyCalendarEvent::where('subject_id', $subjectId)->delete();
            }
            if ($this->hasTableSafe((new LearningNotification())->getTable())) {
                LearningNotification::where('subject_id', $subjectId)->delete();
            }
            if ($this->hasTableSafe((new StudyNotification())->getTable())) {
                StudyNotification::where('subject_id', $subjectId)->delete();
            }
            if ($this->hasTableSafe((new MoodLog())->getTable())) {
                MoodLog::where('subject_id', $subjectId)->delete();
            }
            if ($this->hasTableSafe((new CareerRecommendation())->getTable())) {
                CareerRecommendation::where('subject_id', $subjectId)->delete();
            }
            if ($this->hasTableSafe((new Quiz())->getTable())) {
                Quiz::where('subject_id', $subjectId)->delete();
            }
            if ($this->hasTableSafe((new Lesson())->getTable())) {
                Lesson::where('subject_id', $subjectId)->delete();
            }
            if ($this->hasTableSafe((new Schedule())->getTable())) {
                Schedule::where('subject_id', $subjectId)->delete();
            }
            if ($this->hasTableSafe($studyLogTable)) {
                StudyLog::where('subject_id', $subjectId)->delete();
            }

            $subject->delete();
        });
    }

    private function resolveScheduleTiming(array $data): array
    {
        $startDate = $data['start_date'] ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        $startAt = null;
        $endAt = null;
        $allDay = true;

        if ($startDate) {
            $timezone = config('app.timezone', 'Asia/Bangkok');
            $startAt = $startTime
                ? Carbon::parse($startDate . ' ' . $startTime, $timezone)
                : Carbon::parse($startDate, $timezone)->startOfDay();

            if ($startTime) {
                $allDay = false;
            }

            if ($endTime) {
                if (! $startTime) {
                    return ['error' => 'ต้องระบุเวลาเริ่มก่อนเวลาเลิก'];
                }

                $endAt = Carbon::parse($startDate . ' ' . $endTime, $timezone);
                if ($endAt->lessThan($startAt)) {
                    return ['error' => 'เวลาเลิกต้องไม่ก่อนเวลาเริ่ม'];
                }
            }
        }

        return [
            'start_date' => $startDate,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'all_day' => $allDay,
        ];
    }

    private function syncSubjectCalendarEvent(Subject $subject, $user, array $data, array $timing): void
    {
        if (! $this->hasTableSafe((new StudyCalendarEvent())->getTable())) {
            return;
        }

        try {
            $eventBase = StudyCalendarEvent::where('subject_id', $subject->id)
                ->where('user_id', $user->id);

            try {
                $existingEvent = (clone $eventBase)
                    ->where('metadata->source', 'subject')
                    ->first();
            } catch (Throwable $metadataError) {
                $existingEvent = (clone $eventBase)
                    ->where('metadata', 'like', '%"source":"subject"%')
                    ->first();
            }

            if ($timing['start_date']) {
                $room = array_key_exists('room', $data) ? $data['room'] : $subject->room;
                $payload = [
                    'user_id' => $user->id,
                    'subject_id' => $subject->id,
                    'study_log_id' => $existingEvent?->study_log_id,
                    'title' => $subject->name,
                    'description' => null,
                    'start_time' => $timing['start_at'],
                    'end_time' => $timing['end_at'],
                    'status' => 'planned',
                    'metadata' => [
                        'type' => 'class',
                        'all_day' => $timing['all_day'],
                        'source' => 'subject',
                        'room' => $room,
                    ],
                ];

                if ($this->hasColumnSafe((new StudyCalendarEvent())->getTable(), 'event_type')) {
                    $payload['event_type'] = 'class';
                }

                if ($this->hasColumnSafe((new StudyCalendarEvent())->getTable(), 'room')) {
                    $payload['room'] = $room;
                }

                if ($existingEvent) {
                    $existingEvent->update($payload);
                } else {
                    StudyCalendarEvent::create($payload);
                }
            } elseif ($existingEvent) {
                $existingEvent->delete();
            }
        } catch (Throwable $eventError) {
            report($eventError);
        }
    }

    private function filterSchedulePayload(array $data): array
    {
        $table = (new Subject())->getTable();

        if (! $this->hasColumnSafe($table, 'semester_id')) {
            unset($data['semester_id']);
        } elseif (array_key_exists('semester_id', $data) && $data['semester_id'] === '') {
            $data['semester_id'] = null;
        }

        if (! $this->hasColumnSafe($table, 'start_date')) {
            unset($data['start_date']);
        }

        if (! $this->hasColumnSafe($table, 'start_time')) {
            unset($data['start_time']);
        }

        if (! $this->hasColumnSafe($table, 'end_time')) {
            unset($data['end_time']);
        }

        if (! $this->hasColumnSafe($table, 'room')) {
            unset($data['room']);
        }

        $startDateType = $this->getColumnType($table, 'start_date');
        $startTimeType = $this->getColumnType($table, 'start_time');
        $endTimeType = $this->getColumnType($table, 'end_time');

        $normalizedStartDate = $this->normalizeDateForColumn($data['start_date'] ?? null, $startDateType);
        if (array_key_exists('start_date', $data)) {
            $data['start_date'] = $normalizedStartDate;
        }

        if (array_key_exists('start_time', $data)) {
            $data['start_time'] = $this->normalizeTimeForColumn($data['start_time'], $startTimeType, $normalizedStartDate);
        }

        if (array_key_exists('end_time', $data)) {
            $data['end_time'] = $this->normalizeTimeForColumn($data['end_time'], $endTimeType, $normalizedStartDate);
        }

        return $data;
    }

    private function normalizeDateForColumn(mixed $value, ?string $columnType): ?string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return null;
        }

        $parsed = Carbon::parse($raw, config('app.timezone', 'Asia/Bangkok'));
        if ($this->isDateTimeColumn($columnType)) {
            return $parsed->startOfDay()->format('Y-m-d H:i:s');
        }

        return $parsed->format('Y-m-d');
    }

    private function normalizeTimeForColumn(mixed $value, ?string $columnType, ?string $normalizedDate): ?string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace('.', ':', $raw);
        if (! preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $normalized)) {
            return null;
        }

        [$hourRaw, $minuteRaw, $secondRaw] = array_pad(explode(':', $normalized), 3, '00');
        $hour = (int) $hourRaw;
        $minute = (int) $minuteRaw;
        $second = (int) $secondRaw;

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            return null;
        }

        $time = sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        if (! $this->isDateTimeColumn($columnType)) {
            return $time;
        }

        $datePart = $normalizedDate
            ? Carbon::parse($normalizedDate, config('app.timezone', 'Asia/Bangkok'))->format('Y-m-d')
            : Carbon::now(config('app.timezone', 'Asia/Bangkok'))->format('Y-m-d');

        return $datePart . ' ' . $time;
    }

    private function getColumnType(string $table, string $column): ?string
    {
        try {
            $row = DB::selectOne(
                'SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
                [$table, $column]
            );
            return isset($row->DATA_TYPE) ? strtolower((string) $row->DATA_TYPE) : null;
        } catch (Throwable $error) {
            return null;
        }
    }

    private function isDateTimeColumn(?string $columnType): bool
    {
        return in_array($columnType, ['datetime', 'timestamp'], true);
    }

    private function resolveCurrentSemesterId(): ?int
    {
        if (! $this->hasTableSafe((new Semester())->getTable())) {
            return null;
        }

        $timezone = config('app.timezone', 'Asia/Bangkok');
        $now = Carbon::now($timezone);
        $beYear = (int) $now->year + 543;
        $month = (int) $now->month;

        // Typical Thai academic year:
        // - Semester 1: Jun-Oct (academic_year = current BE year)
        // - Semester 2: Nov-Mar (academic_year = BE year (Nov-Dec) or previous BE year (Jan-Mar))
        // - Summer: Apr-May (academic_year = previous BE year)
        if ($month >= 6 && $month <= 10) {
            $semester = 1;
            $academicYear = $beYear;
        } elseif ($month >= 11 || $month <= 3) {
            $semester = 2;
            $academicYear = $month <= 3 ? $beYear - 1 : $beYear;
        } else {
            $semester = 3;
            $academicYear = $beYear - 1;
        }

        $record = Semester::query()->firstOrCreate([
            'semester' => $semester,
            'academic_year' => $academicYear,
        ]);

        return $record?->semester_id ? (int) $record->semester_id : null;
    }

    private function canUseSemesterColumn(): bool
    {
        return $this->hasColumnSafe((new Subject())->getTable(), 'semester_id')
            && $this->hasTableSafe((new Semester())->getTable());
    }

    private function hasTableSafe(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $exists = DB::selectOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (Throwable $error) {
            $exists = false;
        }

        $cache[$table] = $exists;
        return $exists;
    }

    private function hasColumnSafe(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $exists = DB::selectOne(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
                [$table, $column]
            ) !== null;
        } catch (Throwable $error) {
            $exists = false;
        }

        $cache[$key] = $exists;
        return $exists;
    }
}

