<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\AudioSummaryResource;

class StudyLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'subject_id' => $this->subject_id,
            'title' => $this->title,
            'note' => $this->note,
            'log_date' => $this->log_date?->toDateString(),
            'duration_minutes' => $this->duration_minutes,
            'mood' => $this->mood,
            'log_type' => $this->log_type,
            'files' => FileResource::collection($this->whenLoaded('files')),
            'summaries' => SummaryResource::collection($this->whenLoaded('summaries')),
            'audio_summaries' => AudioSummaryResource::collection($this->whenLoaded('audioSummaries')),
            'created_at' => $this->created_at,
        ];
    }
}
