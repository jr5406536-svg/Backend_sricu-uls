<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'decision' => $this->decision?->value ?? $this->decision,
            'observations' => $this->observations,
            'reviewer_id' => $this->reviewer_id,
            'reviewer' => $this->reviewer?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
