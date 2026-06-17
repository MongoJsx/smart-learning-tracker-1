<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioTranscriptionSegment extends Model
{
    use HasFactory;

    protected $table = 'audio_transcription_segments';

    protected $fillable = [
        'job_id',
        'seq',
        'start_ms',
        'end_ms',
        'speaker',
        'text',
        'confidence',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function job()
    {
        return $this->belongsTo(AudioTranscriptionJob::class, 'job_id');
    }
}
