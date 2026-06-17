<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class NotificationEmailLog extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            }
        });

        static::creating(function (self $log) {
            if (Auth::check() && empty($log->user_id)) {
                $log->user_id = Auth::id();
            }
        });
    }

    protected $table = 'notification_email_logs';

    protected $fillable = [
        'user_id',
        'learning_notification_id',
        'to_email',
        'subject',
        'provider',
        'status',
        'sent_at',
        'error_message',
        'message_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function learningNotification(): BelongsTo
    {
        return $this->belongsTo(LearningNotification::class);
    }
}
