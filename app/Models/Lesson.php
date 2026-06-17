<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    protected $fillable = [
        'subject_id',
        'title',
        'content',
        'summary',
        'order',
        'video_url',
        'audio_notes',
    ];

    protected $casts = [
        'audio_notes' => 'array',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
