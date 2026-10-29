<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'affiliation' => $this->affiliation,
            'orcid' => $this->orcid,
            'user_id' => $this->user_id,
            'position' => $this->whenPivotLoaded('resource_author', fn () => $this->pivot->position),
        ];
    }
}
