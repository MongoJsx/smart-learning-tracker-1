<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'title' => $this->title,
            'description' => $this->description,
            'ai_model' => $this->ai_model,
            'metadata' => $this->metadata,
            'questions' => QuizQuestionResource::collection($this->whenLoaded('questions')),
            'created_at' => $this->created_at,
            'latest_attempt' => $this->when($request->user(), function () use ($request) {
                $answers = $this->relationLoaded('answers')
                    ? $this->getRelation('answers')->where('user_id', $request->user()->id)->sortByDesc('answered_at')
                    : $this->answers()
                        ->where('user_id', $request->user()->id)
                        ->whereNotNull('answered_at')
                        ->orderByDesc('answered_at')
                        ->get();

                if ($answers->isEmpty()) {
                    return null;
                }

                $grouped = $answers->groupBy(function ($answer) {
                    return optional($answer->answered_at)->format('Y-m-d H:i:s');
                })->map(function ($collection) {
                    $total = $collection->count();
                    $score = $collection->sum('score');
                    return [
                        'score' => $score,
                        'total' => $total,
                        'percentage' => $total > 0 ? round(($score / $total) * 100) : 0,
                        'answered_at' => $collection->first()->answered_at,
                    ];
                })->sortKeysDesc();

                return $grouped->first();
            }),
        ];
    }
}
