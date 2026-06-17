<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->question_id,
            'selected_answer' => $this->selected_answer,
            'is_correct' => $this->is_correct,
            'score' => $this->score,
            'correct_answer' => $this->question?->correct_answer,
            'explanation' => $this->question?->explanation,
            'question_text' => $this->question?->question_text,
            'question_type' => $this->question?->question_type,
            'options' => $this->question?->options,
            'metadata' => $this->metadata,
        ];
    }
}
