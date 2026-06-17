<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class StudyCalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject_id',
        'study_log_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'status',
        'room',
        'event_type',
        'metadata',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
        'metadata'   => 'array',
    ];

    /**
     * ✅ ใช้ user id จาก sanctum ก่อน (กัน Auth::check false)
     * ✅ ทำงานได้ทั้ง API (sanctum) และ web session
     */
    protected static function resolveAuthUserId(): ?int
    {
        $id = Auth::guard('sanctum')->id();
        if ($id) return (int) $id;

        $id = Auth::id();
        return $id ? (int) $id : null;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            $userId = static::resolveAuthUserId();
            if ($userId) {
                $query->where((new self)->getTable() . '.user_id', $userId);
            }
        });

        static::creating(function (self $event) {
            $userId = static::resolveAuthUserId();
            if ($userId && empty($event->user_id)) {
                $event->user_id = $userId;
            }
        });
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function studyLog()
    {
        return $this->belongsTo(StudyLog::class);
    }
}
