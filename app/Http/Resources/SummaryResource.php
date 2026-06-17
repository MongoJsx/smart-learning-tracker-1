<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'ai_model' => $this->ai_model,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
