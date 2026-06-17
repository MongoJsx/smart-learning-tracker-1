<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'semester_id',
        'name',
        'description',
        'color',
        'room',
        'target_hours',
        'start_date',
        'start_time',
        'end_time',
    ];


    /* =======================
     | Global Scope + Auto user
     ======================= */
protected static function booted(): void
{
    static::addGlobalScope('user', function (Builder $query) {
        if (Auth::check()) {
            $query->where((new self)->getTable().'.user_id', Auth::id());
        }
    });

    static::creating(function (self $subject) {
        if (Auth::check() && empty($subject->user_id)) {
            $subject->user_id = Auth::id();
        }
    });
}

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function studyLogs(): HasMany
    {
        return $this->hasMany(StudyLog::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }
}
