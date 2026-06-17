<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $includeLogs = filter_var($request->query('include_study_logs'), FILTER_VALIDATE_BOOLEAN);

        $query = Subject::query()
            ->where('user_id', $user->id)
            ->withCount('studyLogs')
            ->orderByDesc('id');

        if ($includeLogs) {
            $query->with(['studyLogs' => function ($q) {
                $q->orderByDesc('log_date');
            }]);
        }

        $subjects = $query->get();

        if ($includeLogs) {
            $subjects->transform(function ($s) {
                $arr = $s->toArray();
                $arr['study_logs'] = $s->studyLogs ? $s->studyLogs->toArray() : [];
                $arr['study_log_count'] = $s->study_logs_count ?? ($s->studyLogs?->count() ?? 0);
                unset($arr['study_logs_count']);
                unset($arr['studyLogs']);
                return $arr;
            });

            return response()->json($subjects);
        }

        $subjects = $subjects->map(function ($s) {
            $arr = $s->toArray();
            $arr['study_log_count'] = $s->study_logs_count ?? 0;
            unset($arr['study_logs_count']);
            return $arr;
        });

        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'color'        => ['nullable', 'string', 'max:12'],
            'room'         => ['nullable', 'string', 'max:255'],
            'target_hours' => ['nullable', 'integer', 'min:0'],

            // รองรับทั้งฐานข้อมูลเก่า/ใหม่ (date,time หรือ datetime)
            'start_date'   => ['nullable', 'string'],
            'start_time'   => ['nullable', 'string'],
            'end_time'     => ['nullable', 'string'],
        ]);
        $data = $this->normalizeScheduleFields($data);

        $subject = Subject::create([
            'user_id' => $request->user()->id,
            ...$data,
        ]);

        // สร้างบันทึกแรกเพื่อให้ปฏิทิน/overview เห็นทันที (ถ้าต้องการ)
        if (!empty($data['start_date'])) {
            $subject->studyLogs()->create([
                'user_id'          => $request->user()->id,
                'title'            => 'บันทึก: ' . $subject->name,
                'note'             => null,
                'duration_minutes' => null,
                'log_date'         => $data['start_date'],
                'subject_id'       => $subject->id,
            ]);
        }

        $subject->loadCount('studyLogs');

        $arr = $subject->toArray();
        $arr['study_log_count'] = $subject->study_logs_count ?? 0;
        unset($arr['study_logs_count']);

        return response()->json($arr, 201);
    }

    public function show(Request $request, Subject $subject)
    {
        abort_if($subject->user_id !== $request->user()->id, 403);

        $subject->loadCount('studyLogs');

        $arr = $subject->toArray();
        $arr['study_log_count'] = $subject->study_logs_count ?? 0;
        unset($arr['study_logs_count']);

        return response()->json($arr);
    }

    public function update(Request $request, Subject $subject)
    {
        abort_if($subject->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'color'        => ['nullable', 'string', 'max:12'],
            'room'         => ['nullable', 'string', 'max:255'],
            'target_hours' => ['nullable', 'integer', 'min:0'],

            'start_date'   => ['nullable', 'string'],
            'start_time'   => ['nullable', 'string'],
            'end_time'     => ['nullable', 'string'],
        ]);
        $data = $this->normalizeScheduleFields($data);

        $subject->update($data);

        $subject->loadCount('studyLogs');

        $arr = $subject->toArray();
        $arr['study_log_count'] = $subject->study_logs_count ?? 0;
        unset($arr['study_logs_count']);

        return response()->json($arr);
    }

    // DELETE /subjects/{subject}
    public function destroy(Request $request, Subject $subject)
    {
        abort_if($subject->user_id !== $request->user()->id, 403);

        $this->deleteSubjectCascade($subject->id);

        return response()->json(['message' => 'deleted']);
    }

    // POST /subjects/delete  body: { subject_id: 123 }
    public function destroyById(Request $request)
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer'],
        ]);

        $subject = Subject::where('user_id', $request->user()->id)
            ->findOrFail($data['subject_id']);

        $this->deleteSubjectCascade($subject->id);

        return response()->json(['message' => 'deleted']);
    }

    /**
     * ลบทุกตารางลูกที่ผูกกับ subject_id ให้ตรงกับฐานข้อมูล db_651998018
     */
    private function deleteSubjectCascade(int $subjectId): void
    {
        DB::transaction(function () use ($subjectId) {

            // ---------- QUIZ CHAIN ----------
            // quiz_answers.question_id -> quiz_questions.id -> quizzes.id (subject_id)
            DB::table('quiz_answers')
                ->whereIn('question_id', function ($q) use ($subjectId) {
                    $q->select('qq.id')
                        ->from('quiz_questions as qq')
                        ->whereIn('qq.quiz_id', function ($q2) use ($subjectId) {
                            $q2->select('qz.id')
                                ->from('quizzes as qz')
                                ->where('qz.subject_id', $subjectId);
                        });
                })
                ->delete();

            // quiz_attempts.quiz_id -> quizzes.id
            DB::table('quiz_attempts')
                ->whereIn('quiz_id', function ($q) use ($subjectId) {
                    $q->select('id')->from('quizzes')->where('subject_id', $subjectId);
                })
                ->delete();

            // quiz_questions.quiz_id -> quizzes.id
            DB::table('quiz_questions')
                ->whereIn('quiz_id', function ($q) use ($subjectId) {
                    $q->select('id')->from('quizzes')->where('subject_id', $subjectId);
                })
                ->delete();

            // quizzes.subject_id
            DB::table('quizzes')->where('subject_id', $subjectId)->delete();


            // ---------- STUDY LOG CHAIN ----------
            // files.study_log_id -> study_logs.id (subject_id)
            DB::table('files')
                ->whereIn('study_log_id', function ($q) use ($subjectId) {
                    $q->select('id')->from('study_logs')->where('subject_id', $subjectId);
                })
                ->delete();

            // summaries.study_log_id -> study_logs.id
            DB::table('summaries')
                ->whereIn('study_log_id', function ($q) use ($subjectId) {
                    $q->select('id')->from('study_logs')->where('subject_id', $subjectId);
                })
                ->delete();

            // study_logs.subject_id
            DB::table('study_logs')->where('subject_id', $subjectId)->delete();


            // ---------- OTHER TABLES THAT HAVE subject_id ----------
            DB::table('study_calendar_events')->where('subject_id', $subjectId)->delete();
            DB::table('lesson_summaries')->where('subject_id', $subjectId)->delete();
            DB::table('lessons')->where('subject_id', $subjectId)->delete();
            DB::table('learning_notifications')->where('subject_id', $subjectId)->delete();
            DB::table('study_notifications')->where('subject_id', $subjectId)->delete();
            DB::table('schedules')->where('subject_id', $subjectId)->delete();
            DB::table('mood_logs')->where('subject_id', $subjectId)->delete();
            DB::table('email_digest_items')->where('subject_id', $subjectId)->delete();
            DB::table('career_recommendations')->where('subject_id', $subjectId)->delete();

            // ai_threads.subject_id -> ai_messages.thread_id
            DB::table('ai_messages')
                ->whereIn('thread_id', function ($q) use ($subjectId) {
                    $q->select('id')->from('ai_threads')->where('subject_id', $subjectId);
                })
                ->delete();
            DB::table('ai_threads')->where('subject_id', $subjectId)->delete();

            // ---------- FINALLY SUBJECT ----------
            DB::table('subjects')->where('id', $subjectId)->delete();
        });
    }

    private function normalizeScheduleFields(array $data): array
    {
        $startDateType = $this->getColumnType('subjects', 'start_date');
        $startTimeType = $this->getColumnType('subjects', 'start_time');
        $endTimeType = $this->getColumnType('subjects', 'end_time');

        $baseDate = $this->normalizeDateValue($data['start_date'] ?? null, $startDateType, true);
        if (array_key_exists('start_date', $data)) {
            $data['start_date'] = $baseDate;
        }

        if (array_key_exists('start_time', $data)) {
            $data['start_time'] = $this->normalizeTimeValue($data['start_time'], $startTimeType, $baseDate, 'start_time');
        }

        if (array_key_exists('end_time', $data)) {
            $data['end_time'] = $this->normalizeTimeValue($data['end_time'], $endTimeType, $baseDate, 'end_time');
        }

        return $data;
    }

    private function normalizeDateValue(mixed $value, ?string $columnType, bool $allowDateOnly = false): ?string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') return null;

        try {
            $parsed = Carbon::parse($raw);
        } catch (\Throwable $error) {
            throw ValidationException::withMessages([
                'start_date' => 'รูปแบบวันที่ไม่ถูกต้อง',
            ]);
        }

        if ($this->isDateTimeColumn($columnType)) {
            if ($allowDateOnly && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                return $parsed->startOfDay()->format('Y-m-d H:i:s');
            }
            return $parsed->format('Y-m-d H:i:s');
        }

        return $parsed->format('Y-m-d');
    }

    private function normalizeTimeValue(mixed $value, ?string $columnType, ?string $baseDate = null, string $field = 'start_time'): ?string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') return null;

        $normalized = str_replace('.', ':', $raw);
        if (!preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $normalized)) {
            throw ValidationException::withMessages([
                $field => 'รูปแบบเวลาไม่ถูกต้อง',
            ]);
        }

        [$hourRaw, $minuteRaw, $secondRaw] = array_pad(explode(':', $normalized), 3, '00');
        $hour = (int) $hourRaw;
        $minute = (int) $minuteRaw;
        $second = (int) $secondRaw;

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            throw ValidationException::withMessages([
                $field => 'รูปแบบเวลาไม่ถูกต้อง',
            ]);
        }

        $time = sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        if (! $this->isDateTimeColumn($columnType)) {
            return $time;
        }

        $datePart = $baseDate
            ? Carbon::parse($baseDate)->format('Y-m-d')
            : now()->format('Y-m-d');

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
        } catch (\Throwable $error) {
            return null;
        }
    }

    private function isDateTimeColumn(?string $columnType): bool
    {
        return in_array($columnType, ['datetime', 'timestamp'], true);
    }
}
