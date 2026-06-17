<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioSummary extends Model
{
    use HasFactory;

    protected $table = 'audio_summaries';

    protected $fillable = [
        'file_id',
        'study_log_id',
        'status',
        'transcript',
        'summary',
        'ai_model',
        'error_message',
        'metadata',
    ];

    protected $casts = [
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
