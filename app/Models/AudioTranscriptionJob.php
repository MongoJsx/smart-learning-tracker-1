<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AudioTranscriptionJob extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            }
        });

        static::creating(function (self $job) {
            if (Auth::check() && empty($job->user_id)) {
                $job->user_id = Auth::id();
            }
        });
    }

    protected $table = 'audio_transcription_jobs';

    protected $fillable = [
        'user_id',
        'file_id',
        'study_log_id',
        'status',
        'language',
        'diarization',
        'speaker_count',
        'provider',
        'model',
        'duration_seconds',
        'started_at',
        'completed_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'diarization' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function file()
    {
        return $this->belongsTo(FileAttachment::class, 'file_id');
    }

    public function studyLog()
    {
        return $this->belongsTo(StudyLog::class);
    }
}
