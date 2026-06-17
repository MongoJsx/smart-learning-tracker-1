<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioSummaryV2 extends Model
{
    use HasFactory;

    protected $table = 'audio_summaries_v2';

    protected $fillable = [
        'job_id',
        'summary_type',
        'prompt',
        'summary_text',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'status',
        'error_message',
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
