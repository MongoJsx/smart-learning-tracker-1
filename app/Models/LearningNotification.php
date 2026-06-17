<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningNotification extends Model
{
    /* =======================
     | Global Scope + Auto user
     ======================= */
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            }
        });

        static::creating(function (self $notification) {
            if (Auth::check() && empty($notification->user_id)) {
                $notification->user_id = Auth::id();
            }
        });
    }

    protected $table = 'learning_notifications';

    protected $fillable = [
        'user_id',
        'subject_id',
        'study_log_id',
        'calendar_event_id',
        'title',
        'body',
        'notify_at',
        'delivered_at',
        'channel',
        'status',
        'metadata',
    ];

    protected $casts = [
        'notify_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'message',
        'is_read',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function studyLog(): BelongsTo
    {
        return $this->belongsTo(StudyLog::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(StudyCalendarEvent::class, 'calendar_event_id');
    }

    public function getMessageAttribute(): string
    {
        return (string) $this->body;
    }

    public function getIsReadAttribute(): bool
    {
        return (bool) data_get($this->metadata, 'is_read', false);
    }

    public function getTypeAttribute(): ?string
    {
        return data_get($this->metadata, 'type');
    }
}
