<?php

namespace App\Http\Controllers;

use App\Mail\ScheduleNotificationMail;
use App\Models\EmailProviderAccount;
use App\Models\LearningNotification;
use App\Models\NotificationEmailLog;
use App\Models\NotificationEmailSetting;
use App\Models\Subject;
use App\Models\StudyCalendarEvent;
use App\Models\StudyLog;
use App\Models\User;
use App\Services\Email\GmailApiMailer;
use App\Services\Notification\NotificationEmailSender;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->hasNotificationTable()) {
            return response()->json([]);
        }

        $this->ensureDailyScheduleNotifications($request->user());
        app(NotificationEmailSender::class)->sendDueForUser($request->user());

        $notifications = LearningNotification::where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'dismissed');
            })
            ->orderBy('notify_at', 'desc')
            ->get()
            ->filter(fn (LearningNotification $notification) => $this->isStudyNotification($notification))
            ->values();

        return response()->json($this->serializeNotifications($notifications, $request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->hasNotificationTable()) {
            return response()->json(['message' => 'Notification table is missing'], 200);
        }

        $validated = $request->validate([
            'subject_id' => 'nullable|exists:subjects,id',
            'study_log_id' => 'nullable|exists:study_logs,id',
            'calendar_event_id' => 'nullable|exists:study_calendar_events,id',
            'type' => 'required|string',
            'title' => 'required|string',
            'message' => 'required|string',
            'notify_at' => 'required|date',
            'channel' => 'nullable|in:in_app,email,push,line',
        ]);

        $userId = $request->user()->id;
        $timezone = $this->resolveTimezone($request->user());
        $subjectId = $validated['subject_id'] ?? null;
        $studyLogId = $validated['study_log_id'] ?? null;
        $calendarEventId = $validated['calendar_event_id'] ?? null;

        if ($subjectId && ! $this->subjectBelongsToUser($subjectId, $userId)) {
            return response()->json(['message' => 'ไม่พบรายวิชาที่เลือก'], 422);
        }

        if ($studyLogId && ! $this->studyLogBelongsToUser($studyLogId, $userId)) {
            return response()->json(['message' => 'ไม่พบบันทึกการเรียนที่เลือก'], 422);
        }

        if ($calendarEventId && ! $this->calendarEventBelongsToUser($calendarEventId, $userId)) {
            return response()->json(['message' => 'ไม่พบตารางที่เลือก'], 422);
        }

        $notifyAt = Carbon::parse($validated['notify_at'], $timezone)->timezone($timezone);
        $notifyAtUtc = $notifyAt->copy()->utc();
        $title = $this->fitNotificationTitle($validated['title']);
        $message = $this->fitNotificationBody($validated['message']);

        $notification = LearningNotification::create([
            'user_id' => $userId,
            'subject_id' => $subjectId,
            'study_log_id' => $studyLogId,
            'calendar_event_id' => $calendarEventId,
            'title' => $title,
            'body' => $message,
            'notify_at' => $notifyAtUtc,
            // Always keep in-app as primary channel; email is sent by NotificationEmailSender.
            'channel' => $validated['channel'] ?? 'in_app',
            'status' => 'pending',
            'metadata' => [
                'type' => $validated['type'],
                'is_read' => false,
                'deliveries' => [
                    'in_app' => true,
                    'email' => true,
                ],
            ],
        ]);

        // Send email immediately for user-created notifications as well.
        $this->sendScheduleEmail($request->user(), $notification, $title, $message);

        return response()->json($this->serializeNotification($notification, $request->user()), 201);
    }

    public function createScheduleNotification(Request $request): JsonResponse
    {
        if (! $this->hasNotificationTable()) {
            return response()->json(['message' => 'Notification table is missing'], 200);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'title' => 'nullable|string',
            'notify_time' => 'nullable|string',
        ]);

        $user = $request->user();
        $timezone = $this->resolveTimezone($user);
        $date = Carbon::parse($validated['date'], $timezone)->startOfDay();
        $notifyAt = $this->resolveNotifyAtForDate($user, $date, $validated['notify_time'] ?? null);
        $this->persistNotifyTime($user, $validated['notify_time'] ?? null);

        [$title, $message] = $this->buildScheduleCopy($user->id, $date, false, $timezone);
        $notification = $this->upsertScheduleNotification(
            $user->id,
            'schedule_day',
            $notifyAt,
            $validated['title'] ?? $title,
            $message
        );

        $this->sendScheduleEmail($user, $notification, $validated['title'] ?? $title, $message);

        return response()->json($this->serializeNotification($notification, $user), 201);
    }

    public function createScheduleRange(Request $request): JsonResponse
    {
        if (! $this->hasNotificationTable()) {
            return response()->json(['message' => 'Notification table is missing'], 200);
        }

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:30',
            'subject_id' => 'nullable|exists:subjects,id',
            'title' => 'nullable|string',
            'notify_time' => 'nullable|string',
        ]);

        $user = $request->user();
        $timezone = $this->resolveTimezone($user);
        $days = (int) $validated['days'];
        $subjectId = $validated['subject_id'] ?? null;

        if ($subjectId && ! $this->subjectBelongsToUser($subjectId, $user->id)) {
            return response()->json(['message' => 'ไม่พบรายวิชาที่เลือก'], 422);
        }

        [$title, $message, $notifyAt] = $this->buildRangeScheduleCopy(
            $user->id,
            $days,
            $subjectId,
            $validated['title'] ?? null,
            $timezone
        );

        $notifyAt = $this->resolveNotifyAtForDate($user, $notifyAt, $validated['notify_time'] ?? null);
        $this->persistNotifyTime($user, $validated['notify_time'] ?? null);

        $notification = $this->upsertScheduleNotification(
            $user->id,
            'schedule_range',
            $notifyAt,
            $title,
            $message,
            $subjectId
        );

        $this->sendScheduleEmail($user, $notification, $title, $message);

        return response()->json($this->serializeNotification($notification, $user), 201);
    }

    public function markAsRead(LearningNotification $notification): JsonResponse
    {
        if (! $this->hasNotificationTable()) {
            return response()->json([], 200);
        }

        $this->authorizeNotification($notification, request()->user()->id);
        $metadata = $this->mergeMetadata($notification->metadata, ['is_read' => true]);
        $notification->update(['metadata' => $metadata]);
        return response()->json($this->serializeNotification($notification, request()->user()));
    }

    public function unread(Request $request): JsonResponse
    {
        if (! $this->hasNotificationTable()) {
            return response()->json(['unread_count' => 0]);
        }

        $count = LearningNotification::where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'dismissed');
            })
            ->get(['id', 'metadata'])
            ->filter(fn (LearningNotification $notification) => $this->isStudyNotification($notification))
            ->filter(fn (LearningNotification $notification) => ! data_get($notification->metadata, 'is_read', false))
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function destroy(LearningNotification $notification): JsonResponse
    {
        if (! $this->hasNotificationTable()) {
            return response()->json(status: 204);
        }

        $this->authorizeNotification($notification, request()->user()->id);
        $type = data_get($notification->metadata, 'type');
        if (in_array($type, ['today_schedule', 'tomorrow_schedule'], true)) {
            $metadata = $this->mergeMetadata($notification->metadata, [
                'is_read' => true,
                'dismissed_at' => Carbon::now()->toIso8601String(),
            ]);
            $notification->update([
                'status' => 'dismissed',
                'metadata' => $metadata,
            ]);
            return response()->json(status: 204);
        }

        $notification->delete();
        return response()->json(status: 204);
    }

    public function updateTime(Request $request, LearningNotification $notification): JsonResponse
    {
        if (! $this->hasNotificationTable()) {
            return response()->json([], 200);
        }

        $this->authorizeNotification($notification, request()->user()->id);

        $validated = $request->validate([
            'notify_time' => 'nullable|string',
            'notify_date' => 'nullable|date',
        ]);

        $notifyTime = $validated['notify_time'] ?? null;
        $notifyDate = $validated['notify_date'] ?? null;

        if (! $notifyTime && ! $notifyDate) {
            return response()->json(['message' => 'ต้องระบุวันหรือเวลาอย่างน้อยหนึ่งรายการ'], 422);
        }

        $timezone = $this->resolveTimezone($request->user());
        $current = $notification->notify_at
            ? $notification->notify_at->timezone($timezone)
            : Carbon::now($timezone);

        $dateValue = $notifyDate ? Carbon::parse($notifyDate, $timezone)->toDateString() : $current->toDateString();

        $timeValue = $notifyTime ? $this->normalizeTimeString($notifyTime) : $current->format('H:i:s');
        if (! $timeValue) {
            return response()->json(['message' => 'รูปแบบเวลาไม่ถูกต้อง'], 422);
        }

        $notifyAt = Carbon::parse($dateValue.' '.$timeValue, $timezone);
        $notifyAtUtc = $notifyAt->copy()->utc();
        $nextBody = $notification->body;
        if (data_get($notification->metadata, 'type') === 'subject_reminder') {
            $subjectName = trim((string) ($notification->subject?->name ?? 'รายวิชา'));
            $nextBody = $this->buildSubjectReminderBody($notifyAt, $subjectName);
        }
        $metadata = $this->mergeMetadata($notification->metadata, [
            'custom_notify_at' => $notifyAt->toIso8601String(),
        ]);
        $notification->update([
            'notify_at' => $notifyAtUtc,
            'body' => $this->fitNotificationBody($nextBody),
            'metadata' => $metadata,
        ]);

        if ($notifyTime && in_array($notification->type, ['today_schedule', 'tomorrow_schedule'], true)) {
            $settings = $this->resolveEmailSettings($request->user());
            $settings?->update(['send_time' => $this->formatSendTimeForStorage($timeValue)]);
        }

        return response()->json($this->serializeNotification($notification, $request->user()));
    }

    public function settings(Request $request): JsonResponse
    {
        if (! Schema::hasTable((new NotificationEmailSetting())->getTable())) {
            return response()->json([
                'send_time' => '00:00:00',
                'timezone' => 'Asia/Bangkok',
                'email_enabled' => true,
            ]);
        }

        $settings = $this->resolveEmailSettings($request->user());

        return response()->json([
            'send_time' => $this->normalizeTimeString($settings?->send_time) ?: '00:00:00',
            'timezone' => $settings?->timezone ?: 'Asia/Bangkok',
            'email_enabled' => (bool) ($settings?->email_enabled ?? true),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        if (! Schema::hasTable((new NotificationEmailSetting())->getTable())) {
            return response()->json(['message' => 'Notification settings table is missing'], 200);
        }

        $validated = $request->validate([
            'send_time' => 'required|string',
        ]);

        $time = $this->normalizeTimeString($validated['send_time']);
        if (! $time) {
            return response()->json(['message' => 'รูปแบบเวลาไม่ถูกต้อง'], 422);
        }

        $settings = $this->resolveEmailSettings($request->user());
        $settings?->update(['send_time' => $this->formatSendTimeForStorage($time)]);
        $this->updateScheduleNotificationTimes($request->user(), $time);

        return response()->json([
            'send_time' => $time,
        ]);
    }

    private function ensureDailyScheduleNotifications(User $user): void
    {
        if (! $this->hasNotificationTable()) {
            return;
        }

        if (! $this->hasCalendarTable()) {
            return;
        }

        $timezone = $this->resolveTimezone($user);
        $today = Carbon::now($timezone)->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $notifyAtToday = $this->resolveNotifyAtForDate($user, $today, null);
        $notifyAtTomorrow = $this->resolveNotifyAtForDate($user, $tomorrow, null);

        $this->upsertDailyScheduleNotification($user, $today, $notifyAtToday, 'today_schedule', false, 'วันนี้');
        $this->upsertDailyScheduleNotification($user, $tomorrow, $notifyAtTomorrow, 'tomorrow_schedule', true, 'พรุ่งนี้');
    }

    private function buildScheduleCopy(
        int $userId,
        Carbon $date,
        bool $isTomorrow,
        string $timezone,
        ?string $relativeLabel = null
    ): array
    {
        $dayNameTh = $this->thaiDayName($date);
        $events = $this->calendarEventsForDate($userId, $date, $timezone);

        $dateLabel = $relativeLabel ?: ($isTomorrow ? 'พรุ่งนี้' : $date->format('d M Y'));
        $title = $isTomorrow
            ? 'แจ้งเตือนเรียนพรุ่งนี้'
            : ($relativeLabel === 'วันนี้' ? 'แจ้งเตือนเรียนวันนี้' : "แจ้งเตือนเรียน {$dayNameTh}");
        $message = $events->isEmpty()
            ? "{$dateLabel} ({$dayNameTh}) คุณไม่มีตารางเรียน"
            : $this->buildCompactScheduleMessage($dateLabel, $dayNameTh, $events, $timezone);

        return [$title, $message];
    }

    private function buildRangeScheduleCopy(
        int $userId,
        int $days,
        ?int $subjectId,
        ?string $customTitle,
        string $timezone
    ): array {
        $start = Carbon::now($timezone)->addDay()->startOfDay();
        $end = $start->copy()->addDays($days - 1)->endOfDay();
        $events = $this->calendarEventsForRange($userId, $start, $end, $subjectId);
        $eventsByDate = $events->groupBy(function ($event) use ($timezone) {
            return $event->start_time->timezone($timezone)->toDateString();
        });

        $lines = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $dayKey = $day->toDateString();
            $dayTh = $this->thaiDayName($day);
            $label = $day->isSameDay($start) ? 'พรุ่งนี้' : $day->format('d M');

            $matches = $eventsByDate->get($dayKey, collect());
            if ($matches->isEmpty()) {
                $lines[] = "{$label} ({$dayTh}) ว่าง";
                continue;
            }

            $parts = $matches->map(fn ($event) => $this->formatEventLabel($event, $timezone))
                ->values()
                ->all();

            $lines[] = $this->fitNotificationBody("{$label} ({$dayTh}): ".implode('; ', $parts), 220);
        }

        $title = $customTitle ?: "ตารางเรียน {$days} วันข้างหน้า";
        $message = $this->fitNotificationBody(implode(' | ', $lines), 240);

        return [$title, $message, $start->copy()->startOfDay()];
    }

    private function authorizeNotification(LearningNotification $notification, int $userId): void
    {
        abort_unless($notification->user_id === $userId, 403, 'Unauthorized');
    }

    private function calendarEventsForDate(int $userId, Carbon $date, string $timezone, ?int $subjectId = null)
    {
        if (! $this->hasCalendarTable()) {
            return collect();
        }

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        return $this->safeCalendarQuery($userId, $start, $end, $subjectId);
    }

    private function calendarEventsForRange(int $userId, Carbon $start, Carbon $end, ?int $subjectId = null)
    {
        if (! $this->hasCalendarTable()) {
            return collect();
        }

        return $this->safeCalendarQuery($userId, $start, $end, $subjectId);
    }

    private function safeCalendarQuery(int $userId, Carbon $start, Carbon $end, ?int $subjectId = null)
    {
        try {
            $this->syncStudyLogEvents($userId, $start, $end);
            $events = StudyCalendarEvent::with(['subject', 'studyLog'])
                ->where('user_id', $userId)
                ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
                ->whereBetween('start_time', [$start, $end])
                ->orderBy('start_time')
                ->get();

            return $events
                ->filter(fn (StudyCalendarEvent $event) => $this->isSubjectClassEvent($event))
                ->reject(fn (StudyCalendarEvent $event) => $this->isSummaryEvent($event))
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function hasNotificationTable(): bool
    {
        return Schema::hasTable((new LearningNotification())->getTable());
    }

    private function hasCalendarTable(): bool
    {
        return Schema::hasTable((new StudyCalendarEvent())->getTable());
    }

    private function subjectBelongsToUser(int $subjectId, int $userId): bool
    {
        return Subject::where('id', $subjectId)->where('user_id', $userId)->exists();
    }

    private function studyLogBelongsToUser(int $studyLogId, int $userId): bool
    {
        return StudyLog::where('id', $studyLogId)
            ->whereHas('subject', fn ($query) => $query->where('user_id', $userId))
            ->exists();
    }

    private function calendarEventBelongsToUser(int $eventId, int $userId): bool
    {
        return StudyCalendarEvent::where('id', $eventId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function syncStudyLogEvents(int $userId, Carbon $start, Carbon $end): void
    {
        if (! $this->hasCalendarTable()) {
            return;
        }

        if (! Schema::hasTable((new StudyLog())->getTable())) {
            return;
        }

        StudyCalendarEvent::where('user_id', $userId)
            ->whereNotNull('study_log_id')
            ->whereDoesntHave('studyLog')
            ->delete();

        $logs = StudyLog::query()
            ->whereHas('subject', fn ($query) => $query->where('user_id', $userId))
            ->whereDate('log_date', '>=', $start->toDateString())
            ->whereDate('log_date', '<=', $end->toDateString())
            ->get();

        foreach ($logs as $log) {
            if ($this->isSummaryLog($log)) {
                StudyCalendarEvent::where('study_log_id', $log->id)
                    ->where('user_id', $userId)
                    ->delete();
                continue;
            }

            $existing = StudyCalendarEvent::where('study_log_id', $log->id)
                ->where('user_id', $userId)
                ->first();

            if ($existing && data_get($existing->metadata, 'source') === 'subject') {
                continue;
            }

            $startTime = Carbon::parse($log->log_date)->startOfDay();
            $metadata = [
                'source' => 'study_log',
                'all_day' => true,
            ];

            if (! is_null($log->duration_minutes)) {
                $metadata['duration_minutes'] = $log->duration_minutes;
            }

            $eventPayload = [
                'user_id' => $userId,
                'subject_id' => $log->subject_id,
                'study_log_id' => $log->id,
                'title' => $log->title,
                'description' => $log->note,
                'start_time' => $startTime,
                'end_time' => null,
                'status' => 'planned',
                'metadata' => $metadata,
            ];

            if (Schema::hasColumn((new StudyCalendarEvent())->getTable(), 'event_type')) {
                $eventPayload['event_type'] = 'class';
            }

            StudyCalendarEvent::updateOrCreate(
                ['study_log_id' => $log->id, 'user_id' => $userId],
                $eventPayload
            );
        }
    }

    private function sendScheduleEmail(
        User $user,
        LearningNotification $notification,
        string $title,
        string $message
    ): void {
        // Respect notify_at: do not force immediate send.
        app(NotificationEmailSender::class)->sendNotification($user, $notification, false);
    }

    private function isMailConfigured(): bool
    {
        $required = [
            config('mail.mailers.smtp.host'),
            config('mail.mailers.smtp.port'),
            config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.password'),
            config('mail.from.address'),
        ];

        foreach ($required as $value) {
            if (! filled($value)) {
                return false;
            }
        }

        return true;
    }

    private function resolveEmailSettings(User $user): ?NotificationEmailSetting
    {
        if (! Schema::hasTable((new NotificationEmailSetting())->getTable())) {
            return null;
        }

        $existing = NotificationEmailSetting::where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }

        $payload = [
            'user_id' => $user->id,
            'email_enabled' => true,
            'email_address' => $user->email,
            'digest_type' => 'daily',
            'days_ahead' => 1,
            'send_time' => $this->formatSendTimeForStorage('00:00:00'),
            'timezone' => 'Asia/Bangkok',
        ];

        try {
            return NotificationEmailSetting::create($payload);
        } catch (QueryException $e) {
            if (strpos($e->getMessage(), "Field 'id' doesn't have a default value") === false) {
                throw $e;
            }
            $nextId = (int) NotificationEmailSetting::withoutGlobalScopes()->max('id') + 1;
            $payload['id'] = $nextId > 0 ? $nextId : 1;
            return NotificationEmailSetting::create($payload);
        }
    }

    private function createEmailLog(
        int $userId,
        int $notificationId,
        string $toEmail,
        string $subject,
        string $provider
    ): ?NotificationEmailLog
    {
        if (! Schema::hasTable((new NotificationEmailLog())->getTable())) {
            return null;
        }

        return NotificationEmailLog::create([
            'user_id' => $userId,
            'learning_notification_id' => $notificationId,
            'to_email' => $toEmail,
            'subject' => $subject,
            'provider' => $provider,
            'status' => 'queued',
        ]);
    }

    private function resolveGmailAccount(User $user): ?EmailProviderAccount
    {
        if (! Schema::hasTable((new EmailProviderAccount())->getTable())) {
            return null;
        }

        $query = EmailProviderAccount::where('user_id', $user->id)
            ->where('provider', 'gmail')
            ->where('auth_type', 'oauth')
            ->where('status', 'active');

        if ($user->email) {
            $query->where('provider_email', $user->email);
        }

        return $query->first();
    }

    private function resolveTimezone(User $user): string
    {
        $settings = $this->resolveEmailSettings($user);
        return $settings?->timezone ?: 'Asia/Bangkok';
    }

    private function normalizeTimeString(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        $time = trim($time);
        if (preg_match('/(?:^|\s|T)(\d{1,2})[:.](\d{2})(?::(\d{2}))?$/', $time, $matches)) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
            $second = isset($matches[3]) ? (int) $matches[3] : 0;

            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
                return null;
            }

            return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        }

        return null;
    }

    private function resolveNotifyAtForDate(User $user, Carbon $date, ?string $notifyTime): Carbon
    {
        $timezone = $this->resolveTimezone($user);
        $settings = $this->resolveEmailSettings($user);
        $time = $this->normalizeTimeString($notifyTime)
            ?: $this->normalizeTimeString($settings?->send_time)
            ?: '00:00:00';

        return Carbon::parse($date->toDateString().' '.$time, $timezone);
    }

    private function persistNotifyTime(User $user, ?string $notifyTime): void
    {
        $time = $this->normalizeTimeString($notifyTime);
        if (! $time) {
            return;
        }

        $settings = $this->resolveEmailSettings($user);
        $settings?->update(['send_time' => $this->formatSendTimeForStorage($time)]);
    }

    private function formatSendTimeForStorage(string $time): string
    {
        $table = (new NotificationEmailSetting())->getTable();
        $columnType = '';
        try {
            $columnType = Schema::getColumnType($table, 'send_time');
        } catch (\Throwable $e) {
            $columnType = '';
        }

        if (in_array($columnType, ['datetime', 'timestamp'], true)) {
            return Carbon::today('Asia/Bangkok')->format('Y-m-d').' '.$time;
        }

        return $time;
    }

    private function updateScheduleNotificationTimes(User $user, string $time): void
    {
        if (! $this->hasNotificationTable()) {
            return;
        }

        $timezone = $this->resolveTimezone($user);
        $types = ['today_schedule', 'tomorrow_schedule', 'schedule_range', 'schedule_day'];

        $notifications = LearningNotification::where('user_id', $user->id)
            ->get()
            ->filter(function (LearningNotification $notification) use ($types) {
                $type = data_get($notification->metadata, 'type');
                return $type && in_array($type, $types, true);
            })
            ->values();

        foreach ($notifications as $notification) {
            $current = $notification->notify_at
                ? $notification->notify_at->timezone($timezone)
                : Carbon::now($timezone);

            $notifyAt = Carbon::parse($current->toDateString().' '.$time, $timezone);
            $notification->update(['notify_at' => $notifyAt]);
        }
    }

    private function thaiDayName(Carbon $date): string
    {
        $dayMap = [
            'Monday' => 'จันทร์',
            'Tuesday' => 'อังคาร',
            'Wednesday' => 'พุธ',
            'Thursday' => 'พฤหัสบดี',
            'Friday' => 'ศุกร์',
            'Saturday' => 'เสาร์',
            'Sunday' => 'อาทิตย์',
        ];

        $dayNameEn = $date->format('l');
        return $dayMap[$dayNameEn] ?? $dayNameEn;
    }

    private function formatEventLabel(StudyCalendarEvent $event, string $timezone): string
    {
        $baseLabel = $event->subject?->name ?: ($event->title ?: 'กิจกรรมไม่ระบุ');
        $eventType = (string) ($event->event_type ?? data_get($event->metadata, 'type') ?? '');
        $typeLabel = match ($eventType) {
            'class' => 'เรียน',
            'exam' => 'สอบ',
            'other' => 'กิจกรรม',
            default => '',
        };
        $label = $typeLabel !== '' ? "{$typeLabel}: {$baseLabel}" : $baseLabel;

        $description = trim((string) ($event->description ?? ''));
        if (data_get($event->metadata, 'all_day')) {
            return $description !== '' ? "{$label} ({$description})" : $label;
        }

        $start = $event->start_time?->timezone($timezone)?->format('H:i') ?? '';
        $end = $event->end_time?->timezone($timezone)?->format('H:i');
        $time = $start;

        if ($end) {
            $time = $start ? "{$start}-{$end}" : $end;
        }

        $line = $time ? "{$label} เวลา {$time}" : $label;

        return $description !== '' ? "{$line} ({$description})" : $line;
    }

    private function upsertScheduleNotification(
        int $userId,
        string $type,
        Carbon $notifyAt,
        string $title,
        string $message,
        ?int $subjectId = null,
        string $channel = 'email'
    ): LearningNotification {
        $query = LearningNotification::where('user_id', $userId)
            ->where('channel', $channel)
            ->whereDate('notify_at', $notifyAt->toDateString());

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        $notification = $query->get()
            ->first(fn (LearningNotification $candidate) => data_get($candidate->metadata, 'type') === $type);
        $metadata = $this->mergeMetadata($notification?->metadata, ['type' => $type]);
        if (! array_key_exists('is_read', $metadata)) {
            $metadata['is_read'] = false;
        }

        if ($notification) {
            $notification->update([
                'subject_id' => $subjectId,
                'title' => $this->fitNotificationTitle($title),
                'body' => $this->fitNotificationBody($message),
                'notify_at' => $notifyAt,
                'status' => $notification->status ?? 'pending',
                'metadata' => $metadata,
            ]);
            return $notification;
        }

        return LearningNotification::create([
            'user_id' => $userId,
            'subject_id' => $subjectId,
            'study_log_id' => null,
            'calendar_event_id' => null,
            'title' => $this->fitNotificationTitle($title),
            'body' => $this->fitNotificationBody($message),
            'notify_at' => $notifyAt,
            'channel' => $channel,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);
    }

    private function upsertDailyScheduleNotification(
        User $user,
        Carbon $date,
        Carbon $notifyAt,
        string $type,
        bool $isTomorrow,
        string $relativeLabel
    ): void {
        if ($this->isDailyScheduleDismissedForDate($user->id, $type, $notifyAt->toDateString())) {
            return;
        }

        $timezone = $this->resolveTimezone($user);
        [$title, $message] = $this->buildScheduleCopy($user->id, $date, $isTomorrow, $timezone, $relativeLabel);

        $sameTypeNotifications = LearningNotification::where('user_id', $user->id)
            ->get()
            ->filter(fn (LearningNotification $candidate) => data_get($candidate->metadata, 'type') === $type)
            ->values();

        $notification = $sameTypeNotifications
            ->sortByDesc(fn (LearningNotification $candidate) => $candidate->updated_at?->timestamp ?? 0)
            ->first();

        $sameTypeNotifications
            ->filter(fn (LearningNotification $candidate) => ! $notification || $candidate->id !== $notification->id)
            ->each
            ->delete();

        $metadata = $this->mergeMetadata($notification?->metadata, ['type' => $type]);
        if (! array_key_exists('is_read', $metadata)) {
            $metadata['is_read'] = false;
        }

        $resolvedNotifyAt = $this->resolveStoredNotifyAt($metadata, $notifyAt, $timezone);

        if ($notification) {
            $notification->update([
                'title' => $this->fitNotificationTitle($title),
                'body' => $this->fitNotificationBody($message),
                'notify_at' => $resolvedNotifyAt,
                'status' => $notification->status ?? 'pending',
                'metadata' => $metadata,
            ]);
        } else {
            $notification = LearningNotification::create([
                'user_id' => $user->id,
                'subject_id' => null,
                'study_log_id' => null,
                'calendar_event_id' => null,
                'title' => $this->fitNotificationTitle($title),
                'body' => $this->fitNotificationBody($message),
                'notify_at' => $resolvedNotifyAt,
                'channel' => 'in_app',
                'status' => 'pending',
                'metadata' => $metadata,
            ]);
        }

        if ($notification->status !== 'sent') {
            $this->sendScheduleEmail($user, $notification, $title, $message);
        }
    }

    private function mergeMetadata(?array $metadata, array $updates): array
    {
        $metadata = is_array($metadata) ? $metadata : [];

        return array_merge($metadata, $updates);
    }

    private function serializeNotifications($notifications, User $user): array
    {
        return collect($notifications)
            ->map(fn (LearningNotification $notification) => $this->serializeNotification($notification, $user))
            ->values()
            ->all();
    }

    private function serializeNotification(LearningNotification $notification, User $user): array
    {
        $payload = $notification->toArray();
        $timezone = $this->resolveTimezone($user);
        $payload['notify_at'] = $notification->notify_at?->timezone($timezone)->format('Y-m-d H:i:s');
        $payload['delivered_at'] = $notification->delivered_at?->timezone($timezone)->format('Y-m-d H:i:s');

        return $payload;
    }

    private function resolveStoredNotifyAt(array $metadata, Carbon $defaultNotifyAt, string $timezone): Carbon
    {
        $custom = data_get($metadata, 'custom_notify_at');
        if (! is_string($custom) || trim($custom) === '') {
            return $defaultNotifyAt;
        }

        try {
            return Carbon::parse($custom, $timezone);
        } catch (\Throwable $e) {
            return $defaultNotifyAt;
        }
    }

    private function buildCompactScheduleMessage(
        string $dateLabel,
        string $dayNameTh,
        $events,
        string $timezone
    ): string {
        $labels = $events
            ->map(fn ($event) => $this->fitNotificationBody($this->formatEventLabel($event, $timezone), 42))
            ->filter(fn ($label) => $label !== '')
            ->values();

        $visible = $labels->take(3)->implode('; ');
        $hiddenCount = max(0, $labels->count() - 3);
        $suffix = $hiddenCount > 0 ? "; +{$hiddenCount} รายการ" : '';

        return $this->fitNotificationBody(
            "{$dateLabel} ({$dayNameTh}) คุณมี {$labels->count()} รายการ: {$visible}{$suffix}"
        );
    }

    private function fitNotificationTitle(string $value, int $fallback = 150): string
    {
        return $this->fitNotificationText('title', $value, $fallback);
    }

    private function fitNotificationBody(string $value, int $fallback = 240): string
    {
        return $this->fitNotificationText('body', $value, $fallback);
    }

    private function fitNotificationText(string $column, string $value, int $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $limit = $this->resolveNotificationColumnLength($column) ?: $fallback;
        if ($limit <= 0) {
            return $value;
        }

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        $suffix = $limit > 3 ? '...' : '';
        $sliceLength = max(1, $limit - mb_strlen($suffix));

        return rtrim(mb_substr($value, 0, $sliceLength)).$suffix;
    }

    private function resolveNotificationColumnLength(string $column): ?int
    {
        static $cache = [];

        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        try {
            $database = DB::getDatabaseName();
            $row = DB::table('information_schema.COLUMNS')
                ->select('CHARACTER_MAXIMUM_LENGTH', 'DATA_TYPE')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', (new LearningNotification())->getTable())
                ->where('COLUMN_NAME', $column)
                ->first();

            if (! $row) {
                return $cache[$column] = null;
            }

            $dataType = strtolower((string) ($row->DATA_TYPE ?? ''));
            if (in_array($dataType, ['text', 'mediumtext', 'longtext'], true)) {
                return $cache[$column] = null;
            }

            $maxLength = (int) ($row->CHARACTER_MAXIMUM_LENGTH ?? 0);
            return $cache[$column] = $maxLength > 0 ? $maxLength : null;
        } catch (\Throwable $e) {
            return $cache[$column] = null;
        }
    }

    private function isSummaryEvent(StudyCalendarEvent $event): bool
    {
        if ($this->isSummaryText($event->title) || $this->isSummaryText($event->description)) {
            return true;
        }

        if ($event->study_log_id) {
            if (! $event->studyLog) {
                return true;
            }

            if ($this->isSummaryLog($event->studyLog)) {
                return true;
            }
        }

        return false;
    }

    private function isSubjectClassEvent(StudyCalendarEvent $event): bool
    {
        if (! $event->subject_id) {
            return false;
        }

        $eventType = Str::lower((string) ($event->event_type ?? data_get($event->metadata, 'type') ?? ''));
        if ($eventType !== '' && $eventType !== 'class') {
            return false;
        }

        $source = Str::lower((string) data_get($event->metadata, 'source', ''));
        if ($source !== '' && ! in_array($source, ['subject', 'study_log'], true)) {
            return false;
        }

        return true;
    }

    private function isSummaryLog(?StudyLog $log): bool
    {
        if (! $log) {
            return false;
        }

        if ($log->isSummary()) {
            return true;
        }

        return $this->isSummaryText($log->title) || $this->isSummaryText($log->note);
    }

    private function isSummaryText(?string $text): bool
    {
        $value = trim((string) $text);
        if ($value === '') {
            return false;
        }

        $lower = Str::lower($value);
        if (Str::startsWith($lower, ['สรุป', 'summary'])) {
            return true;
        }

        return Str::contains($lower, [
            'สรุปเอกสาร',
            'สรุปเสียง',
            'อัปโหลดไฟล์เพื่อสรุป',
            'อัปโหลดเสียงเพื่อสรุป',
            'summary',
        ]);
    }

    private function isStudyNotification(LearningNotification $notification): bool
    {
        $type = data_get($notification->metadata, 'type');

        return in_array($type, [
            'today_schedule',
            'tomorrow_schedule',
            'schedule_day',
            'schedule_range',
            'subject_reminder',
        ], true);
    }

    private function isDailyScheduleDismissedForDate(int $userId, string $type, string $date): bool
    {
        return LearningNotification::where('user_id', $userId)
            ->where('status', 'dismissed')
            ->whereDate('notify_at', $date)
            ->get(['metadata'])
            ->contains(fn (LearningNotification $item) => data_get($item->metadata, 'type') === $type);
    }

    private function buildSubjectReminderBody(Carbon $notifyAt, string $subjectName): string
    {
        $dateLabel = $notifyAt->copy()->locale('th')->translatedFormat('j M Y');
        $timeLabel = $notifyAt->format('H:i');

        return "แจ้งเตือนว่า {$dateLabel} เวลา {$timeLabel} น. มีเรียนวิชา \"{$subjectName}\"";
    }
}
