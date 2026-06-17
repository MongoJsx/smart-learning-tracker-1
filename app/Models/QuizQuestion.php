<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JsonException;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question_text',
        'question_type',
        'options',
        'correct_answer',
        'points',
        'explanation',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public function getOptionsAttribute($value): ?array
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

    public function setOptionsAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['options'] = null;
            return;
        }

        if (is_array($value)) {
            $items = array_values(array_filter(array_map(
                static fn ($item) => trim((string) $item),
                $value
            ), static fn (string $item) => $item !== ''));

            $this->attributes['options'] = $items !== []
                ? json_encode($items, JSON_UNESCAPED_UNICODE)
                : null;
            return;
        }

        $this->attributes['options'] = $value;
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class, 'question_id');
    }
}
