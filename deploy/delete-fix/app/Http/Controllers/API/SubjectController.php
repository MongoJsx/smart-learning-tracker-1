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
use App\Models\StudyCalendarEvent;
use App\Models\StudyLog;
use App\Models\StudyNotification;
use App\Models\Subject;
use App\Models\Summary;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


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
        $query = request()->user()->subjects()->withCount('studyLogs');

        if (request()->boolean('include_study_logs')) {
            $query->with(['studyLogs' => fn ($q) => $q->latest('log_date')]);
        }

        $subjects = $query->orderByDesc('updated_at')->get();
        return SubjectResource::collection($subjects);
    }

    public function store(SubjectRequest $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validated();
        $startDate = $data['start_date'] ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        $startAt = null;
        $endAt = null;
        $allDay = true;

        if ($startDate) {
            $timezone = config('app.timezone', 'Asia/Bangkok');
            $startAt = $startTime
                ? Carbon::parse($startDate.' '.$startTime, $timezone)
                : Carbon::parse($startDate, $timezone)->startOfDay();

            if ($startTime) {
                $allDay = false;
            }

            if ($endTime) {
                if (! $startTime) {
                    return response()->json(['message' => 'ต้องระบุเวลาเริ่มก่อนเวลาเลิก'], 422);
                }

                $endAt = Carbon::parse($startDate.' '.$endTime, $timezone);
                if ($endAt->lessThan($startAt)) {
                    return response()->json(['message' => 'เวลาเลิกต้องไม่ก่อนเวลาเริ่ม'], 422);
                }
            }
        }

        $subject = $user->subjects()->create($data);

        // ✅ ถ้ามี start_date ให้สร้างบันทึกแรก เพื่อให้ “หน้าปฏิทิน/ตาราง” เห็นทันที
        if (!empty($startDate)) {
            $logPayload = [
                'title' => 'เริ่มบันทึก',
                'note' => null,
                'duration_minutes' => null,
                'log_date' => $startDate,
            ];
            if (Schema::hasColumn((new \App\Models\StudyLog())->getTable(), 'user_id')) {
                $logPayload['user_id'] = $user->id;
            }

            $log = $subject->studyLogs()->create($logPayload);

            if ($startAt && Schema::hasTable((new StudyCalendarEvent())->getTable())) {
                StudyCalendarEvent::updateOrCreate(
                    ['study_log_id' => $log->id, 'user_id' => $user->id],
                    [
                        'user_id' => $user->id,
                        'subject_id' => $subject->id,
                        'study_log_id' => $log->id,
                        'title' => $subject->name,
                        'description' => null,
                        'start_time' => $startAt,
                        'end_time' => $endAt,
                        'status' => 'planned',
                        'metadata' => [
                            'type' => 'class',
                            'all_day' => $allDay,
                            'source' => 'subject',
                        ],
                    ]
                );
            }
        }

        return response()->json(new SubjectResource($subject->fresh()->loadCount('studyLogs')), 201);
    }

    public function show(Subject $subject): SubjectResource
    {
        $this->authorizeSubject($subject);
        return new SubjectResource($subject->loadCount('studyLogs'));
    }

    public function update(SubjectRequest $request, Subject $subject): SubjectResource
    {
        $this->authorizeSubject($subject);
        $subject->update($request->validated());
        return new SubjectResource($subject->fresh()->loadCount('studyLogs'));
    }

    public function destroy(Request $request, Subject $subject): JsonResponse
    {
        if ((int) $subject->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::transaction(function () use ($subject) {
            $subjectId = $subject->id;
            $studyLogTable = (new StudyLog())->getTable();
            $studyLogIds = collect();

            if (Schema::hasTable($studyLogTable)) {
                $studyLogIds = StudyLog::where('subject_id', $subjectId)->pluck('id');
            }

            if ($studyLogIds->isNotEmpty()) {
                if (Schema::hasTable((new FileAttachment())->getTable())) {
                    FileAttachment::whereIn('study_log_id', $studyLogIds)->delete();
                }
                if (Schema::hasTable((new Summary())->getTable())) {
                    Summary::whereIn('study_log_id', $studyLogIds)->delete();
                }
                if (Schema::hasTable((new StudyCalendarEvent())->getTable())) {
                    StudyCalendarEvent::whereIn('study_log_id', $studyLogIds)->delete();
                }
                if (Schema::hasTable((new LearningNotification())->getTable())) {
                    LearningNotification::whereIn('study_log_id', $studyLogIds)->delete();
                }
            }

            if (Schema::hasTable((new StudyCalendarEvent())->getTable())) {
                StudyCalendarEvent::where('subject_id', $subjectId)->delete();
            }
            if (Schema::hasTable((new LearningNotification())->getTable())) {
                LearningNotification::where('subject_id', $subjectId)->delete();
            }
            if (Schema::hasTable((new StudyNotification())->getTable())) {
                StudyNotification::where('subject_id', $subjectId)->delete();
            }
            if (Schema::hasTable((new MoodLog())->getTable())) {
                MoodLog::where('subject_id', $subjectId)->delete();
            }
            if (Schema::hasTable((new CareerRecommendation())->getTable())) {
                CareerRecommendation::where('subject_id', $subjectId)->delete();
            }
            if (Schema::hasTable((new Quiz())->getTable())) {
                Quiz::where('subject_id', $subjectId)->delete();
            }
            if (Schema::hasTable((new Lesson())->getTable())) {
                Lesson::where('subject_id', $subjectId)->delete();
            }
            if (Schema::hasTable((new Schedule())->getTable())) {
                Schedule::where('subject_id', $subjectId)->delete();
            }
            if (Schema::hasTable($studyLogTable)) {
                StudyLog::where('subject_id', $subjectId)->delete();
            }

            $subject->delete();
        });

        return response()->json(['message' => 'Deleted'], 200);
    }

    private function authorizeSubject(Subject $subject): void
    {
        abort_unless($subject->user_id === request()->user()->id, 403, 'Unauthorized');
    }
}
