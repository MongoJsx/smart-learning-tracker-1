<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StudyNotification extends Model
{
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

    protected $fillable = [
        'user_id',
        'subject_id',
        'type',
        'title',
        'message',
        'notify_at',
        'is_read',
    ];

    protected $casts = [
        'notify_at' => 'datetime',
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
