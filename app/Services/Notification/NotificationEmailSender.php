<?php

namespace App\Services\Notification;

use App\Mail\ScheduleNotificationMail;
use App\Models\EmailProviderAccount;
use App\Models\LearningNotification;
use App\Models\NotificationEmailLog;
use App\Models\NotificationEmailSetting;
use App\Models\User;
use App\Services\Email\GmailApiMailer;
use App\Services\Email\PhpMailerSmtpMailer;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationEmailSender
{
    public function __construct(
        private readonly GmailApiMailer $gmailMailer,
        private readonly PhpMailerSmtpMailer $smtpMailer
    ) {
    }

    public function sendDueForUser(User $user): int
    {
        // Notifications are delivered in-app only.
        return 0;
    }

    public function sendNotification(User $user, LearningNotification $notification, bool $forceSend = false): bool
    {
        // Notifications are delivered in-app only.
        return false;
    }

    private function isMailConfigured(): bool
    {
        // ต้องมีครบตามที่ระบบคุณเช็ค (และสอดคล้องกับการส่งผ่าน PhpMailerSmtpMailer)
        $required = [
            config('mail.mailers.smtp.host'),
            config('mail.mailers.smtp.port'),
            config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.password'),
            config('mail.from.address'),
        ];

        foreach ($required as $value) {
            if (!filled($value)) {
                return false;
            }
        }

        return true;
    }

    private function resolveEmailSettings(User $user): ?NotificationEmailSetting
    {
        if (!Schema::hasTable((new NotificationEmailSetting())->getTable())) {
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
            'digest_type' => 'daily',     // ตรง enum: daily/weekly
            'days_ahead' => 1,
            'send_time' => $this->formatSendTimeForStorage('20:00:00'),
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

    private function resolveTimezone(User $user): string
    {
        $settings = $this->resolveEmailSettings($user);
        return $settings?->timezone ?: 'Asia/Bangkok';
    }

    private function createEmailLog(
        int $userId,
        int $notificationId,
        string $toEmail,
        string $subject,
        string $provider
    ): ?NotificationEmailLog {
        if (!Schema::hasTable((new NotificationEmailLog())->getTable())) {
            return null;
        }

        // provider ต้องเป็น gmail_smtp หรือ gmail_api เท่านั้น (ตาม enum ใน migration)
        $payload = [
            'user_id' => $userId,
            'learning_notification_id' => $notificationId,
            'to_email' => $toEmail,
            'subject' => $subject,
            'provider' => $provider,
            'status' => 'queued', // ตรง enum: queued/sent/failed
        ];

        try {
            return NotificationEmailLog::create($payload);
        } catch (QueryException $e) {
            if (strpos($e->getMessage(), "Field 'id' doesn't have a default value") === false) {
                throw $e;
            }
            $nextId = (int) NotificationEmailLog::withoutGlobalScopes()->max('id') + 1;
            $payload['id'] = $nextId > 0 ? $nextId : 1;
            return NotificationEmailLog::create($payload);
        } catch (Throwable $e) {
            // Logging must not break notifications fetch/sending.
            report($e);
            return null;
        }
    }

    private function resolveGmailAccount(User $user): ?EmailProviderAccount
    {
        if (!Schema::hasTable((new EmailProviderAccount())->getTable())) {
            return null;
        }

        $query = EmailProviderAccount::where('user_id', $user->id)
            ->where('provider', 'gmail')
            ->where('auth_type', 'oauth');

        $active = (clone $query)->where('status', 'active');
        if ($user->email) {
            $active->where('provider_email', $user->email);
        }

        $account = $active->first();
        if ($account) {
            return $account;
        }

        return $query->orderByDesc('status')->first();
    }

    private function logMissingProvider(
        int $userId,
        int $notificationId,
        string $toEmail,
        string $subject
    ): void {
        if (!Schema::hasTable((new NotificationEmailLog())->getTable())) {
            return;
        }

        try {
            $exists = NotificationEmailLog::where('learning_notification_id', $notificationId)
                ->where('provider', 'gmail_api')
                ->where('status', 'failed')
                ->exists();

            if ($exists) {
                return;
            }

            NotificationEmailLog::create([
                'user_id' => $userId,
                'learning_notification_id' => $notificationId,
                'to_email' => $toEmail,
                'subject' => $subject,
                'provider' => 'gmail_api', // ตรง enum
                'status' => 'failed',      // ตรง enum
                'error_message' => 'Gmail not connected and SMTP not configured.',
            ]);
        } catch (Throwable $e) {
            // Logging must not break the /notifications API.
            report($e);
        }
    }

    private function formatSendTimeForStorage(string $time): string
    {
        $table = (new NotificationEmailSetting())->getTable();
        $columnType = '';
        try {
            $columnType = Schema::getColumnType($table, 'send_time');
        } catch (Throwable $e) {
            $columnType = '';
        }

        if (in_array($columnType, ['datetime', 'timestamp'], true)) {
            return Carbon::today('Asia/Bangkok')->format('Y-m-d').' '.$time;
        }

        return $time;
    }
}
