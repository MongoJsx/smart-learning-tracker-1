<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_log_id',
        'content',
        'ai_model',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function studyLog()
    {
        return $this->belongsTo(StudyLog::class);
    }
}
