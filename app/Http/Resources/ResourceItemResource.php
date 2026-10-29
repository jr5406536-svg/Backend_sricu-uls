<?php

namespace App\Http\Resources;

use App\Services\ResourceAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $record = $this->resource;
        if ($record->relationLoaded('files')) {
            $record->files->each->setRelation('resource', $record);
        }

        $user = $request->user();
        $staff = $user && ($user->isAdmin() || $user->hasRole('gestor') || $user->hasRole('revisor') || $user->id === $record->owner_id);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type?->value ?? $this->type,
            'resource_date' => $this->resource_date?->toDateString(),
            'language' => $this->language,
            'status' => $this->status?->value ?? $this->status,
            'access_level' => $this->access_level?->value ?? $this->access_level,
            'provenance' => $this->provenance,
            'external_url' => $this->external_url,
            'technical_description' => $this->technical_description,
            'technologies' => $this->technologies,
            'published_at' => $this->published_at?->toIso8601String(),
            'withdrawn_at' => $this->when($staff, $this->withdrawn_at?->toIso8601String()),
            'withdrawal_reason' => $this->when($staff, $this->withdrawal_reason),
            'license' => new LicenseResource($this->whenLoaded('license')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'collection' => new CollectionResource($this->whenLoaded('collection')),
            'authors' => AuthorResource::collection($this->whenLoaded('authors')),
            'keywords' => KeywordResource::collection($this->whenLoaded('keywords')),
            'files' => ResourceFileResource::collection($this->whenLoaded('files')),
            'reviews' => $this->when($staff, ReviewResource::collection($this->whenLoaded('reviews'))),
            'owner_id' => $this->when($staff, $this->owner_id),
            'download_allowed' => app(ResourceAccess::class)->canDownload($user, $record),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
