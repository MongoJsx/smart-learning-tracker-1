<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'options' => $this->options,
            'correct_answer' => $this->when($request->user()?->id === $this->quiz?->subject?->user_id, $this->correct_answer),
            'explanation' => $this->explanation,
        ];
    }
}
