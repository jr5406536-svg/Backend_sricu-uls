<?php

namespace App\Http\Resources;

use App\Services\ResourceAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $file = $this->resource;
        $downloadable = app(ResourceAccess::class)->canDownload($request->user(), $file->resource);

        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'extension' => $this->extension,
            'mime' => $this->mime,
            'size' => $this->size,
            'downloadable' => $downloadable,
            'download_url' => $downloadable
                ? url('/api/resources/'.$this->resource_id.'/files/'.$this->id.'/download')
                : null,
        ];
    }
}
