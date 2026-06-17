<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use JsonException;

class Quiz extends Model
{
    protected $fillable = [
        'subject_id',
        'title',
        'description',
        'ai_model',
        'metadata',
    ];

    protected $casts = [
    ];

    public function getMetadataAttribute($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function setMetadataAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['metadata'] = null;
            return;
        }

        if (is_array($value)) {
            $this->attributes['metadata'] = json_encode($value, JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->attributes['metadata'] = $value;
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function answers()
    {
        return $this->hasManyThrough(
            QuizAnswer::class,
            QuizQuestion::class,
            'quiz_id',
            'question_id',
            'id',
            'id'
        );
    }
}
